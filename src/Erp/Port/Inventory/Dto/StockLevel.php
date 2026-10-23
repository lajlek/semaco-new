<?php

declare(strict_types=1);

namespace App\Erp\Port\Inventory\Dto;

final readonly class StockLevel
{
    public function __construct(
        public string $variantExternalIdentifier,
        public int $onHand,
        public \DateTimeImmutable $modifiedAt,
    ) {
    }
}
