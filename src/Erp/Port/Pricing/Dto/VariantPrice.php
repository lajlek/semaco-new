<?php

declare(strict_types=1);

namespace App\Erp\Port\Pricing\Dto;

use App\Erp\Port\Common\Dto\Money;

final readonly class VariantPrice
{
    public function __construct(
        public string $variantExternalIdentifier,
        public string $priceLevelExternalIdentifier,
        public Money $price,
        public \DateTimeImmutable $modifiedAt,
    ) {
    }
}
