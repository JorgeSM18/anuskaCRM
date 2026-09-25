<?php

namespace App\Entity;

use App\Enum\CommunicationSource;
use App\Enum\CommunicationType;
use App\Repository\CommunicationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunicationRepository::class)]
#[ORM\Index(fields: ['occurredAt'])]
#[ORM\Index(fields: ['pendingReply'])]
class Communication implements Auditable
{
    use AuditableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'communications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contact $contact = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Assert\NotNull(message: 'La fecha es obligatoria.')]
    private ?\DateTimeImmutable $occurredAt = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'El asunto es obligatorio.')]
    private string $subject;

    #[ORM\Column(enumType: CommunicationType::class)]
    private CommunicationType $type = CommunicationType::EMAIL;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column]
    private bool $isImportant = false;

    #[ORM\Column]
    private bool $pendingReply = false;

    // --- Ganchos inertes para la futura integración de correo (no se usan aún) ---
    #[ORM\Column(enumType: CommunicationSource::class)]
    private CommunicationSource $sourceType = CommunicationSource::MANUAL;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fromAddress = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getOccurredAt(): ?\DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(?\DateTimeImmutable $occurredAt): static
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getType(): CommunicationType
    {
        return $this->type;
    }

    public function setType(CommunicationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function isImportant(): bool
    {
        return $this->isImportant;
    }

    public function setIsImportant(bool $isImportant): static
    {
        $this->isImportant = $isImportant;

        return $this;
    }

    public function isPendingReply(): bool
    {
        return $this->pendingReply;
    }

    public function setPendingReply(bool $pendingReply): static
    {
        $this->pendingReply = $pendingReply;

        return $this;
    }

    public function getSourceType(): CommunicationSource
    {
        return $this->sourceType;
    }

    public function setSourceType(CommunicationSource $sourceType): static
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getFromAddress(): ?string
    {
        return $this->fromAddress;
    }

    public function setFromAddress(?string $fromAddress): static
    {
        $this->fromAddress = $fromAddress;

        return $this;
    }
}
