<?php

declare(strict_types=1);

namespace App\Inquiry\Entity;

use App\Entity\User\AdminUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'app_contact_message_answer')]
#[ORM\UniqueConstraint(name: 'UNIQ_contact_message_answer_legacy_id', columns: ['legacy_id'])]
class ContactMessageAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AdminUser::class)]
    #[ORM\JoinColumn(name: 'admin_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AdminUser $adminUser = null;

    #[ORM\Column(name: 'from_admin', type: 'boolean')]
    private bool $fromAdmin = false;

    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    #[ORM\Column(type: 'text')]
    private ?string $message = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'legacy_id', type: 'integer', nullable: true)]
    private ?int $legacyId = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: ContactMessage::class, inversedBy: 'answers')]
        #[ORM\JoinColumn(name: 'contact_message_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private ContactMessage $contactMessage,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContactMessage(): ContactMessage
    {
        return $this->contactMessage;
    }

    public function getAdminUser(): ?AdminUser
    {
        return $this->adminUser;
    }

    public function setAdminUser(?AdminUser $adminUser): void
    {
        $this->adminUser = $adminUser;
    }

    public function isFromAdmin(): bool
    {
        return $this->fromAdmin;
    }

    public function setFromAdmin(bool $fromAdmin): void
    {
        $this->fromAdmin = $fromAdmin;
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

    public function getLegacyId(): ?int
    {
        return $this->legacyId;
    }

    public function setLegacyId(?int $legacyId): void
    {
        $this->legacyId = $legacyId;
    }
}
