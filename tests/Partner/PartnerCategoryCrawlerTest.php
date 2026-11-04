<?php

declare(strict_types=1);

namespace App\Tests\Partner;

use App\Entity\Product\ProductVariant;
use App\Partner\Entity\PartnerCategory;
use App\Partner\Entity\PartnerItem;
use App\Partner\Fetch\PartnerCategoryCrawler;
use App\Partner\Fetch\PolitePageFetcher;
use App\Partner\Fetch\SitemapReader;
use App\Partner\Parser\BrwFamilyParser;
use App\Partner\Rules\PartnerRules;
use App\Partner\Service\PartnerItemMatcher;
use App\Partner\Service\PartnerItemStore;
use App\Partner\Service\PartnerParserRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PartnerCategoryCrawlerTest extends TestCase
{
    private const BASE = 'https://www.brw.ch/de-CH/';

    /** @var list<string> */
    private array $requests = [];

    private int $sitemapPadding = 0;

    public function testFetchesOnlyProductPagesOfTheCategoryFromSitemap(): void
    {
        $category = new PartnerCategory('brw', 'FUTURO', null, 'futuro');
        $result = $this->crawler([
            self::BASE . 'futuro-a-111111' => self::family('111111', 'FUTURO A', '111111.0010'),
        ])->crawl($category, 30);

        self::assertSame(1, $result['pages']);
        self::assertTrue($result['finished']);
        self::assertSame(['111111'], $result['families']);
        self::assertSame(PartnerCategory::STATE_DONE, $category->getState());
        self::assertSame([
            'https://www.brw.ch/robots.txt',
            'https://www.brw.ch/storage/sitemap.xml',
            'https://www.brw.ch/storage/sitemap-1.xml',
            self::BASE . 'futuro-a-111111',
        ], $this->requests);
    }

    public function testLargeSitemapIsReadWhileLargePageIsRefused(): void
    {
        $this->sitemapPadding = 16 * 1024 * 1024;
        $category = new PartnerCategory('brw', 'FUTURO', null, 'futuro');
        $result = $this->crawler([
            self::BASE . 'futuro-a-111111' => new MockResponse(str_repeat(' ', 16 * 1024 * 1024)),
        ])->crawl($category, 30);

        self::assertNotNull($result['stopped']);
        self::assertStringContainsString('15 MB', (string) $result['stopped']);
        self::assertSame([self::BASE . 'futuro-a-111111'], $category->getPendingUrls());
    }

    public function testContinuesWhereItStoppedWithoutReadingSitemapAgain(): void
    {
        $category = new PartnerCategory('brw', 'NERIOX', null, 'neriox');
        $crawler = $this->crawler([
            self::BASE . 'neriox-a-333333' => self::family('333333', 'NERIOX A', '333333.0010'),
            self::BASE . 'neriox-b-444444' => self::family('444444', 'NERIOX B', '444444.0010'),
        ]);

        $first = $crawler->crawl($category, 1);
        self::assertSame(['pages' => 1, 'remaining' => 1, 'finished' => false], array_intersect_key($first, ['pages' => 0, 'remaining' => 0, 'finished' => 0]));

        $this->requests = [];
        $second = $crawler->crawl($category, 1);
        self::assertTrue($second['finished']);
        self::assertSame([self::BASE . 'neriox-b-444444'], $this->requests);
    }

    public function testPageOfForeignBrandIsSkippedAndNotFollowed(): void
    {
        $category = new PartnerCategory('brw', 'Tvrzené', null, 'foreign-c');
        $stored = [];
        $result = $this->crawler([
            self::BASE . 'foreign-c-555555' => self::family('555555', 'Messschieber TESA', '555555.0010', self::BASE . 'product/9?page=2'),
        ], $stored)->crawl($category, 30);

        self::assertSame(1, $result['skipped']);
        self::assertSame([], $result['families']);
        self::assertSame([], $stored);
        self::assertTrue($result['finished']);
        self::assertNotContains(self::BASE . 'product/9?page=2', $this->requests);
    }

    public function testFollowsPaginationWithinPartnerAndNotOutside(): void
    {
        $category = new PartnerCategory('brw', 'FUTURO', self::BASE . 'futuro-a-111111', null);
        $result = $this->crawler([
            self::BASE . 'futuro-a-111111' => self::family('111111', 'FUTURO A', '111111.0010', self::BASE . 'product/1?page=2'),
            self::BASE . 'product/1?page=2' => self::family('111111', 'FUTURO A', '111111.0020'),
        ])->crawl($category, 30);

        self::assertSame(2, $result['pages']);
        self::assertTrue($result['finished']);
        self::assertContains(self::BASE . 'product/1?page=2', $this->requests);
    }

    public function testStopsWhenPartnerAsksToSlowDownAndKeepsPendingPage(): void
    {
        $category = new PartnerCategory('brw', 'FUTURO', self::BASE . 'futuro-a-111111', null);
        $result = $this->crawler([self::BASE . 'futuro-a-111111' => new MockResponse('', ['http_code' => 429])])->crawl($category, 30);

        self::assertSame(0, $result['pages']);
        self::assertFalse($result['finished']);
        self::assertNotNull($result['stopped']);
        self::assertSame([self::BASE . 'futuro-a-111111'], $category->getPendingUrls());
    }

    public function testMissingPageIsSkipped(): void
    {
        $category = new PartnerCategory('brw', 'FUTURO', self::BASE . 'futuro-a-111111', null);
        $result = $this->crawler([self::BASE . 'futuro-a-111111' => new MockResponse('', ['http_code' => 404])])->crawl($category, 30);

        self::assertTrue($result['finished']);
    }

    private static function family(string $code, string $title, string $orderCode, ?string $nextPage = null): MockResponse
    {
        $pagination = $nextPage === null ? '' : sprintf('<div class="brw_product_page_article_list_footer_pagination_box"><a href="%s">2</a></div>', $nextPage);

        return new MockResponse(sprintf(
            '<div class="brw-product-detail-top-box-description"><p>%s</p><h1>%s</h1></div>%s<div id="product-variants"><div class="brw_product_page_article_list_row"><div><div class="brw_product_page_label_container">%s</div><a class="product_link_show_tab">%s</a></div></div></div>',
            $code,
            $title,
            $pagination,
            $orderCode,
            $title,
        ));
    }

    /**
     * @param array<string, MockResponse> $pages
     * @param list<PartnerItem> $stored
     */
    private function crawler(array $pages, array &$stored = []): PartnerCategoryCrawler
    {
        $sitemap = '<urlset>' . implode('', array_map(static fn (string $url): string => sprintf('<url><loc>%s</loc></url>', $url), [
            self::BASE . 'futuro-a-111111',
            self::BASE . 'messschieber-tesa-222222',
            self::BASE . 'futuro-werkzeuge-ts_220433',
            self::BASE . 'neriox-a-333333',
            self::BASE . 'neriox-b-444444',
            self::BASE . 'foreign-c-555555',
        ])) . str_repeat(' ', $this->sitemapPadding) . '</urlset>';

        $client = new MockHttpClient(function (string $method, string $url) use ($pages, $sitemap): MockResponse {
            $this->requests[] = $url;

            return match (true) {
                str_ends_with($url, '/robots.txt') => new MockResponse("User-agent: *\nCrawl-delay: 0"),
                str_ends_with($url, '/storage/sitemap.xml') => new MockResponse('<sitemapindex><sitemap><loc>https://www.brw.ch/storage/sitemap-1.xml</loc></sitemap></sitemapindex>'),
                str_ends_with($url, '/storage/sitemap-1.xml') => new MockResponse($sitemap),
                default => $pages[$url] ?? throw new \RuntimeException('Unexpected request ' . $url),
            };
        });

        $itemRepository = $this->createStub(EntityRepository::class);
        $itemRepository->method('findOneBy')->willReturn(null);
        $variantRepository = $this->createStub(EntityRepository::class);
        $variantRepository->method('findBy')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(static fn (string $class): EntityRepository => $class === ProductVariant::class ? $variantRepository : $itemRepository);
        $entityManager->method('persist')->willReturnCallback(static function (object $item) use (&$stored): void {
            if ($item instanceof PartnerItem) {
                $stored[] = $item;
            }
        });

        $rules = new PartnerRules();
        $fetcher = new PolitePageFetcher($client, $rules, 'test-agent', 0.0, 6, sys_get_temp_dir() . '/partner-category-test', new ArrayAdapter());

        return new PartnerCategoryCrawler(
            $fetcher,
            new SitemapReader($fetcher),
            new PartnerParserRegistry([new BrwFamilyParser()]),
            new PartnerItemStore($entityManager, new PartnerItemMatcher($entityManager)),
            $rules,
            $entityManager,
        );
    }
}
