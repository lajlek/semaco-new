<?php

declare(strict_types=1);

namespace App\Partner\Fetch;

use App\Partner\Parser\HtmlDocument;
use App\Partner\Rules\PartnerRules;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PolitePageFetcher
{
    private const MAX_REDIRECTS = 3;

    private const MAX_CRAWL_DELAY_SECONDS = 60.0;

    private const MAX_BODY_BYTES = 15 * 1024 * 1024;

    private const MAX_SITEMAP_BYTES = 64 * 1024 * 1024;

    private const ROBOTS_TTL_SECONDS = 86400;

    /** @var array<string, array{policy: RobotsPolicy, loadedAt: int}> */
    private array $robots = [];

    /** @var array<string, float> */
    private array $lastRequestAt = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PartnerRules $rules,
        #[Autowire('%app.partners.user_agent%')]
        private readonly string $userAgent,
        #[Autowire('%app.partners.min_delay_seconds%')]
        private readonly float $minDelaySeconds,
        #[Autowire('%app.partners.cooldown_hours%')]
        private readonly int $backoffHours,
        #[Autowire('%kernel.project_dir%/var/partners/snapshots')]
        private readonly string $snapshotDirectory,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function fetch(string $url, string $partnerCode): string
    {
        $body = $this->fetchChecked($url, $partnerCode, self::MAX_BODY_BYTES);
        $this->filesystem->dumpFile(
            sprintf('%s/%s/%s-%s.html', $this->snapshotDirectory, $partnerCode, date('Ymd\THis'), substr(sha1($url), 0, 12)),
            '<!-- ' . str_replace('--', '', $url) . " -->\n" . $body,
        );

        return $body;
    }

    public function fetchSitemap(string $url, string $partnerCode): string
    {
        return $this->fetchChecked($url, $partnerCode, self::MAX_SITEMAP_BYTES);
    }

    private function fetchChecked(string $url, string $partnerCode, int $maxBytes): string
    {
        for ($redirects = 0; ; ++$redirects) {
            $host = $this->assertAllowed($url, $partnerCode);
            $policy = $this->robotsFor($host);
            if (!$policy->isAllowed($url)) {
                throw new FetchForbiddenException(sprintf('robots.txt zakazuje %s', $url));
            }

            $result = $this->exchange($host, $url, $policy, 60, $maxBytes);
            $status = $result['status'];
            if ($status === 429 || $status === 403 || $status >= 500) {
                $this->backOff($host, $status);
            }
            if ($status >= 300 && $status < 400) {
                if ($result['location'] === null || $redirects >= self::MAX_REDIRECTS) {
                    throw new FetchForbiddenException(sprintf('%s přesměrovává (%d), nepokračuji', $url, $status));
                }
                $url = $result['location'];

                continue;
            }
            if ($status === 404 || $status === 410) {
                throw new PageGoneException(sprintf('%s neexistuje (%d)', $url, $status));
            }
            if ($status >= 400) {
                throw new FetchForbiddenException(sprintf('%s odpověděl %d, nepokračuji', $url, $status));
            }

            return $result['body'];
        }
    }

    public function backoffUntil(string $host): ?\DateTimeImmutable
    {
        $item = $this->cache->getItem('partner_backoff_' . md5(strtolower($host)));
        if (!$item->isHit() || !is_int($item->get()) || $item->get() <= time()) {
            return null;
        }

        return new \DateTimeImmutable('@' . $item->get());
    }

    private function assertAllowed(string $url, string $partnerCode): string
    {
        if (!$this->rules->get($partnerCode)->isAllowedUrl($url)) {
            throw new FetchForbiddenException(sprintf('%s není adresa na webu partnera, nestahuji', $url));
        }

        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));
        $until = $this->backoffUntil($host);
        if ($until !== null) {
            throw new FetchForbiddenException(sprintf('%s požádal o zpomalení, další požadavky až po %s', $host, $until->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('d. m. H:i')));
        }

        return $host;
    }

    private function backOff(string $host, int $status): never
    {
        $item = $this->cache->getItem('partner_backoff_' . md5(strtolower($host)));
        $this->cache->save($item->set(time() + $this->backoffHours * 3600)->expiresAfter($this->backoffHours * 3600));

        throw new FetchForbiddenException(sprintf('%s odpověděl %d, zastavuji a %d h nic nestahuji', $host, $status, $this->backoffHours));
    }

    private function request(string $url, float $maxDuration, int $maxBytes): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => $this->userAgent, 'Accept-Language' => 'de-CH,de;q=0.9,en;q=0.8'],
            'timeout' => 30,
            'max_duration' => $maxDuration,
            'max_redirects' => 0,
            'on_progress' => static function (int $downloaded) use ($maxBytes): void {
                if ($downloaded > $maxBytes) {
                    throw new FetchForbiddenException(sprintf('Odpověď je větší než %d MB, nepokračuji', $maxBytes / 1024 / 1024));
                }
            },
        ]);
    }

    private function robotsFor(string $host): RobotsPolicy
    {
        $memo = $this->robots[$host] ?? null;
        if ($memo !== null && $memo['loadedAt'] > time() - self::ROBOTS_TTL_SECONDS) {
            return $memo['policy'];
        }

        $cached = $this->cache->getItem('partner_robots_' . md5($host));
        if ($cached->isHit() && is_string($cached->get())) {
            $content = $cached->get();
        } else {
            $url = sprintf('https://%s/robots.txt', $host);
            for ($redirects = 0; ; ++$redirects) {
                $result = $this->exchange($host, $url, null, 15, self::MAX_BODY_BYTES);
                $status = $result['status'];
                if ($status === 429 || $status >= 500) {
                    $this->backOff($host, $status);
                }
                if ($status >= 300 && $status < 400) {
                    $location = $result['location'];
                    if ($location === null || $redirects >= self::MAX_REDIRECTS || !str_starts_with($location, 'https://') || strtolower((string) parse_url($location, \PHP_URL_HOST)) !== $host) {
                        throw new FetchForbiddenException(sprintf('robots.txt na %s přesměrovává mimo web, nepokračuji', $host));
                    }
                    $url = $location;

                    continue;
                }

                $content = $status >= 400 ? '' : $result['body'];

                break;
            }
            $this->cache->save($cached->set($content)->expiresAfter(self::ROBOTS_TTL_SECONDS));
        }

        $policy = $this->checkedPolicy(RobotsPolicy::parse($content), $host);
        $this->robots[$host] = ['policy' => $policy, 'loadedAt' => time()];

        return $policy;
    }

    /** @return array{status: int, location: ?string, body: string} */
    private function exchange(string $host, string $url, ?RobotsPolicy $policy, float $maxDuration, int $maxBytes): array
    {
        $this->waitForTurn($host, $policy);

        try {
            $response = $this->request($url, $maxDuration, $maxBytes);
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            if ($status < 200 || $status >= 300) {
                $response->cancel();

                return ['status' => $status, 'location' => HtmlDocument::absoluteUrl($headers['location'][0] ?? null, $url), 'body' => ''];
            }

            $body = $response->getContent(false);
            if (strlen($body) > $maxBytes) {
                throw new FetchForbiddenException(sprintf('Odpověď je větší než %d MB, nepokračuji', $maxBytes / 1024 / 1024));
            }

            return ['status' => $status, 'location' => null, 'body' => $body];
        } catch (TransportExceptionInterface $exception) {
            throw new FetchForbiddenException(sprintf('Spojení s %s selhalo (%s), nepokračuji', $host, $exception->getMessage()));
        } finally {
            $this->markRequestDone($host);
        }
    }

    private function checkedPolicy(RobotsPolicy $policy, string $host): RobotsPolicy
    {
        if (($policy->crawlDelay ?? 0.0) > self::MAX_CRAWL_DELAY_SECONDS) {
            throw new FetchForbiddenException(sprintf('%s žádá rozestup %d s, automatické načítání by bylo příliš pomalé', $host, (int) $policy->crawlDelay));
        }

        return $policy;
    }

    private function waitForTurn(string $host, ?RobotsPolicy $policy): void
    {
        $delay = max($this->minDelaySeconds, $policy === null ? 0.0 : ($policy->crawlDelay ?? 0.0));
        $shared = $this->cache->getItem('partner_last_request_' . md5($host));
        $last = max($this->lastRequestAt[$host] ?? 0.0, $shared->isHit() && is_float($shared->get()) ? $shared->get() : 0.0);
        $wait = $last + $delay - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }

        $this->markRequestDone($host);
    }

    private function markRequestDone(string $host): void
    {
        $this->lastRequestAt[$host] = microtime(true);
        $shared = $this->cache->getItem('partner_last_request_' . md5($host));
        $this->cache->save($shared->set($this->lastRequestAt[$host])->expiresAfter(3600));
    }
}
