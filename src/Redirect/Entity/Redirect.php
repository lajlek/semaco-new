<?php

declare(strict_types=1);

namespace App\Redirect\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_redirect')]
#[ORM\UniqueConstraint(name: 'UNIQ_redirect_source', columns: ['source'])]
class Redirect implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 768)]
    private string $source;

    #[ORM\Column(type: 'string', length: 2048)]
    private string $destination;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $permanent = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $count = 0;

    #[ORM\Column(name: 'last_accessed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAccessedAt = null;

    public function __construct(string $source = '', string $destination = '')
    {
        $this->source = $source;
        $this->destination = $destination;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): void
    {
        $this->destination = $destination;
    }

    public function isPermanent(): bool
    {
        return $this->permanent;
    }

    public function setPermanent(bool $permanent): void
    {
        $this->permanent = $permanent;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getLastAccessedAt(): ?\DateTimeImmutable
    {
        return $this->lastAccessedAt;
    }

    public function markAccessed(): void
    {
        ++$this->count;
        $this->lastAccessedAt = new \DateTimeImmutable();
    }
}
