<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Cms\Entity\CmsPage;
use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use App\Redirect\Entity\Redirect;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 65)]
final readonly class TermsRedirectMigrationStep implements MigrationStepInterface
{
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
        private string $localeCode,
    ) {
    }

    public function name(): string
    {
        return 'terms-redirects';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('terms-redirects');

        /** @var list<array{slug: string, name: string}> $rows */
        $rows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT slug, name FROM setono_sylius_terms__terms_translation WHERE locale = ?',
            [$this->localeCode],
        );

        foreach ($rows as $row) {
            $page = $this->entityManager->getRepository(CmsPage::class)->findOneBy(['title' => $row['name']]);
            if (!$page instanceof CmsPage) {
                $result->warn(sprintf('Pro podmínky „%s“ není CMS stránka se stejným názvem', $row['name']));
                $result->skipped();

                continue;
            }

            $destination = sprintf('/%s/page/%s', $this->localeCode, $page->getSlug());
            foreach (['', '/partial'] as $suffix) {
                $source = sprintf('/%s/terms/%s%s', $this->localeCode, $row['slug'], $suffix);
                $redirect = $this->entityManager->getRepository(Redirect::class)->findOneBy(['source' => $source]);
                if ($redirect instanceof Redirect) {
                    $redirect->setDestination($destination);
                    $result->updated();
                } else {
                    $this->entityManager->persist(new Redirect($source, $destination));
                    $result->created();
                }
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }
}
