<?php

namespace App\Entity;

use App\Repository\EmailVerificationCodeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailVerificationCodeRepository::class)]
#[ORM\Table(name: 'v2_email_verification_codes')]
class EmailVerificationCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 6)]
    private string $code;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $expiresAt;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $consumedAt = null;

    #[ORM\Column(length: 32)]
    private string $purpose = 'registration';

    // Link to preliminary token to scope verification to a session of registration
    #[ORM\Column(length: 64)]
    private string $preToken;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $dt): self { $this->createdAt = $dt; return $this; }

    public function getExpiresAt(): \DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeInterface $dt): self { $this->expiresAt = $dt; return $this; }

    public function getAttempts(): int { return $this->attempts; }
    public function incrementAttempts(): self { $this->attempts++; return $this; }

    public function getConsumedAt(): ?\DateTimeInterface { return $this->consumedAt; }
    public function setConsumedAt(?\DateTimeInterface $dt): self { $this->consumedAt = $dt; return $this; }

    public function getPurpose(): string { return $this->purpose; }
    public function setPurpose(string $purpose): self { $this->purpose = $purpose; return $this; }

    public function getPreToken(): string { return $this->preToken; }
    public function setPreToken(string $preToken): self { $this->preToken = $preToken; return $this; }
}

