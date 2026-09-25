<?php

namespace App\Entity;

use App\Repository\FairParticipationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un proveedor presente en una feria, con las notas de esa participación.
 */
#[ORM\Entity(repositoryClass: FairParticipationRepository::class)]
#[ORM\UniqueConstraint(fields: ['fair', 'supplier'])]
class FairParticipation implements Auditable
{
    use AuditableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Fair $fair;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contactsMet = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $meetingNotes = null;

    #[ORM\Column]
    private bool $followUp = false;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $nextAction = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFair(): Fair
    {
        return $this->fair;
    }

    public function setFair(Fair $fair): static
    {
        $this->fair = $fair;

        return $this;
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

    public function getContactsMet(): ?string
    {
        return $this->contactsMet;
    }

    public function setContactsMet(?string $contactsMet): static
    {
        $this->contactsMet = $contactsMet;

        return $this;
    }

    public function getMeetingNotes(): ?string
    {
        return $this->meetingNotes;
    }

    public function setMeetingNotes(?string $meetingNotes): static
    {
        $this->meetingNotes = $meetingNotes;

        return $this;
    }

    public function isFollowUp(): bool
    {
        return $this->followUp;
    }

    public function setFollowUp(bool $followUp): static
    {
        $this->followUp = $followUp;

        return $this;
    }

    public function getNextAction(): ?string
    {
        return $this->nextAction;
    }

    public function setNextAction(?string $nextAction): static
    {
        $this->nextAction = $nextAction;

        return $this;
    }
}
