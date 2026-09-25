<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Supplier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CategoryTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function login(string $role): User
    {
        $user = new User();
        $user->setEmail('tester@anuska.local');
        $user->setFullName('Tester');
        $user->setRoles([$role]);
        $user->setPassword('x');
        $this->em->persist($user);
        $this->em->flush();
        $this->client->loginUser($user);

        return $user;
    }

    public function testAdminCreatesCategory(): void
    {
        $this->login('ROLE_ADMIN');

        $this->client->request('GET', '/configuracion/categorias');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Añadir', ['category[name]' => 'Sombreros']);
        $this->assertResponseRedirects();

        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Sombreros']);
        $this->assertNotNull($category);
    }

    public function testFilterSuppliersByCategory(): void
    {
        $this->login('ROLE_USER');

        $cat = (new Category())->setName('Punto');
        $this->em->persist($cat);

        $withCat = new Supplier();
        $withCat->setBrandName('Marca Con Punto');
        $withCat->addCategory($cat);
        $this->em->persist($withCat);

        $without = new Supplier();
        $without->setBrandName('Marca Sin Categoria');
        $this->em->persist($without);

        $this->em->flush();

        $this->client->request('GET', '/proveedores?category='.$cat->getId());
        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Marca Con Punto', $content);
        self::assertStringNotContainsString('Marca Sin Categoria', $content);
    }

    public function testNonAdminCannotManageCategories(): void
    {
        $this->login('ROLE_USER');
        $this->client->request('GET', '/configuracion/categorias');
        $this->assertResponseStatusCodeSame(403);
    }
}
