<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Campos de auditoría. Úsalo junto con la interfaz Auditable.
 */
trait AuditableTrait
{
    // No nulables: el AuditableListener siempre las rellena en prePersist.
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function setCreatedAt(\DateTimeImmutable $at): void
    {
        $this->createdAt = $at;
    }

    public function setUpdatedAt(\DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    public function setCreatedBy(?User $user): void
    {
        $this->createdBy = $user;
    }

    public function setUpdatedBy(?User $user): void
    {
        $this->updatedBy = $user;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }
}
