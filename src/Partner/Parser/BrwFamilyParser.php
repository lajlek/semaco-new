<?php

declare(strict_types=1);

namespace App\Partner\Parser;

use App\Partner\Dto\ParsedItem;
use App\Partner\Dto\ParsedPage;

final class BrwFamilyParser implements PartnerParserInterface, ArticleDetailParserInterface
{
    public const PARTNER = 'brw';

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
        $familyCode = HtmlDocument::text($document->querySelector('.brw-product-detail-top-box-description p'));
        $familyName = HtmlDocument::text($document->querySelector('.brw-product-detail-top-box-description h1'));

        $images = [];
        foreach ($document->querySelectorAll('.brw-product-detail-page-gallery a[data-src]') as $link) {
            $url = HtmlDocument::absoluteUrl($link->getAttribute('data-src'), $pageUrl);
            if ($url !== null) {
                $images[$url] = true;
            }
        }

        $items = [];
        foreach ($document->querySelectorAll('#product-variants .brw_product_page_article_list_row') as $row) {
            $code = HtmlDocument::text($row->querySelector('.brw_product_page_label_container'));
            if ($code === null) {
                continue;
            }

            $nameLink = $row->querySelector('a.product_link_show_tab');
            $dataUrl = null;
            $detailsId = ltrim((string) $nameLink?->getAttribute('href'), '#');
            if (preg_match('/^[\w-]+$/', $detailsId) === 1) {
                $dataUrl = HtmlDocument::absoluteUrl($document->querySelector(sprintf('#%s [data-load]', $detailsId))?->getAttribute('data-load'), $pageUrl);
            }
            $spec = null;
            $productColumn = $nameLink?->parentElement;
            if ($productColumn !== null) {
                $full = HtmlDocument::text($productColumn) ?? '';
                $spec = trim(str_replace([$code, HtmlDocument::text($nameLink) ?? ''], '', $full)) ?: null;
            }

            $parameters = $spec === null ? [] : ['spec' => $spec];
            $label = null;
            foreach ($row->querySelectorAll('.brw_article_attributes div') as $cell) {
                if ($cell->children->length > 0) {
                    continue;
                }
                $text = HtmlDocument::text($cell);
                if (str_contains((string) $cell->getAttribute('class'), 'd-sm-none')) {
                    $label = $text;
                } elseif ($label !== null && $text !== null) {
                    $parameters[$label] = $text;
                    $label = null;
                }
            }

            $items[] = new ParsedItem(
                orderCode: $code,
                name: HtmlDocument::text($nameLink) ?? $familyName,
                subtitle: $familyName,
                detailUrl: HtmlDocument::withoutFragment($pageUrl),
                familyCode: $familyCode,
                parameters: $parameters,
                imageUrls: array_keys($images),
                price: null,
                currency: null,
                availability: null,
                dataUrl: $dataUrl,
            );
        }

        $pageUrls = [];
        foreach ($document->querySelectorAll('.brw_product_page_article_list_footer_pagination_box a[href]') as $link) {
            $url = HtmlDocument::absoluteUrl($link->getAttribute('href'), $pageUrl);
            if ($url !== null) {
                $pageUrls[HtmlDocument::withoutFragment($url)] = true;
            }
        }

        $total = null;
        if (preg_match('/(\d+)\s+gefundene Artikel/u', HtmlDocument::text($document->querySelector('.brw_product_page_article_list_footer_info')) ?? '', $m) === 1) {
            $total = (int) $m[1];
        }

        return new ParsedPage($items, array_keys($pageUrls), $total);
    }

    public function parseArticleDetails(string $html): array
    {
        $document = HtmlDocument::load('<!DOCTYPE html><html><body>' . $html . '</body></html>');
        $parameters = [];
        foreach ($document->querySelectorAll('table.brw_product_attribute_list tr') as $row) {
            $label = HtmlDocument::text($row->querySelector('td.brw_product_attribute_list_name'));
            $value = HtmlDocument::text($row->querySelector('td:not(.brw_product_attribute_list_name)'));
            if ($label === null || $value === null) {
                continue;
            }

            $parameters[$label] = trim((string) preg_replace('/\s*(mm|°)$/u', '', $value));
        }

        return $parameters;
    }
}
