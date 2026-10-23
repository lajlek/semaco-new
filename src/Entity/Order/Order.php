<?php

declare(strict_types=1);

namespace App\Entity\Order;

use App\B2B\Entity\Organization;
use App\B2B\Entity\SalesRepresentative;
use App\Entity\Customer\Customer;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Order as BaseOrder;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order')]
class Order extends BaseOrder
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    #[ORM\ManyToOne(targetEntity: SalesRepresentative::class)]
    #[ORM\JoinColumn(name: 'sales_representative_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?SalesRepresentative $salesRepresentative = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'contact_customer_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Customer $contactCustomer = null;

    #[ORM\Column(name: 'external_identifier', type: 'string', length: 128, nullable: true)]
    private ?string $externalIdentifier = null;

    #[ORM\Column(name: 'erp_export_state', type: 'string', length: 16, nullable: true)]
    private ?string $erpExportState = null;

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function getSalesRepresentative(): ?SalesRepresentative
    {
        return $this->salesRepresentative;
    }

    public function setSalesRepresentative(?SalesRepresentative $salesRepresentative): void
    {
        $this->salesRepresentative = $salesRepresentative;
    }

    public function getContactCustomer(): ?Customer
    {
        return $this->contactCustomer;
    }

    public function setContactCustomer(?Customer $contactCustomer): void
    {
        $this->contactCustomer = $contactCustomer;
    }

    public function getExternalIdentifier(): ?string
    {
        return $this->externalIdentifier;
    }

    public function setExternalIdentifier(?string $externalIdentifier): void
    {
        $this->externalIdentifier = $externalIdentifier;
    }

    public function getErpExportState(): ?string
    {
        return $this->erpExportState;
    }

    public function setErpExportState(?string $erpExportState): void
    {
        $this->erpExportState = $erpExportState;
    }
}
