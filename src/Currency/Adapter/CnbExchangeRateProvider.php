<?php

declare(strict_types=1);

namespace App\Currency\Adapter;

use App\Currency\Port\ExchangeRateProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CnbExchangeRateProvider implements ExchangeRateProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $dailyRatesUrl,
    ) {
    }

    public function fetchCzkRates(): array
    {
        $content = $this->httpClient->request('GET', $this->dailyRatesUrl, ['timeout' => 20])->getContent();

        return self::parse($content);
    }

    /** @return array<string, float> */
    public static function parse(string $content): array
    {
        $rates = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $columns = explode('|', trim($line));
            if (count($columns) !== 5 || !is_numeric($columns[2])) {
                continue;
            }

            $amount = (float) $columns[2];
            $rate = (float) str_replace(',', '.', $columns[4]);
            if ($amount > 0 && $rate > 0) {
                $rates[strtoupper($columns[3])] = $rate / $amount;
            }
        }

        return $rates;
    }
}
