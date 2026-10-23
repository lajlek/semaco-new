<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog\Dto;

final readonly class TaxonSnapshot
{
    public function __construct(
        public string $externalIdentifier,
        public ?string $parentExternalIdentifier,
        public string $name,
        public ?string $description,
        public ?string $metaTitle,
        public ?string $metaKeywords,
        public int $position,
        public bool $enabled,
        public \DateTimeImmutable $modifiedAt,
    ) {
    }
}
