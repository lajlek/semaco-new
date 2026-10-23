<?php

declare(strict_types=1);

namespace App\Cms\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ImageInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_cms_banner')]
#[ORM\UniqueConstraint(name: 'UNIQ_cms_banner_code', columns: ['code'])]
class Banner implements ImageInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $code = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $path = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private ?\SplFileInfo $file = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getFile(): ?\SplFileInfo
    {
        return $this->file;
    }

    public function setFile(?\SplFileInfo $file): void
    {
        $this->file = $file;
        if ($file !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function hasFile(): bool
    {
        return $this->file !== null;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getOwner(): object
    {
        return $this;
    }

    public function setOwner($owner): void
    {
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): void
    {
        $this->alt = $alt;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): void
    {
        $this->link = $link;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
