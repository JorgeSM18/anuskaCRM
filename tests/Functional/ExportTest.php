<?php

namespace App\Tests\Functional;

use App\Entity\Invoice;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExportTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())->setEmail('tester@anuska.local')->setFullName('Tester')->setRoles(['ROLE_USER']);
        $user->setPassword('x');
        $this->em->persist($user);

        $supplier = new Supplier();
        $supplier->setBrandName('Marca Exportable');
        $this->em->persist($supplier);

        $invoice = new Invoice();
        $invoice->setSupplier($supplier);
        $invoice->setNumber('FAC-EXPORT-1');
        $invoice->setIssuedAt(new \DateTimeImmutable());
        $invoice->setBaseAmount('100.00');
        $invoice->setVatRate('21.00');
        $invoice->setTotal('121.00');
        $invoice->setStatus(InvoiceStatus::PENDING);
        $this->em->persist($invoice);

        $this->em->flush();
        $this->client->loginUser($user);
    }

    public function testSupplierExportCsv(): void
    {
        $this->client->request('GET', '/proveedores/exportar');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));

        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('Marca Exportable', $csv);
        self::assertStringContainsString('Marca', $csv); // fila de cabeceras
    }

    public function testInvoiceExportCsv(): void
    {
        $this->client->request('GET', '/facturas/exportar');

        $this->assertResponseIsSuccessful();
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('FAC-EXPORT-1', $csv);
        self::assertStringContainsString('121', $csv);
    }
}
