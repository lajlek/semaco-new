<?php

declare(strict_types=1);

namespace App\Erp\Port\Common\Dto;

final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currencyCode,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Money amount cannot be negative.');
        }
        if (strlen($currencyCode) !== 3) {
            throw new \InvalidArgumentException('Currency code must be ISO 4217 (3 letters).');
        }
    }
}
