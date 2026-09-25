<?php

namespace App\Tests\Functional;

use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PurchaseOrderTest extends WebTestCase
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
        $this->supplier->setBrandName('Proveedor Pedidos');
        $this->em->persist($this->supplier);
        $this->em->flush();

        $this->client->loginUser($user);
    }

    public function testCreateOrderFromSupplier(): void
    {
        $this->client->request('GET', '/proveedores/'.$this->supplier->getId().'/pedidos/nuevo');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Guardar', [
            'purchase_order[number]' => 'PED-2027-001',
            'purchase_order[orderedAt]' => '2027-01-15',
            'purchase_order[status]' => OrderStatus::PLACED->value,
            'purchase_order[season]' => 'PV27',
            'purchase_order[estimatedAmount]' => '1500',
        ]);

        $this->assertResponseRedirects();

        $order = $this->em->getRepository(PurchaseOrder::class)->findOneBy(['number' => 'PED-2027-001']);
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::PLACED, $order->getStatus());
        $this->assertSame($this->supplier->getId(), $order->getSupplier()->getId());
        // Comparación numérica: SQLite devuelve el decimal sin ceros de escala.
        $this->assertEquals(1500.00, (float) $order->getEstimatedAmount());
        $this->assertNotNull($order->getCreatedAt(), 'La auditoría debe rellenarse.');
    }

    public function testOrderAppearsInSupplierTab(): void
    {
        $supplierId = $this->supplier->getId();
        $order = new PurchaseOrder();
        $order->setSupplier($this->supplier);
        $order->setNumber('PED-VISIBLE');
        $order->setOrderedAt(new \DateTimeImmutable('2027-02-01'));
        $this->em->persist($order);
        $this->em->flush();
        // Descartar el mapa de identidad para que la petición cargue fresco de la BD
        // (el lado inverso purchaseOrders no se mantiene en memoria).
        $this->em->clear();

        $this->client->request('GET', '/proveedores/'.$supplierId.'/ficha/pedidos');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.tab.is-active', 'Pedidos');
        self::assertStringContainsString('PED-VISIBLE', (string) $this->client->getResponse()->getContent());
    }

    public function testGlobalIndexLoads(): void
    {
        $this->client->request('GET', '/pedidos');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Pedidos');
    }
}
