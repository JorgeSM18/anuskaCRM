<?php

namespace App\Tests\Functional;

use App\Entity\Communication;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\CommunicationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CommunicationTest extends WebTestCase
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
        $this->supplier->setBrandName('Proveedor Comms');
        $this->em->persist($this->supplier);
        $this->em->flush();

        $this->client->loginUser($user);
    }

    public function testCreateCommunication(): void
    {
        $this->client->request('GET', '/proveedores/'.$this->supplier->getId().'/comunicaciones/nueva');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Guardar', [
            'communication[type]' => CommunicationType::PHONE->value,
            'communication[occurredAt]' => '2027-03-01T10:30',
            'communication[subject]' => 'Llamada confirmación pedido',
            'communication[pendingReply]' => '1',
        ]);

        $this->assertResponseRedirects();

        $comm = $this->em->getRepository(Communication::class)->findOneBy(['subject' => 'Llamada confirmación pedido']);
        $this->assertNotNull($comm);
        $this->assertSame(CommunicationType::PHONE, $comm->getType());
        $this->assertTrue($comm->isPendingReply());
        $this->assertNotNull($comm->getCreatedAt());
    }

    public function testTabShowsCommunicationAndLastContact(): void
    {
        $supplierId = $this->supplier->getId();
        $comm = new Communication();
        $comm->setSupplier($this->supplier);
        $comm->setSubject('Email colección invierno');
        $comm->setType(CommunicationType::EMAIL);
        $comm->setOccurredAt(new \DateTimeImmutable('2027-02-10 09:00'));
        $this->em->persist($comm);
        $this->em->flush();
        $this->em->clear();

        // Pestaña comunicaciones lista la comunicación
        $this->client->request('GET', '/proveedores/'.$supplierId.'/ficha/comunicaciones');
        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Email colección invierno', (string) $this->client->getResponse()->getContent());

        // La tarjeta "Último contacto" del resumen muestra la fecha
        $this->client->request('GET', '/proveedores/'.$supplierId);
        self::assertStringContainsString('10/02/2027', (string) $this->client->getResponse()->getContent());
    }

    public function testGlobalIndexPendingFilter(): void
    {
        $comm = new Communication();
        $comm->setSupplier($this->supplier);
        $comm->setSubject('Pendiente de contestar');
        $comm->setType(CommunicationType::EMAIL);
        $comm->setOccurredAt(new \DateTimeImmutable());
        $comm->setPendingReply(true);
        $this->em->persist($comm);
        $this->em->flush();

        $this->client->request('GET', '/comunicaciones?pending=1');
        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Pendiente de contestar', (string) $this->client->getResponse()->getContent());
    }
}
