<?php

declare(strict_types=1);

namespace App\Entity\Customer;

use App\B2B\Entity\Organization;
use App\B2B\Entity\SalesRepresentative;
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

    #[ORM\ManyToOne(targetEntity: SalesRepresentative::class)]
    #[ORM\JoinColumn(name: 'sales_representative_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?SalesRepresentative $salesRepresentative = null;

    #[ORM\Column(name: 'company_name', type: 'string', length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(name: 'identification_number', type: 'string', length: 32, nullable: true)]
    private ?string $identificationNumber = null;

    #[ORM\Column(name: 'vat_number', type: 'string', length: 32, nullable: true)]
    private ?string $vatNumber = null;

    #[ORM\Column(name: 'invoice_email', type: 'string', length: 255, nullable: true)]
    private ?string $invoiceEmail = null;

    #[ORM\Column(name: 'vat_payer', type: 'boolean', options: ['default' => false])]
    private bool $vatPayer = false;

    #[ORM\Column(name: 'gdpr_agreed', type: 'boolean', options: ['default' => false])]
    private bool $gdprAgreed = false;

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

    public function getSalesRepresentative(): ?SalesRepresentative
    {
        return $this->salesRepresentative;
    }

    public function setSalesRepresentative(?SalesRepresentative $salesRepresentative): void
    {
        $this->salesRepresentative = $salesRepresentative;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): void
    {
        $this->companyName = $companyName;
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

    public function getInvoiceEmail(): ?string
    {
        return $this->invoiceEmail;
    }

    public function setInvoiceEmail(?string $invoiceEmail): void
    {
        $this->invoiceEmail = $invoiceEmail;
    }

    public function isVatPayer(): bool
    {
        return $this->vatPayer;
    }

    public function setVatPayer(bool $vatPayer): void
    {
        $this->vatPayer = $vatPayer;
    }

    public function isGdprAgreed(): bool
    {
        return $this->gdprAgreed;
    }

    public function setGdprAgreed(bool $gdprAgreed): void
    {
        $this->gdprAgreed = $gdprAgreed;
    }
}
