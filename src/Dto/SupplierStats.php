<?php

namespace App\Dto;

use App\Entity\Appointment;

final readonly class SupplierStats
{
    public function __construct(
        public int $ordersCount,
        public string $invoicedTotal,
        public string $pendingTotal,
        public ?\DateTimeImmutable $lastContactAt = null,
        public ?Appointment $nextAppointment = null,
    ) {
    }
}
