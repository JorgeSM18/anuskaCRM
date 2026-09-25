<?php

namespace App\Controller;

use App\Entity\Communication;
use App\Entity\Document;
use App\Entity\Fair;
use App\Entity\Invoice;
use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Entity\User;
use App\Service\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DocumentController extends AbstractController
{
    #[Route('/documentos/subir', name: 'document_upload', methods: ['POST'])]
    public function upload(Request $request, DocumentStorage $storage, EntityManagerInterface $em): Response
    {
        $ownerType = (string) $request->request->get('ownerType');
        $ownerId = $request->request->getInt('ownerId');

        if (!$this->isCsrfTokenValid('upload'.$ownerType.$ownerId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $owner = $this->resolveOwner($em, $ownerType, $ownerId);
        if (null === $owner) {
            throw $this->createNotFoundException();
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Selecciona un archivo.');

            return $this->redirect($this->ownerUrl($ownerType, $owner));
        }

        try {
            $document = $storage->store($file);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirect($this->ownerUrl($ownerType, $owner));
        }

        $document->setTitle(trim((string) $request->request->get('title')) ?: null);
        $user = $this->getUser();
        $document->setUploadedBy($user instanceof User ? $user : null);
        $this->assignOwner($document, $owner);

        $em->persist($document);
        $em->flush();
        $this->addFlash('success', 'Documento subido.');

        return $this->redirect($this->ownerUrl($ownerType, $owner));
    }

    #[Route('/documentos/{id}', name: 'document_download', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function download(Document $document, DocumentStorage $storage): Response
    {
        $path = $storage->absolutePath($document);
        if (!is_file($path)) {
            throw $this->createNotFoundException('El archivo ya no está disponible.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $document->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(
            $document->isInlineViewable() ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $document->getOriginalName(),
        );

        return $response;
    }

    #[Route('/documentos/{id}/borrar', name: 'document_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Document $document, Request $request, DocumentStorage $storage, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('doc_delete'.$document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $back = $this->documentOwnerUrl($document);
        $storage->remove($document);
        $em->remove($document);
        $em->flush();
        $this->addFlash('success', 'Documento eliminado.');

        return $this->redirect($back);
    }

    private function resolveOwner(EntityManagerInterface $em, string $ownerType, int $ownerId): ?object
    {
        $class = match ($ownerType) {
            'supplier' => Supplier::class,
            'order' => PurchaseOrder::class,
            'invoice' => Invoice::class,
            'communication' => Communication::class,
            'fair' => Fair::class,
            default => null,
        };

        return null === $class ? null : $em->getRepository($class)->find($ownerId);
    }

    private function assignOwner(Document $document, object $owner): void
    {
        match (true) {
            $owner instanceof Supplier => $document->setSupplier($owner),
            $owner instanceof PurchaseOrder => $document->setPurchaseOrder($owner),
            $owner instanceof Invoice => $document->setInvoice($owner),
            $owner instanceof Communication => $document->setCommunication($owner),
            $owner instanceof Fair => $document->setFair($owner),
            default => throw new \LogicException('Propietario de documento no soportado.'),
        };
    }

    private function ownerUrl(string $ownerType, object $owner): string
    {
        return match (true) {
            $owner instanceof Supplier => $this->generateUrl('supplier_tab', ['id' => $owner->getId(), 'tab' => 'documentos']),
            $owner instanceof PurchaseOrder => $this->generateUrl('purchase_order_show', ['id' => $owner->getId()]),
            $owner instanceof Invoice => $this->generateUrl('invoice_show', ['id' => $owner->getId()]),
            $owner instanceof Communication => $this->generateUrl('supplier_tab', ['id' => $owner->getSupplier()->getId(), 'tab' => 'comunicaciones']),
            $owner instanceof Fair => $this->generateUrl('fair_show', ['id' => $owner->getId()]),
            default => $this->generateUrl('dashboard'),
        };
    }

    private function documentOwnerUrl(Document $document): string
    {
        return match (true) {
            null !== $document->getSupplier() => $this->generateUrl('supplier_tab', ['id' => $document->getSupplier()->getId(), 'tab' => 'documentos']),
            null !== $document->getPurchaseOrder() => $this->generateUrl('purchase_order_show', ['id' => $document->getPurchaseOrder()->getId()]),
            null !== $document->getInvoice() => $this->generateUrl('invoice_show', ['id' => $document->getInvoice()->getId()]),
            null !== $document->getFair() => $this->generateUrl('fair_show', ['id' => $document->getFair()->getId()]),
            default => $this->generateUrl('dashboard'),
        };
    }
}
