<?php

declare(strict_types=1);

namespace App\Partner\Entity;

use App\Entity\Product\ProductVariant;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_partner_item')]
#[ORM\UniqueConstraint(name: 'UNIQ_partner_item_code', columns: ['partner_code', 'order_code'])]
#[ORM\Index(name: 'IDX_partner_item_match', columns: ['partner_code', 'match_status'])]
class PartnerItem implements ResourceInterface
{
    public const MATCH_UNMATCHED = 'unmatched';

    public const MATCH_MATCHED = 'matched';

    public const MATCH_AMBIGUOUS = 'ambiguous';

    public const STATE_NEW = 'new';

    public const STATE_NEEDS_REVIEW = 'needs_review';

    public const STATE_READY = 'ready';

    public const STATE_EXPORTED = 'exported';

    public const STATE_PAIRED = 'paired';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'partner_code', type: 'string', length: 32)]
    private string $partnerCode;

    #[ORM\Column(name: 'order_code', type: 'string', length: 64)]
    private string $orderCode;

    #[ORM\Column(name: 'family_code', type: 'string', length: 64, nullable: true)]
    private ?string $familyCode = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(name: 'source_url', type: 'text', nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(name: 'detail_url', type: 'text', nullable: true)]
    private ?string $detailUrl = null;

    /** @var array<string, string> */
    #[ORM\Column(type: 'json')]
    private array $parameters = [];

    /** @var list<string> */
    #[ORM\Column(name: 'image_urls', type: 'json')]
    private array $imageUrls = [];

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $availability = null;

    #[ORM\Column(name: 'content_hash', type: 'string', length: 64)]
    private string $contentHash = '';

    #[ORM\Column(name: 'match_status', type: 'string', length: 16)]
    private string $matchStatus = self::MATCH_UNMATCHED;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(name: 'matched_variant_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ProductVariant $matchedVariant = null;

    #[ORM\Column(name: 'workflow_state', type: 'string', length: 16)]
    private string $workflowState = self::STATE_NEW;

    #[ORM\Column(name: 'first_seen_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column(name: 'changed_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $changedAt;

    public function __construct(string $partnerCode, string $orderCode)
    {
        $this->partnerCode = $partnerCode;
        $this->orderCode = $orderCode;
        $this->firstSeenAt = new \DateTimeImmutable();
        $this->lastSeenAt = $this->firstSeenAt;
        $this->changedAt = $this->firstSeenAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartnerCode(): string
    {
        return $this->partnerCode;
    }

    public function getOrderCode(): string
    {
        return $this->orderCode;
    }

    public function getFamilyCode(): ?string
    {
        return $this->familyCode;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function getDetailUrl(): ?string
    {
        return $this->detailUrl;
    }

    /** @return array<string, string> */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /** @return list<string> */
    public function getImageUrls(): array
    {
        return $this->imageUrls;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getAvailability(): ?string
    {
        return $this->availability;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    /**
     * @param array<string, string> $parameters
     * @param list<string> $imageUrls
     */
    public function updateContent(
        ?string $familyCode,
        ?string $name,
        ?string $subtitle,
        ?string $sourceUrl,
        ?string $detailUrl,
        array $parameters,
        array $imageUrls,
        ?string $price,
        ?string $currency,
        ?string $availability,
        string $contentHash,
    ): bool {
        $now = new \DateTimeImmutable();
        $this->lastSeenAt = $now;
        $this->sourceUrl = $sourceUrl;
        if ($this->contentHash === $contentHash) {
            return false;
        }

        $this->familyCode = $familyCode;
        $this->name = $name;
        $this->subtitle = $subtitle;
        $this->detailUrl = $detailUrl;
        $this->parameters = $parameters;
        $this->imageUrls = $imageUrls;
        $this->price = $price;
        $this->currency = $currency;
        $this->availability = $availability;
        $this->contentHash = $contentHash;
        $this->changedAt = $now;

        return true;
    }

    public function getMatchStatus(): string
    {
        return $this->matchStatus;
    }

    public function getMatchedVariant(): ?ProductVariant
    {
        return $this->matchedVariant;
    }

    public function match(string $status, ?ProductVariant $variant): void
    {
        $this->matchStatus = $status;
        $this->matchedVariant = $variant;
        if ($status === self::MATCH_MATCHED && $this->workflowState === self::STATE_EXPORTED) {
            $this->workflowState = self::STATE_PAIRED;
        }
    }

    public function getWorkflowState(): string
    {
        return $this->workflowState;
    }

    public function setWorkflowState(string $workflowState): void
    {
        $this->workflowState = $workflowState;
    }

    public function getFirstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }
}
