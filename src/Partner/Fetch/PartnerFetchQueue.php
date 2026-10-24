<?php

declare(strict_types=1);

namespace App\Partner\Fetch;

use App\Partner\Entity\PartnerFetchRun;
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

        $this->assertNoActiveRun($partnerCode, $rule->label);
        foreach ($this->runRepository->findBy(['partnerCode' => $partnerCode, 'kind' => PartnerFetchRun::KIND_LISTING], ['requestedAt' => 'DESC'], 5) as $run) {
            if ($run->getStatus() !== PartnerFetchRun::STATUS_CANCELLED && $run->getUrl() === $url && $run->getRequestedAt() > new \DateTimeImmutable(sprintf('-%d hours', $this->cooldownHours))) {
                throw new \DomainException(sprintf('Stejná adresa se načítala %s. Znovu ji lze načíst po %d hodinách.', $run->getRequestedAt()->format('d. m. H:i'), $this->cooldownHours));
            }
        }

        $run = new PartnerFetchRun($partnerCode, $url, $maxPages, $requestedBy);
        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return $run;
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
        $this->assertNoActiveRun($partnerCode, $rule->label);

        $run = new PartnerFetchRun($partnerCode, '', min($pending, $this->maxPages), $requestedBy, PartnerFetchRun::KIND_DETAILS);
        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return $run;
    }

    public function cancel(int $id): bool
    {
        $run = $this->runRepository->find($id);
        if (!$run instanceof PartnerFetchRun || $run->getStatus() !== PartnerFetchRun::STATUS_QUEUED) {
            return false;
        }

        $run->finish(PartnerFetchRun::STATUS_CANCELLED, null);
        $this->entityManager->flush();

        return true;
    }

    public function processNext(): ?PartnerFetchRun
    {
        $lock = $this->lockFactory->createLock('app-partner-fetch', null);
        if (!$lock->acquire()) {
            return null;
        }

        try {
            $run = $this->runRepository->findBy(['status' => PartnerFetchRun::STATUS_QUEUED], ['requestedAt' => 'ASC'], 1)[0] ?? null;
            if (!$run instanceof PartnerFetchRun) {
                return null;
            }

            $run->start();
            $this->entityManager->flush();

            if ($run->getKind() === PartnerFetchRun::KIND_DETAILS) {
                $this->processDetails($run);

                return $run;
            }

            try {
                $result = $this->crawler->crawl(
                    $run->getPartnerCode(),
                    $run->getUrl(),
                    min($run->getMaxPages(), $this->maxPages),
                    false,
                    static fn (string $url, int $count, array $summary) => $run->addPage($summary),
                );
                $this->preparer->prepare($run->getPartnerCode());
                $message = $result['stopped'] ?? ($result['queued'] > 0 ? sprintf('Další stránky nenačteny (limit): %d', $result['queued']) : null);
                $run->finish($result['stopped'] !== null ? PartnerFetchRun::STATUS_STOPPED : PartnerFetchRun::STATUS_DONE, $message);
            } catch (\Throwable $exception) {
                $run->finish(PartnerFetchRun::STATUS_FAILED, mb_substr($exception->getMessage(), 0, 1000));
            }
            $this->entityManager->flush();

            return $run;
        } finally {
            $lock->release();
        }
    }

    private function processDetails(PartnerFetchRun $run): void
    {
        try {
            $result = $this->detailFetcher->fetch(
                $run->getPartnerCode(),
                min($run->getMaxPages(), $this->maxPages),
                static fn () => $run->addPage(['created' => 0, 'changed' => 1, 'unchanged' => 0]),
            );
            $this->preparer->prepare($run->getPartnerCode());
            $message = $result['stopped'] ?? ($result['remaining'] > 0 ? sprintf('Zbývá načíst detail u %d položek', $result['remaining']) : null);
            $run->finish($result['stopped'] !== null ? PartnerFetchRun::STATUS_STOPPED : PartnerFetchRun::STATUS_DONE, $message);
        } catch (\Throwable $exception) {
            $run->finish(PartnerFetchRun::STATUS_FAILED, mb_substr($exception->getMessage(), 0, 1000));
        }
        $this->entityManager->flush();
    }

    private function assertNoActiveRun(string $partnerCode, string $label): void
    {
        foreach ($this->runRepository->findBy(['partnerCode' => $partnerCode], ['requestedAt' => 'DESC'], 5) as $run) {
            if (in_array($run->getStatus(), [PartnerFetchRun::STATUS_QUEUED, PartnerFetchRun::STATUS_RUNNING], true)) {
                throw new \DomainException(sprintf('Načítání %s už čeká nebo běží.', $label));
            }
        }
    }

    /** @return list<PartnerFetchRun> */
    public function recent(int $limit = 10): array
    {
        return array_values($this->runRepository->findBy([], ['requestedAt' => 'DESC'], $limit));
    }
}
