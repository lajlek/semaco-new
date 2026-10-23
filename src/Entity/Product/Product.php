<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Product as BaseProduct;
use Sylius\Component\Product\Model\ProductTranslationInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product')]
#[ORM\Index(name: 'IDX_product_external_id', columns: ['external_identifier'])]
class Product extends BaseProduct
{
    #[ORM\Column(name: 'external_identifier', type: 'string', length: 128, nullable: true)]
    private ?string $externalIdentifier = null;

    #[ORM\Column(name: 'skupzbo', type: 'string', length: 64, nullable: true)]
    private ?string $skupzbo = null;

    #[ORM\Column(name: 'minimum_quantity', type: 'integer', options: ['default' => 1])]
    private int $minimumQuantity = 1;

    #[ORM\Column(name: 'quantity_step', type: 'integer', options: ['default' => 1])]
    private int $quantityStep = 1;

    #[ORM\Column(name: 'sync_with_erp', type: 'boolean', options: ['default' => true])]
    private bool $syncWithErp = true;

    #[ORM\ManyToOne(targetEntity: Brand::class)]
    #[ORM\JoinColumn(name: 'brand_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Brand $brand = null;

    /** @var Collection<int, ProductDocument> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductDocument::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $documents;

    public function __construct()
    {
        parent::__construct();
        $this->documents = new ArrayCollection();
    }

    public function getExternalIdentifier(): ?string
    {
        return $this->externalIdentifier;
    }

    public function setExternalIdentifier(?string $externalIdentifier): void
    {
        $this->externalIdentifier = $externalIdentifier;
    }

    public function getSkupzbo(): ?string
    {
        return $this->skupzbo;
    }

    public function setSkupzbo(?string $skupzbo): void
    {
        $this->skupzbo = $skupzbo;
    }

    public function getMinimumQuantity(): int
    {
        return $this->minimumQuantity;
    }

    public function setMinimumQuantity(int $minimumQuantity): void
    {
        $this->minimumQuantity = max(1, $minimumQuantity);
    }

    public function getQuantityStep(): int
    {
        return $this->quantityStep;
    }

    public function setQuantityStep(int $quantityStep): void
    {
        $this->quantityStep = max(1, $quantityStep);
    }

    public function isSyncWithErp(): bool
    {
        return $this->syncWithErp;
    }

    public function setSyncWithErp(bool $syncWithErp): void
    {
        $this->syncWithErp = $syncWithErp;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): void
    {
        $this->brand = $brand;
    }

    /** @return Collection<int, ProductDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(ProductDocument $document): void
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
        }
    }

    public function removeDocument(ProductDocument $document): void
    {
        $this->documents->removeElement($document);
    }

    protected function createTranslation(): ProductTranslationInterface
    {
        return new ProductTranslation();
    }
}
