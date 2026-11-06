<?php

declare(strict_types=1);

namespace App\Partner\Parser;

use App\Partner\Dto\ParsedItem;
use App\Partner\Dto\ParsedPage;

final class PokolmListingParser implements PartnerParserInterface
{
    public const PARTNER = 'pokolm';

    public function partnerCode(): string
    {
        return self::PARTNER;
    }

    public function supportsAutomaticFetching(): bool
    {
        return false;
    }

    public function parse(string $html, string $pageUrl): ParsedPage
    {
        $document = HtmlDocument::load($html);

        $columns = [];
        foreach ($document->querySelectorAll('[id$="productsTable"] thead th') as $index => $header) {
            $columns[$index] = HtmlDocument::text($header->querySelector('.ui-column-title'));
        }

        $items = [];
        foreach ($document->querySelectorAll('tbody[id$="productsTable_data"] tr[data-rk]') as $row) {
            $code = trim((string) $row->getAttribute('data-rk'));
            if ($code === '') {
                continue;
            }

            $codeLink = $row->querySelector('a.pp-product-artnr');
            $descriptions = [];
            foreach ($row->querySelectorAll('nobr.pp-text__gray') as $line) {
                $text = HtmlDocument::text($line);
                if ($text !== null) {
                    $descriptions[] = $text;
                }
            }

            $parameters = [];
            $index = -1;
            foreach ($row->children as $cell) {
                if ($cell->tagName !== 'TD') {
                    continue;
                }
                ++$index;
                $label = $columns[$index] ?? null;
                $value = HtmlDocument::text($cell);
                if ($label !== null && $value !== null && $index > 1) {
                    $parameters[$label] = $value;
                }
            }

            $image = HtmlDocument::imageUrl($row->querySelector('img')?->getAttribute('src'), $pageUrl);

            $items[] = new ParsedItem(
                orderCode: $code,
                name: $descriptions[0] ?? null,
                subtitle: implode(' · ', array_slice($descriptions, 1)) ?: null,
                detailUrl: HtmlDocument::absoluteUrl($codeLink?->getAttribute('href'), $pageUrl),
                familyCode: null,
                parameters: $parameters,
                imageUrls: $image === null || !str_starts_with($image, 'http') ? [] : [$image],
                price: null,
                currency: null,
                availability: null,
            );
        }

        return new ParsedPage($items, [], null);
    }
}
