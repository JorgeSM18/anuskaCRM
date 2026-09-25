<?php

namespace App\Tests\Functional;

use App\Entity\Invoice;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InvoiceTest extends WebTestCase
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
        $this->supplier->setBrandName('Proveedor Facturas');
        $this->em->persist($this->supplier);
        $this->em->flush();

        $this->client->loginUser($user);
    }

    public function testCreateInvoiceComputesTotals(): void
    {
        $this->client->request('GET', '/proveedores/'.$this->supplier->getId().'/facturas/nueva');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Guardar', [
            'invoice[number]' => 'FAC-2027-001',
            'invoice[issuedAt]' => '2027-01-20',
            'invoice[baseAmount]' => '1000',
            'invoice[vatRate]' => '21',
            'invoice[status]' => InvoiceStatus::PENDING->value,
        ]);

        $this->assertResponseRedirects();

        $invoice = $this->em->getRepository(Invoice::class)->findOneBy(['number' => 'FAC-2027-001']);
        $this->assertNotNull($invoice);
        // Comparación numérica: SQLite devuelve el decimal sin ceros de escala
        // ('210' en vez de '210.00'); lo que importa es el valor.
        $this->assertEquals(210.00, (float) $invoice->getVatAmount());
        $this->assertEquals(1210.00, (float) $invoice->getTotal());
    }

    public function testOverdueIsDerived(): void
    {
        $invoice = new Invoice();
        $invoice->setSupplier($this->supplier);
        $invoice->setNumber('FAC-VENCIDA');
        $invoice->setIssuedAt(new \DateTimeImmutable('-40 days'));
        $invoice->setDueAt(new \DateTimeImmutable('-10 days'));
        $invoice->setBaseAmount('100.00');
        $invoice->setVatRate('21.00');
        $invoice->setStatus(InvoiceStatus::PENDING);

        $this->assertTrue($invoice->isOverdue(), 'Pendiente y fuera de plazo debe ser vencida.');

        $invoice->setStatus(InvoiceStatus::PAID);
        $this->assertFalse($invoice->isOverdue(), 'Pagada nunca es vencida.');
    }

    public function testGlobalIndexLoads(): void
    {
        $this->client->request('GET', '/facturas');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Facturas');
    }
}
