<?php

declare(strict_types=1);

namespace App\Shop\Twig;

use App\Shop\Service\OrientationalPriceConverter;
use App\Shop\Service\VariantOrderCodeResolverInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ShopExtension extends AbstractExtension
{
    public function __construct(
        private readonly OrientationalPriceConverter $orientationalPriceConverter,
        private readonly VariantOrderCodeResolverInterface $variantOrderCodeResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('semaco_orientational_price', $this->orientationalPriceConverter->convert(...)),
            new TwigFunction('semaco_order_code', $this->orderCode(...)),
        ];
    }

    private function orderCode(?ProductVariantInterface $variant): string
    {
        return null === $variant ? '' : $this->variantOrderCodeResolver->resolve($variant);
    }
}
