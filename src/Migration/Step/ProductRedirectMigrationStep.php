<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use App\Redirect\Entity\Redirect;
use App\Redirect\Repository\RedirectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProductRedirectMigrationStep implements MigrationStepInterface
{
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
        private RedirectRepositoryInterface $redirectRepository,
        private string $localeCode,
    ) {
    }

    public function name(): string
    {
        return 'product-redirects';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('product-redirects');

        /** @var list<array{ext: string, slug: string}> $oldRows */
        $oldRows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT p.externalIdentifier AS ext, t.slug
             FROM sylius_product p JOIN sylius_product_translation t ON t.translatable_id = p.id AND t.locale = ?
             WHERE p.externalIdentifier IS NOT NULL AND p.enabled = 1',
            [$this->localeCode],
        );

        /** @var list<array{ext: string, slug: string}> $currentRows */
        $currentRows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT p.external_identifier AS ext, t.slug
             FROM sylius_product p JOIN sylius_product_translation t ON t.translatable_id = p.id AND t.locale = ?
             WHERE p.external_identifier IS NOT NULL',
            [$this->localeCode],
        );
        $currentSlugs = array_column($currentRows, 'slug', 'ext');
        $liveSlugs = array_flip(array_map('strval', $currentSlugs));

        foreach ($oldRows as $old) {
            $newSlug = $currentSlugs[$old['ext']] ?? null;
            if ($newSlug === null || $newSlug === $old['slug'] || isset($liveSlugs[$old['slug']])) {
                $result->skipped();

                continue;
            }

            $source = sprintf('/%s/products/%s', $this->localeCode, $old['slug']);
            $destination = sprintf('/%s/products/%s', $this->localeCode, $newSlug);
            $redirect = $this->redirectRepository->findOneBy(['source' => $source]);
            if ($redirect instanceof Redirect) {
                $redirect->setDestination($destination);
                $result->updated();
            } else {
                $redirect = new Redirect($source, $destination);
                $this->entityManager->persist($redirect);
                $result->created();
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }
}
