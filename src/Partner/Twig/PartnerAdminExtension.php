<?php

declare(strict_types=1);

namespace App\Partner\Twig;

use App\Partner\Entity\PartnerFetchRun;
use App\Partner\Entity\PartnerItem;
use App\Partner\Fetch\PartnerDetailFetcher;
use App\Partner\Fetch\PartnerFetchQueue;
use App\Partner\Rules\PartnerRule;
use App\Partner\Rules\PartnerRules;
use App\Partner\Service\PartnerItemPreparer;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class PartnerAdminExtension
{
    public function __construct(
        private PartnerItemPreparer $preparer,
        private PartnerRules $rules,
        private PartnerFetchQueue $fetchQueue,
        private PartnerDetailFetcher $detailFetcher,
    ) {
    }

    /** @return list<array{code: string, name: string, value: string, source: string, override: string}> */
    #[AsTwigFunction('partner_option_fields')]
    public function optionFields(PartnerItem $item): array
    {
        $names = $this->preparer->optionNames();
        $codes = array_values($this->rules->get($item->getPartnerCode())->options);
        $product = $item->getProposedProduct();
        if ($product !== null) {
            $codes = array_values(array_map(static fn (ProductOptionInterface $option): string => (string) $option->getCode(), $product->getOptions()->toArray()));
        }
        $prepared = $item->getPreparedOptions();
        $overrides = $item->getOverrides()['options'] ?? [];
        $codes = array_values(array_unique([...$codes, ...array_keys($prepared)]));

        $fields = array_map(static fn (string $code): array => [
            'code' => $code,
            'name' => $names[$code] ?? $code,
            'value' => $prepared[$code]['value'] ?? '',
            'source' => $prepared[$code]['source'] ?? '',
            'override' => $overrides[$code] ?? '',
        ], $codes);
        usort($fields, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $fields;
    }

    /** @return array<string, PartnerRule> */
    #[AsTwigFunction('partner_rules')]
    public function rules(): array
    {
        return $this->rules->all();
    }

    /** @return array<string, int> partner => items without fetched article details */
    #[AsTwigFunction('partner_details_pending')]
    public function detailsPending(): array
    {
        $pending = [];
        foreach (array_keys($this->rules->all()) as $partnerCode) {
            if ($this->detailFetcher->supports($partnerCode)) {
                $pending[$partnerCode] = count($this->detailFetcher->pending($partnerCode));
            }
        }

        return $pending;
    }

    /** @return list<PartnerFetchRun> */
    #[AsTwigFunction('partner_recent_fetch_runs')]
    public function recentFetchRuns(): array
    {
        return $this->fetchQueue->recent(5);
    }
}
