<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog\Dto;

final readonly class BrandSnapshot
{
    public function __construct(
        public string $externalIdentifier,
        public string $name,
    ) {
    }
}
