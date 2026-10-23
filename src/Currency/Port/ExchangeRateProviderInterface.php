<?php

declare(strict_types=1);

namespace App\Currency\Port;

interface ExchangeRateProviderInterface
{
    /**
     * @return array<string, float> currency code => CZK for one unit
     */
    public function fetchCzkRates(): array;
}
