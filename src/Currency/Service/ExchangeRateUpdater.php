<?php

declare(strict_types=1);

namespace App\Currency\Service;

use App\Currency\Port\ExchangeRateProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Currency\Model\ExchangeRateInterface;
use Sylius\Component\Currency\Repository\ExchangeRateRepositoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

final readonly class ExchangeRateUpdater
{
    /**
     * @param ExchangeRateRepositoryInterface<ExchangeRateInterface> $exchangeRateRepository
     * @param FactoryInterface<ExchangeRateInterface> $exchangeRateFactory
     * @param RepositoryInterface<CurrencyInterface> $currencyRepository
     * @param list<string> $sourceCurrencies
     */
    public function __construct(
        private ExchangeRateProviderInterface $provider,
        private ExchangeRateRepositoryInterface $exchangeRateRepository,
        private FactoryInterface $exchangeRateFactory,
        private RepositoryInterface $currencyRepository,
        private EntityManagerInterface $entityManager,
        private array $sourceCurrencies,
    ) {
    }

    /** @return array<string, float> updated pairs "EUR/CZK" => ratio */
    public function update(): array
    {
        $rates = $this->provider->fetchCzkRates();
        $czk = $this->currencyRepository->findOneBy(['code' => 'CZK']);
        if (!$czk instanceof CurrencyInterface) {
            return [];
        }

        $updated = [];
        foreach ($this->sourceCurrencies as $code) {
            $source = $this->currencyRepository->findOneBy(['code' => $code]);
            $ratio = $rates[$code] ?? null;
            if (!$source instanceof CurrencyInterface || $ratio === null) {
                continue;
            }

            $exchangeRate = $this->exchangeRateRepository->findOneWithCurrencyPair($code, 'CZK');
            if (!$exchangeRate instanceof ExchangeRateInterface) {
                $exchangeRate = $this->exchangeRateFactory->createNew();
                $exchangeRate->setSourceCurrency($source);
                $exchangeRate->setTargetCurrency($czk);
                $this->entityManager->persist($exchangeRate);
            }

            if ($exchangeRate->getSourceCurrency()?->getCode() === 'CZK') {
                $exchangeRate->setRatio(1 / $ratio);
            } else {
                $exchangeRate->setRatio($ratio);
            }

            $updated[$code . '/CZK'] = $ratio;
        }

        $this->entityManager->flush();

        return $updated;
    }
}
