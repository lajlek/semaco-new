<?php

declare(strict_types=1);

namespace App\Admin\Product;

use App\Entity\Product\Product;
use Sylius\Component\Product\Model\ProductTranslationInterface;

final class ProductContentFingerprint
{
    /** @return array<string, string> */
    public static function of(Product $product): array
    {
        $translations = [];
        foreach ($product->getTranslations() as $translation) {
            if ($translation instanceof ProductTranslationInterface) {
                $translations[(string) $translation->getLocale()] = $translation;
            }
        }
        ksort($translations);

        $text = static fn (\Closure $read): string => (string) json_encode(array_filter(array_map(
            static fn (ProductTranslationInterface $translation): string => self::normalize($read($translation)),
            $translations,
        ), static fn (string $value): bool => $value !== ''));

        $taxons = [];
        foreach ($product->getProductTaxons() as $productTaxon) {
            $taxons[] = (string) $productTaxon->getTaxon()?->getCode();
        }
        sort($taxons);

        $attributes = [];
        foreach ($product->getAttributes() as $attributeValue) {
            if ($attributeValue->getValue() === null) {
                continue;
            }
            $attributes[] = sprintf(
                '%s|%s|%s',
                (string) $attributeValue->getAttribute()?->getCode(),
                (string) $attributeValue->getLocaleCode(),
                json_encode($attributeValue->getValue()),
            );
        }
        sort($attributes);

        return [
            Product::ERP_FIELD_NAME => $text(static fn (ProductTranslationInterface $translation): ?string => $translation->getName()),
            Product::ERP_FIELD_DESCRIPTION => $text(static fn (ProductTranslationInterface $translation): ?string => $translation->getDescription()),
            Product::ERP_FIELD_SHORT_DESCRIPTION => $text(static fn (ProductTranslationInterface $translation): ?string => $translation->getShortDescription()),
            Product::ERP_FIELD_META => $text(static fn (ProductTranslationInterface $translation): string => self::normalize($translation->getMetaKeywords()) . "\0" . self::normalize($translation->getMetaDescription())),
            Product::ERP_FIELD_TAXONS => implode(',', $taxons) . '|' . (string) $product->getMainTaxon()?->getCode(),
            Product::ERP_FIELD_ATTRIBUTES => implode("\n", $attributes),
        ];
    }

    /**
     * @param array<string, string> $before
     * @param array<string, string> $after
     *
     * @return list<string>
     */
    public static function changedFields(array $before, array $after): array
    {
        return array_values(array_keys(array_filter(
            $after,
            static fn (string $value, string $field): bool => ($before[$field] ?? null) !== $value,
            \ARRAY_FILTER_USE_BOTH,
        )));
    }

    private static function normalize(?string $value): string
    {
        return trim(str_replace("\r\n", "\n", (string) $value), " \n\r\t\v\0");
    }
}
