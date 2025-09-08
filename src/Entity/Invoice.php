<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $number;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $issueDate;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $sellDate;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $paymentDueDate;

    #[ORM\Column(type: 'string', length: 32)]
    private string $paymentMethod = 'przelew';

    #[ORM\Column(type: 'string', length: 200)]
    private string $sellerName;

    #[ORM\Column(type: 'string', length: 300)]
    private string $sellerAddress;

    #[ORM\Column(type: 'string', length: 32)]
    private string $sellerNip;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $sellerIban = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $sellerBank = null;

    #[ORM\Column(type: 'string', length: 200)]
    private string $buyerName;

    #[ORM\Column(type: 'string', length: 300)]
    private string $buyerAddress;

    #[ORM\Column(type: 'string', length: 32)]
    private string $buyerNip;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $paidAmount = '0.00';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, InvoiceItem> */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceItem::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['lp' => 'ASC'])]
    private Collection $items;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $new = false;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }

    public function getNumber(): string { return $this->number; }
    public function setNumber(string $number): self { $this->number = $number; return $this; }

    public function getIssueDate(): \DateTimeImmutable { return $this->issueDate; }
    public function setIssueDate(\DateTimeImmutable $d): self { $this->issueDate = $d; return $this; }

    public function getSellDate(): \DateTimeImmutable { return $this->sellDate; }
    public function setSellDate(\DateTimeImmutable $d): self { $this->sellDate = $d; return $this; }

    public function getPaymentDueDate(): \DateTimeImmutable { return $this->paymentDueDate; }
    public function setPaymentDueDate(\DateTimeImmutable $d): self { $this->paymentDueDate = $d; return $this; }

    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function setPaymentMethod(string $m): self { $this->paymentMethod = $m; return $this; }

    public function getSellerName(): string { return $this->sellerName; }
    public function setSellerName(string $v): self { $this->sellerName = $v; return $this; }

    public function getSellerAddress(): string { return $this->sellerAddress; }
    public function setSellerAddress(string $v): self { $this->sellerAddress = $v; return $this; }

    public function getSellerNip(): string { return $this->sellerNip; }
    public function setSellerNip(string $v): self { $this->sellerNip = $v; return $this; }

    public function getSellerIban(): ?string { return $this->sellerIban; }
    public function setSellerIban(?string $v): self { $this->sellerIban = $v; return $this; }

    public function getSellerBank(): ?string { return $this->sellerBank; }
    public function setSellerBank(?string $v): self { $this->sellerBank = $v; return $this; }

    public function getBuyerName(): string { return $this->buyerName; }
    public function setBuyerName(string $v): self { $this->buyerName = $v; return $this; }

    public function getBuyerAddress(): string { return $this->buyerAddress; }
    public function setBuyerAddress(string $v): self { $this->buyerAddress = $v; return $this; }

    public function getBuyerNip(): string { return $this->buyerNip; }
    public function setBuyerNip(string $v): self { $this->buyerNip = $v; return $this; }

    public function getPaidAmount(): float { return (float)$this->paidAmount; }
    public function setPaidAmount(float $v): self { $this->paidAmount = number_format($v, 2, '.', ''); return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $d): self { $this->createdAt = $d; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $d): self { $this->updatedAt = $d; return $this; }

    /** @return Collection<int, InvoiceItem> */
    public function getItems(): Collection { return $this->items; }
    public function addItem(InvoiceItem $item): self {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInvoice($this);
        }
        return $this;
    }
    public function removeItem(InvoiceItem $item): self {
        if ($this->items->removeElement($item) && $item->getInvoice() === $this) {
            $item->setInvoice(null);
        }
        return $this;
    }

    public function isNew(): bool { return $this->new; }
    public function setNew(bool $new): self { $this->new = $new; return $this; }
}
