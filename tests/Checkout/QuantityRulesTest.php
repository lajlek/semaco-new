<?php

declare(strict_types=1);

namespace App\Tests\Checkout;

use App\Checkout\Quantity\QuantityRules;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use PHPUnit\Framework\TestCase;

final class QuantityRulesTest extends TestCase
{
    public function testStepAndMinimum(): void
    {
        $rules = QuantityRules::forVariant($this->variant(step: 10, minimum: 10));

        self::assertTrue($rules->isRestricted());
        self::assertFalse($rules->allows(5));
        self::assertFalse($rules->allows(15));
        self::assertTrue($rules->allows(10));
        self::assertTrue($rules->allows(30));
        self::assertSame(20, $rules->normalize(11));
    }

    public function testMinimumIsAtLeastOneStep(): void
    {
        $rules = QuantityRules::forVariant($this->variant(step: 5, minimum: 1));

        self::assertSame(5, $rules->minimum);
        self::assertFalse($rules->allows(1));
    }

    public function testUnrestrictedVariant(): void
    {
        $rules = QuantityRules::forVariant(null);

        self::assertFalse($rules->isRestricted());
        self::assertTrue($rules->allows(1));
        self::assertSame(0, $rules->normalize(0));
    }

    private function variant(int $step, int $minimum): ProductVariant
    {
        $product = new Product();
        $product->setQuantityStep($step);
        $product->setMinimumQuantity($minimum);
        $variant = new ProductVariant();
        $variant->setProduct($product);

        return $variant;
    }
}
