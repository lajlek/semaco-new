<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_brand')]
#[ORM\UniqueConstraint(name: 'UNIQ_brand_code', columns: ['code'])]
class Brand implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $code;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'external_identifier', type: 'string', length: 128, nullable: true)]
    private ?string $externalIdentifier = null;

    #[ORM\Column(name: 'logo_path', type: 'string', length: 255, nullable: true)]
    private ?string $logoPath = null;

    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getExternalIdentifier(): ?string
    {
        return $this->externalIdentifier;
    }

    public function setExternalIdentifier(?string $externalIdentifier): void
    {
        $this->externalIdentifier = $externalIdentifier;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): void
    {
        $this->logoPath = $logoPath;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
