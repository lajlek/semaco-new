<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog\Dto;

final readonly class VariantSnapshot
{
    /**
     * @param array<array-key, list<string>> $optionValues attribute group externalIdentifier => value keys
     */
    public function __construct(
        public string $externalIdentifier,
        public string $code,
        public ?string $regcis,
        public string $name,
        public bool $enabled,
        public array $optionValues,
        public ?float $weight,
        public ?float $height,
        public ?float $depth,
        public ?string $barcode,
        public int $quantityStep,
        public int $minimumQuantity,
        public \DateTimeImmutable $modifiedAt,
    ) {
    }
}
