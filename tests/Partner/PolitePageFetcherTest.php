<?php

declare(strict_types=1);

namespace App\Tests\Partner;

use App\Partner\Fetch\FetchForbiddenException;
use App\Partner\Fetch\PolitePageFetcher;
use App\Partner\Rules\PartnerRules;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PolitePageFetcherTest extends TestCase
{
    /** @var list<string> */
    private array $requests = [];

    public function testRefusesHostsOutsideThePartner(): void
    {
        $fetcher = $this->fetcher(static fn (): MockResponse => new MockResponse('ok'));

        foreach (['https://evil.example.com/x', 'http://www.brw.ch/x', 'https://www.pokolm.com/portal/x'] as $url) {
            try {
                $fetcher->fetch($url, str_contains($url, 'pokolm') ? 'pokolm' : 'brw');
                self::fail('Fetch should be refused: ' . $url);
            } catch (FetchForbiddenException) {
            }
        }
        self::assertSame([], $this->requests);
    }

    public function testDoesNotFollowRedirectToAnotherHost(): void
    {
        $fetcher = $this->fetcher(static fn (string $url): MockResponse => str_ends_with($url, '/robots.txt')
            ? new MockResponse('')
            : new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://internal.example.com/']]));

        $this->expectException(FetchForbiddenException::class);

        try {
            $fetcher->fetch('https://www.brw.ch/de-CH/x', 'brw');
        } finally {
            self::assertSame(['https://www.brw.ch/robots.txt', 'https://www.brw.ch/de-CH/x'], $this->requests);
        }
    }

    public function testBacksOffAfterTooManyRequests(): void
    {
        $fetcher = $this->fetcher(static fn (string $url): MockResponse => str_ends_with($url, '/robots.txt')
            ? new MockResponse('')
            : new MockResponse('', ['http_code' => 429]));

        try {
            $fetcher->fetch('https://www.brw.ch/de-CH/a', 'brw');
            self::fail('429 should stop fetching');
        } catch (FetchForbiddenException) {
        }

        self::assertNotNull($fetcher->backoffUntil('www.brw.ch'));
        $this->expectException(FetchForbiddenException::class);
        $fetcher->fetch('https://www.brw.ch/de-CH/b', 'brw');
    }

    /** @param \Closure(string): MockResponse $responder */
    private function fetcher(\Closure $responder): PolitePageFetcher
    {
        $client = new MockHttpClient(function (string $method, string $url) use ($responder): MockResponse {
            $this->requests[] = $url;

            return $responder($url);
        });

        return new PolitePageFetcher($client, new PartnerRules(), 'test-agent', 0.0, 6, sys_get_temp_dir() . '/partner-fetcher-test', new ArrayAdapter());
    }
}
