<?php

declare(strict_types=1);

namespace App\Partner\Fetch;

use App\Partner\Rules\PartnerRules;
use App\Partner\Service\PartnerItemStore;
use App\Partner\Service\PartnerParserRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PartnerListingCrawler
{
    public function __construct(
        private PolitePageFetcher $fetcher,
        private PartnerParserRegistry $parsers,
        private PartnerItemStore $store,
        private PartnerRules $rules,
        #[Autowire('%app.partners.max_pages%')]
        private int $maxPagesCap,
    ) {
    }

    /**
     * @param callable(string, int, array{created: int, changed: int, unchanged: int}): void|null $onPage
     *
     * @return array{pages: int, created: int, changed: int, unchanged: int, reported: int|null, queued: int, stopped: string|null}
     */
    public function crawl(string $partnerCode, string $startUrl, int $maxPages, bool $dryRun = false, ?callable $onPage = null): array
    {
        $parser = $this->parsers->get($partnerCode);
        if (!$parser->supportsAutomaticFetching()) {
            throw new FetchForbiddenException(sprintf('U partnera %s automatické načítání neprovádíme (robots.txt / podmínky).', $partnerCode));
        }

        $rule = $this->rules->get($partnerCode);
        if (!$rule->isAllowedUrl($startUrl)) {
            throw new FetchForbiddenException(sprintf('%s není adresa na webu partnera %s.', $startUrl, $rule->label));
        }
        $maxPages = max(1, min($maxPages, $this->maxPagesCap));

        $queue = [self::normalize($startUrl)];
        $visited = [];
        $result = ['pages' => 0, 'created' => 0, 'changed' => 0, 'unchanged' => 0, 'reported' => null, 'queued' => 0, 'stopped' => null];

        while ($queue !== [] && count($visited) < $maxPages) {
            $url = array_shift($queue);
            if (isset($visited[$url])) {
                continue;
            }
            $visited[$url] = true;

            try {
                $page = $parser->parse($this->fetcher->fetch($url, $partnerCode), $url);
            } catch (FetchForbiddenException $exception) {
                $result['stopped'] = $exception->getMessage();

                break;
            }

            $result['reported'] ??= $page->totalCount;
            $summary = $this->store->store($partnerCode, $page->items, $url, $dryRun);
            ++$result['pages'];
            $result['created'] += $summary['created'];
            $result['changed'] += $summary['changed'];
            $result['unchanged'] += $summary['unchanged'];
            if ($onPage !== null) {
                $onPage($url, count($page->items), $summary);
            }

            foreach (array_map(self::normalize(...), $page->pageUrls) as $pageUrl) {
                if ($rule->isAllowedUrl($pageUrl) && !isset($visited[$pageUrl]) && !in_array($pageUrl, $queue, true)) {
                    $queue[] = $pageUrl;
                }
            }
        }
        $result['queued'] = count($queue);

        return $result;
    }

    private static function normalize(string $url): string
    {
        $url = (string) preg_replace('/([?&])page=1(?=&|$)/', '$1', $url);

        return rtrim(str_replace(['?&', '&&'], ['?', '&'], $url), '?&');
    }
}
