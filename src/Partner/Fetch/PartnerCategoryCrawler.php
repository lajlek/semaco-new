<?php

declare(strict_types=1);

namespace App\Partner\Fetch;

use App\Partner\Entity\PartnerCategory;
use App\Partner\Rules\PartnerRule;
use App\Partner\Rules\PartnerRules;
use App\Partner\Service\PartnerItemStore;
use App\Partner\Service\PartnerParserRegistry;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PartnerCategoryCrawler
{
    public function __construct(
        private PolitePageFetcher $fetcher,
        private SitemapReader $sitemaps,
        private PartnerParserRegistry $parsers,
        private PartnerItemStore $store,
        private PartnerRules $rules,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param callable(array{created: int, changed: int, unchanged: int}): void|null $onPage
     *
     * @return array{pages: int, skipped: int, remaining: int, finished: bool, stopped: string|null, families: list<string>}
     */
    public function crawl(PartnerCategory $category, int $maxPages, ?callable $onPage = null): array
    {
        $partnerCode = $category->getPartnerCode();
        $rule = $this->rules->get($partnerCode);
        $parser = $this->parsers->get($partnerCode);
        if (!$parser->supportsAutomaticFetching()) {
            throw new FetchForbiddenException(sprintf('U partnera %s automatické načítání neprovádíme.', $rule->label));
        }

        $result = ['pages' => 0, 'skipped' => 0, 'remaining' => 0, 'finished' => false, 'stopped' => null, 'families' => []];
        if ($category->getPendingUrls() === null) {
            try {
                $category->begin($this->startUrls($category, $rule));
            } catch (FetchForbiddenException $exception) {
                $result['stopped'] = $exception->getMessage();

                return $result;
            }
            $this->entityManager->flush();
        }

        $families = [];
        while ($result['pages'] < $maxPages && ($url = $category->nextUrl()) !== null) {
            try {
                $page = $parser->parse($this->fetcher->fetch($url, $partnerCode), $url);
            } catch (PageGoneException) {
                $category->markVisited($url);
                $this->entityManager->flush();

                continue;
            } catch (FetchForbiddenException $exception) {
                $result['stopped'] = $exception->getMessage();

                break;
            }

            ++$result['pages'];
            if (!$rule->allowsPage($page)) {
                ++$result['skipped'];
                $category->markVisited($url);
                $this->entityManager->flush();

                continue;
            }

            $summary = $this->store->store($partnerCode, $page->items, $url, false);
            foreach ($page->items as $item) {
                if ($item->familyCode !== null) {
                    $families[$item->familyCode] = true;
                }
            }
            if ($onPage !== null) {
                $onPage($summary);
            }
            $category->markVisited($url, array_values(array_filter(array_map(PartnerListingCrawler::normalize(...), $page->pageUrls), $rule->isAllowedUrl(...))));
            $this->entityManager->flush();
        }

        $result['families'] = array_map(strval(...), array_keys($families));
        $result['remaining'] = count($category->getPendingUrls() ?? []);
        if ($result['stopped'] === null && $result['remaining'] === 0) {
            $category->finish();
            $result['finished'] = true;
            $this->entityManager->flush();
        }

        return $result;
    }

    /** @return list<string> */
    private function startUrls(PartnerCategory $category, PartnerRule $rule): array
    {
        $urls = [];
        if ($category->getStartUrl() !== null && $rule->isAllowedUrl($category->getStartUrl())) {
            $urls[] = PartnerListingCrawler::normalize($category->getStartUrl());
        }

        if ($rule->sitemapUrl !== null && $category->getUrlKeywords() !== null) {
            foreach ($this->sitemaps->urls($category->getPartnerCode(), $rule->sitemapUrl) as $url) {
                if ($rule->isAllowedUrl($url) && $rule->isListingUrl($url) && $category->matchesUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }
}
