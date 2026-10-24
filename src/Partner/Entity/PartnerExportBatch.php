<?php

declare(strict_types=1);

namespace App\Partner\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_partner_export_batch')]
class PartnerExportBatch implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'created_by', type: 'string', length: 255)]
    private string $createdBy;

    #[ORM\Column(name: 'item_count', type: 'integer')]
    private int $itemCount;

    #[ORM\Column(type: 'text')]
    private string $content;

    /** @var Collection<int, PartnerItem> */
    #[ORM\OneToMany(mappedBy: 'exportBatch', targetEntity: PartnerItem::class)]
    private Collection $items;

    public function __construct(string $createdBy, int $itemCount, string $content)
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->createdBy = $createdBy;
        $this->itemCount = $itemCount;
        $this->content = $content;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function getItemCount(): int
    {
        return $this->itemCount;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getFileName(): string
    {
        return sprintf('partneri-helios-%d-%s.csv', (int) $this->id, $this->createdAt->format('Ymd-His'));
    }

    /** @return Collection<int, PartnerItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getPairedCount(): int
    {
        return $this->items->filter(static fn (PartnerItem $item): bool => $item->getWorkflowState() === PartnerItem::STATE_PAIRED)->count();
    }
}
