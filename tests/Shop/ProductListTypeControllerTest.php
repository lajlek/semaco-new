<?php

declare(strict_types=1);

namespace App\Tests\Shop;

use App\Shop\Controller\ProductListTypeController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductListTypeControllerTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function returnUrls(): iterable
    {
        yield 'relative path' => ['/cs_CZ/products-list/valcove?page=2', '/cs_CZ/products-list/valcove?page=2'];
        yield 'same host from old links' => ['https://eshop.semaco.cz/cs_CZ/products-list/valcove', '/cs_CZ/products-list/valcove'];
        yield 'same host other case' => ['https://ESHOP.semaco.cz/cs_CZ/', '/cs_CZ/'];
        yield 'empty' => ['', null];
        yield 'other host' => ['https://evil.example/cs_CZ/', null];
        yield 'protocol relative' => ['//evil.example/cs_CZ/', null];
        yield 'backslash' => ['/\\evil.example', null];
        yield 'javascript' => ['javascript:alert(1)', null];
        yield 'host without scheme' => ['evil.example/cs_CZ/', null];
        yield 'double slash path' => ['https://eshop.semaco.cz//evil.example', null];
    }

    #[DataProvider('returnUrls')]
    public function testOnlyLocalReturnPathsAreAccepted(string $url, ?string $expected): void
    {
        self::assertSame($expected, ProductListTypeController::localReturnPath($url, 'eshop.semaco.cz'));
    }
}
