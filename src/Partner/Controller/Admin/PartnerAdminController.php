<?php

declare(strict_types=1);

namespace App\Partner\Controller\Admin;

use App\Partner\Entity\PartnerExportBatch;
use App\Partner\Entity\PartnerItem;
use App\Partner\Fetch\PartnerFetchQueue;
use App\Partner\Rules\PartnerRules;
use App\Partner\Service\PartnerExporter;
use App\Partner\Service\PartnerItemMatcher;
use App\Partner\Service\PartnerItemPreparer;
use App\Partner\Service\PartnerItemStore;
use App\Partner\Service\PartnerParserRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PartnerAdminController extends AbstractController
{
    /**
     * @param RepositoryInterface<PartnerItem> $itemRepository
     * @param RepositoryInterface<PartnerExportBatch> $batchRepository
     * @param RepositoryInterface<TaxonInterface> $taxonRepository
     */
    public function __construct(
        #[Autowire(service: 'app.repository.partner_item')]
        private readonly RepositoryInterface $itemRepository,
        #[Autowire(service: 'app.repository.partner_export_batch')]
        private readonly RepositoryInterface $batchRepository,
        #[Autowire(service: 'sylius.repository.taxon')]
        private readonly RepositoryInterface $taxonRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PartnerItemPreparer $preparer,
        private readonly PartnerItemMatcher $matcher,
        private readonly PartnerExporter $exporter,
        private readonly PartnerFetchQueue $fetchQueue,
        private readonly PartnerRules $rules,
        private readonly PartnerParserRegistry $parsers,
        private readonly PartnerItemStore $store,
        #[Autowire('%kernel.project_dir%/var/partners/snapshots')]
        private readonly string $snapshotDirectory,
    ) {
    }

    #[Route('/admin/partner-items/bulk-approve', name: 'app_admin_partner_item_bulk_approve', methods: ['POST'])]
    public function bulkApprove(Request $request): Response
    {
        $this->assertToken($request, 'partner_bulk');
        $approved = 0;
        $items = $this->selectedItems($request);
        foreach ($items as $item) {
            if ($item->canBeApproved(false)) {
                $item->approve($this->adminName());
                ++$approved;
            }
        }
        $this->entityManager->flush();

        $this->addFlash($approved > 0 ? 'success' : 'warning', sprintf('Schváleno %d z %d vybraných položek.%s', $approved, count($items), $approved < count($items) ? ' Ostatní mají nevyřešená upozornění, změnily se po schválení (ty schvalte v detailu), jsou už schválené nebo existují v Semaco.' : ''));

        return $this->redirectBack($request);
    }

    #[Route('/admin/partner-items/bulk-export', name: 'app_admin_partner_item_bulk_export', methods: ['POST'])]
    public function bulkExport(Request $request): Response
    {
        $this->assertToken($request, 'partner_bulk');
        $batch = $this->exporter->export($this->selectedItems($request), $this->adminName());
        if ($batch === null) {
            $this->addFlash('warning', 'Mezi vybranými není žádná schválená položka (stav Připraveno).');

            return $this->redirectBack($request);
        }

        $this->addFlash('success', sprintf('Vytvořena dávka %d (%d položek). Soubor nahrajte do Heliosu; po synchronizaci se položky spárují samy.', (int) $batch->getId(), $batch->getItemCount()));

        return $this->redirectToRoute('app_admin_partner_export_batch_index');
    }

    #[Route('/admin/partner-items/{id}/approve', name: 'app_admin_partner_item_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(Request $request, int $id): Response
    {
        $item = $this->item($id);
        $this->assertToken($request, 'partner_item_' . $id);
        if ($item->canBeApproved()) {
            $item->approve($this->adminName());
            $this->entityManager->flush();
            $this->addFlash('success', 'Položka je schválená a připravená k exportu.');
        } else {
            $this->addFlash('error', 'Položku nelze schválit, vyřešte nejdřív upozornění.');
        }

        return $this->redirectToRoute('app_admin_partner_item_show', ['id' => $id]);
    }

    #[Route('/admin/partner-items/{id}/approve-family', name: 'app_admin_partner_item_approve_family', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approveFamily(Request $request, int $id): Response
    {
        $item = $this->item($id);
        $this->assertToken($request, 'partner_item_' . $id);
        if ($item->getFamilyKey() === null) {
            return $this->redirectToRoute('app_admin_partner_item_show', ['id' => $id]);
        }

        $approved = 0;
        $members = $this->itemRepository->findBy(['partnerCode' => $item->getPartnerCode(), 'familyKey' => $item->getFamilyKey()]);
        foreach ($members as $member) {
            if ($member->canBeApproved(false)) {
                $member->approve($this->adminName());
                ++$approved;
            }
        }
        $this->entityManager->flush();
        $this->addFlash($approved > 0 ? 'success' : 'warning', sprintf('V rodině %s schváleno %d položek. Položky s upozorněním, změněné po schválení, už schválené nebo existující v Semaco zůstaly beze změny.', $item->getFamilyKey(), $approved));

        return $this->redirectToRoute('app_admin_partner_item_index', ['criteria' => ['search' => ['type' => 'contains', 'value' => $item->getFamilyKey()], 'partnerCode' => $item->getPartnerCode()]]);
    }

    #[Route('/admin/partner-items/{id}/overrides', name: 'app_admin_partner_item_overrides', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function overrides(Request $request, int $id): Response
    {
        $item = $this->item($id);
        $this->assertToken($request, 'partner_item_' . $id);

        $taxonCode = trim($request->request->getString('taxon_code'));
        if ($taxonCode !== '' && $this->taxonRepository->findOneBy(['code' => $taxonCode]) === null) {
            $this->addFlash('error', sprintf('Kategorie s kódem %s neexistuje.', $taxonCode));

            return $this->redirectToRoute('app_admin_partner_item_show', ['id' => $id]);
        }

        $options = [];
        foreach ($request->request->all('options') as $code => $value) {
            if (is_string($code) && is_string($value) && trim($value) !== '') {
                $options[$code] = trim($value);
            }
        }
        $family = [
            'product_name' => trim($request->request->getString('product_name')),
            'taxon_code' => $taxonCode,
        ];

        $targets = [$item];
        if ($request->request->getBoolean('apply_family') && $item->getFamilyKey() !== null) {
            $targets = $this->itemRepository->findBy(['partnerCode' => $item->getPartnerCode(), 'familyKey' => $item->getFamilyKey()]);
        }
        $updated = 0;
        foreach ($targets as $target) {
            $overrides = $target->getOverrides();
            $before = $overrides;
            foreach ($family as $key => $value) {
                if ($value !== '') {
                    $overrides[$key] = $value;
                } elseif ($target === $item) {
                    unset($overrides[$key]);
                }
            }
            if ($target === $item) {
                $name = trim($request->request->getString('name'));
                if ($name !== '') {
                    $overrides['name'] = $name;
                } else {
                    unset($overrides['name']);
                }
                $overrides['options'] = $options;
            }
            if ($overrides !== $before) {
                $target->setOverrides($overrides);
                ++$updated;
            }
        }
        $this->entityManager->flush();
        $this->preparer->prepare($item->getPartnerCode(), $item->getFamilyKey() ?? '#' . $item->getOrderCode());
        $this->addFlash('success', $updated > 1 ? sprintf('Uloženo pro %d položek rodiny.', $updated) : 'Uloženo.');

        return $this->redirectToRoute('app_admin_partner_item_show', ['id' => $id]);
    }

    #[Route('/admin/partner-items/prepare', name: 'app_admin_partner_item_prepare', methods: ['POST'])]
    public function prepare(Request $request): Response
    {
        $this->assertToken($request, 'partner_prepare');
        $count = 0;
        foreach (array_keys($this->rules->all()) as $partnerCode) {
            foreach ($this->itemRepository->findBy(['partnerCode' => $partnerCode]) as $item) {
                $this->matcher->match($item);
            }
            $this->entityManager->flush();
            $count += $this->preparer->prepare($partnerCode);
        }
        $this->addFlash('success', sprintf('Párování a návrhy přepočítány u %d položek.', $count));

        return $this->redirectToRoute('app_admin_partner_item_index');
    }

    #[Route('/admin/partner-fetch-runs', name: 'app_admin_partner_fetch_run_queue', methods: ['POST'])]
    public function queueFetch(Request $request): Response
    {
        $this->assertToken($request, 'partner_fetch');

        try {
            $this->fetchQueue->queue(
                $request->request->getString('partner'),
                trim($request->request->getString('url')),
                $request->request->getInt('max_pages', 1),
                $this->adminName(),
            );
            $this->addFlash('success', 'Načítání je zařazené. Spustí se do minuty a stránky se stahují s odstupem nejméně 10 s.');
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_partner_item_index');
    }

    #[Route('/admin/partner-items/import-html', name: 'app_admin_partner_item_import_html', methods: ['POST'])]
    public function importHtml(Request $request): Response
    {
        $this->assertToken($request, 'partner_import');
        $partnerCode = $request->request->getString('partner');
        $url = trim($request->request->getString('url'));
        $file = $request->files->get('html');
        if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > 20 * 1024 * 1024 || !in_array(strtolower($file->getClientOriginalExtension()), ['html', 'htm'], true)) {
            $this->addFlash('error', 'Nahrajte stránku uloženou z prohlížeče jako soubor .html (nejvýš 20 MB).');

            return $this->redirectToRoute('app_admin_partner_item_index');
        }
        if ($url !== '' && !str_starts_with($url, 'https://')) {
            $this->addFlash('error', 'Adresa stránky musí začínat https://');

            return $this->redirectToRoute('app_admin_partner_item_index');
        }

        try {
            $parser = $this->parsers->get($partnerCode);
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_admin_partner_item_index');
        }

        $html = (string) file_get_contents($file->getPathname());
        $sourceUrl = $url !== '' ? $url : 'soubor ' . mb_substr($file->getClientOriginalName(), 0, 200);
        $page = $parser->parse($html, $sourceUrl);
        if ($page->items === []) {
            $this->addFlash('warning', sprintf('Ve stránce nebyly nalezeny položky %s. Uložte celou stránku výpisu (Ctrl+S, „Webová stránka, kompletní“ nebo „jen HTML“).', $this->rules->get($partnerCode)->label));

            return $this->redirectToRoute('app_admin_partner_item_index');
        }

        (new Filesystem())->dumpFile(
            sprintf('%s/%s/upload-%s-%s.html', $this->snapshotDirectory, $partnerCode, date('Ymd\THis'), substr(sha1($html), 0, 12)),
            '<!-- ' . str_replace('--', '', $sourceUrl) . " -->\n" . $html,
        );
        $summary = $this->store->store($partnerCode, $page->items, $sourceUrl, false);
        $this->preparer->prepare($partnerCode);
        $this->addFlash('success', sprintf('Načteno %d položek: nové %d, změněné %d, beze změny %d.', count($page->items), $summary['created'], $summary['changed'], $summary['unchanged']));

        return $this->redirectToRoute('app_admin_partner_item_index', ['criteria' => ['partnerCode' => $partnerCode]]);
    }

    #[Route('/admin/partner-fetch-runs/details', name: 'app_admin_partner_fetch_run_details', methods: ['POST'])]
    public function queueDetails(Request $request): Response
    {
        $this->assertToken($request, 'partner_fetch');

        try {
            $run = $this->fetchQueue->queueDetails($request->request->getString('partner'), $this->adminName());
            $this->addFlash('success', sprintf('Načtení detailů je zařazené (nejvýš %d artiklů, odstup nejméně 10 s).', $run->getMaxPages()));
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_partner_item_index');
    }

    #[Route('/admin/partner-fetch-runs/{id}/cancel', name: 'app_admin_partner_fetch_run_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelFetch(Request $request, int $id): Response
    {
        $this->assertToken($request, 'partner_fetch');
        if ($this->fetchQueue->cancel($id)) {
            $this->addFlash('success', 'Načítání zrušeno.');
        } else {
            $this->addFlash('warning', 'Načítání už běží nebo skončilo.');
        }

        return $this->redirectToRoute('app_admin_partner_item_index');
    }

    #[Route('/admin/partner-export-batches/{id}/download', name: 'app_admin_partner_export_batch_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(int $id): Response
    {
        $batch = $this->batchRepository->find($id);
        if (!$batch instanceof PartnerExportBatch) {
            throw $this->createNotFoundException();
        }

        return new Response($batch->getContent(), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $batch->getFileName()),
        ]);
    }

    /** @return list<PartnerItem> */
    private function selectedItems(Request $request): array
    {
        $ids = array_values(array_filter(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $request->request->all('ids')), static fn (int $id): bool => $id > 0));

        return $ids === [] ? [] : array_values($this->itemRepository->findBy(['id' => $ids]));
    }

    private function item(int $id): PartnerItem
    {
        $item = $this->itemRepository->find($id);
        if (!$item instanceof PartnerItem) {
            throw $this->createNotFoundException();
        }

        return $item;
    }

    private function assertToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function adminName(): string
    {
        return $this->getUser()?->getUserIdentifier() ?? 'admin';
    }

    private function redirectBack(Request $request): Response
    {
        $referer = (string) $request->headers->get('referer');
        $path = parse_url($referer, \PHP_URL_PATH);
        $query = parse_url($referer, \PHP_URL_QUERY);

        return is_string($path) && str_starts_with($path, '/admin/partner-items')
            ? $this->redirect($path . (is_string($query) ? '?' . $query : ''))
            : $this->redirectToRoute('app_admin_partner_item_index');
    }
}
