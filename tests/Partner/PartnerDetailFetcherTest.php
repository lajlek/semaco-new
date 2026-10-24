<?php

declare(strict_types=1);

namespace App\Tests\Partner;

use App\Partner\Entity\PartnerItem;
use App\Partner\Fetch\PartnerDetailFetcher;
use App\Partner\Fetch\PolitePageFetcher;
use App\Partner\Parser\BrwFamilyParser;
use App\Partner\Rules\PartnerRules;
use App\Partner\Service\PartnerParserRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PartnerDetailFetcherTest extends TestCase
{
    private const DETAIL = '<table class="brw_product_attribute_list"><tbody>'
        . '<tr><td class="brw_product_attribute_list_name">d1 [mm]</td><td>0.1 mm</td></tr>'
        . '<tr><td class="brw_product_attribute_list_name">l1 [mm]</td><td>40 mm</td></tr>'
        . '<tr><td class="brw_product_attribute_list_name">Z</td><td>2</td></tr>'
        . '<tr><td class="brw_product_attribute_list_name">Toleranz [mm]</td><td>+/- 0.005 mm</td></tr>'
        . '</tbody></table>';

    public function testFetchesOnlyAllowedPendingItemsWithinLimit(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return str_ends_with($url, '/robots.txt')
                ? new MockResponse("User-agent: *\nCrawl-delay: 0\nDisallow: /Products/Articles/")
                : new MockResponse(self::DETAIL);
        });

        $first = $this->item('222401.0010', 'https://www.brw.ch/de-CH/product/accordion/article-details/1');
        $second = $this->item('222401.0020', 'https://www.brw.ch/de-CH/product/accordion/article-details/2');
        $foreign = $this->item('222401.0030', 'https://evil.example.com/details/3');
        $noUrl = $this->item('222401.0040', null);

        $result = $this->fetcher([$first, $second, $foreign, $noUrl], $client)->fetch('brw', 1);

        self::assertSame(['fetched' => 1, 'stopped' => null, 'remaining' => 1], $result);
        self::assertSame(['https://www.brw.ch/robots.txt', 'https://www.brw.ch/de-CH/product/accordion/article-details/1'], $requests);
        self::assertSame(['d1 [mm]' => '0.1', 'l1 [mm]' => '40', 'Z' => '2', 'Toleranz [mm]' => '+/- 0.005'], $first->getDetailParameters());
        self::assertNotNull($first->getDetailsFetchedAt());
        self::assertNull($second->getDetailsFetchedAt());
    }

    public function testStopsWhenPartnerAsksToSlowDown(): void
    {
        $client = new MockHttpClient(static fn (string $method, string $url): MockResponse => str_ends_with($url, '/robots.txt')
            ? new MockResponse('')
            : new MockResponse('', ['http_code' => 429]));

        $item = $this->item('222401.0010', 'https://www.brw.ch/de-CH/product/accordion/article-details/1');
        $result = $this->fetcher([$item, $this->item('222401.0020', 'https://www.brw.ch/de-CH/product/accordion/article-details/2')], $client)->fetch('brw', 30);

        self::assertSame(0, $result['fetched']);
        self::assertNotNull($result['stopped']);
        self::assertNull($item->getDetailsFetchedAt());
    }

    private function item(string $code, ?string $dataUrl): PartnerItem
    {
        $item = new PartnerItem('brw', $code);
        $item->updateContent('222401', 'FUTURO', null, 'https://www.brw.ch/de-CH/x', null, [], [], null, null, null, sha1($code), $dataUrl);

        return $item;
    }

    /** @param list<PartnerItem> $items */
    private function fetcher(array $items, MockHttpClient $client): PartnerDetailFetcher
    {
        $repository = $this->createStub(RepositoryInterface::class);
        $repository->method('findBy')->willReturn($items);
        $pageFetcher = new PolitePageFetcher($client, new PartnerRules(), 'test-agent', 0.0, 6, sys_get_temp_dir() . '/partner-detail-test', new ArrayAdapter());

        return new PartnerDetailFetcher($repository, $this->createStub(EntityManagerInterface::class), new PartnerParserRegistry([new BrwFamilyParser()]), new PartnerRules(), $pageFetcher);
    }
}
