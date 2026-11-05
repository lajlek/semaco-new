<?php

declare(strict_types=1);

namespace App\Catalog\BrandMenu;

use App\Entity\Product\Product;
use App\Entity\Taxonomy\Taxon;
use App\Entity\Taxonomy\TaxonImage;
use App\Erp\Application\ImportResult;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class BrandMenuSynchronizer
{
    /**
     * @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository
     * @param FactoryInterface<TaxonInterface> $taxonFactory
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'sylius.repository.taxon')]
        private TaxonRepositoryInterface $taxonRepository,
        #[Autowire(service: 'sylius.factory.taxon')]
        private FactoryInterface $taxonFactory,
        #[Autowire(service: 'sylius.repository.channel')]
        private ChannelRepositoryInterface $channelRepository,
        #[Autowire(param: 'locale')]
        private string $localeCode,
    ) {
    }

    public function sync(): ImportResult
    {
        $result = new ImportResult('brand-menu');
        $taxonIds = $this->ensureTree($result);
        $this->assignProducts($taxonIds, $result);

        return $result;
    }

    /** @return array<string, int> */
    private function ensureTree(ImportResult $result): array
    {
        $root = $this->ensureTaxon(BrandMenuTree::ROOT_CODE, null, BrandMenuTree::ROOT_NAME, BrandMenuTree::ROOT_SLUG, 0, $result);

        $taxons = [];
        $positions = [];
        foreach (BrandMenuTree::nodes() as $key => $node) {
            $parentKey = $node['parent'];
            $parent = $parentKey === null ? $root : $taxons[$parentKey];
            $position = $positions[$parentKey ?? ''] ?? 0;
            $positions[$parentKey ?? ''] = $position + 1;
            $taxons[$key] = $this->ensureTaxon(BrandMenuTree::code($key), $parent, $node['name'], BrandMenuTree::slug($key), $position, $result);
        }

        $this->ensureImages($taxons);
        foreach ($this->channelRepository->findAll() as $channel) {
            if ($channel->getMenuTaxon()?->getCode() !== BrandMenuTree::ROOT_CODE) {
                $channel->setMenuTaxon($root);
            }
        }
        $this->entityManager->flush();

        return array_map(static fn (TaxonInterface $taxon): int => (int) $taxon->getId(), $taxons);
    }

    private function ensureTaxon(string $code, ?TaxonInterface $parent, string $name, string $slug, int $position, ImportResult $result): TaxonInterface
    {
        $taxon = $this->taxonRepository->findOneBy(['code' => $code]);
        if ($taxon instanceof TaxonInterface) {
            return $taxon;
        }

        $taxon = $this->taxonFactory->createNew();
        \assert($taxon instanceof Taxon);
        $taxon->setCode($code);
        $taxon->setCurrentLocale($this->localeCode);
        $taxon->setFallbackLocale($this->localeCode);
        $taxon->setName($name);
        $taxon->setSlug($slug);
        $taxon->setParent($parent);
        $taxon->setPosition($position);
        $taxon->setEnabled(true);
        $this->entityManager->persist($taxon);
        $this->entityManager->flush();
        $result->created();

        return $taxon;
    }

    /** @param array<string, TaxonInterface> $taxons */
    private function ensureImages(array $taxons): void
    {
        $connection = $this->entityManager->getConnection();
        /** @var array<string, string|null> $logos */
        $logos = $connection->fetchAllKeyValue('SELECT UPPER(name), logo_path FROM app_brand');
        $priceHitsImage = $connection->fetchOne(
            'SELECT i.path FROM sylius_taxon_image i JOIN sylius_taxon t ON t.id = i.owner_id WHERE t.code = :code ORDER BY i.id ASC LIMIT 1',
            ['code' => BrandMenuClassifier::PRICE_HITS_TAXON],
        );

        $paths = [
            BrandMenuTree::POKOLM => $logos['POKOLM'] ?? null,
            BrandMenuTree::FRAISA => $logos['FRAISA'] ?? null,
            BrandMenuTree::BRW => $logos['BRW'] ?? null,
            BrandMenuTree::OTHER => is_string($priceHitsImage) ? $priceHitsImage : null,
        ];
        foreach ($paths as $key => $path) {
            if ($path !== null && $taxons[$key]->getImages()->isEmpty()) {
                $image = new TaxonImage();
                $image->setPath($path);
                $taxons[$key]->addImage($image);
            }
        }
    }

    /** @param array<string, int> $taxonIds */
    private function assignProducts(array $taxonIds, ImportResult $result): void
    {
        $connection = $this->entityManager->getConnection();
        $products = $this->sortedProducts($connection);
        $links = $this->productTaxonCodes($connection);

        $locked = [];
        $desired = [];
        foreach ($products as $product) {
            $productId = (int) $product['id'];
            $lockedFields = json_decode((string) $product['erp_locked_fields'], true);
            if (is_array($lockedFields) && in_array(Product::ERP_FIELD_TAXONS, $lockedFields, true)) {
                $locked[$productId] = true;

                continue;
            }

            $keys = BrandMenuClassifier::classify($product['brand'], $product['skupzbo'], $links[$productId] ?? [], (string) $product['name']);
            foreach (array_unique(array_merge(...array_map(BrandMenuTree::withAncestors(...), $keys))) as $key) {
                $desired[$taxonIds[$key]][] = $productId;
            }
        }

        /** @var list<array{id: int|string, product_id: int|string, taxon_id: int|string, position: int|string}> $existingRows */
        $existingRows = $connection->fetchAllAssociative(
            'SELECT id, product_id, taxon_id, position FROM sylius_product_taxon WHERE taxon_id IN (:taxons)',
            ['taxons' => array_values($taxonIds)],
            ['taxons' => ArrayParameterType::INTEGER],
        );
        $existing = [];
        foreach ($existingRows as $row) {
            $existing[(int) $row['taxon_id']][(int) $row['product_id']] = ['id' => (int) $row['id'], 'position' => (int) $row['position']];
        }

        $connection->transactional(static function (Connection $connection) use ($desired, $existing, $locked, $result): void {
            foreach ($existing as $taxonId => $rows) {
                $keep = array_flip($desired[$taxonId] ?? []);
                foreach ($rows as $productId => $row) {
                    if (!isset($keep[$productId]) && !isset($locked[$productId])) {
                        $connection->delete('sylius_product_taxon', ['id' => $row['id']]);
                        $result->updated();
                    }
                }
            }

            foreach ($desired as $taxonId => $productIds) {
                foreach ($productIds as $position => $productId) {
                    $row = $existing[$taxonId][$productId] ?? null;
                    if ($row === null) {
                        $connection->insert('sylius_product_taxon', ['product_id' => $productId, 'taxon_id' => $taxonId, 'position' => $position]);
                        $result->created();
                    } elseif ($row['position'] !== $position) {
                        $connection->update('sylius_product_taxon', ['position' => $position], ['id' => $row['id']]);
                        $result->updated();
                    }
                }
            }
        });
    }

    /** @return list<array{id: int|string, brand: string|null, skupzbo: string|null, name: string|null, code: string, erp_locked_fields: string|null}> */
    private function sortedProducts(Connection $connection): array
    {
        /** @var list<array{id: int|string, brand: string|null, skupzbo: string|null, name: string|null, code: string, erp_locked_fields: string|null}> $products */
        $products = $connection->fetchAllAssociative(
            'SELECT p.id, p.code, p.skupzbo, p.erp_locked_fields, b.name AS brand, t.name
             FROM sylius_product p
             LEFT JOIN app_brand b ON b.id = p.brand_id
             LEFT JOIN sylius_product_translation t ON t.translatable_id = p.id AND t.locale = :locale',
            ['locale' => $this->localeCode],
        );

        $collator = new \Collator($this->localeCode);
        $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
        usort($products, static function (array $left, array $right) use ($collator): int {
            $order = $collator->compare((string) $left['name'], (string) $right['name']);

            return $order !== 0 && $order !== false ? $order : strcmp($left['code'], $right['code']);
        });

        return $products;
    }

    /** @return array<int, list<string>> */
    private function productTaxonCodes(Connection $connection): array
    {
        /** @var list<array{product_id: int|string, code: string}> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT pt.product_id, t.code FROM sylius_product_taxon pt JOIN sylius_taxon t ON t.id = pt.taxon_id',
        );

        $codes = [];
        foreach ($rows as $row) {
            if (!BrandMenuTree::isMenuTaxon($row['code'])) {
                $codes[(int) $row['product_id']][] = $row['code'];
            }
        }

        return $codes;
    }
}
