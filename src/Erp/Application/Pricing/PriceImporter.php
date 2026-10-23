<?php

declare(strict_types=1);

namespace App\Erp\Application\Pricing;

use App\B2B\Entity\PriceLevel;
use App\B2B\Entity\ProductPrice;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Product\ProductVariant;
use App\Erp\Application\ImportResult;
use App\Erp\Port\Pricing\Dto\VariantPrice;
use App\Erp\Port\Pricing\PricingProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class PriceImporter
{
    private const FLUSH_EVERY = 500;

    /** @var array<string, int> */
    private array $variantIds = [];

    /** @var array<string, int> */
    private array $levelIds = [];

    /**
     * @param list<string> $priceLevelExternalIdentifiers
     */
    public function __construct(
        private readonly PricingProviderInterface $pricingProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly array $priceLevelExternalIdentifiers,
        private readonly string $channelPriceLevelExternalIdentifier,
        private readonly string $channelCode,
        private readonly string $currencyCode,
    ) {
    }

    public function importChangedSince(\DateTimeInterface $since, ?\DateTimeImmutable &$maxModifiedAt = null): ImportResult
    {
        return $this->importPrices(
            $this->pricingProvider->pullChangedSince($since, $this->levels(), $this->currencyCode),
            $maxModifiedAt,
        );
    }

    /** @param list<string> $variantExternalIdentifiers */
    public function importForVariants(array $variantExternalIdentifiers): ImportResult
    {
        return $this->importPrices(
            $this->pricingProvider->pullForVariants($variantExternalIdentifiers, $this->levels(), $this->currencyCode),
        );
    }

    /** @param iterable<VariantPrice> $prices */
    private function importPrices(iterable $prices, ?\DateTimeImmutable &$maxModifiedAt = null): ImportResult
    {
        $result = new ImportResult('prices');
        $this->warmUp();
        $processed = 0;

        foreach ($prices as $price) {
            if ($maxModifiedAt === null || $price->modifiedAt > $maxModifiedAt) {
                $maxModifiedAt = $price->modifiedAt;
            }

            $variantId = $this->variantIds[$price->variantExternalIdentifier] ?? null;
            $levelId = $this->levelIds[$price->priceLevelExternalIdentifier] ?? null;
            if ($variantId === null || $levelId === null) {
                $result->skipped();

                continue;
            }

            $this->upsertProductPrice($variantId, $levelId, $price->price->amount) ? $result->created() : $result->updated();

            if ($price->priceLevelExternalIdentifier === $this->channelPriceLevelExternalIdentifier) {
                $this->upsertChannelPricing($variantId, $price->price->amount);
            }

            if (++$processed % self::FLUSH_EVERY === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        return $result;
    }

    private function upsertProductPrice(int $variantId, int $levelId, int $amount): bool
    {
        $existing = $this->entityManager->getRepository(ProductPrice::class)->findOneBy([
            'variant' => $variantId,
            'priceLevel' => $levelId,
            'currencyCode' => $this->currencyCode,
        ]);

        if ($existing instanceof ProductPrice) {
            if ($existing->getAmount() !== $amount) {
                $existing->updateAmount($amount);
            }

            return false;
        }

        $variant = $this->entityManager->getReference(ProductVariant::class, $variantId);
        $level = $this->entityManager->getReference(PriceLevel::class, $levelId);
        \assert($variant instanceof ProductVariant && $level instanceof PriceLevel);
        $this->entityManager->persist(new ProductPrice($variant, $level, $this->currencyCode, $amount));

        return true;
    }

    private function upsertChannelPricing(int $variantId, int $amount): void
    {
        $pricing = $this->entityManager->getRepository(ChannelPricing::class)->findOneBy([
            'productVariant' => $variantId,
            'channelCode' => $this->channelCode,
        ]);

        if (!$pricing instanceof ChannelPricing) {
            $pricing = new ChannelPricing();
            $pricing->setChannelCode($this->channelCode);
            $pricing->setProductVariant($this->entityManager->getReference(ProductVariant::class, $variantId));
            $this->entityManager->persist($pricing);
        }

        $pricing->setPrice($amount);
    }

    /** @return list<string> */
    private function levels(): array
    {
        return array_values(array_unique([...$this->priceLevelExternalIdentifiers, $this->channelPriceLevelExternalIdentifier]));
    }

    private function warmUp(): void
    {
        /** @var list<array{id: int, externalIdentifier: string}> $variants */
        $variants = $this->entityManager->createQuery(
            'SELECT v.id, v.externalIdentifier FROM App\Entity\Product\ProductVariant v WHERE v.externalIdentifier IS NOT NULL',
        )->getArrayResult();
        $this->variantIds = array_column($variants, 'id', 'externalIdentifier');

        /** @var list<array{id: int, externalIdentifier: string}> $levels */
        $levels = $this->entityManager->createQuery(
            'SELECT l.id, l.externalIdentifier FROM App\B2B\Entity\PriceLevel l WHERE l.externalIdentifier IS NOT NULL',
        )->getArrayResult();
        $this->levelIds = array_column($levels, 'id', 'externalIdentifier');
    }
}
