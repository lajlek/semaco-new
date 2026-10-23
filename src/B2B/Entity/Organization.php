<?php

declare(strict_types=1);

namespace App\B2B\Entity;

use App\Entity\Addressing\Address;
use App\Entity\Customer\Customer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_organization')]
#[ORM\UniqueConstraint(name: 'UNIQ_organization_external_id', columns: ['external_identifier'])]
#[ORM\Index(name: 'IDX_organization_org_number', columns: ['org_number'])]
class Organization implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'external_identifier', type: 'string', length: 128)]
    private string $externalIdentifier;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'org_number', type: 'string', length: 16, nullable: true)]
    private ?string $orgNumber = null;

    #[ORM\Column(name: 'identification_number', type: 'string', length: 32, nullable: true)]
    private ?string $ico = null;

    #[ORM\Column(name: 'vat_identification_number', type: 'string', length: 32, nullable: true)]
    private ?string $dic = null;

    #[ORM\Column(name: 'bank_account', type: 'string', length: 64, nullable: true)]
    private ?string $bankAccount = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(name: 'street_number', type: 'string', length: 32, nullable: true)]
    private ?string $streetNumber = null;

    #[ORM\Column(name: 'street_number_supplement', type: 'string', length: 32, nullable: true)]
    private ?string $streetNumberSupplement = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(name: 'zip_code', type: 'string', length: 16, nullable: true)]
    private ?string $zipCode = null;

    #[ORM\Column(name: 'country_code', type: 'string', length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\ManyToOne(targetEntity: PriceLevel::class)]
    #[ORM\JoinColumn(name: 'price_level_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?PriceLevel $priceLevel = null;

    #[ORM\Column(name: 'customer_type', type: 'string', length: 32, nullable: true)]
    private ?string $customerType = null;

    #[ORM\Column(name: 'free_shipping', type: 'boolean', options: ['default' => false])]
    private bool $freeShipping = false;

    #[ORM\Column(name: 'sales_representative_code', type: 'string', length: 32, nullable: true)]
    private ?string $salesRepresentativeCode = null;

    /** @var Collection<int, Address> */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Address::class, cascade: ['persist'])]
    private Collection $addresses;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'default_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AddressInterface $defaultAddress = null;

    /** @var Collection<int, Customer> */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Customer::class, fetch: 'EXTRA_LAZY')]
    private Collection $customers;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $externalIdentifier, string $name)
    {
        $this->externalIdentifier = $externalIdentifier;
        $this->name = $name;
        $this->addresses = new ArrayCollection();
        $this->customers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalIdentifier(): string
    {
        return $this->externalIdentifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function getOrgNumber(): ?string
    {
        return $this->orgNumber;
    }

    public function setOrgNumber(?string $orgNumber): void
    {
        $this->orgNumber = $orgNumber;
        $this->touch();
    }

    public function getIco(): ?string
    {
        return $this->ico;
    }

    public function setIco(?string $ico): void
    {
        $this->ico = $ico;
        $this->touch();
    }

    public function getDic(): ?string
    {
        return $this->dic;
    }

    public function setDic(?string $dic): void
    {
        $this->dic = $dic;
        $this->touch();
    }

    public function getBankAccount(): ?string
    {
        return $this->bankAccount;
    }

    public function setBankAccount(?string $bankAccount): void
    {
        $this->bankAccount = $bankAccount;
        $this->touch();
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function getStreetNumber(): ?string
    {
        return $this->streetNumber;
    }

    public function getStreetNumberSupplement(): ?string
    {
        return $this->streetNumberSupplement;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setAddress(
        ?string $street,
        ?string $streetNumber,
        ?string $streetNumberSupplement,
        ?string $city,
        ?string $zipCode,
        ?string $countryCode,
    ): void {
        $this->street = $street;
        $this->streetNumber = $streetNumber;
        $this->streetNumberSupplement = $streetNumberSupplement;
        $this->city = $city;
        $this->zipCode = $zipCode;
        $this->countryCode = $countryCode;
        $this->touch();
    }

    public function getPriceLevel(): ?PriceLevel
    {
        return $this->priceLevel;
    }

    public function setPriceLevel(?PriceLevel $priceLevel): void
    {
        $this->priceLevel = $priceLevel;
        $this->touch();
    }

    public function getCustomerType(): ?string
    {
        return $this->customerType;
    }

    public function setCustomerType(?string $customerType): void
    {
        $this->customerType = $customerType;
        $this->touch();
    }

    public function hasFreeShipping(): bool
    {
        return $this->freeShipping;
    }

    public function setFreeShipping(bool $freeShipping): void
    {
        $this->freeShipping = $freeShipping;
        $this->touch();
    }

    public function getSalesRepresentativeCode(): ?string
    {
        return $this->salesRepresentativeCode;
    }

    public function setSalesRepresentativeCode(?string $salesRepresentativeCode): void
    {
        $this->salesRepresentativeCode = $salesRepresentativeCode;
        $this->touch();
    }

    /** @return Collection<int, Address> */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function addAddress(Address $address): void
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
            $address->setOrganization($this);
        }
    }

    public function removeAddress(Address $address): void
    {
        if ($this->addresses->removeElement($address)) {
            $address->setOrganization(null);
        }
    }

    public function getDefaultAddress(): ?AddressInterface
    {
        return $this->defaultAddress;
    }

    public function setDefaultAddress(?AddressInterface $defaultAddress): void
    {
        $this->defaultAddress = $defaultAddress;
        $this->touch();
    }

    /** @return Collection<int, Customer> */
    public function getCustomers(): Collection
    {
        return $this->customers;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
