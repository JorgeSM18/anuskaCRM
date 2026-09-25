<?php

namespace App\Controller;

use App\Entity\Communication;
use App\Entity\Supplier;
use App\Enum\CommunicationType as CommType;
use App\Form\CommunicationType;
use App\Repository\CommunicationRepository;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CommunicationController extends AbstractController
{
    #[Route('/comunicaciones', name: 'communication_index', methods: ['GET'])]
    public function index(Request $request, CommunicationRepository $comms, SupplierRepository $suppliers): Response
    {
        $type = CommType::tryFrom((string) $request->query->get('type', ''));
        $pendingOnly = $request->query->getBoolean('pending');
        $supplierId = $request->query->getInt('supplier');
        $supplier = $supplierId > 0 ? $suppliers->find($supplierId) : null;
        $page = $request->query->getInt('page', 1);

        $paginator = $comms->findForIndex($supplier, $type, $pendingOnly, $page);
        $total = \count($paginator);

        return $this->render('communication/index.html.twig', [
            'communications' => $paginator,
            'total' => $total,
            'page' => max(1, $page),
            'pages' => (int) ceil($total / $comms->perPage()),
            'type' => $type,
            'pendingOnly' => $pendingOnly,
            'supplier' => $supplier,
        ]);
    }

    #[Route('/proveedores/{id}/comunicaciones/nueva', name: 'communication_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new(Supplier $supplier, Request $request, CommunicationRepository $comms): Response
    {
        $communication = new Communication();
        $communication->setSupplier($supplier);
        $communication->setOccurredAt(new \DateTimeImmutable());

        $form = $this->createForm(CommunicationType::class, $communication, ['supplier' => $supplier]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comms->save($communication);
            $this->addFlash('success', 'Comunicación registrada.');

            return $this->redirectToRoute('supplier_tab', ['id' => $supplier->getId(), 'tab' => 'comunicaciones']);
        }

        return $this->render('communication/form.html.twig', [
            'form' => $form,
            'supplier' => $supplier,
            'is_new' => true,
        ]);
    }

    #[Route('/comunicaciones/{id}/editar', name: 'communication_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Communication $communication, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CommunicationType::class, $communication, ['supplier' => $communication->getSupplier()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Comunicación actualizada.');

            return $this->redirectToRoute('supplier_tab', ['id' => $communication->getSupplier()->getId(), 'tab' => 'comunicaciones']);
        }

        return $this->render('communication/form.html.twig', [
            'form' => $form,
            'supplier' => $communication->getSupplier(),
            'is_new' => false,
        ]);
    }

    #[Route('/comunicaciones/{id}/resuelta', name: 'communication_resolve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resolve(Communication $communication, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('resolve'.$communication->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $communication->setPendingReply(false);
        $em->flush();
        $this->addFlash('success', 'Marcada como respondida.');

        return $this->redirectToRoute('supplier_tab', ['id' => $communication->getSupplier()->getId(), 'tab' => 'comunicaciones']);
    }
}
