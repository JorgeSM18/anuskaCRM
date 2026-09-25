<?php

namespace App\Tests\Functional;

use App\Entity\Appointment;
use App\Entity\Communication;
use App\Entity\Invoice;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use App\Enum\CommunicationType;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('tester@anuska.local');
        $user->setFullName('Tester Uno');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('x');
        $this->em->persist($user);

        $supplier = new Supplier();
        $supplier->setBrandName('Proveedor Panel');
        $this->em->persist($supplier);

        $appt = new Appointment();
        $appt->setTitle('Cita de hoy panel');
        $appt->setSupplier($supplier);
        $appt->setStartsAt(new \DateTimeImmutable('today 15:00'));
        $appt->setStatus(AppointmentStatus::PENDING);
        $this->em->persist($appt);

        $comm = new Communication();
        $comm->setSupplier($supplier);
        $comm->setSubject('Responder al proveedor panel');
        $comm->setType(CommunicationType::EMAIL);
        $comm->setOccurredAt(new \DateTimeImmutable('-1 day'));
        $comm->setPendingReply(true);
        $this->em->persist($comm);

        $invoice = new Invoice();
        $invoice->setSupplier($supplier);
        $invoice->setNumber('FAC-PANEL');
        $invoice->setIssuedAt(new \DateTimeImmutable('-2 days'));
        $invoice->setDueAt(new \DateTimeImmutable('+3 days'));
        $invoice->setBaseAmount('100.00');
        $invoice->setVatRate('21.00');
        $invoice->setTotal('121.00');
        $invoice->setStatus(InvoiceStatus::PENDING);
        $this->em->persist($invoice);

        $this->em->flush();
        $this->client->loginUser($user);
    }

    public function testDashboardLoadsWithBlocks(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Requiere tu atención', $content);
        self::assertStringContainsString('Gasto en pedidos', $content);
    }

    public function testShowsTodaysData(): void
    {
        $this->client->request('GET', '/');
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('Cita de hoy panel', $content);
        self::assertStringContainsString('Responder al proveedor panel', $content);
        self::assertStringContainsString('FAC-PANEL', $content);
    }

    public function testSummaryCounts(): void
    {
        $this->client->request('GET', '/');
        $this->assertAnySelectorTextContains('.stat-card__label', 'Importe pendiente');
        $this->assertAnySelectorTextContains('.stat-card__label', 'Pedidos abiertos');
    }
}
