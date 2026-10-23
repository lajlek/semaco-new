<?php

declare(strict_types=1);

namespace App\Withdrawal\Entity;

use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'app_withdrawal_request')]
#[ORM\Index(name: 'IDX_withdrawal_request_submitted_at', columns: ['submitted_at'])]
#[ORM\UniqueConstraint(name: 'UNIQ_withdrawal_request_token', columns: ['token'])]
class WithdrawalRequest implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'full_name', type: 'string', length: 255)]
    private ?string $fullName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $email = null;

    #[Assert\Length(max: 50)]
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $phone = null;

    #[Assert\Length(max: 500)]
    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $address = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[ORM\Column(name: 'order_number', type: 'string', length: 64)]
    private ?string $orderNumber = null;

    #[Assert\Length(max: 64)]
    #[ORM\Column(name: 'received_on', type: 'string', length: 64, nullable: true)]
    private ?string $receivedOn = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    #[ORM\Column(type: 'text')]
    private ?string $items = null;

    #[Assert\Length(max: 64)]
    #[ORM\Column(name: 'bank_account', type: 'string', length: 64, nullable: true)]
    private ?string $bankAccount = null;

    #[Assert\Length(max: 5000)]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(name: 'submitted_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $submittedAt;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(name: 'user_agent', type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'handled_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    public function __construct()
    {
        $this->token = bin2hex(random_bytes(16));
        $this->submittedAt = new \DateTimeImmutable();
    }

    /** @return array<string, string|null> */
    public function toDraft(): array
    {
        return [
            'fullName' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'orderNumber' => $this->orderNumber,
            'receivedOn' => $this->receivedOn,
            'items' => $this->items,
            'bankAccount' => $this->bankAccount,
            'note' => $this->note,
        ];
    }

    /** @param array<array-key, mixed> $draft */
    public static function fromDraft(array $draft): self
    {
        $request = new self();
        $text = static fn (string $key): ?string => isset($draft[$key]) && is_string($draft[$key]) && trim($draft[$key]) !== '' ? trim($draft[$key]) : null;
        $request->fullName = $text('fullName');
        $request->email = $text('email');
        $request->phone = $text('phone');
        $request->address = $text('address');
        $request->orderNumber = $text('orderNumber');
        $request->receivedOn = $text('receivedOn');
        $request->items = $text('items');
        $request->bankAccount = $text('bankAccount');
        $request->note = $text('note');

        return $request;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): void
    {
        $this->order = $order;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): void
    {
        $this->fullName = $fullName;
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }

    public function getReceivedOn(): ?string
    {
        return $this->receivedOn;
    }

    public function setReceivedOn(?string $receivedOn): void
    {
        $this->receivedOn = $receivedOn;
    }

    public function getItems(): ?string
    {
        return $this->items;
    }

    public function setItems(?string $items): void
    {
        $this->items = $items;
    }

    public function getBankAccount(): ?string
    {
        return $this->bankAccount;
    }

    public function setBankAccount(?string $bankAccount): void
    {
        $this->bankAccount = $bankAccount;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function markSubmitted(\DateTimeImmutable $submittedAt, ?string $ip, ?string $userAgent): void
    {
        $this->submittedAt = $submittedAt;
        $this->ip = $ip;
        $this->userAgent = $userAgent === null ? null : mb_substr($userAgent, 0, 255);
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function isOrderMatched(): bool
    {
        return $this->order !== null;
    }

    public function getHandledAt(): ?\DateTimeImmutable
    {
        return $this->handledAt;
    }

    public function markHandled(\DateTimeImmutable $handledAt): void
    {
        $this->handledAt = $handledAt;
    }

    public function isHandled(): bool
    {
        return $this->handledAt !== null;
    }
}
