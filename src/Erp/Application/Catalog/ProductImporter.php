<?php

declare(strict_types=1);

namespace App\Erp\Application\Catalog;

use App\Entity\Channel\Channel;
use App\Entity\Product\Brand;
use App\Entity\Product\Product;
use App\Entity\Product\ProductAttribute;
use App\Entity\Product\ProductOption;
use App\Entity\Product\ProductOptionValue;
use App\Entity\Product\ProductVariant;
use App\Entity\Taxation\TaxCategory;
use App\Entity\Taxonomy\Taxon;
use App\Erp\Application\ImportResult;
use App\Erp\Port\Catalog\Dto\ProductSnapshot;
use App\Erp\Port\Catalog\Dto\VariantSnapshot;
use App\Erp\Port\Catalog\ProductSyncInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Attribute\AttributeType\SelectAttributeType;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class ProductImporter
{
    private const CLEAR_EVERY = 25;

    /** @var array<string, int> */
    private array $optionValueIds = [];

    /** @var array<string, int> */
    private array $optionIds = [];

    /** @var array<string, int> */
    private array $attributeIds = [];

    /** @var array<string, string> */
    private array $attributeTypes = [];

    /** @var array<string, int> */
    private array $taxonIds = [];

    private ?int $channelId = null;

    private ?int $taxCategoryId = null;

    /**
     * @param RepositoryInterface<Product> $productRepository
     * @param ProductFactoryInterface<Product> $productFactory
     * @param RepositoryInterface<ProductVariant> $variantRepository
     * @param ProductVariantFactoryInterface<ProductVariant> $variantFactory
     * @param FactoryInterface<ProductTaxonInterface> $productTaxonFactory
     * @param FactoryInterface<ProductAttributeValueInterface> $attributeValueFactory
     * @param RepositoryInterface<Taxon> $taxonRepository
     * @param RepositoryInterface<ProductAttributeInterface> $attributeRepository
     * @param RepositoryInterface<ProductOptionInterface> $optionRepository
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<TaxCategoryInterface> $taxCategoryRepository
     * @param RepositoryInterface<Brand> $brandRepository
     */
    public function __construct(
        private readonly ProductSyncInterface $productSync,
        private readonly RepositoryInterface $productRepository,
        private readonly ProductFactoryInterface $productFactory,
        private readonly RepositoryInterface $variantRepository,
        private readonly ProductVariantFactoryInterface $variantFactory,
        private readonly FactoryInterface $productTaxonFactory,
        private readonly FactoryInterface $attributeValueFactory,
        private readonly RepositoryInterface $taxonRepository,
        private readonly RepositoryInterface $attributeRepository,
        private readonly RepositoryInterface $optionRepository,
        private readonly RepositoryInterface $channelRepository,
        private readonly RepositoryInterface $taxCategoryRepository,
        private readonly RepositoryInterface $brandRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly string $channelCode,
        private readonly string $localeCode,
        private readonly string $taxCategoryCode,
    ) {
    }

    /**
     * @param callable(ProductSnapshot): void|null $onProduct
     */
    public function importChangedSince(\DateTimeInterface $since, ?callable $onProduct = null): ImportResult
    {
        return $this->importSnapshots($this->productSync->pullChangedSince($since), $onProduct);
    }

    /**
     * @param list<string> $externalIdentifiers
     * @param callable(ProductSnapshot): void|null $onProduct
     */
    public function importByIds(array $externalIdentifiers, ?callable $onProduct = null): ImportResult
    {
        return $this->importSnapshots($this->productSync->pullByIds($externalIdentifiers), $onProduct);
    }

    /**
     * @param iterable<ProductSnapshot> $snapshots
     * @param callable(ProductSnapshot): void|null $onProduct
     */
    private function importSnapshots(iterable $snapshots, ?callable $onProduct): ImportResult
    {
        $result = new ImportResult('products');
        $this->warmUp();
        $processed = 0;

        foreach ($snapshots as $snapshot) {
            $product = $this->productRepository->findOneBy(['externalIdentifier' => $snapshot->externalIdentifier]);
            if ($product instanceof Product && !$product->isSyncWithErp()) {
                $result->skipped();

                continue;
            }

            if (!$product instanceof Product) {
                $product = $this->createProduct($snapshot);
                $result->created();
            } else {
                $result->updated();
            }

            $this->applyProduct($product, $snapshot);
            $this->applyVariants($product, $snapshot, $result);
            $this->entityManager->flush();

            if ($onProduct !== null) {
                $onProduct($snapshot);
            }

            if (++$processed % self::CLEAR_EVERY === 0) {
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        return $result;
    }

    private function createProduct(ProductSnapshot $snapshot): Product
    {
        $product = $this->productFactory->createNew();
        $product->setCode($this->uniqueProductCode($snapshot->code, $snapshot->externalIdentifier));
        $product->setExternalIdentifier($snapshot->externalIdentifier);
        $product->setCurrentLocale($this->localeCode);
        $product->setFallbackLocale($this->localeCode);
        $product->setVariantSelectionMethod(Product::VARIANT_SELECTION_CHOICE);
        $product->setSlug($this->uniqueSlug($this->slugGenerator->generate($snapshot->name), $snapshot->externalIdentifier));
        $this->entityManager->persist($product);

        return $product;
    }

    private function applyProduct(Product $product, ProductSnapshot $snapshot): void
    {
        $product->setCurrentLocale($this->localeCode);
        $product->setFallbackLocale($this->localeCode);
        $product->setEnabled($snapshot->enabled);
        $product->setName($snapshot->name);
        $product->setDescription($snapshot->description);
        $product->setShortDescription($snapshot->shortDescription);
        $product->setMetaKeywords($snapshot->metaKeywords);
        $product->setMetaDescription($snapshot->metaDescription);
        $product->setSkupzbo($snapshot->skupzbo);
        $product->setQuantityStep($snapshot->quantityStep);
        $product->setMinimumQuantity($snapshot->minimumQuantity);
        $product->setBrand($snapshot->brand === null ? null : $this->brand($snapshot->brand->externalIdentifier, $snapshot->brand->name));

        $channel = $this->reference(Channel::class, $this->channelId);
        if ($channel instanceof ChannelInterface && !$product->hasChannel($channel)) {
            $product->addChannel($channel);
        }

        $this->applyTaxons($product, $snapshot);
        $this->applyAttributes($product, $snapshot);
        $this->applyOptions($product, $snapshot);
    }

    private function applyTaxons(Product $product, ProductSnapshot $snapshot): void
    {
        $desired = [];
        $mainTaxon = null;
        $hasMain = false;
        foreach ($snapshot->taxonLinks as $link) {
            $taxon = $this->reference(Taxon::class, $this->taxonIds[$link->taxonExternalIdentifier] ?? null);
            if (!$taxon instanceof TaxonInterface) {
                continue;
            }

            $desired[(int) $taxon->getId()] = ['taxon' => $taxon, 'position' => $link->position];
            if (($link->main && !$hasMain) || $mainTaxon === null) {
                $mainTaxon = $taxon;
                $hasMain = $hasMain || $link->main;
            }

            foreach ($taxon->getAncestors() as $ancestor) {
                if ($ancestor instanceof TaxonInterface) {
                    $desired[(int) $ancestor->getId()] ??= ['taxon' => $ancestor, 'position' => $link->position];
                }
            }
        }

        foreach ($product->getProductTaxons() as $productTaxon) {
            $taxonId = (int) $productTaxon->getTaxon()?->getId();
            if (!isset($desired[$taxonId])) {
                $product->removeProductTaxon($productTaxon);

                continue;
            }

            $productTaxon->setPosition($desired[$taxonId]['position']);
            unset($desired[$taxonId]);
        }

        foreach ($desired as $item) {
            $productTaxon = $this->productTaxonFactory->createNew();
            $productTaxon->setTaxon($item['taxon']);
            $productTaxon->setPosition($item['position']);
            $product->addProductTaxon($productTaxon);
        }

        $product->setMainTaxon($mainTaxon);
    }

    private function applyAttributes(Product $product, ProductSnapshot $snapshot): void
    {
        $desired = [];
        foreach ($snapshot->attributes as $groupId => $value) {
            $code = CatalogCodeGenerator::attributeCode((string) $groupId);
            if (!isset($this->attributeIds[$code])) {
                continue;
            }

            if ($this->attributeTypes[$code] === SelectAttributeType::TYPE) {
                $desired[$code] = is_array($value) ? array_map(CatalogCodeGenerator::attributeChoiceKey(...), $value) : null;
            } else {
                $desired[$code] = is_int($value) ? $value : null;
            }
        }

        foreach ($product->getAttributes() as $attributeValue) {
            $code = (string) $attributeValue->getAttribute()?->getCode();
            if (!str_starts_with($code, 'attr_group_')) {
                continue;
            }

            if (!array_key_exists($code, $desired) || $desired[$code] === null) {
                $product->removeAttribute($attributeValue);

                continue;
            }

            $attributeValue->setValue($desired[$code]);
            unset($desired[$code]);
        }

        foreach ($desired as $code => $value) {
            $attribute = $this->reference(ProductAttribute::class, $this->attributeIds[$code]);
            if ($value === null || !$attribute instanceof ProductAttributeInterface) {
                continue;
            }

            $attributeValue = $this->attributeValueFactory->createNew();
            $attributeValue->setAttribute($attribute);
            $attributeValue->setLocaleCode($this->localeCode);
            $attributeValue->setValue($value);
            $product->addAttribute($attributeValue);
        }
    }

    private function applyOptions(Product $product, ProductSnapshot $snapshot): void
    {
        $groups = [];
        foreach ($snapshot->variants as $variant) {
            foreach (array_keys($variant->optionValues) as $groupId) {
                $groups[CatalogCodeGenerator::optionCode((string) $groupId)] = true;
            }
        }

        foreach ($product->getOptions() as $option) {
            if (!isset($groups[(string) $option->getCode()])) {
                $product->removeOption($option);
            } else {
                unset($groups[(string) $option->getCode()]);
            }
        }

        foreach (array_keys($groups) as $code) {
            $option = $this->reference(ProductOption::class, $this->optionIds[$code] ?? null);
            if ($option instanceof ProductOptionInterface) {
                $product->addOption($option);
            }
        }
    }

    private function applyVariants(Product $product, ProductSnapshot $snapshot, ImportResult $result): void
    {
        $seen = [];
        $ordered = $snapshot->variants;
        usort($ordered, static fn (VariantSnapshot $a, VariantSnapshot $b): int => strnatcmp((string) $a->regcis, (string) $b->regcis));

        foreach ($ordered as $position => $variantSnapshot) {
            $variant = $this->variantRepository->findOneBy(['externalIdentifier' => $variantSnapshot->externalIdentifier]);
            if (!$variant instanceof ProductVariant) {
                $variant = $this->variantFactory->createForProduct($product);
                $variant->setCode($this->uniqueVariantCode($variantSnapshot->code, $variantSnapshot->externalIdentifier));
                $variant->setExternalIdentifier($variantSnapshot->externalIdentifier);
                $this->entityManager->persist($variant);
            } elseif ($variant->getProduct() !== $product) {
                $variant->getProduct()?->removeVariant($variant);
                $product->addVariant($variant);
                $result->warn(sprintf('Varianta %s přesunuta k produktu %s', $variantSnapshot->externalIdentifier, $snapshot->externalIdentifier));
            }

            $this->applyVariant($variant, $variantSnapshot, $position);
            $seen[$variantSnapshot->externalIdentifier] = true;
        }

        foreach ($product->getVariants() as $variant) {
            if ($variant instanceof ProductVariant && !isset($seen[(string) $variant->getExternalIdentifier()])) {
                $variant->setEnabled(false);
            }
        }
    }

    private function applyVariant(ProductVariant $variant, VariantSnapshot $snapshot, int $position): void
    {
        $variant->setCurrentLocale($this->localeCode);
        $variant->setFallbackLocale($this->localeCode);
        $variant->setName($snapshot->name);
        $variant->setEnabled($snapshot->enabled);
        $variant->setRegcis($snapshot->regcis);
        $variant->setBarcode($snapshot->barcode);
        $variant->setWeight($snapshot->weight);
        $variant->setHeight($snapshot->height);
        $variant->setDepth($snapshot->depth);
        $variant->setPosition($position);
        $variant->setShippingRequired(true);
        $variant->setTracked(false);

        $taxCategory = $this->reference(TaxCategory::class, $this->taxCategoryId);
        if ($taxCategory instanceof TaxCategoryInterface) {
            $variant->setTaxCategory($taxCategory);
        }

        $desired = [];
        foreach ($snapshot->optionValues as $groupId => $keys) {
            foreach ($keys as $key) {
                $code = CatalogCodeGenerator::optionValueCode((string) $groupId, $key);
                if (isset($this->optionValueIds[$code])) {
                    $desired[$code] = $this->optionValueIds[$code];
                }
            }
        }

        foreach ($variant->getOptionValues() as $optionValue) {
            $code = (string) $optionValue->getCode();
            if (!isset($desired[$code])) {
                $variant->removeOptionValue($optionValue);
            } else {
                unset($desired[$code]);
            }
        }

        foreach ($desired as $id) {
            $optionValue = $this->reference(ProductOptionValue::class, $id);
            if ($optionValue instanceof ProductOptionValueInterface) {
                $variant->addOptionValue($optionValue);
            }
        }
    }

    private function brand(string $externalIdentifier, string $name): Brand
    {
        $brand = $this->brandRepository->findOneBy(['externalIdentifier' => $externalIdentifier]);
        if (!$brand instanceof Brand) {
            $brand = new Brand(md5($externalIdentifier), $name);
            $brand->setExternalIdentifier($externalIdentifier);
            $this->entityManager->persist($brand);
        }

        $brand->setName($name);

        return $brand;
    }

    private function uniqueProductCode(string $code, string $externalIdentifier): string
    {
        return $this->productRepository->findOneBy(['code' => $code]) === null ? $code : $code . '_' . $externalIdentifier;
    }

    private function uniqueVariantCode(string $code, string $externalIdentifier): string
    {
        return $this->variantRepository->findOneBy(['code' => $code]) === null ? $code : $code . '_' . $externalIdentifier;
    }

    private function uniqueSlug(string $slug, string $externalIdentifier): string
    {
        $exists = (int) $this->entityManager->createQuery(
            'SELECT COUNT(t.id) FROM App\Entity\Product\ProductTranslation t WHERE t.slug = :slug AND t.locale = :locale',
        )->setParameters(['slug' => $slug, 'locale' => $this->localeCode])->getSingleScalarResult();

        return $exists === 0 ? $slug : $slug . '-' . $externalIdentifier;
    }

    private function warmUp(): void
    {
        $channel = $this->channelRepository->findOneBy(['code' => $this->channelCode]);
        $this->channelId = $channel?->getId();
        $this->taxCategoryId = $this->taxCategoryRepository->findOneBy(['code' => $this->taxCategoryCode])?->getId();

        $this->optionIds = [];
        $this->optionValueIds = [];
        foreach ($this->optionRepository->findAll() as $option) {
            $this->optionIds[(string) $option->getCode()] = (int) $option->getId();
            foreach ($option->getValues() as $value) {
                $this->optionValueIds[(string) $value->getCode()] = (int) $value->getId();
            }
        }

        $this->attributeIds = [];
        $this->attributeTypes = [];
        foreach ($this->attributeRepository->findAll() as $attribute) {
            $this->attributeIds[(string) $attribute->getCode()] = (int) $attribute->getId();
            $this->attributeTypes[(string) $attribute->getCode()] = (string) $attribute->getType();
        }

        $this->taxonIds = [];
        foreach ($this->taxonRepository->findAll() as $taxon) {
            if ($taxon->getExternalIdentifier() !== null) {
                $this->taxonIds[$taxon->getExternalIdentifier()] = (int) $taxon->getId();
            }
        }

        $this->entityManager->clear();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function reference(string $class, ?int $id): ?object
    {
        return $id === null ? null : $this->entityManager->find($class, $id);
    }
}
