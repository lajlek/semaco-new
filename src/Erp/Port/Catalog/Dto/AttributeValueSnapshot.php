<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog\Dto;

final readonly class AttributeValueSnapshot
{
    public function __construct(
        public string $key,
        public string $value,
    ) {
    }
}
