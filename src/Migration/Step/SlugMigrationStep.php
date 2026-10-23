<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 200)]
final readonly class SlugMigrationStep implements MigrationStepInterface
{
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
        private string $localeCode,
    ) {
    }

    public function name(): string
    {
        return 'slugs';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('slugs');
        $connection = $this->entityManager->getConnection();

        $this->migrateProductSlugs($connection, $result, $dryRun);
        $this->migrateTaxonSlugs($connection, $result, $dryRun);

        return $result;
    }

    private function migrateProductSlugs(Connection $connection, ImportResult $result, bool $dryRun): void
    {
        /** @var list<array{ext: string, slug: string, name: string}> $oldRows */
        $oldRows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT p.externalIdentifier AS ext, t.slug, t.name
             FROM sylius_product p JOIN sylius_product_translation t ON t.translatable_id = p.id AND t.locale = ?
             WHERE p.externalIdentifier IS NOT NULL',
            [$this->localeCode],
        );
        $old = [];
        foreach ($oldRows as $row) {
            $old[$row['ext']] = ['slug' => $row['slug'], 'name' => $row['name']];
        }

        /** @var list<array{ext: string, translation_id: int|string, slug: string, name: string}> $currentRows */
        $currentRows = $connection->fetchAllAssociative(
            'SELECT p.external_identifier AS ext, t.id AS translation_id, t.slug, t.name
             FROM sylius_product p JOIN sylius_product_translation t ON t.translatable_id = p.id AND t.locale = ?
             WHERE p.external_identifier IS NOT NULL',
            [$this->localeCode],
        );
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['ext']] = ['translation_id' => (int) $row['translation_id'], 'slug' => $row['slug'], 'name' => $row['name']];
        }

        $final = [];
        foreach ($current as $ext => $row) {
            $previous = $old[$ext] ?? null;
            if ($previous !== null && $previous['name'] === $row['name']) {
                $final[$ext] = $previous['slug'];
            } elseif ($previous !== null && $previous['slug'] !== $row['slug']) {
                $result->warn(sprintf('Produkt %s: název změněn („%s“ → „%s“), starý slug %s patří do redirectu', $ext, $previous['name'], $row['name'], $previous['slug']));
            }
        }

        $reserved = array_flip($final);
        $taken = [];
        foreach ($current as $ext => $row) {
            if (isset($final[$ext])) {
                continue;
            }
            $slug = $row['slug'];
            if (isset($reserved[$slug]) || isset($taken[$slug])) {
                $slug = $row['slug'] . '-' . $ext;
            }
            $taken[$slug] = true;
            $final[$ext] = $slug;
        }

        $changes = array_filter($final, static fn (string $slug, int|string $ext): bool => $current[$ext]['slug'] !== $slug, \ARRAY_FILTER_USE_BOTH);
        $result->updated(count($changes));
        $result->skipped(count($current) - count($changes));
        if ($dryRun || $changes === []) {
            return;
        }

        $connection->transactional(function (Connection $connection) use ($changes, $current): void {
            foreach (array_keys($changes) as $ext) {
                $connection->update('sylius_product_translation', ['slug' => '__migrating__' . $current[$ext]['translation_id']], ['id' => $current[$ext]['translation_id']]);
            }
            foreach ($changes as $ext => $slug) {
                $connection->update('sylius_product_translation', ['slug' => $slug], ['id' => $current[$ext]['translation_id']]);
            }
        });
    }

    private function migrateTaxonSlugs(Connection $connection, ImportResult $result, bool $dryRun): void
    {
        /** @var list<array{ext: string, slug: string}> $oldRows */
        $oldRows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT x.externalIdentifier AS ext, t.slug
             FROM sylius_taxon x JOIN sylius_taxon_translation t ON t.translatable_id = x.id AND t.locale = ?
             WHERE x.externalIdentifier IS NOT NULL',
            [$this->localeCode],
        );

        foreach ($oldRows as $old) {
            $translationId = $connection->fetchOne(
                'SELECT t.id FROM sylius_taxon x JOIN sylius_taxon_translation t ON t.translatable_id = x.id AND t.locale = ?
                 WHERE x.external_identifier = ? AND t.slug <> ?',
                [$this->localeCode, $old['ext'], $old['slug']],
            );

            if ($translationId === false) {
                $result->skipped();

                continue;
            }

            if (!$dryRun) {
                $connection->update('sylius_taxon_translation', ['slug' => $old['slug']], ['id' => $translationId]);
            }
            $result->updated();
        }
    }
}
