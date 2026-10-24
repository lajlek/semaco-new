<?php

declare(strict_types=1);

namespace App\Partner\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_partner_fetch_run')]
#[ORM\Index(name: 'IDX_partner_fetch_run_status', columns: ['status'])]
class PartnerFetchRun implements ResourceInterface
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_STOPPED = 'stopped';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const KIND_LISTING = 'listing';

    public const KIND_DETAILS = 'details';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'partner_code', type: 'string', length: 32)]
    private string $partnerCode;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => self::KIND_LISTING])]
    private string $kind;

    #[ORM\Column(type: 'text')]
    private string $url;

    #[ORM\Column(name: 'max_pages', type: 'integer')]
    private int $maxPages;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::STATUS_QUEUED;

    #[ORM\Column(name: 'requested_by', type: 'string', length: 255)]
    private string $requestedBy;

    #[ORM\Column(name: 'requested_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: 'integer')]
    private int $pages = 0;

    #[ORM\Column(name: 'items_created', type: 'integer')]
    private int $itemsCreated = 0;

    #[ORM\Column(name: 'items_changed', type: 'integer')]
    private int $itemsChanged = 0;

    #[ORM\Column(name: 'items_unchanged', type: 'integer')]
    private int $itemsUnchanged = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    public function __construct(string $partnerCode, string $url, int $maxPages, string $requestedBy, string $kind = self::KIND_LISTING)
    {
        $this->kind = $kind;
        $this->partnerCode = $partnerCode;
        $this->url = $url;
        $this->maxPages = $maxPages;
        $this->requestedBy = $requestedBy;
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartnerCode(): string
    {
        return $this->partnerCode;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMaxPages(): int
    {
        return $this->maxPages;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRequestedBy(): string
    {
        return $this->requestedBy;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getPages(): int
    {
        return $this->pages;
    }

    public function getItemsCreated(): int
    {
        return $this->itemsCreated;
    }

    public function getItemsChanged(): int
    {
        return $this->itemsChanged;
    }

    public function getItemsUnchanged(): int
    {
        return $this->itemsUnchanged;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function start(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->startedAt = new \DateTimeImmutable();
    }

    /** @param array{created: int, changed: int, unchanged: int} $summary */
    public function addPage(array $summary): void
    {
        ++$this->pages;
        $this->itemsCreated += $summary['created'];
        $this->itemsChanged += $summary['changed'];
        $this->itemsUnchanged += $summary['unchanged'];
    }

    public function finish(string $status, ?string $message): void
    {
        $this->status = $status;
        $this->message = $message;
        $this->finishedAt = new \DateTimeImmutable();
    }
}
