<?php

declare(strict_types=1);

namespace App\Partner\Fetch;

use App\Partner\Entity\PartnerItem;
use App\Partner\Parser\ArticleDetailParserInterface;
use App\Partner\Rules\PartnerRules;
use App\Partner\Service\PartnerParserRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PartnerDetailFetcher
{
    /** @param RepositoryInterface<PartnerItem> $itemRepository */
    public function __construct(
        #[Autowire(service: 'app.repository.partner_item')]
        private RepositoryInterface $itemRepository,
        private EntityManagerInterface $entityManager,
        private PartnerParserRegistry $parsers,
        private PartnerRules $rules,
        private PolitePageFetcher $fetcher,
    ) {
    }

    public function supports(string $partnerCode): bool
    {
        return $this->parsers->get($partnerCode) instanceof ArticleDetailParserInterface &&
            $this->parsers->get($partnerCode)->supportsAutomaticFetching();
    }

    public function countPending(string $partnerCode): int
    {
        $count = $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM app_partner_item WHERE partner_code = ? AND details_fetched_at IS NULL AND data_url IS NOT NULL',
            [$partnerCode],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /** @return list<PartnerItem> items without fetched details, unmatched first */
    public function pending(string $partnerCode): array
    {
        $rule = $this->rules->get($partnerCode);
        $pending = array_values(array_filter(
            $this->itemRepository->findBy(['partnerCode' => $partnerCode], ['orderCode' => 'ASC']),
            static fn (PartnerItem $item): bool => $item->getDetailsFetchedAt() === null &&
                $item->getDataUrl() !== null &&
                $rule->isAllowedUrl($item->getDataUrl()),
        ));
        usort($pending, static fn (PartnerItem $a, PartnerItem $b): int => ($a->getMatchStatus() === PartnerItem::MATCH_MATCHED) <=> ($b->getMatchStatus() === PartnerItem::MATCH_MATCHED));

        return $pending;
    }

    /**
     * @param callable(PartnerItem): void|null $onItem
     *
     * @return array{fetched: int, stopped: string|null, remaining: int}
     */
    public function fetch(string $partnerCode, int $limit, ?callable $onItem = null): array
    {
        $parser = $this->parsers->get($partnerCode);
        if (!$parser instanceof ArticleDetailParserInterface || !$parser->supportsAutomaticFetching()) {
            throw new FetchForbiddenException(sprintf('Detaily artiklů u partnera %s nenačítáme.', $partnerCode));
        }

        $pending = $this->pending($partnerCode);
        $fetched = 0;
        $stopped = null;
        foreach (array_slice($pending, 0, $limit) as $item) {
            try {
                $item->applyDetails($parser->parseArticleDetails($this->fetcher->fetch((string) $item->getDataUrl(), $partnerCode)));
            } catch (PageGoneException) {
                $item->applyDetails([]);
            } catch (FetchForbiddenException $exception) {
                $stopped = $exception->getMessage();

                break;
            }
            $this->entityManager->flush();
            ++$fetched;
            if ($onItem !== null) {
                $onItem($item);
            }
        }

        return ['fetched' => $fetched, 'stopped' => $stopped, 'remaining' => count($pending) - $fetched];
    }
}
