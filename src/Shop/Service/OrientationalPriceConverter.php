<?php

declare(strict_types=1);

namespace App\Shop\Service;

use Sylius\Component\Currency\Model\ExchangeRateInterface;
use Sylius\Component\Currency\Repository\ExchangeRateRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OrientationalPriceConverter
{
    /** @var array<string, ExchangeRateInterface|null> */
    private array $rates = [];

    /** @param ExchangeRateRepositoryInterface<ExchangeRateInterface> $exchangeRateRepository */
    public function __construct(
        #[Autowire(service: 'sylius.repository.exchange_rate')]
        private readonly ExchangeRateRepositoryInterface $exchangeRateRepository,
    ) {
    }

    public function convert(int $amount, string $sourceCurrencyCode, string $targetCurrencyCode): ?int
    {
        if ($sourceCurrencyCode === $targetCurrencyCode) {
            return $amount;
        }

        $rate = $this->findRate($sourceCurrencyCode, $targetCurrencyCode);
        if (null === $rate) {
            return null;
        }

        if ($rate->getSourceCurrency()?->getCode() === $sourceCurrencyCode) {
            return (int) round($amount * (float) $rate->getRatio());
        }

        return (int) round($amount / (float) $rate->getRatio());
    }

    private function findRate(string $sourceCurrencyCode, string $targetCurrencyCode): ?ExchangeRateInterface
    {
        $key = $sourceCurrencyCode . '-' . $targetCurrencyCode;
        if (!array_key_exists($key, $this->rates)) {
            $this->rates[$key] = $this->exchangeRateRepository->findOneWithCurrencyPair($sourceCurrencyCode, $targetCurrencyCode);
        }

        return $this->rates[$key];
    }
}
