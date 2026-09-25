<?php

namespace App\Tests\Functional;

use App\Entity\Invoice;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PaymentTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $invoiceId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())->setEmail('tester@anuska.local')->setFullName('Tester')->setRoles(['ROLE_USER']);
        $user->setPassword('x');
        $this->em->persist($user);

        $supplier = new Supplier();
        $supplier->setBrandName('Proveedor Pagos');
        $this->em->persist($supplier);

        $invoice = new Invoice();
        $invoice->setSupplier($supplier);
        $invoice->setNumber('FAC-PAGO-1');
        $invoice->setIssuedAt(new \DateTimeImmutable());
        $invoice->setBaseAmount('100.00');
        $invoice->setVatRate('21.00');
        $invoice->setVatAmount('21.00');
        $invoice->setTotal('121.00');
        $invoice->setStatus(InvoiceStatus::PENDING);
        $this->em->persist($invoice);

        $this->em->flush();
        $this->invoiceId = $invoice->getId();
        $this->client->loginUser($user);
    }

    private function addPayment(string $amount): void
    {
        $crawler = $this->client->request('GET', '/facturas/'.$this->invoiceId);
        $form = $crawler->filter('form[action$="/pagos"]')->form();
        $form['amount'] = $amount;
        $this->client->submit($form);
    }

    private function freshInvoice(): Invoice
    {
        return static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Invoice::class)->find($this->invoiceId);
    }

    public function testPartialPaymentKeepsPending(): void
    {
        $this->addPayment('50');
        $this->assertResponseRedirects();

        $invoice = $this->freshInvoice();
        $this->assertSame('50.00', $invoice->getPaidAmount());
        $this->assertSame('71.00', $invoice->getPendingAmount());
        $this->assertSame(InvoiceStatus::PENDING, $invoice->getStatus());
    }

    public function testFullPaymentMarksPaid(): void
    {
        $this->addPayment('121');
        $this->assertResponseRedirects();

        $invoice = $this->freshInvoice();
        $this->assertSame('0.00', $invoice->getPendingAmount());
        $this->assertTrue($invoice->isFullyPaid());
        $this->assertSame(InvoiceStatus::PAID, $invoice->getStatus());
        $this->assertNotNull($invoice->getPaidAt());
    }
}
