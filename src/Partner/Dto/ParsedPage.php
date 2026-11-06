<?php

declare(strict_types=1);

namespace App\Partner\Dto;

final readonly class ParsedPage
{
    /**
     * @param list<ParsedItem> $items
     * @param list<string> $pageUrls absolute URLs of further listing pages
     * @param list<string> $categoryPath category names from the page breadcrumb
     */
    public function __construct(
        public array $items,
        public array $pageUrls,
        public ?int $totalCount,
        public ?string $title = null,
        public array $categoryPath = [],
    ) {
    }
}
