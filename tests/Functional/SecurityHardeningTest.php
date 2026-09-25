<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityHardeningTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testSecurityHeadersPresent(): void
    {
        $this->client->request('GET', '/login');
        $headers = $this->client->getResponse()->headers;

        $this->assertSame('DENY', $headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertSame('same-origin', $headers->get('Referrer-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $headers->get('Content-Security-Policy'));
    }

    public function testLoginThrottlingBlocksBruteForce(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Email único por ejecución: clave de throttling siempre fresca.
        $email = 'brute_'.uniqid().'@anuska.local';
        $user = (new User())->setEmail($email)->setFullName('Bruto')->setRoles(['ROLE_USER']);
        $user->setPassword($hasher->hashPassword($user, 'anuska1234'));
        $em->persist($user);
        $em->flush();

        // 5 intentos con contraseña incorrecta.
        for ($i = 0; $i < 5; ++$i) {
            $this->submitLogin($email, 'incorrecta');
        }

        // 6º intento con la contraseña CORRECTA: debe seguir bloqueado por el throttling.
        $this->submitLogin($email, 'anuska1234');
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotSame('/', $location, 'El throttling debería impedir el acceso aun con credenciales correctas.');

        $this->client->followRedirect();
        self::assertMatchesRegularExpression(
            '/intento|Too many|throttl/i',
            (string) $this->client->getResponse()->getContent(),
            'La página de login debería mostrar el aviso de demasiados intentos.',
        );
    }

    private function submitLogin(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Entrar')->form([
            '_username' => $email,
            '_password' => $password,
        ]);
        $this->client->submit($form);
    }
}
