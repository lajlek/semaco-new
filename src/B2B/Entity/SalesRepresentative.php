<?php

declare(strict_types=1);

namespace App\B2B\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_sales_representative')]
#[ORM\UniqueConstraint(name: 'UNIQ_sales_representative_external_id', columns: ['external_identifier'])]
class SalesRepresentative implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'external_identifier', type: 'string', length: 128)]
    private string $externalIdentifier;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'phone_number', type: 'string', length: 64, nullable: true)]
    private ?string $phoneNumber = null;

    public function __construct(string $externalIdentifier, string $name)
    {
        $this->externalIdentifier = $externalIdentifier;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalIdentifier(): string
    {
        return $this->externalIdentifier;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
