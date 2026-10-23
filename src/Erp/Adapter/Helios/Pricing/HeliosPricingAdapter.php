<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Pricing;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRateLimiter;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRetryTrait;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Port\Common\Dto\Money;
use App\Erp\Port\Pricing\Dto\VariantPrice;
use App\Erp\Port\Pricing\PricingProviderInterface;
use Doctrine\DBAL\ArrayParameterType;

final readonly class HeliosPricingAdapter implements PricingProviderInterface
{
    use HeliosRetryTrait;

    private const BATCH_SIZE = 1000;

    private const COLUMNS = 'id, idkmenzbozi, cenovauroven, cenakc, cenaval1, cenaval2, cenaval3, cenaval4, cenaval5,
        mena1, mena2, mena3, mena4, mena5, poslednizmena';

    public function __construct(
        private HeliosConnectionFactory $connections,
        private HeliosRateLimiter $rateLimiter,
        private string $heliosMainCurrency = 'CZK',
    ) {
    }

    public function pullChangedSince(\DateTimeInterface $since, array $priceLevelExternalIdentifiers, string $currencyCode): iterable
    {
        $levels = self::levelIds($priceLevelExternalIdentifiers);
        if ($levels === []) {
            return;
        }

        $offset = 0;
        while (true) {
            $this->rateLimiter->consume();
            $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
                sprintf(
                    'SELECT %s FROM hvw_SapertaTabSapCenoveUrovne
                     WHERE cenovauroven IN (:levels) AND poslednizmena > :since
                     ORDER BY poslednizmena ASC, id ASC
                     OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
                    self::COLUMNS,
                    $offset,
                    self::BATCH_SIZE,
                ),
                ['levels' => $levels, 'since' => HeliosRow::formatSince($since)],
                ['levels' => ArrayParameterType::INTEGER],
            ));

            if ($rows === []) {
                return;
            }

            yield from $this->mapRows($rows, $currencyCode);

            if (count($rows) < self::BATCH_SIZE) {
                return;
            }

            $offset += self::BATCH_SIZE;
            usleep(100_000);
        }
    }

    public function pullForVariants(array $variantExternalIdentifiers, array $priceLevelExternalIdentifiers, string $currencyCode): iterable
    {
        $levels = self::levelIds($priceLevelExternalIdentifiers);
        $variantIds = array_values(array_unique(array_filter(array_map('intval', $variantExternalIdentifiers), static fn (int $id): bool => $id > 0)));
        if ($levels === [] || $variantIds === []) {
            return;
        }

        foreach (array_chunk($variantIds, 500) as $chunk) {
            $this->rateLimiter->consume();
            $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
                sprintf('SELECT %s FROM hvw_SapertaTabSapCenoveUrovne WHERE cenovauroven IN (:levels) AND idkmenzbozi IN (:ids) ORDER BY id ASC', self::COLUMNS),
                ['levels' => $levels, 'ids' => $chunk],
                ['levels' => ArrayParameterType::INTEGER, 'ids' => ArrayParameterType::INTEGER],
            ));

            yield from $this->mapRows($rows, $currencyCode);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return iterable<VariantPrice>
     */
    private function mapRows(array $rows, string $currencyCode): iterable
    {
        foreach ($rows as $row) {
            $variantId = HeliosRow::string($row, 'idkmenzbozi');
            $level = HeliosRow::string($row, 'cenovauroven');
            $amount = $this->amountIn($row, $currencyCode);
            if ($variantId === null || $level === null || $amount === null) {
                continue;
            }

            yield new VariantPrice(
                variantExternalIdentifier: $variantId,
                priceLevelExternalIdentifier: $level,
                price: new Money(HeliosRow::toMinorUnit($amount), $currencyCode),
                modifiedAt: HeliosRow::dateTime($row, 'poslednizmena'),
            );
        }
    }

    /** @param array<string, mixed> $row */
    private function amountIn(array $row, string $currencyCode): ?float
    {
        if (strcasecmp($currencyCode, $this->heliosMainCurrency) === 0) {
            return HeliosRow::float($row, 'cenakc');
        }

        for ($i = 1; $i <= 5; ++$i) {
            if (strcasecmp(HeliosRow::string($row, 'mena' . $i) ?? '', $currencyCode) === 0) {
                return HeliosRow::float($row, 'cenaval' . $i);
            }
        }

        return null;
    }

    /**
     * @param list<string> $priceLevelExternalIdentifiers
     *
     * @return list<int>
     */
    private static function levelIds(array $priceLevelExternalIdentifiers): array
    {
        return array_values(array_filter(array_map('intval', $priceLevelExternalIdentifiers), static fn (int $id): bool => $id > 0));
    }
}
