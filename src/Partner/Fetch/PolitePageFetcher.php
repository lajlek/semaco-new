<?php

declare(strict_types=1);

namespace App\Partner\Fetch;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PolitePageFetcher
{
    /** @var array<string, RobotsPolicy> */
    private array $robots = [];

    /** @var array<string, float> */
    private array $lastRequestAt = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%app.partners.user_agent%')]
        private readonly string $userAgent,
        #[Autowire('%app.partners.min_delay_seconds%')]
        private readonly float $minDelaySeconds,
        #[Autowire('%kernel.project_dir%/var/partners/snapshots')]
        private readonly string $snapshotDirectory,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function fetch(string $url, string $partnerCode): string
    {
        $host = (string) parse_url($url, \PHP_URL_HOST);
        $policy = $this->robotsFor($url, $host);
        if (!$policy->isAllowed($url)) {
            throw new FetchForbiddenException(sprintf('robots.txt zakazuje %s', $url));
        }

        $this->waitForTurn($host, $policy);
        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => $this->userAgent, 'Accept-Language' => 'de-CH,de;q=0.9,en;q=0.8'],
            'timeout' => 30,
            'max_redirects' => 3,
        ]);
        $status = $response->getStatusCode();
        if ($status === 429 || $status === 503) {
            throw new FetchForbiddenException(sprintf('%s odpověděl %d, zastavuji', $host, $status));
        }

        $content = $response->getContent();
        $this->filesystem->dumpFile(
            sprintf('%s/%s/%s-%s.html', $this->snapshotDirectory, $partnerCode, date('Ymd\THis'), substr(sha1($url), 0, 12)),
            '<!-- ' . str_replace('--', '', $url) . " -->\n" . $content,
        );

        return $content;
    }

    private function robotsFor(string $url, string $host): RobotsPolicy
    {
        if (isset($this->robots[$host])) {
            return $this->robots[$host];
        }

        $scheme = (string) (parse_url($url, \PHP_URL_SCHEME) ?? 'https');
        $this->waitForTurn($host, null);
        $response = $this->httpClient->request('GET', sprintf('%s://%s/robots.txt', $scheme, $host), [
            'headers' => ['User-Agent' => $this->userAgent],
            'timeout' => 15,
        ]);

        $status = $response->getStatusCode();
        if ($status >= 500) {
            throw new FetchForbiddenException(sprintf('robots.txt na %s nedostupný (%d), nepokračuji', $host, $status));
        }

        return $this->robots[$host] = $status >= 400 ? RobotsPolicy::parse('') : RobotsPolicy::parse($response->getContent(false));
    }

    private function waitForTurn(string $host, ?RobotsPolicy $policy): void
    {
        $delay = max($this->minDelaySeconds, $policy === null ? 0.0 : ($policy->crawlDelay ?? 0.0));
        $last = $this->lastRequestAt[$host] ?? null;
        if ($last !== null) {
            $wait = $last + $delay - microtime(true);
            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }

        $this->lastRequestAt[$host] = microtime(true);
    }
}
