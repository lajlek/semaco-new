<?php

declare(strict_types=1);

namespace App\Shop\Service;

use App\Entity\Product\ProductVariant;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class VariantCodeOrderCodeResolver implements VariantOrderCodeResolverInterface
{
    public function resolve(ProductVariantInterface $variant): string
    {
        if ($variant instanceof ProductVariant && null !== $variant->getRegcis() && '' !== $variant->getRegcis()) {
            return $variant->getRegcis();
        }

        return (string) $variant->getCode();
    }
}
