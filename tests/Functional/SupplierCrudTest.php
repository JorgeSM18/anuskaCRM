<?php

namespace App\Tests\Functional;

use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\SupplierStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SupplierCrudTest extends WebTestCase
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
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword('x'); // el login se simula, no se verifica
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);
    }

    public function testIndexLoads(): void
    {
        $this->client->request('GET', '/proveedores');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Proveedores');
    }

    public function testCreateSupplier(): void
    {
        $this->client->request('GET', '/proveedores/nuevo');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Guardar', [
            'supplier[brandName]' => 'Marca de Prueba',
            'supplier[status]' => SupplierStatus::ACTIVE->value,
            'supplier[email]' => 'info@marcaprueba.com',
        ]);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Marca de Prueba');

        $saved = $this->em->getRepository(Supplier::class)->findOneBy(['brandName' => 'Marca de Prueba']);
        $this->assertNotNull($saved);
        $this->assertNotNull($saved->getCreatedAt(), 'La auditoría debe rellenar createdAt.');
        $this->assertSame('Tester', $saved->getCreatedBy()?->getFullName(), 'createdBy debe ser el usuario logueado.');
    }

    public function testFichaResumenTab(): void
    {
        $supplier = new Supplier();
        $supplier->setBrandName('Marca Ficha');
        $this->em->persist($supplier);
        $this->em->flush();

        $this->client->request('GET', '/proveedores/'.$supplier->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.tab.is-active', 'Resumen');
        $this->assertAnySelectorTextContains('h3', 'Datos');
        $this->assertAnySelectorTextContains('.stat-card__label', 'Contactos');
    }

    public function testFichaDocumentosTabRenders(): void
    {
        $supplier = new Supplier();
        $supplier->setBrandName('Marca Pestañas');
        $this->em->persist($supplier);
        $this->em->flush();

        $this->client->request('GET', '/proveedores/'.$supplier->getId().'/ficha/documentos');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.tab.is-active', 'Documentos');
        $this->assertAnySelectorTextContains('h3', 'Documentos');
    }

    public function testAddContactBecomesPrimary(): void
    {
        $supplier = new Supplier();
        $supplier->setBrandName('Marca Con Contactos');
        $this->em->persist($supplier);
        $this->em->flush();
        $supplierId = $supplier->getId();

        $this->client->request('GET', \sprintf('/proveedores/%d/contactos/nuevo', $supplierId));
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Guardar', [
            'contact[firstName]' => 'Laura',
            'contact[lastName]' => 'García',
            'contact[role]' => 'sales_rep',
        ]);

        $this->assertResponseRedirects();

        // El kernel se reinicia tras la petición: recargamos fresco por id.
        $fresh = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Supplier::class)->find($supplierId);
        $this->assertNotNull($fresh);
        $this->assertCount(1, $fresh->getContacts());
        $this->assertNotNull($fresh->getPrimaryContact(), 'El primer contacto debe pasar a comercial principal.');
        $this->assertSame('Laura García', $fresh->getPrimaryContact()->getFullName());
    }
}
