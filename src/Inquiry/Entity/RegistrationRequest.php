<?php

declare(strict_types=1);

namespace App\Inquiry\Entity;

use App\Entity\Customer\Customer;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'app_registration_request')]
#[ORM\Index(name: 'IDX_registration_request_created_at', columns: ['created_at'])]
#[ORM\UniqueConstraint(name: 'UNIQ_registration_request_legacy_id', columns: ['legacy_id'])]
class RegistrationRequest implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'company_name', type: 'string', length: 255, nullable: true)]
    private ?string $companyName = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    #[ORM\Column(name: 'identification_number', type: 'string', length: 255, nullable: true)]
    private ?string $identificationNumber = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $street = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    #[ORM\Column(name: 'street_number', type: 'string', length: 255, nullable: true)]
    private ?string $streetNumber = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $city = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 16)]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $postcode = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $email = null;

    #[Assert\Length(max: 50)]
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $phone = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'first_name', type: 'string', length: 255, nullable: true)]
    private ?string $firstName = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'last_name', type: 'string', length: 255, nullable: true)]
    private ?string $lastName = null;

    #[Assert\Length(max: 5000)]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(name: 'user_agent', type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'legacy_id', type: 'integer', nullable: true)]
    private ?int $legacyId = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
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

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): void
    {
        $this->street = $street;
    }

    public function getStreetNumber(): ?string
    {
        return $this->streetNumber;
    }

    public function setStreetNumber(?string $streetNumber): void
    {
        $this->streetNumber = $streetNumber;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    public function setPostcode(?string $postcode): void
    {
        $this->postcode = $postcode;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getFullName(): ?string
    {
        $name = trim(sprintf('%s %s', $this->firstName ?? '', $this->lastName ?? ''));

        return $name === '' ? null : $name;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): void
    {
        $this->ip = $ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent === null ? null : mb_substr($userAgent, 0, 255);
    }

    public function getLegacyId(): ?int
    {
        return $this->legacyId;
    }

    public function setLegacyId(?int $legacyId): void
    {
        $this->legacyId = $legacyId;
    }
}
