<?php

namespace App\Service;

use App\Entity\Appointment;

/**
 * Construye la rejilla mensual (semanas de lunes a domingo) para el calendario.
 */
class MonthCalendar
{
    /**
     * @param list<Appointment> $appointments
     *
     * @return list<list<array{date: \DateTimeImmutable, inMonth: bool, appointments: list<Appointment>}>>
     */
    public function build(int $year, int $month, array $appointments): array
    {
        $byDay = [];
        foreach ($appointments as $appointment) {
            $key = $appointment->getStartsAt()?->format('Y-m-d');
            if (null !== $key) {
                $byDay[$key][] = $appointment;
            }
        }

        $first = new \DateTimeImmutable(\sprintf('%04d-%02d-01', $year, $month));
        $last = $first->modify('last day of this month');
        // Rejilla de lunes (N=1) a domingo (N=7).
        $cursor = $first->modify('-'.((int) $first->format('N') - 1).' days');
        $gridEnd = $last->modify('+'.(7 - (int) $last->format('N')).' days');

        $weeks = [];
        while ($cursor <= $gridEnd) {
            $week = [];
            for ($i = 0; $i < 7; ++$i) {
                $key = $cursor->format('Y-m-d');
                $week[] = [
                    'date' => $cursor,
                    'inMonth' => (int) $cursor->format('n') === $month,
                    'appointments' => $byDay[$key] ?? [],
                ];
                $cursor = $cursor->modify('+1 day');
            }
            $weeks[] = $week;
        }

        return $weeks;
    }
}
