<?php

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Repository\PurchaseOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PurchaseOrderRepository::class)]
#[ORM\Table(name: 'purchase_order')]
#[ORM\Index(fields: ['status'])]
#[ORM\Index(fields: ['expectedDeliveryAt'])]
class PurchaseOrder implements Auditable
{
    use AuditableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'purchaseOrders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'El número de pedido es obligatorio.')]
    private string $number;

    // Nullable en BD (el formulario lo bindea nullable); el validador NotNull lo exige en la app.
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Assert\NotNull(message: 'La fecha del pedido es obligatoria.')]
    private ?\DateTimeImmutable $orderedAt = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $season = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $campaign = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contact $contact = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PLACED;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'El importe no puede ser negativo.')]
    private ?string $estimatedAmount = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'El importe no puede ser negativo.')]
    private ?string $finalAmount = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expectedDeliveryAt = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, Document> */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'purchaseOrder')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
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

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getOrderedAt(): ?\DateTimeImmutable
    {
        return $this->orderedAt;
    }

    public function setOrderedAt(?\DateTimeImmutable $orderedAt): static
    {
        $this->orderedAt = $orderedAt;

        return $this;
    }

    public function getSeason(): ?string
    {
        return $this->season;
    }

    public function setSeason(?string $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    public function setCampaign(?string $campaign): static
    {
        $this->campaign = $campaign;

        return $this;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getEstimatedAmount(): ?string
    {
        return $this->estimatedAmount;
    }

    public function setEstimatedAmount(?string $estimatedAmount): static
    {
        $this->estimatedAmount = $estimatedAmount;

        return $this;
    }

    public function getFinalAmount(): ?string
    {
        return $this->finalAmount;
    }

    public function setFinalAmount(?string $finalAmount): static
    {
        $this->finalAmount = $finalAmount;

        return $this;
    }

    public function getExpectedDeliveryAt(): ?\DateTimeImmutable
    {
        return $this->expectedDeliveryAt;
    }

    public function setExpectedDeliveryAt(?\DateTimeImmutable $expectedDeliveryAt): static
    {
        $this->expectedDeliveryAt = $expectedDeliveryAt;

        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

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
}
