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

    /** @var array<string, RobotsPolicy> */
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
        for ($redirects = 0; ; ++$redirects) {
            $host = $this->assertAllowed($url, $partnerCode);
            $policy = $this->robotsFor($url, $host);
            if (!$policy->isAllowed($url)) {
                throw new FetchForbiddenException(sprintf('robots.txt zakazuje %s', $url));
            }

            $this->waitForTurn($host, $policy);

            try {
                $response = $this->request($url, 60);
                $status = $response->getStatusCode();
                if ($status === 429 || $status === 503) {
                    $this->backOff($host, $status);
                }

                if ($status >= 300 && $status < 400) {
                    $location = HtmlDocument::absoluteUrl($response->getHeaders(false)['location'][0] ?? null, $url);
                    if ($location === null || $redirects >= self::MAX_REDIRECTS) {
                        throw new FetchForbiddenException(sprintf('%s přesměrovává (%d), nepokračuji', $url, $status));
                    }
                    $url = $location;

                    continue;
                }

                $content = $response->getContent();
            } catch (TransportExceptionInterface $exception) {
                throw new FetchForbiddenException(sprintf('Spojení s %s selhalo (%s), nepokračuji', $host, $exception->getMessage()));
            }

            $this->filesystem->dumpFile(
                sprintf('%s/%s/%s-%s.html', $this->snapshotDirectory, $partnerCode, date('Ymd\THis'), substr(sha1($url), 0, 12)),
                '<!-- ' . str_replace('--', '', $url) . " -->\n" . $content,
            );

            return $content;
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

    private function request(string $url, float $maxDuration): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => $this->userAgent, 'Accept-Language' => 'de-CH,de;q=0.9,en;q=0.8'],
            'timeout' => 30,
            'max_duration' => $maxDuration,
            'max_redirects' => 0,
            'on_progress' => static function (int $downloaded): void {
                if ($downloaded > self::MAX_BODY_BYTES) {
                    throw new FetchForbiddenException('Odpověď je větší než 15 MB, nepokračuji');
                }
            },
        ]);
    }

    private function robotsFor(string $url, string $host): RobotsPolicy
    {
        if (isset($this->robots[$host])) {
            return $this->robots[$host];
        }

        $cached = $this->cache->getItem('partner_robots_' . md5($host));
        if ($cached->isHit() && is_string($cached->get())) {
            return $this->robots[$host] = $this->checkedPolicy(RobotsPolicy::parse($cached->get()), $host);
        }

        $this->waitForTurn($host, null);

        try {
            $response = $this->request(sprintf('https://%s/robots.txt', $host), 15);
            $status = $response->getStatusCode();
            if ($status === 429) {
                $this->backOff($host, $status);
            }
            if ($status >= 500 || ($status >= 300 && $status < 400)) {
                throw new FetchForbiddenException(sprintf('robots.txt na %s nedostupný (%d), nepokračuji', $host, $status));
            }

            $content = $status >= 400 ? '' : $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new FetchForbiddenException(sprintf('robots.txt na %s nedostupný (%s), nepokračuji', $host, $exception->getMessage()));
        }
        $this->cache->save($cached->set($content)->expiresAfter(86400));

        return $this->robots[$host] = $this->checkedPolicy(RobotsPolicy::parse($content), $host);
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

        $this->lastRequestAt[$host] = microtime(true);
        $this->cache->save($shared->set($this->lastRequestAt[$host])->expiresAfter(3600));
    }
}
