<?php

namespace App\Entity;

/**
 * Entidades que registran quién y cuándo las creó/modificó.
 * El relleno lo hace App\EventListener\AuditableListener.
 */
interface Auditable
{
    public function setCreatedAt(\DateTimeImmutable $at): void;

    public function setUpdatedAt(\DateTimeImmutable $at): void;

    public function setCreatedBy(?User $user): void;

    public function setUpdatedBy(?User $user): void;

    public function getCreatedAt(): ?\DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function getCreatedBy(): ?User;

    public function getUpdatedBy(): ?User;
}
