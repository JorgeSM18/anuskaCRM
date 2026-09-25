<?php

namespace App\Tests\Functional;

use App\Entity\Document;
use App\Entity\Supplier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DocumentTest extends WebTestCase
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
        $this->supplier->setBrandName('Proveedor Docs');
        $this->em->persist($this->supplier);
        $this->em->flush();

        $this->client->loginUser($user);
    }

    private function tempFile(string $name, string $content): string
    {
        // Subdirectorio único para conservar el nombre de archivo limpio
        // (BrowserKit usa el basename como nombre original subido).
        $dir = sys_get_temp_dir().'/anuska_test_'.uniqid();
        mkdir($dir);
        $path = $dir.'/'.$name;
        file_put_contents($path, $content);

        return $path;
    }

    public function testUploadDownloadDelete(): void
    {
        $pdf = $this->tempFile('factura.pdf', '%PDF-1.4 contenido de prueba');

        $crawler = $this->client->request('GET', '/proveedores/'.$this->supplier->getId().'/ficha/documentos');
        $form = $crawler->filter('form[action$="/documentos/subir"]')->form();
        $form['title'] = 'Factura enero';
        $form['file']->upload($pdf);
        $this->client->submit($form);

        $this->assertResponseRedirects();

        $doc = $this->em->getRepository(Document::class)->findOneBy(['originalName' => 'factura.pdf']);
        $this->assertNotNull($doc);
        $this->assertSame('Factura enero', $doc->getTitle());
        $this->assertSame($this->supplier->getId(), $doc->getSupplier()?->getId());
        $this->assertSame('Tester', $doc->getUploadedBy()?->getFullName());
        $this->assertStringEndsWith('.pdf', $doc->getStoragePath());

        // Descarga: PDF se sirve inline
        $this->client->request('GET', '/documentos/'.$doc->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));

        // Borrado: elimina fila y archivo
        $docId = $doc->getId();
        $crawler = $this->client->request('GET', '/proveedores/'.$this->supplier->getId().'/ficha/documentos');
        $deleteForm = $crawler->filter('form[action$="/'.$docId.'/borrar"]')->form();
        $this->client->submit($deleteForm);
        $this->assertResponseRedirects();

        $gone = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Document::class)->find($docId);
        $this->assertNull($gone);

        @unlink($pdf);
    }

    public function testRejectsDisallowedExtension(): void
    {
        $exe = $this->tempFile('malware.exe', 'MZ binario');

        $crawler = $this->client->request('GET', '/proveedores/'.$this->supplier->getId().'/ficha/documentos');
        $form = $crawler->filter('form[action$="/documentos/subir"]')->form();
        $form['file']->upload($exe);
        $this->client->submit($form);

        $this->assertResponseRedirects();
        $count = $this->em->getRepository(Document::class)->count([]);
        $this->assertSame(0, $count, 'No debe crearse ningún documento para una extensión no permitida.');

        @unlink($exe);
    }

    public function testRejectsContentNotMatchingExtension(): void
    {
        // HTML con script disfrazado de PDF: la extensión pasa, pero el MIME real no.
        $fake = $this->tempFile('factura.pdf', '<html><script>alert(1)</script></html>');

        $crawler = $this->client->request('GET', '/proveedores/'.$this->supplier->getId().'/ficha/documentos');
        $form = $crawler->filter('form[action$="/documentos/subir"]')->form();
        $form['file']->upload($fake);
        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->assertSame(0, $this->em->getRepository(Document::class)->count([]), 'Un HTML renombrado a .pdf debe rechazarse.');

        @unlink($fake);
    }

    public function testDeleteRejectedWithoutCsrfToken(): void
    {
        $doc = (new Document())
            ->setOriginalName('x.pdf')->setStoragePath('2026/01/x.pdf')
            ->setMimeType('application/pdf')->setSizeBytes(1)->setSupplier($this->supplier);
        $this->em->persist($doc);
        $this->em->flush();

        // POST de borrado sin _token: debe rechazarse (403), el documento sobrevive.
        $this->client->request('POST', '/documentos/'.$doc->getId().'/borrar');

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->em->getRepository(Document::class)->find($doc->getId()));
    }
}
