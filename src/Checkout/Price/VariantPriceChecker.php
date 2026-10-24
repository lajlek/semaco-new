<?php

declare(strict_types=1);

namespace App\Checkout\Price;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Exception\MissingChannelConfigurationException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class VariantPriceChecker
{
    public function __construct(
        #[Autowire(service: 'sylius.calculator.product_variant_price')]
        private ProductVariantPricesCalculatorInterface $calculator,
        private ChannelContextInterface $channelContext,
    ) {
    }

    public function hasPrice(?ProductVariantInterface $variant, ?ChannelInterface $channel = null): bool
    {
        if ($variant === null) {
            return false;
        }

        $channel ??= $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            return false;
        }

        try {
            return $this->calculator->calculate($variant, ['channel' => $channel]) > 0;
        } catch (MissingChannelConfigurationException) {
            return false;
        }
    }
}
