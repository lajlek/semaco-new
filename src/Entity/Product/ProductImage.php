<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductImage as BaseProductImage;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_image')]
#[ORM\Index(name: 'IDX_product_image_external_id', columns: ['external_identifier'])]
class ProductImage extends BaseProductImage
{
    #[ORM\Column(name: 'external_identifier', type: 'string', length: 255, nullable: true)]
    private ?string $externalIdentifier = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    public function getExternalIdentifier(): ?string
    {
        return $this->externalIdentifier;
    }

    public function setExternalIdentifier(?string $externalIdentifier): void
    {
        $this->externalIdentifier = $externalIdentifier;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}
