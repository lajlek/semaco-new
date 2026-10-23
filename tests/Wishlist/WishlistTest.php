<?php

declare(strict_types=1);

namespace App\Tests\Wishlist;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Wishlist\Entity\Wishlist;
use App\Wishlist\WishlistCartFiller;
use PHPUnit\Framework\TestCase;

final class WishlistTest extends TestCase
{
    public function testAddsVariantOnlyOnce(): void
    {
        $wishlist = new Wishlist();
        $variant = new ProductVariant();

        self::assertTrue($wishlist->addVariant($variant));
        self::assertFalse($wishlist->addVariant($variant));
        self::assertSame(1, $wishlist->count());
        self::assertTrue($wishlist->hasVariant($variant));

        self::assertTrue($wishlist->removeVariant($variant));
        self::assertFalse($wishlist->removeVariant($variant));
        self::assertSame(0, $wishlist->count());
    }

    public function testGuestTokensAreUnique(): void
    {
        self::assertNotSame((new Wishlist())->getToken(), (new Wishlist())->getToken());
    }

    public function testQuantityRespectsMinimumAndStep(): void
    {
        $product = new Product();
        $product->setQuantityStep(5);
        $product->setMinimumQuantity(10);
        $variant = new ProductVariant();
        $variant->setProduct($product);

        self::assertSame(0, WishlistCartFiller::normalizeQuantity($variant, 0));
        self::assertSame(10, WishlistCartFiller::normalizeQuantity($variant, 1));
        self::assertSame(15, WishlistCartFiller::normalizeQuantity($variant, 11));
        self::assertSame(20, WishlistCartFiller::normalizeQuantity($variant, 20));
    }

    public function testQuantityWithoutRules(): void
    {
        $variant = new ProductVariant();
        $variant->setProduct(new Product());

        self::assertSame(3, WishlistCartFiller::normalizeQuantity($variant, 3));
    }
}
