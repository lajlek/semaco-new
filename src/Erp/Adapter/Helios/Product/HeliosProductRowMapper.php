<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Product;

use App\Erp\Adapter\Helios\Attribute\HeliosAttributeValueKey;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Port\Catalog\Dto\BrandSnapshot;
use App\Erp\Port\Catalog\Dto\ProductSnapshot;
use App\Erp\Port\Catalog\Dto\ProductTaxonLink;
use App\Erp\Port\Catalog\Dto\VariantSnapshot;

final class HeliosProductRowMapper
{
    /**
     * @param array<string, mixed> $master
     * @param list<array<string, mixed>> $variantRows
     * @param array<int, list<array{group: string, dictionary: mixed, value: string}>> $properties
     * @param array<array-key, string> $groupTypes
     * @param list<array{taxon: string, position: int, main: bool}> $taxonLinks
     */
    public function mapProduct(array $master, array $variantRows, array $properties, array $groupTypes, array $taxonLinks): ProductSnapshot
    {
        $id = (HeliosRow::string($master, 'id') ?? '');
        $brandId = HeliosRow::int($master, 'znacka');
        $brandName = HeliosRow::string($master, 'znackanaz');

        $modifiedAt = HeliosRow::dateTime($master, 'incremental_modified_at', 'poslednizmena', 'DatumPosledniZmenaEshop');
        $variants = [];
        foreach ($variantRows as $row) {
            $variant = $this->mapVariant($row, $properties[(HeliosRow::int($row, 'id') ?? 0)] ?? []);
            $variants[] = $variant;
            if ($variant->modifiedAt > $modifiedAt) {
                $modifiedAt = $variant->modifiedAt;
            }
        }

        return new ProductSnapshot(
            externalIdentifier: $id,
            code: self::code($master),
            regcis: HeliosRow::string($master, 'regcis'),
            skupzbo: HeliosRow::string($master, 'skupzbo'),
            name: HeliosRow::string($master, 'titulek') ?? HeliosRow::string($master, 'nazev1') ?? self::code($master),
            description: HeliosRow::string($master, 'html2') ?? HeliosRow::string($master, 'internetpopis2'),
            shortDescription: HeliosRow::string($master, 'html1') ?? HeliosRow::string($master, 'internetpopis1'),
            metaKeywords: HeliosRow::string($master, '_SapertaSeoKeyWords'),
            metaDescription: HeliosRow::string($master, '_SapertaSeoPopisek'),
            enabled: self::isEnabled($master),
            brand: $brandId !== null && $brandId > 0 && $brandName !== null ? new BrandSnapshot((string) $brandId, $brandName) : null,
            quantityStep: self::positiveInt($master, 'baleniv'),
            minimumQuantity: self::positiveInt($master, 'minimum_odberatel'),
            taxonLinks: array_map(
                static fn (array $link): ProductTaxonLink => new ProductTaxonLink($link['taxon'], $link['position'], $link['main']),
                $taxonLinks,
            ),
            attributes: $this->mapAttributes($properties[(HeliosRow::int($master, 'id') ?? 0)] ?? [], $groupTypes),
            variants: $variants,
            modifiedAt: $modifiedAt,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array{group: string, dictionary: mixed, value: string}> $properties
     */
    private function mapVariant(array $row, array $properties): VariantSnapshot
    {
        $optionValues = [];
        foreach ($properties as $property) {
            $key = HeliosAttributeValueKey::of($property['dictionary'], $property['value']);
            if (!in_array($key, $optionValues[$property['group']] ?? [], true)) {
                $optionValues[$property['group']][] = $key;
            }
        }

        return new VariantSnapshot(
            externalIdentifier: (HeliosRow::string($row, 'id') ?? ''),
            code: self::code($row),
            regcis: HeliosRow::string($row, 'regcis'),
            name: HeliosRow::string($row, '_Semaco_VariantaNazev') ?? '',
            enabled: self::isEnabled($row),
            optionValues: $optionValues,
            weight: HeliosRow::positiveFloat($row, 'hmotnost'),
            height: HeliosRow::positiveFloat($row, 'vyska'),
            depth: HeliosRow::positiveFloat($row, 'hloubka'),
            barcode: HeliosRow::string($row, 'BarCode'),
            quantityStep: self::positiveInt($row, 'baleniv'),
            minimumQuantity: self::positiveInt($row, 'minimum_odberatel'),
            modifiedAt: HeliosRow::dateTime($row, 'poslednizmena', 'DatumPosledniZmenaEshop'),
        );
    }

    /**
     * @param list<array{group: string, dictionary: mixed, value: string}> $properties
     * @param array<array-key, string> $groupTypes
     *
     * @return array<array-key, list<string>|int>
     */
    private function mapAttributes(array $properties, array $groupTypes): array
    {
        $attributes = [];
        foreach ($properties as $property) {
            $group = $property['group'];
            if (($groupTypes[$group] ?? 'C') === 'N') {
                if (is_numeric(str_replace(',', '.', $property['value']))) {
                    $attributes[$group] = (int) str_replace(',', '.', $property['value']);
                }

                continue;
            }

            $key = HeliosAttributeValueKey::of($property['dictionary'], $property['value']);
            $current = $attributes[$group] ?? [];
            if (is_array($current) && !in_array($key, $current, true)) {
                $current[] = $key;
                $attributes[$group] = $current;
            }
        }

        return $attributes;
    }

    /** @param array<string, mixed> $row */
    private static function code(array $row): string
    {
        return str_replace(' ', '_', (HeliosRow::string($row, 'skupzbo') ?? '') . '_' . (HeliosRow::string($row, 'regcis') ?? (HeliosRow::string($row, 'id') ?? '')));
    }

    /** @param array<string, mixed> $row */
    private static function isEnabled(array $row): bool
    {
        return !HeliosRow::bool($row, 'hidden') && !HeliosRow::bool($row, 'blokovano');
    }

    /** @param array<string, mixed> $row */
    private static function positiveInt(array $row, string $column): int
    {
        $value = HeliosRow::positiveFloat($row, $column);

        return $value === null ? 1 : max(1, (int) round($value));
    }
}
