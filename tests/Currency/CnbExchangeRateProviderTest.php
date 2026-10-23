<?php

declare(strict_types=1);

namespace App\Tests\Currency;

use App\Currency\Adapter\CnbExchangeRateProvider;
use PHPUnit\Framework\TestCase;

final class CnbExchangeRateProviderTest extends TestCase
{
    public function testParsesDailyRatesPerOneUnit(): void
    {
        $content = "23.10.2026 #205\nzemě|měna|množství|kód|kurz\nEMU|euro|1|EUR|24,335\nJaponsko|jen|100|JPY|14,512\nUSA|dolar|1|USD|21,741\n";

        $rates = CnbExchangeRateProvider::parse($content);

        self::assertEqualsWithDelta(24.335, $rates['EUR'], 0.0001);
        self::assertEqualsWithDelta(0.14512, $rates['JPY'], 0.000001);
        self::assertArrayNotHasKey('kód', $rates);
        self::assertCount(3, $rates);
    }
}
