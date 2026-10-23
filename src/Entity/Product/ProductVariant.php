<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;
use Sylius\Component\Product\Model\ProductVariantTranslationInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_variant')]
#[ORM\Index(name: 'IDX_variant_regcis', columns: ['regcis'])]
#[ORM\Index(name: 'IDX_variant_external_id', columns: ['external_identifier'])]
#[ORM\Index(name: 'IDX_variant_regcis_compact', columns: ['regcis_compact'])]
class ProductVariant extends BaseProductVariant
{
    #[ORM\Column(name: 'external_identifier', type: 'string', length: 128, nullable: true)]
    private ?string $externalIdentifier = null;

    #[ORM\Column(name: 'regcis', type: 'string', length: 64, nullable: true)]
    private ?string $regcis = null;

    #[ORM\Column(name: 'regcis_compact', type: 'string', length: 64, nullable: true)]
    private ?string $regcisCompact = null;

    #[ORM\Column(name: 'barcode', type: 'string', length: 64, nullable: true)]
    private ?string $barcode = null;

    public function getExternalIdentifier(): ?string
    {
        return $this->externalIdentifier;
    }

    public function setExternalIdentifier(?string $externalIdentifier): void
    {
        $this->externalIdentifier = $externalIdentifier;
    }

    public function getRegcis(): ?string
    {
        return $this->regcis;
    }

    public function setRegcis(?string $regcis): void
    {
        $this->regcis = $regcis;
        $this->regcisCompact = $regcis === null ? null : (string) preg_replace('/\s+/', '', $regcis);
    }

    public function getRegcisCompact(): ?string
    {
        return $this->regcisCompact;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): void
    {
        $this->barcode = $barcode;
    }

    protected function createTranslation(): ProductVariantTranslationInterface
    {
        return new ProductVariantTranslation();
    }
}
