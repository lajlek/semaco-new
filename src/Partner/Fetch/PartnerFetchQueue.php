<?php

declare(strict_types=1);

namespace App\Partner\Fetch;

use App\Partner\Entity\PartnerFetchRun;
use App\Partner\Rules\PartnerRule;
use App\Partner\Rules\PartnerRules;
use App\Partner\Service\PartnerItemPreparer;
use App\Partner\Service\PartnerParserRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

final readonly class PartnerFetchQueue
{
    /** @param RepositoryInterface<PartnerFetchRun> $runRepository */
    public function __construct(
        #[Autowire(service: 'app.repository.partner_fetch_run')]
        private RepositoryInterface $runRepository,
        private EntityManagerInterface $entityManager,
        private PartnerRules $rules,
        private PartnerParserRegistry $parsers,
        private PartnerListingCrawler $crawler,
        private PartnerDetailFetcher $detailFetcher,
        private PolitePageFetcher $pageFetcher,
        private PartnerItemPreparer $preparer,
        private LockFactory $lockFactory,
        #[Autowire('%app.partners.max_pages%')]
        private int $maxPages,
        #[Autowire('%app.partners.cooldown_hours%')]
        private int $cooldownHours,
    ) {
    }

    public function queue(string $partnerCode, string $url, int $maxPages, string $requestedBy): PartnerFetchRun
    {
        $rule = $this->rules->get($partnerCode);
        if (!$this->parsers->get($partnerCode)->supportsAutomaticFetching()) {
            throw new \DomainException(sprintf('%s nepovoluje automatické načítání (robots.txt). Uložte stránku v prohlížeči a nahrajte ji (Nahrát stránku).', $rule->label));
        }
        if (!$rule->isAllowedUrl($url)) {
            throw new \DomainException(sprintf('Adresa musí začínat https://%s/', implode(' nebo https://', $rule->allowedHosts)));
        }
        if ($maxPages < 1 || $maxPages > $this->maxPages) {
            throw new \DomainException(sprintf('Počet stránek musí být 1 až %d.', $this->maxPages));
        }

        return $this->locked(function () use ($rule, $partnerCode, $url, $maxPages, $requestedBy): PartnerFetchRun {
            $this->assertCanQueue($rule);
            $comparable = PartnerRule::comparableUrl($url);
            $since = new \DateTimeImmutable(sprintf('-%d hours', $this->cooldownHours));
            foreach ($this->runRepository->findBy(['partnerCode' => $partnerCode, 'kind' => PartnerFetchRun::KIND_LISTING], ['requestedAt' => 'DESC'], 200) as $run) {
                if ($run->getRequestedAt() < $since) {
                    break;
                }
                if ($run->getStatus() !== PartnerFetchRun::STATUS_CANCELLED && PartnerRule::comparableUrl($run->getUrl()) === $comparable) {
                    throw new \DomainException(sprintf('Stejná adresa se načítala %s. Znovu ji lze načíst po %d hodinách.', $run->getRequestedAt()->format('d. m. H:i'), $this->cooldownHours));
                }
            }

            return $this->persist(new PartnerFetchRun($partnerCode, $url, $maxPages, $requestedBy));
        });
    }

    public function queueDetails(string $partnerCode, string $requestedBy): PartnerFetchRun
    {
        $rule = $this->rules->get($partnerCode);
        if (!$this->detailFetcher->supports($partnerCode)) {
            throw new \DomainException(sprintf('U partnera %s se detaily artiklů nenačítají.', $rule->label));
        }
        $pending = count($this->detailFetcher->pending($partnerCode));
        if ($pending === 0) {
            throw new \DomainException(sprintf('Všechny položky %s už mají načtený detail.', $rule->label));
        }

        return $this->locked(function () use ($rule, $partnerCode, $pending, $requestedBy): PartnerFetchRun {
            $this->assertCanQueue($rule);

            return $this->persist(new PartnerFetchRun($partnerCode, '', min($pending, $this->maxPages), $requestedBy, PartnerFetchRun::KIND_DETAILS));
        });
    }

    public function cancel(int $id): bool
    {
        return $this->entityManager->getConnection()->executeStatement(
            'UPDATE app_partner_fetch_run SET status = :cancelled, finished_at = NOW() WHERE id = :id AND status = :queued',
            ['cancelled' => PartnerFetchRun::STATUS_CANCELLED, 'id' => $id, 'queued' => PartnerFetchRun::STATUS_QUEUED],
        ) === 1;
    }

    public function processNext(): ?PartnerFetchRun
    {
        $lock = $this->lockFactory->createLock('app-partner-fetch', null);
        if (!$lock->acquire()) {
            return null;
        }

        try {
            $connection = $this->entityManager->getConnection();
            $connection->executeStatement(
                'UPDATE app_partner_fetch_run SET status = :failed, finished_at = NOW(), message = :message WHERE status = :running',
                ['failed' => PartnerFetchRun::STATUS_FAILED, 'message' => 'Běh byl přerušen (restart nebo chyba workeru)', 'running' => PartnerFetchRun::STATUS_RUNNING],
            );

            $run = $this->runRepository->findBy(['status' => PartnerFetchRun::STATUS_QUEUED], ['requestedAt' => 'ASC'], 1)[0] ?? null;
            if (!$run instanceof PartnerFetchRun) {
                return null;
            }
            $started = $connection->executeStatement(
                'UPDATE app_partner_fetch_run SET status = :running, started_at = NOW() WHERE id = :id AND status = :queued',
                ['running' => PartnerFetchRun::STATUS_RUNNING, 'id' => $run->getId(), 'queued' => PartnerFetchRun::STATUS_QUEUED],
            );
            $this->entityManager->refresh($run);
            if ($started !== 1) {
                return null;
            }

            try {
                if ($run->getKind() === PartnerFetchRun::KIND_DETAILS) {
                    $result = $this->detailFetcher->fetch(
                        $run->getPartnerCode(),
                        min($run->getMaxPages(), $this->maxPages),
                        static fn () => $run->addPage(['created' => 0, 'changed' => 1, 'unchanged' => 0]),
                    );
                    $message = $result['stopped'] ?? ($result['remaining'] > 0 ? sprintf('Zbývá načíst detail u %d položek', $result['remaining']) : null);
                } else {
                    $result = $this->crawler->crawl(
                        $run->getPartnerCode(),
                        $run->getUrl(),
                        min($run->getMaxPages(), $this->maxPages),
                        false,
                        static fn (string $url, int $count, array $summary) => $run->addPage($summary),
                    );
                    $message = $result['stopped'] ?? ($result['queued'] > 0 ? sprintf('Další stránky nenačteny (limit): %d', $result['queued']) : null);
                }
                $this->preparer->prepare($run->getPartnerCode());
                $run->finish($result['stopped'] !== null ? PartnerFetchRun::STATUS_STOPPED : PartnerFetchRun::STATUS_DONE, $message);
                $this->entityManager->flush();
            } catch (\Throwable $exception) {
                $message = mb_substr($exception->getMessage(), 0, 1000);
                if ($this->entityManager->isOpen()) {
                    $run->finish(PartnerFetchRun::STATUS_FAILED, $message);
                    $this->entityManager->flush();
                } else {
                    $connection->executeStatement(
                        'UPDATE app_partner_fetch_run SET status = :failed, finished_at = NOW(), message = :message WHERE id = :id',
                        ['failed' => PartnerFetchRun::STATUS_FAILED, 'message' => $message, 'id' => $run->getId()],
                    );
                }
            }

            return $run;
        } finally {
            $lock->release();
        }
    }

    /** @return list<PartnerFetchRun> */
    public function recent(int $limit = 10): array
    {
        return array_values($this->runRepository->findBy([], ['requestedAt' => 'DESC'], $limit));
    }

    /**
     * @param \Closure(): PartnerFetchRun $callback
     */
    private function locked(\Closure $callback): PartnerFetchRun
    {
        $lock = $this->lockFactory->createLock('app-partner-queue', 30);
        $lock->acquire(true);

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function assertCanQueue(PartnerRule $rule): void
    {
        if ($this->runRepository->findBy(['partnerCode' => $rule->partnerCode, 'status' => [PartnerFetchRun::STATUS_QUEUED, PartnerFetchRun::STATUS_RUNNING]]) !== []) {
            throw new \DomainException(sprintf('Načítání %s už čeká nebo běží.', $rule->label));
        }
        foreach ($rule->allowedHosts as $host) {
            $until = $this->pageFetcher->backoffUntil($host);
            if ($until !== null) {
                throw new \DomainException(sprintf('%s požádal o zpomalení. Další načtení lze zařadit po %s.', $rule->label, $until->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('d. m. H:i')));
            }
        }
    }

    private function persist(PartnerFetchRun $run): PartnerFetchRun
    {
        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return $run;
    }
}
