<?php

declare(strict_types=1);

namespace App\Wishlist\Entity;

use App\Entity\Product\ProductVariant;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'app_wishlist_item')]
#[ORM\UniqueConstraint(name: 'UNIQ_wishlist_item_variant', columns: ['wishlist_id', 'variant_id'])]
class WishlistItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'added_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $addedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Wishlist::class, inversedBy: 'items')]
        #[ORM\JoinColumn(name: 'wishlist_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Wishlist $wishlist,
        #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
        #[ORM\JoinColumn(name: 'variant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private ProductVariant $variant,
    ) {
        $this->addedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWishlist(): Wishlist
    {
        return $this->wishlist;
    }

    public function getVariant(): ProductVariant
    {
        return $this->variant;
    }

    public function getAddedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }
}
