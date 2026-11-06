<?php

declare(strict_types=1);

namespace App\Partner\Parser;

use App\Partner\Dto\ParsedItem;
use App\Partner\Dto\ParsedPage;

final class FraisaListingParser implements PartnerParserInterface
{
    public const PARTNER = 'fraisa';

    public function partnerCode(): string
    {
        return self::PARTNER;
    }

    public function supportsAutomaticFetching(): bool
    {
        return true;
    }

    public function parse(string $html, string $pageUrl): ParsedPage
    {
        $document = HtmlDocument::load($html);
        $items = [];
        $seen = [];

        foreach ($document->querySelectorAll('[data-testing-sku]') as $row) {
            $code = trim((string) $row->getAttribute('data-testing-sku'));
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;

            $title = $row->querySelector('a.product-title');
            $subtitle = HtmlDocument::text($title?->querySelector('span'));
            $name = HtmlDocument::text($title);
            if ($name !== null && $subtitle !== null) {
                $name = trim(str_replace($subtitle, '', $name));
            }

            $spec = HtmlDocument::text($row->querySelector('.product-description'));
            $detailUrl = HtmlDocument::absoluteUrl($title?->getAttribute('href') ?? $row->querySelector('a[data-testing-id="product-image-link"]')?->getAttribute('href'), $pageUrl);
            $image = HtmlDocument::imageUrl($row->querySelector('img.product-image')?->getAttribute('src'), $pageUrl);

            $items[] = new ParsedItem(
                orderCode: $code,
                name: $name,
                subtitle: $subtitle,
                detailUrl: $detailUrl === null ? null : HtmlDocument::withoutFragment((string) preg_replace('/\?view=list$/', '', $detailUrl)),
                familyCode: null,
                parameters: $spec === null ? [] : ['spec' => $spec] + self::parseSpec($spec),
                imageUrls: $image === null ? [] : [$image],
                price: null,
                currency: null,
                availability: null,
            );
        }

        $pageUrls = [];
        foreach ($document->querySelectorAll('.product-list-paging a[href]') as $link) {
            $url = HtmlDocument::absoluteUrl($link->getAttribute('href'), $pageUrl);
            if ($url !== null) {
                $pageUrls[HtmlDocument::withoutFragment($url)] = true;
            }
        }

        return new ParsedPage($items, array_keys($pageUrls), null);
    }

    /** @return array<string, string> */
    public static function parseSpec(string $spec): array
    {
        $parameters = [];
        if (preg_match('~ø\s*([\d.]+)\s*/\s*([\d.]+)\s*x\s*([\d.]+)\s*/\s*([\d.]+)~u', $spec, $m) === 1) {
            $parameters += ['d1' => $m[1], 'd2' => $m[2], 'l1' => $m[3], 'l2' => $m[4]];
        }
        if (preg_match('~\br\s*([\d.]+)~u', $spec, $m) === 1) {
            $parameters['r'] = $m[1];
        }
        if (preg_match('~\bz\s*(\d+)~u', $spec, $m) === 1) {
            $parameters['z'] = $m[1];
        }

        return $parameters;
    }
}
