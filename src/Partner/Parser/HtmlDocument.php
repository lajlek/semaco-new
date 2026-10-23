<?php

declare(strict_types=1);

namespace App\Partner\Parser;

final class HtmlDocument
{
    public static function load(string $html): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString($html, \LIBXML_NOERROR | \LIBXML_HTML_NOIMPLIED);
    }

    public static function text(?\Dom\Element $element): ?string
    {
        if ($element === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $element->textContent));

        return $text !== '' ? $text : null;
    }

    public static function absoluteUrl(?string $url, string $baseUrl): ?string
    {
        if ($url === null || $url === '' || str_starts_with($url, '#')) {
            return null;
        }

        if (preg_match('~^https?://~i', $url) === 1) {
            return $url;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        return str_starts_with($url, '/') ? $origin . $url : $origin . '/' . ltrim($url, './');
    }

    public static function withoutFragment(string $url): string
    {
        return explode('#', $url, 2)[0];
    }
}
