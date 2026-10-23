<?php

declare(strict_types=1);

namespace App\Erp\Port\Order\Dto;

final readonly class OrderLine
{
    public const TYPE_GOODS = 0;

    public const TYPE_SHIPPING = 3;

    public function __construct(
        public int $type,
        public ?string $variantExternalIdentifier,
        public int $quantity,
        public float $total,
        public string $priceType,
        public float $vatRate,
    ) {
    }
}
