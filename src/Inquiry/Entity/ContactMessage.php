<?php

declare(strict_types=1);

namespace App\Inquiry\Entity;

use App\Entity\Customer\Customer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'app_contact_message')]
#[ORM\Index(name: 'IDX_contact_message_created_at', columns: ['created_at'])]
#[ORM\UniqueConstraint(name: 'UNIQ_contact_message_legacy_id', columns: ['legacy_id'])]
class ContactMessage implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'first_name', type: 'string', length: 255, nullable: true)]
    private ?string $firstName = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'last_name', type: 'string', length: 255, nullable: true)]
    private ?string $lastName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $email = null;

    #[Assert\Length(max: 50)]
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $phone = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    #[ORM\Column(type: 'text')]
    private ?string $message = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(name: 'user_agent', type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'legacy_id', type: 'integer', nullable: true)]
    private ?int $legacyId = null;

    /** @var Collection<int, ContactMessageAnswer> */
    #[ORM\OneToMany(targetEntity: ContactMessageAnswer::class, mappedBy: 'contactMessage', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $answers;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->answers = new ArrayCollection();
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

    public function getSenderName(): string
    {
        return $this->getFullName() ?? (string) $this->email;
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

    /** @return Collection<int, ContactMessageAnswer> */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(ContactMessageAnswer $answer): void
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
        }
    }
}
