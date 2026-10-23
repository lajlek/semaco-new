<?php

declare(strict_types=1);

namespace App\Erp\Port\Pricing;

use App\Erp\Port\Pricing\Dto\VariantPrice;

interface PricingProviderInterface
{
    /**
     * @param list<string> $priceLevelExternalIdentifiers
     *
     * @return iterable<VariantPrice>
     */
    public function pullChangedSince(\DateTimeInterface $since, array $priceLevelExternalIdentifiers, string $currencyCode): iterable;

    /**
     * @param list<string> $variantExternalIdentifiers
     * @param list<string> $priceLevelExternalIdentifiers
     *
     * @return iterable<VariantPrice>
     */
    public function pullForVariants(array $variantExternalIdentifiers, array $priceLevelExternalIdentifiers, string $currencyCode): iterable;
}
