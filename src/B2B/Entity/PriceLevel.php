<?php

declare(strict_types=1);

namespace App\B2B\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_price_level')]
#[ORM\UniqueConstraint(name: 'UNIQ_price_level_code', columns: ['code'])]
#[ORM\UniqueConstraint(name: 'UNIQ_price_level_external_id', columns: ['external_identifier'])]
class PriceLevel implements ResourceInterface
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

    public function __construct(string $code, string $name, ?string $externalIdentifier = null)
    {
        $this->code = $code;
        $this->name = $name;
        $this->externalIdentifier = $externalIdentifier;
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
}
