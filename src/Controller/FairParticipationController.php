<?php

namespace App\Controller;

use App\Entity\Fair;
use App\Entity\FairParticipation;
use App\Entity\Supplier;
use App\Form\FairParticipationType;
use App\Repository\FairParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FairParticipationController extends AbstractController
{
    #[Route('/ferias/{id}/participantes', name: 'fair_participation_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function add(Fair $fair, Request $request, EntityManagerInterface $em, FairParticipationRepository $participations): Response
    {
        if (!$this->isCsrfTokenValid('add_participation'.$fair->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $supplier = $em->getRepository(Supplier::class)->find($request->request->getInt('supplier'));
        if (!$supplier instanceof Supplier) {
            $this->addFlash('error', 'Selecciona un proveedor.');

            return $this->redirectToRoute('fair_show', ['id' => $fair->getId()]);
        }

        $exists = $participations->findOneBy(['fair' => $fair, 'supplier' => $supplier]);
        if (null === $exists) {
            $participation = (new FairParticipation())->setFair($fair)->setSupplier($supplier);
            $participations->save($participation);
            $this->addFlash('success', \sprintf('%s añadido a la feria.', $supplier->getBrandName()));
        }

        return $this->redirectToRoute('fair_show', ['id' => $fair->getId()]);
    }

    #[Route('/participaciones/{id}/editar', name: 'fair_participation_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(FairParticipation $participation, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FairParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Notas de la participación guardadas.');

            return $this->redirectToRoute('fair_show', ['id' => $participation->getFair()->getId()]);
        }

        return $this->render('fair/participation_edit.html.twig', [
            'form' => $form,
            'participation' => $participation,
        ]);
    }

    #[Route('/participaciones/{id}/borrar', name: 'fair_participation_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(FairParticipation $participation, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_participation'.$participation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $fairId = $participation->getFair()->getId();
        $em->remove($participation);
        $em->flush();
        $this->addFlash('success', 'Proveedor quitado de la feria.');

        return $this->redirectToRoute('fair_show', ['id' => $fairId]);
    }
}
