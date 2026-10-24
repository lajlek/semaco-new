<?php

declare(strict_types=1);

namespace App\Checkout\Price;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class VariantPriceExtension extends AbstractExtension
{
    public function __construct(
        private readonly VariantPriceChecker $priceChecker,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('semaco_has_price', $this->priceChecker->hasPrice(...)),
        ];
    }
}
