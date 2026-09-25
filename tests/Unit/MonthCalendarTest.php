<?php

namespace App\Tests\Unit;

use App\Entity\Appointment;
use App\Service\MonthCalendar;
use PHPUnit\Framework\TestCase;

class MonthCalendarTest extends TestCase
{
    public function testGridStartsOnMondayAndCoversMonth(): void
    {
        // Enero 2027: el día 1 es viernes -> la rejilla empieza el lunes 28/12/2026.
        $weeks = (new MonthCalendar())->build(2027, 1, []);

        $firstCell = $weeks[0][0];
        $this->assertSame('2026-12-28', $firstCell['date']->format('Y-m-d'));
        $this->assertFalse($firstCell['inMonth']);

        foreach ($weeks as $week) {
            $this->assertCount(7, $week);
        }

        // El 1 de enero debe estar marcado como del mes.
        $jan1 = $weeks[0][4];
        $this->assertSame('2027-01-01', $jan1['date']->format('Y-m-d'));
        $this->assertTrue($jan1['inMonth']);
    }

    public function testAppointmentLandsOnItsDay(): void
    {
        $appt = new Appointment();
        $appt->setTitle('Reunión');
        $appt->setStartsAt(new \DateTimeImmutable('2027-01-15 11:00'));

        $weeks = (new MonthCalendar())->build(2027, 1, [$appt]);

        $found = null;
        foreach ($weeks as $week) {
            foreach ($week as $day) {
                if ('2027-01-15' === $day['date']->format('Y-m-d')) {
                    $found = $day;
                }
            }
        }

        $this->assertNotNull($found);
        $this->assertCount(1, $found['appointments']);
        $this->assertSame('Reunión', $found['appointments'][0]->getTitle());
    }
}
