<?php

declare(strict_types=1);

namespace App\Wishlist\Entity;

use App\Entity\Product\ProductVariant;
use App\Entity\User\ShopUser;
use App\Wishlist\Repository\WishlistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WishlistRepository::class)]
#[ORM\Table(name: 'app_wishlist')]
class Wishlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $token;

    #[ORM\OneToOne(targetEntity: ShopUser::class)]
    #[ORM\JoinColumn(name: 'shop_user_id', referencedColumnName: 'id', unique: true, nullable: true, onDelete: 'CASCADE')]
    private ?ShopUser $shopUser = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, WishlistItem> */
    #[ORM\OneToMany(targetEntity: WishlistItem::class, mappedBy: 'wishlist', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['addedAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->token = bin2hex(random_bytes(20));
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getShopUser(): ?ShopUser
    {
        return $this->shopUser;
    }

    public function setShopUser(?ShopUser $shopUser): void
    {
        $this->shopUser = $shopUser;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, WishlistItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function count(): int
    {
        return $this->items->count();
    }

    public function hasVariant(ProductVariant $variant): bool
    {
        return $this->findItem($variant) !== null;
    }

    public function findItem(ProductVariant $variant): ?WishlistItem
    {
        foreach ($this->items as $item) {
            if ($item->getVariant() === $variant || ($variant->getId() !== null && $item->getVariant()->getId() === $variant->getId())) {
                return $item;
            }
        }

        return null;
    }

    public function addVariant(ProductVariant $variant): bool
    {
        if ($this->hasVariant($variant)) {
            return false;
        }

        $this->items->add(new WishlistItem($this, $variant));

        return true;
    }

    public function removeVariant(ProductVariant $variant): bool
    {
        $item = $this->findItem($variant);
        if ($item === null) {
            return false;
        }

        $this->items->removeElement($item);

        return true;
    }
}
