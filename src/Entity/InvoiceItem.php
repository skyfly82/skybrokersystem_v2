<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceItemRepository::class)]
#[ORM\Table(name: 'invoice_items')]
class InvoiceItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'invoice_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\Column(type: 'integer')]
    private int $lp = 1;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $qty = '1.00';

    #[ORM\Column(type: 'string', length: 16)]
    private string $jm = 'szt';

    #[ORM\Column(type: 'integer')]
    private int $vat = 23;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $unitBrutto = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalBrutto = '0.00';

    public function getId(): ?int { return $this->id; }

    public function getInvoice(): ?Invoice { return $this->invoice; }
    public function setInvoice(?Invoice $invoice): self { $this->invoice = $invoice; return $this; }

    public function getLp(): int { return $this->lp; }
    public function setLp(int $lp): self { $this->lp = $lp; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): self { $this->code = $code; return $this; }

    public function getQty(): float { return (float)$this->qty; }
    public function setQty(float $qty): self { $this->qty = number_format($qty, 2, '.', ''); return $this; }

    public function getJm(): string { return $this->jm; }
    public function setJm(string $jm): self { $this->jm = $jm; return $this; }

    public function getVat(): int { return $this->vat; }
    public function setVat(int $vat): self { $this->vat = $vat; return $this; }

    public function getUnitBrutto(): float { return (float)$this->unitBrutto; }
    public function setUnitBrutto(float $v): self { $this->unitBrutto = number_format($v, 2, '.', ''); return $this; }

    public function getTotalBrutto(): float { return (float)$this->totalBrutto; }
    public function setTotalBrutto(float $v): self { $this->totalBrutto = number_format($v, 2, '.', ''); return $this; }
}

