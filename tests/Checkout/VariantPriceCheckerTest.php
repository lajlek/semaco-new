<?php

declare(strict_types=1);

namespace App\Tests\Checkout;

use App\Checkout\Price\VariantPriceChecker;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Exception\MissingChannelConfigurationException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class VariantPriceCheckerTest extends TestCase
{
    public function testOnlyPositivePriceIsOrderable(): void
    {
        $variant = $this->createStub(ProductVariantInterface::class);

        self::assertTrue($this->checker(fn (): int => 1)->hasPrice($variant));
        self::assertFalse($this->checker(fn (): int => 0)->hasPrice($variant));
        self::assertFalse($this->checker(fn (): int => throw new MissingChannelConfigurationException('none'))->hasPrice($variant));
        self::assertFalse($this->checker(fn (): int => 1)->hasPrice(null));
    }

    /** @param \Closure(): int $price */
    private function checker(\Closure $price): VariantPriceChecker
    {
        $calculator = $this->createStub(ProductVariantPricesCalculatorInterface::class);
        $calculator->method('calculate')->willReturnCallback($price);
        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($this->createStub(ChannelInterface::class));

        return new VariantPriceChecker($calculator, $channelContext);
    }
}
