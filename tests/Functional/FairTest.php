<?php

namespace App\Tests\Functional;

use App\Entity\Fair;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\FairStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FairTest extends WebTestCase
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
        $this->em->flush();
        $this->client->loginUser($user);
    }

    public function testCreateFair(): void
    {
        $this->client->request('GET', '/ferias/nueva');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Guardar', [
            'fair[name]' => 'Feria de Prueba',
            'fair[status]' => FairStatus::CONFIRMED->value,
            'fair[city]' => 'Madrid',
            'fair[startsAt]' => '2027-03-01',
        ]);

        $this->assertResponseRedirects();
        $fair = $this->em->getRepository(Fair::class)->findOneBy(['name' => 'Feria de Prueba']);
        $this->assertNotNull($fair);
        $this->assertSame(FairStatus::CONFIRMED, $fair->getStatus());
    }

    public function testAddParticipant(): void
    {
        $supplier = new Supplier();
        $supplier->setBrandName('Marca Feriante');
        $this->em->persist($supplier);

        $fair = new Fair();
        $fair->setName('Feria con Proveedores');
        $fair->setStartsAt(new \DateTimeImmutable('2027-04-01'));
        $this->em->persist($fair);
        $this->em->flush();
        $fairId = $fair->getId();

        $crawler = $this->client->request('GET', '/ferias/'.$fairId);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/participantes"]')->form();
        $form['supplier'] = (string) $supplier->getId();
        $this->client->submit($form);

        $this->assertResponseRedirects();

        $fresh = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Fair::class)->find($fairId);
        $this->assertCount(1, $fresh->getParticipations());
        $this->assertSame('Marca Feriante', $fresh->getParticipations()->first()->getSupplier()->getBrandName());
    }

    public function testIndexLoads(): void
    {
        $this->client->request('GET', '/ferias');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Ferias');
    }
}
