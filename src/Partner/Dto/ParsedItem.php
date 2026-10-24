<?php

declare(strict_types=1);

namespace App\Partner\Dto;

final readonly class ParsedItem
{
    /**
     * @param array<string, string> $parameters source label => value
     * @param list<string> $imageUrls
     */
    public function __construct(
        public string $orderCode,
        public ?string $name,
        public ?string $subtitle,
        public ?string $detailUrl,
        public ?string $familyCode,
        public array $parameters,
        public array $imageUrls,
        public ?string $price,
        public ?string $currency,
        public ?string $availability,
        public ?string $dataUrl = null,
    ) {
    }
}
