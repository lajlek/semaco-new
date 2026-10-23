<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Attribute\Model\AttributeTranslationInterface;
use Sylius\Component\Product\Model\ProductAttribute as BaseProductAttribute;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_attribute')]
class ProductAttribute extends BaseProductAttribute
{
    #[ORM\Column(name: 'filter_type', type: 'integer', nullable: true)]
    private ?int $filterType = null;

    public function getFilterType(): ?int
    {
        return $this->filterType;
    }

    public function setFilterType(?int $filterType): void
    {
        $this->filterType = $filterType;
    }

    protected function createTranslation(): AttributeTranslationInterface
    {
        return new ProductAttributeTranslation();
    }
}
