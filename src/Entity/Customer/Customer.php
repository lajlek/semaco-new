<?php

declare(strict_types=1);

namespace App\Entity\Customer;

use App\B2B\Entity\Organization;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Customer as BaseCustomer;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_customer')]
#[ORM\Index(name: 'IDX_customer_external_id', columns: ['external_identifier'])]
class Customer extends BaseCustomer
{
    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'customers')]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    #[ORM\Column(name: 'external_identifier', type: 'string', length: 128, nullable: true)]
    private ?string $externalIdentifier = null;

    #[ORM\Column(name: 'sync_with_erp', type: 'boolean', options: ['default' => true])]
    private bool $syncWithErp = true;

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

    public function isSyncWithErp(): bool
    {
        return $this->syncWithErp;
    }

    public function setSyncWithErp(bool $syncWithErp): void
    {
        $this->syncWithErp = $syncWithErp;
    }
}
