<?php

namespace App\Entity;

use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Index(fields: ['status'])]
#[ORM\Index(fields: ['dueAt'])]
class Invoice implements Auditable
{
    use AuditableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?PurchaseOrder $purchaseOrder = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'El número de factura es obligatorio.')]
    private string $number;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Assert\NotNull(message: 'La fecha de emisión es obligatoria.')]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\NotNull(message: 'La base imponible es obligatoria.')]
    #[Assert\PositiveOrZero]
    private ?string $baseAmount = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\NotNull(message: 'El tipo de IVA es obligatorio.')]
    #[Assert\PositiveOrZero]
    private ?string $vatRate = '21.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $vatAmount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $total = '0.00';

    #[ORM\Column(enumType: InvoiceStatus::class)]
    private InvoiceStatus $status = InvoiceStatus::PENDING;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, Document> */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'invoice')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $documents;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'invoice', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['paidAt' => 'DESC'])]
    private Collection $payments;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getPurchaseOrder(): ?PurchaseOrder
    {
        return $this->purchaseOrder;
    }

    public function setPurchaseOrder(?PurchaseOrder $purchaseOrder): static
    {
        $this->purchaseOrder = $purchaseOrder;

        return $this;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;

        return $this;
    }

    public function getBaseAmount(): ?string
    {
        return $this->baseAmount;
    }

    public function setBaseAmount(?string $baseAmount): static
    {
        $this->baseAmount = $baseAmount;

        return $this;
    }

    public function getVatRate(): ?string
    {
        return $this->vatRate;
    }

    public function setVatRate(?string $vatRate): static
    {
        $this->vatRate = $vatRate;

        return $this;
    }

    public function getVatAmount(): string
    {
        return $this->vatAmount;
    }

    public function setVatAmount(string $vatAmount): static
    {
        $this->vatAmount = $vatAmount;

        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setInvoice($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        $this->payments->removeElement($payment);

        return $this;
    }

    /**
     * Suma de los pagos registrados.
     *
     * @return numeric-string
     */
    public function getPaidAmount(): string
    {
        $sum = '0';
        foreach ($this->payments as $payment) {
            $amount = $payment->getAmount();
            $sum = bcadd($sum, is_numeric($amount) ? $amount : '0', 2);
        }

        return $sum;
    }

    /**
     * Importe que queda por pagar.
     *
     * @return numeric-string
     */
    public function getPendingAmount(): string
    {
        $total = is_numeric($this->total) ? $this->total : '0';

        return bcsub($total, $this->getPaidAmount(), 2);
    }

    public function isFullyPaid(): bool
    {
        return bccomp($this->getPaidAmount(), is_numeric($this->total) ? $this->total : '0', 2) >= 0;
    }

    /**
     * "Vencida" es un estado derivado, no almacenado: pendiente y pasada la fecha de vencimiento.
     */
    public function isOverdue(): bool
    {
        return InvoiceStatus::PENDING === $this->status
            && null !== $this->dueAt
            && $this->dueAt < new \DateTimeImmutable('today');
    }
}
