<?php

declare(strict_types=1);

namespace App\Shop\Service;

use Sylius\Component\Core\Model\ProductVariantInterface;

interface VariantOrderCodeResolverInterface
{
    public function resolve(ProductVariantInterface $variant): string;
}
