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
    public const ERP_FIELD_NAME = 'name';

    public const ERP_FIELD_DESCRIPTION = 'description';

    public const ERP_FIELD_SHORT_DESCRIPTION = 'short_description';

    public const ERP_FIELD_META = 'meta';

    public const ERP_FIELD_TAXONS = 'taxons';

    public const ERP_FIELD_ATTRIBUTES = 'attributes';

    public const ERP_FIELDS = [
        self::ERP_FIELD_NAME,
        self::ERP_FIELD_DESCRIPTION,
        self::ERP_FIELD_SHORT_DESCRIPTION,
        self::ERP_FIELD_META,
        self::ERP_FIELD_TAXONS,
        self::ERP_FIELD_ATTRIBUTES,
    ];

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

    /** @var list<string>|null */
    #[ORM\Column(name: 'erp_locked_fields', type: 'json', nullable: true)]
    private ?array $erpLockedFields = null;

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

    /** @return list<string> */
    public function getErpLockedFields(): array
    {
        return $this->erpLockedFields ?? [];
    }

    /** @param iterable<string> $fields */
    public function setErpLockedFields(iterable $fields): void
    {
        $locked = array_values(array_intersect(self::ERP_FIELDS, [...$fields]));
        $this->erpLockedFields = $locked === [] ? null : $locked;
    }

    public function isErpFieldLocked(string $field): bool
    {
        return in_array($field, $this->getErpLockedFields(), true);
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
