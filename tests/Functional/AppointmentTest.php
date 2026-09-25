<?php

namespace App\Tests\Functional;

use App\Entity\Appointment;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use App\Enum\AppointmentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AppointmentTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Supplier $supplier;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('tester@anuska.local');
        $user->setFullName('Tester');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('x');
        $this->em->persist($user);

        $this->supplier = new Supplier();
        $this->supplier->setBrandName('Proveedor Citas');
        $this->em->persist($this->supplier);
        $this->em->flush();

        $this->client->loginUser($user);
    }

    public function testCreateFromSupplier(): void
    {
        $this->client->request('GET', '/proveedores/'.$this->supplier->getId().'/citas/nueva');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Guardar', [
            'appointment[title]' => 'Visita colección PV27',
            'appointment[type]' => AppointmentType::SALES_VISIT->value,
            'appointment[startsAt]' => '2027-04-10T11:00',
            'appointment[status]' => AppointmentStatus::PENDING->value,
        ]);

        $this->assertResponseRedirects();

        $appt = $this->em->getRepository(Appointment::class)->findOneBy(['title' => 'Visita colección PV27']);
        $this->assertNotNull($appt);
        $this->assertSame($this->supplier->getId(), $appt->getSupplier()?->getId());
        $this->assertSame(AppointmentStatus::PENDING, $appt->getStatus());
    }

    public function testNextAppointmentCardOnFicha(): void
    {
        $supplierId = $this->supplier->getId();
        $appt = new Appointment();
        $appt->setTitle('Reunión pedido');
        $appt->setSupplier($this->supplier);
        $appt->setStartsAt(new \DateTimeImmutable('+5 days 09:30'));
        $appt->setStatus(AppointmentStatus::PENDING);
        $this->em->persist($appt);
        $this->em->flush();
        $this->em->clear();

        $this->client->request('GET', '/proveedores/'.$supplierId);
        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Reunión pedido', (string) $this->client->getResponse()->getContent());
    }

    public function testCalendarShowsAppointment(): void
    {
        $appt = new Appointment();
        $appt->setTitle('Cita en calendario');
        $appt->setSupplier($this->supplier);
        $appt->setStartsAt(new \DateTimeImmutable('2027-05-12 10:00'));
        $this->em->persist($appt);
        $this->em->flush();

        $this->client->request('GET', '/citas?view=calendar&year=2027&month=5');
        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Cita en calendario', (string) $this->client->getResponse()->getContent());
    }

    public function testMarkDoneFromList(): void
    {
        $appt = new Appointment();
        $appt->setTitle('Cita a realizar');
        $appt->setSupplier($this->supplier);
        $appt->setStartsAt(new \DateTimeImmutable('+2 days 12:00'));
        $appt->setStatus(AppointmentStatus::PENDING);
        $this->em->persist($appt);
        $this->em->flush();
        $apptId = $appt->getId();

        $this->client->request('GET', '/citas');
        $this->client->submitForm('Realizada');

        $this->assertResponseRedirects();
        $fresh = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Appointment::class)->find($apptId);
        $this->assertSame(AppointmentStatus::DONE, $fresh->getStatus());
    }
}
