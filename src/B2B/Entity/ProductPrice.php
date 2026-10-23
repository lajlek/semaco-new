<?php

declare(strict_types=1);

namespace App\B2B\Entity;

use App\Entity\Product\ProductVariant;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_product_price')]
#[ORM\UniqueConstraint(
    name: 'UNIQ_product_price_variant_level_currency',
    columns: ['product_variant_id', 'price_level_id', 'currency_code'],
)]
#[ORM\Index(name: 'IDX_product_price_variant', columns: ['product_variant_id'])]
#[ORM\Index(name: 'IDX_product_price_level', columns: ['price_level_id'])]
class ProductPrice implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(name: 'product_variant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ProductVariant $variant;

    #[ORM\ManyToOne(targetEntity: PriceLevel::class)]
    #[ORM\JoinColumn(name: 'price_level_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PriceLevel $priceLevel;

    #[ORM\Column(name: 'currency_code', type: 'string', length: 3)]
    private string $currencyCode;

    #[ORM\Column(type: 'integer')]
    private int $amount;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        ProductVariant $variant,
        PriceLevel $priceLevel,
        string $currencyCode,
        int $amount,
    ) {
        $this->variant = $variant;
        $this->priceLevel = $priceLevel;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVariant(): ProductVariant
    {
        return $this->variant;
    }

    public function getPriceLevel(): PriceLevel
    {
        return $this->priceLevel;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function updateAmount(int $amount): void
    {
        $this->amount = $amount;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
