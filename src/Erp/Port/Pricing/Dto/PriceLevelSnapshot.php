<?php

declare(strict_types=1);

namespace App\Erp\Port\Pricing\Dto;

final readonly class PriceLevelSnapshot
{
    public function __construct(
        public string $externalIdentifier,
        public string $name,
    ) {
    }
}
