<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'v2_notifications')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $type = null; // 'email', 'sms', 'push', 'system'

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: CustomerUser::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?CustomerUser $customerUser = null;

    #[ORM\ManyToOne(targetEntity: SystemUser::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SystemUser $systemUser = null;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null; // Additional data for the notification

    #[ORM\Column(length: 50)]
    private ?string $status = 'pending'; // 'pending', 'sent', 'failed', 'delivered'

    #[ORM\Column(length: 50)]
    private ?string $priority = 'normal'; // 'low', 'normal', 'high', 'urgent'

    #[ORM\Column(name: 'is_read', nullable: true)]
    private ?bool $read = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->data = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;
        return $this;
    }

    public function getCustomerUser(): ?CustomerUser
    {
        return $this->customerUser;
    }

    public function setCustomerUser(?CustomerUser $customerUser): static
    {
        $this->customerUser = $customerUser;
        return $this;
    }

    public function getSystemUser(): ?SystemUser
    {
        return $this->systemUser;
    }

    public function setSystemUser(?SystemUser $systemUser): static
    {
        $this->systemUser = $systemUser;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->read;
    }

    public function setRead(bool $read): static
    {
        $this->read = $read;
        if ($read && !$this->readAt) {
            $this->readAt = new \DateTime();
        }
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeInterface
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeInterface $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeInterface $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function markAsSent(): static
    {
        $this->status = 'sent';
        $this->sentAt = new \DateTime();
        return $this;
    }

    public function markAsFailed(string $error = null): static
    {
        $this->status = 'failed';
        if ($error) {
            $this->errorMessage = $error;
        }
        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}