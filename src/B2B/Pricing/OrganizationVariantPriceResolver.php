<?php

declare(strict_types=1);

namespace App\B2B\Pricing;

use App\B2B\Context\ActiveOrganizationResolverInterface;
use App\B2B\Repository\ProductPriceRepositoryInterface;
use App\Entity\Product\ProductVariant;
use Sylius\Component\Core\Model\ChannelInterface;

final class OrganizationVariantPriceResolver
{
    /** @var array<string, int|null> */
    private array $cache = [];

    public function __construct(
        private readonly ActiveOrganizationResolverInterface $organizationResolver,
        private readonly ProductPriceRepositoryInterface $productPriceRepository,
    ) {
    }

    public function resolve(ProductVariant $variant, ChannelInterface $channel): ?int
    {
        $priceLevel = $this->organizationResolver->resolve()?->getPriceLevel();
        $currencyCode = $channel->getBaseCurrency()?->getCode();
        if ($priceLevel === null || $currencyCode === null || $variant->getId() === null) {
            return null;
        }

        $key = sprintf('%d|%d|%s', $variant->getId(), (int) $priceLevel->getId(), $currencyCode);
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->productPriceRepository
                ->findForVariantLevelCurrency($variant, $priceLevel, $currencyCode)
                ?->getAmount();
        }

        return $this->cache[$key];
    }
}
