<?php

namespace App\Tests\Functional;

use App\Entity\Invoice;
use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SearchTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

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

        $supplier = new Supplier();
        $supplier->setBrandName('Marca Buscable');
        $this->em->persist($supplier);

        $order = new PurchaseOrder();
        $order->setSupplier($supplier);
        $order->setNumber('PED-XYZ-1');
        $order->setOrderedAt(new \DateTimeImmutable());
        $this->em->persist($order);

        $invoice = new Invoice();
        $invoice->setSupplier($supplier);
        $invoice->setNumber('FAC-XYZ-1');
        $invoice->setIssuedAt(new \DateTimeImmutable());
        $invoice->setBaseAmount('100.00');
        $invoice->setVatRate('21.00');
        $this->em->persist($invoice);

        $this->em->flush();
        $this->client->loginUser($user);
    }

    public function testFindsSupplier(): void
    {
        $this->client->request('GET', '/buscar?q=Buscable');
        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('h3', 'Proveedores');
        self::assertStringContainsString('Marca Buscable', (string) $this->client->getResponse()->getContent());
    }

    public function testFindsOrderAndInvoiceByNumber(): void
    {
        $this->client->request('GET', '/buscar?q=XYZ');
        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('PED-XYZ-1', $content);
        self::assertStringContainsString('FAC-XYZ-1', $content);
    }

    public function testShortQueryPrompts(): void
    {
        $this->client->request('GET', '/buscar?q=a');
        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('al menos 2 caracteres', (string) $this->client->getResponse()->getContent());
    }
}
