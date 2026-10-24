<?php

declare(strict_types=1);

namespace App\Tests\Admin\Product;

use App\Admin\Product\ProductContentFingerprint;
use App\Entity\Product\Product;
use App\Entity\Product\ProductTranslation;
use PHPUnit\Framework\TestCase;

final class ProductContentFingerprintTest extends TestCase
{
    public function testUnchangedProductHasNoChangedFields(): void
    {
        $product = $this->product();
        $before = ProductContentFingerprint::of($product);
        $product->setDescription("Řádek 1\r\nŘádek 2  ");

        self::assertSame([], ProductContentFingerprint::changedFields($before, ProductContentFingerprint::of($product)));
    }

    public function testChangedTextFieldsAreReported(): void
    {
        $product = $this->product();
        $before = ProductContentFingerprint::of($product);
        $product->setName('Nový název');
        $product->setMetaDescription('SEO');

        self::assertSame(
            [Product::ERP_FIELD_NAME, Product::ERP_FIELD_META],
            ProductContentFingerprint::changedFields($before, ProductContentFingerprint::of($product)),
        );
    }

    public function testEmptyTranslationInAnotherLocaleIsNotAChange(): void
    {
        $product = $this->product();
        $before = ProductContentFingerprint::of($product);
        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setName('');
        $product->addTranslation($translation);

        self::assertSame([], ProductContentFingerprint::changedFields($before, ProductContentFingerprint::of($product)));
    }

    public function testLockedFieldsKeepKnownFieldsInOrder(): void
    {
        $product = new Product();
        $product->setErpLockedFields(['meta', 'unknown', 'name', 'meta']);

        self::assertSame([Product::ERP_FIELD_NAME, Product::ERP_FIELD_META], $product->getErpLockedFields());
        self::assertTrue($product->isErpFieldLocked(Product::ERP_FIELD_META));
        self::assertFalse($product->isErpFieldLocked(Product::ERP_FIELD_TAXONS));

        $product->setErpLockedFields([]);
        self::assertSame([], $product->getErpLockedFields());
    }

    private function product(): Product
    {
        $product = new Product();
        $product->setCurrentLocale('cs_CZ');
        $product->setFallbackLocale('cs_CZ');
        $product->setName('Fréza');
        $product->setDescription("Řádek 1\nŘádek 2");

        return $product;
    }
}
