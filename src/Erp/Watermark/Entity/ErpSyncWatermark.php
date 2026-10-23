<?php

declare(strict_types=1);

namespace App\Erp\Watermark\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_erp_sync_watermark')]
#[ORM\UniqueConstraint(name: 'UNIQ_watermark_domain', columns: ['domain'])]
class ErpSyncWatermark implements ResourceInterface
{
    public const DOMAIN_PRODUCT = 'product';

    public const DOMAIN_PRICE = 'price';

    public const DOMAIN_STOCK = 'stock';

    public const DOMAIN_ORGANIZATION = 'organization';

    public const DOMAIN_CUSTOMER = 'customer';

    public const DOMAIN_ORDER = 'order';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32)]
    private string $domain;

    #[ORM\Column(name: 'last_pulled_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastPulledAt = null;

    #[ORM\Column(name: 'last_success_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    #[ORM\Column(name: 'last_cursor', type: 'string', length: 255, nullable: true)]
    private ?string $lastCursor = null;

    #[ORM\Column(name: 'last_error', type: 'text', nullable: true)]
    private ?string $lastError = null;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getLastPulledAt(): ?\DateTimeImmutable
    {
        return $this->lastPulledAt;
    }

    public function getLastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function getLastCursor(): ?string
    {
        return $this->lastCursor;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function recordAttempt(): void
    {
        $this->lastPulledAt = new \DateTimeImmutable();
    }

    public function recordSuccess(?string $cursor = null): void
    {
        $now = new \DateTimeImmutable();
        $this->lastPulledAt = $now;
        $this->lastSuccessAt = $now;
        $this->lastCursor = $cursor;
        $this->lastError = null;
    }

    public function recordError(string $error): void
    {
        $this->lastPulledAt = new \DateTimeImmutable();
        $this->lastError = $error;
    }
}
