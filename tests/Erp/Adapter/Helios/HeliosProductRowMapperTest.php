<?php

declare(strict_types=1);

namespace App\Tests\Erp\Adapter\Helios;

use App\Erp\Adapter\Helios\Product\HeliosProductRowMapper;
use PHPUnit\Framework\TestCase;

final class HeliosProductRowMapperTest extends TestCase
{
    public function testMapsMasterAsProductAndItsOwnFirstVariant(): void
    {
        $master = $this->row(['id' => '62882', 'regcis' => '4 40 331', 'skupzbo' => '180', 'titulek' => 'PLANWORX® D40 - 250 MM', 'nazev1' => 'Fréza Planworx', '_Semaco_VariantaNazev' => 'Průměr 40']);
        $child = $this->row(['id' => '62883', 'idpredloha' => '62882', 'regcis' => '5 50 331', 'skupzbo' => '180', '_Semaco_VariantaNazev' => 'Průměr 50']);

        $snapshot = (new HeliosProductRowMapper())->mapProduct($master, [$master, $child], [], [], []);

        self::assertSame('62882', $snapshot->externalIdentifier);
        self::assertSame('180_4_40_331', $snapshot->code);
        self::assertSame('PLANWORX® D40 - 250 MM', $snapshot->name);
        self::assertCount(2, $snapshot->variants);
        self::assertSame('62882', $snapshot->variants[0]->externalIdentifier);
        self::assertSame('Průměr 40', $snapshot->variants[0]->name);
        self::assertSame('180_5_50_331', $snapshot->variants[1]->code);
        self::assertSame('5 50 331', $snapshot->variants[1]->regcis);
    }

    public function testNameFallsBackToNazev1AndDescriptionsPreferHtml(): void
    {
        $master = $this->row(['nazev1' => 'Fréza HM', 'html2' => '<p>HTML</p>', 'internetpopis2' => 'text', 'internetpopis1' => 'krátký']);

        $snapshot = (new HeliosProductRowMapper())->mapProduct($master, [$master], [], [], []);

        self::assertSame('Fréza HM', $snapshot->name);
        self::assertSame('<p>HTML</p>', $snapshot->description);
        self::assertSame('krátký', $snapshot->shortDescription);
    }

    public function testHiddenOrBlockedItemsAreDisabled(): void
    {
        $mapper = new HeliosProductRowMapper();

        self::assertFalse($mapper->mapProduct($this->row(['hidden' => '1']), [], [], [], [])->enabled);
        self::assertFalse($mapper->mapProduct($this->row(['blokovano' => '1']), [], [], [], [])->enabled);
        self::assertTrue($mapper->mapProduct($this->row([]), [], [], [], [])->enabled);
    }

    public function testQuantityStepAndMinimumDefaultToOne(): void
    {
        $mapper = new HeliosProductRowMapper();

        $withValues = $mapper->mapProduct($this->row(['baleniv' => '10.000000', 'minimum_odberatel' => '5.000000']), [], [], [], []);
        $withoutValues = $mapper->mapProduct($this->row(['baleniv' => '.000000']), [], [], [], []);

        self::assertSame(10, $withValues->quantityStep);
        self::assertSame(5, $withValues->minimumQuantity);
        self::assertSame(1, $withoutValues->quantityStep);
        self::assertSame(1, $withoutValues->minimumQuantity);
    }

    public function testSelectAttributesUseDictionaryKeyAndIntegerAttributesKeepNumber(): void
    {
        $master = $this->row(['id' => '1']);
        $properties = [1 => [
            ['group' => '5', 'dictionary' => '123', 'value' => 'Nástrčné'],
            ['group' => '5', 'dictionary' => null, 'value' => 'Válcová'],
            ['group' => '7', 'dictionary' => null, 'value' => '4'],
        ]];

        $snapshot = (new HeliosProductRowMapper())->mapProduct($master, [$master], $properties, ['5' => 'C', '7' => 'N'], []);

        self::assertSame(['5' => ['123', md5('Válcová')], '7' => 4], $snapshot->attributes);
        self::assertSame(['5' => ['123', md5('Válcová')], '7' => [md5('4')]], $snapshot->variants[0]->optionValues);
    }

    public function testBrandAndMainTaxon(): void
    {
        $master = $this->row(['znacka' => '2', 'znackanaz' => 'POKOLM']);

        $snapshot = (new HeliosProductRowMapper())->mapProduct($master, [], [], [], [
            ['taxon' => '14', 'position' => 3, 'main' => true],
        ]);

        self::assertSame('2', $snapshot->brand?->externalIdentifier);
        self::assertSame('POKOLM', $snapshot->brand->name);
        self::assertTrue($snapshot->taxonLinks[0]->main);
        self::assertSame(3, $snapshot->taxonLinks[0]->position);
    }

    /**
     * @param array<string, string|null> $values
     *
     * @return array<string, string|null>
     */
    private function row(array $values): array
    {
        return $values + [
            'id' => '10',
            'idpredloha' => null,
            'regcis' => 'R1',
            'skupzbo' => '100',
            'titulek' => null,
            'nazev1' => null,
            '_Semaco_VariantaNazev' => null,
            'hidden' => '0',
            'blokovano' => '0',
            'druhskladu' => '1',
            'poslednizmena' => '2026-01-01 10:00:00.000',
        ];
    }
}
