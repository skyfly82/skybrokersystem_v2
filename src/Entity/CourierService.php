<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CourierServiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourierServiceRepository::class)]
#[ORM\Table(name: 'v2_courier_services')]
class CourierService
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $code = null; // 'inpost', 'dhl', 'ups', etc.

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column]
    private ?bool $active = true;

    #[ORM\Column]
    private ?bool $domestic = true;

    #[ORM\Column]
    private ?bool $international = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $supportedServices = null; // List of service types

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $apiCredentials = null; // Encrypted API settings

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->supportedServices = [];
        $this->apiCredentials = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    public function isDomestic(): ?bool
    {
        return $this->domestic;
    }

    public function setDomestic(bool $domestic): static
    {
        $this->domestic = $domestic;
        return $this;
    }

    public function isInternational(): ?bool
    {
        return $this->international;
    }

    public function setInternational(bool $international): static
    {
        $this->international = $international;
        return $this;
    }

    public function getSupportedServices(): ?array
    {
        return $this->supportedServices;
    }

    public function setSupportedServices(?array $supportedServices): static
    {
        $this->supportedServices = $supportedServices;
        return $this;
    }

    public function getApiCredentials(): ?array
    {
        return $this->apiCredentials;
    }

    public function setApiCredentials(?array $apiCredentials): static
    {
        $this->apiCredentials = $apiCredentials;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}