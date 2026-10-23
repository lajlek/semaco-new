<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog\Dto;

final readonly class ProductTaxonLink
{
    public function __construct(
        public string $taxonExternalIdentifier,
        public int $position,
        public bool $main,
    ) {
    }
}
