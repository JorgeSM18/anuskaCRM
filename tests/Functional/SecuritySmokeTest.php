<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecuritySmokeTest extends WebTestCase
{
    public function testAuthenticatedUserOnLoginRedirectsToDashboard(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())->setEmail('log@anuska.local')->setFullName('Log')->setRoles(['ROLE_USER']);
        $user->setPassword('x');
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/login');

        $this->assertResponseRedirects('/');
    }

    public function testHomeRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseRedirects('/login');
    }

    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('button[type="submit"]', 'Entrar');
        $this->assertCount(1, $crawler->filter('input[name="_username"]'));
        $this->assertCount(1, $crawler->filter('input[name="_password"]'));
    }

    public function testAdminAreaRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/usuarios');

        $this->assertResponseRedirects('/login');
    }

    public function testRoleUserIsForbiddenFromAdmin(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())->setEmail('user@anuska.local')->setFullName('User')->setRoles(['ROLE_USER']);
        $user->setPassword('x');
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/admin/usuarios');

        // Autenticado pero sin ROLE_ADMIN: 403, no redirección a login.
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnonymousCannotDownloadDocument(): void
    {
        $client = static::createClient();
        $client->request('GET', '/documentos/1');

        // El firewall corta antes del controller aunque el documento no exista.
        $this->assertResponseRedirects('/login');
    }
}
