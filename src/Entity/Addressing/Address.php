<?php

declare(strict_types=1);

namespace App\Entity\Addressing;

use App\B2B\Entity\Organization;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Address as BaseAddress;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_address')]
#[ORM\Index(name: 'IDX_address_external_id', columns: ['external_identifier'])]
class Address extends BaseAddress
{
    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'addresses')]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    #[ORM\Column(name: 'external_identifier', type: 'string', length: 128, nullable: true)]
    private ?string $externalIdentifier = null;

    #[ORM\Column(name: 'identification_number', type: 'string', length: 32, nullable: true)]
    private ?string $identificationNumber = null;

    #[ORM\Column(name: 'vat_number', type: 'string', length: 32, nullable: true)]
    private ?string $vatNumber = null;

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function getExternalIdentifier(): ?string
    {
        return $this->externalIdentifier;
    }

    public function setExternalIdentifier(?string $externalIdentifier): void
    {
        $this->externalIdentifier = $externalIdentifier;
    }

    public function getIdentificationNumber(): ?string
    {
        return $this->identificationNumber;
    }

    public function setIdentificationNumber(?string $identificationNumber): void
    {
        $this->identificationNumber = $identificationNumber;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): void
    {
        $this->vatNumber = $vatNumber;
    }
}
