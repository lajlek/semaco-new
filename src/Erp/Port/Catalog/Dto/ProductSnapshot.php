<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog\Dto;

final readonly class ProductSnapshot
{
    /**
     * @param list<ProductTaxonLink> $taxonLinks
     * @param array<array-key, list<string>|int> $attributes attribute group externalIdentifier => value keys (select) or number (integer)
     * @param list<VariantSnapshot> $variants the master's own variant first, then its children
     */
    public function __construct(
        public string $externalIdentifier,
        public string $code,
        public ?string $regcis,
        public ?string $skupzbo,
        public string $name,
        public ?string $description,
        public ?string $shortDescription,
        public ?string $metaKeywords,
        public ?string $metaDescription,
        public bool $enabled,
        public ?BrandSnapshot $brand,
        public int $quantityStep,
        public int $minimumQuantity,
        public array $taxonLinks,
        public array $attributes,
        public array $variants,
        public \DateTimeImmutable $modifiedAt,
    ) {
    }
}
