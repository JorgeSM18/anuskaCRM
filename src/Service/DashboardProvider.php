<?php

namespace App\Service;

use App\Dto\DashboardData;
use App\Repository\AppointmentRepository;
use App\Repository\CommunicationRepository;
use App\Repository\InvoiceRepository;
use App\Repository\PurchaseOrderRepository;

/**
 * Reúne los datos del panel de inicio (solo lo accionable) en una sola llamada.
 */
class DashboardProvider
{
    private const array MONTHS = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly InvoiceRepository $invoices,
        private readonly CommunicationRepository $communications,
        private readonly AppointmentRepository $appointments,
    ) {
    }

    public function build(): DashboardData
    {
        $now = new \DateTimeImmutable();
        $today = new \DateTimeImmutable('today');
        $in7Days = $today->modify('+7 days');

        return new DashboardData(
            todayAppointments: $this->appointments->findToday(),
            upcomingAppointments: $this->appointments->findUpcoming($now, $in7Days),
            dueInvoices: \array_slice($this->invoices->findDueBefore($in7Days), 0, 8),
            upcomingDeliveries: $this->orders->findUpcomingDeliveries($today, $in7Days),
            pendingReplies: $this->communications->findPendingReplies(6),
            pendingAmount: $this->invoices->sumPendingTotal(),
            openOrders: $this->orders->countOpen(),
            upcomingCount: $this->appointments->countUpcoming($now),
            spendChart: $this->buildSpendChart($today),
        );
    }

    /**
     * Gasto en pedidos (importe final o estimado) de los últimos 6 meses.
     *
     * @return list<array{label: string, amount: string, pct: int}>
     */
    private function buildSpendChart(\DateTimeImmutable $today): array
    {
        $start = $today->modify('first day of this month')->modify('-5 months');

        // Inicializa 6 meses a cero, en orden.
        $buckets = [];
        for ($i = 0; $i < 6; ++$i) {
            $month = $start->modify("+{$i} months");
            $buckets[$month->format('Y-m')] = ['label' => self::MONTHS[(int) $month->format('n')], 'amount' => 0.0];
        }

        foreach ($this->orders->findSince($start) as $order) {
            $key = $order->getOrderedAt()?->format('Y-m');
            if (null !== $key && isset($buckets[$key])) {
                $buckets[$key]['amount'] += (float) ($order->getFinalAmount() ?? $order->getEstimatedAmount() ?? '0');
            }
        }

        $max = max(1.0, ...array_column($buckets, 'amount'));

        $chart = [];
        foreach ($buckets as $bucket) {
            $chart[] = [
                'label' => $bucket['label'],
                'amount' => number_format($bucket['amount'], 2, '.', ''),
                'pct' => (int) round($bucket['amount'] / $max * 100),
            ];
        }

        return $chart;
    }
}
