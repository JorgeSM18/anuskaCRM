<?php

namespace App\Dto;

use App\Entity\Appointment;
use App\Entity\Communication;
use App\Entity\Invoice;
use App\Entity\PurchaseOrder;

final readonly class DashboardData
{
    /**
     * @param list<Appointment>                                    $todayAppointments
     * @param list<Appointment>                                    $upcomingAppointments
     * @param list<Invoice>                                        $dueInvoices
     * @param list<PurchaseOrder>                                  $upcomingDeliveries
     * @param list<Communication>                                  $pendingReplies
     * @param list<array{label: string, amount: string, pct: int}> $spendChart
     */
    public function __construct(
        public array $todayAppointments,
        public array $upcomingAppointments,
        public array $dueInvoices,
        public array $upcomingDeliveries,
        public array $pendingReplies,
        public string $pendingAmount,
        public int $openOrders,
        public int $upcomingCount,
        public array $spendChart,
    ) {
    }

    /** ¿Hay algo que requiera atención? Para mostrar "todo al día" si no. */
    public function hasAttentionItems(): bool
    {
        return [] !== $this->todayAppointments
            || [] !== $this->upcomingAppointments
            || [] !== $this->dueInvoices
            || [] !== $this->upcomingDeliveries
            || [] !== $this->pendingReplies;
    }
}
