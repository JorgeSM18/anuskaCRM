<?php

namespace App\Service;

use App\Dto\SupplierStats;
use App\Entity\Supplier;
use App\Repository\AppointmentRepository;
use App\Repository\CommunicationRepository;
use App\Repository\InvoiceRepository;
use App\Repository\PurchaseOrderRepository;

/**
 * Calcula las tarjetas de resumen de la ficha del proveedor.
 */
class SupplierSummary
{
    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly InvoiceRepository $invoices,
        private readonly CommunicationRepository $communications,
        private readonly AppointmentRepository $appointments,
    ) {
    }

    public function for(Supplier $supplier): SupplierStats
    {
        return new SupplierStats(
            $this->orders->count(['supplier' => $supplier]),
            $this->invoices->sumTotalBySupplier($supplier),
            $this->invoices->sumPendingBySupplier($supplier),
            $this->communications->lastContactAt($supplier),
            $this->appointments->nextForSupplier($supplier),
        );
    }
}
