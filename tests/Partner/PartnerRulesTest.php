<?php

declare(strict_types=1);

namespace App\Tests\Partner;

use App\Partner\Parser\HtmlDocument;
use App\Partner\Rules\PartnerRule;
use App\Partner\Rules\PartnerRules;
use App\Partner\Service\PartnerExporter;
use App\Partner\Service\PartnerItemPreparer;
use PHPUnit\Framework\TestCase;

final class PartnerRulesTest extends TestCase
{
    public function testFamilyKeys(): void
    {
        $rules = new PartnerRules();

        self::assertSame('P5337', $rules->get('fraisa')->familyKey('P5337180', null));
        self::assertSame('15232', $rules->get('fraisa')->familyKey('15232120', null));
        self::assertSame('331', $rules->get('pokolm')->familyKey('4 40 331', null));
        self::assertSame('222401', $rules->get('brw')->familyKey('222401.0100', '222401'));
    }

    public function testBrwSpecProvidesLengthL2(): void
    {
        $rule = (new PartnerRules())->get('brw');

        self::assertSame('0.3', $rule->sourceValues(['spec' => 'Ø0.3 x r0.15 x 0.3 x 1.5'])['l2']);
        self::assertSame('0.3', $rule->sourceValues(['spec' => 'Ø0.1 x r0.05 x 0.3'])['l2']);
        self::assertArrayNotHasKey('l2', $rule->sourceValues(['spec' => 'jiný formát']));
    }

    public function testOnlyPartnerHostsOverHttpsAreAllowed(): void
    {
        $rules = new PartnerRules();

        self::assertTrue($rules->get('brw')->isAllowedUrl('https://www.brw.ch/de-CH/futuro-222401'));
        self::assertFalse($rules->get('brw')->isAllowedUrl('http://www.brw.ch/de-CH/futuro-222401'));
        self::assertFalse($rules->get('brw')->isAllowedUrl('https://www.brw.ch.example.com/'));
        self::assertFalse($rules->get('fraisa')->isAllowedUrl('https://www.brw.ch/'));
        self::assertFalse($rules->get('pokolm')->isAllowedUrl('https://www.pokolm.com/portal/'));
        self::assertFalse($rules->get('brw')->isAllowedUrl('https://www.brw.ch:8443/de-CH/x'));
        self::assertTrue($rules->get('brw')->isAllowedUrl('https://www.brw.ch:443/de-CH/x'));
        self::assertFalse($rules->get('brw')->isAllowedUrl('https://user@www.brw.ch/de-CH/x'));
        self::assertSame('https://cdn.example.com/x', HtmlDocument::absoluteUrl('//cdn.example.com/x', 'https://www.brw.ch/de-CH/a'));
    }

    public function testComparableUrlIgnoresCosmeticDifferences(): void
    {
        $base = PartnerRule::comparableUrl('https://webshop.fraisa.com/CH/en/mills?view=list&sort=a');

        self::assertSame($base, PartnerRule::comparableUrl('https://WEBSHOP.fraisa.com/CH/en/mills/?sort=a&view=list&page=1#top'));
        self::assertNotSame($base, PartnerRule::comparableUrl('https://webshop.fraisa.com/CH/en/mills?view=list&sort=a&page=2'));
    }

    public function testCsvCellsCannotStartFormulas(): void
    {
        self::assertSame("'=HYPERLINK(1)", PartnerExporter::cell('=HYPERLINK(1)'));
        self::assertSame("'-2+3+cmd|' /C calc'!A0", PartnerExporter::cell("-2+3+cmd|' /C calc'!A0"));
        self::assertSame("'@SUM(1)", PartnerExporter::cell('@SUM(1)'));
        self::assertSame('+/-0,005', PartnerExporter::cell('+/-0,005'));
        self::assertSame('-12,5', PartnerExporter::cell('-12,5'));
        self::assertSame('P5337180', PartnerExporter::cell('P5337180'));
    }

    public function testNumbersUseDecimalComma(): void
    {
        self::assertSame('6,7', PartnerItemPreparer::number('6.7'));
        self::assertSame('40', PartnerItemPreparer::number('40'));
        self::assertSame('1,5', PartnerItemPreparer::number('1.50'));
        self::assertSame('0,25', PartnerItemPreparer::number('0,25'));
        self::assertNull(PartnerItemPreparer::number('—'));
        self::assertNull(PartnerItemPreparer::number(' '));
        self::assertSame('DIN 6535', PartnerItemPreparer::number('DIN 6535'));
        self::assertSame('+/-0,005', PartnerItemPreparer::number('+/- 0.005'));
    }
}
