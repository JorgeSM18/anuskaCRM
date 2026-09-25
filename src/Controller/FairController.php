<?php

namespace App\Controller;

use App\Entity\Fair;
use App\Enum\FairStatus;
use App\Enum\SupplierStatus;
use App\Form\FairType;
use App\Repository\FairRepository;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FairController extends AbstractController
{
    #[Route('/ferias', name: 'fair_index', methods: ['GET'])]
    public function index(Request $request, FairRepository $fairs): Response
    {
        $status = FairStatus::tryFrom((string) $request->query->get('status', ''));
        $page = $request->query->getInt('page', 1);

        $paginator = $fairs->findForIndex($status, $page);
        $total = \count($paginator);

        return $this->render('fair/index.html.twig', [
            'fairs' => $paginator,
            'total' => $total,
            'page' => max(1, $page),
            'pages' => (int) ceil($total / $fairs->perPage()),
            'status' => $status,
        ]);
    }

    #[Route('/ferias/nueva', name: 'fair_new', methods: ['GET', 'POST'])]
    public function new(Request $request, FairRepository $fairs): Response
    {
        $fair = new Fair();
        $form = $this->createForm(FairType::class, $fair);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fairs->save($fair);
            $this->addFlash('success', 'Feria creada. Añade ahora los proveedores presentes.');

            return $this->redirectToRoute('fair_show', ['id' => $fair->getId()]);
        }

        return $this->render('fair/form.html.twig', ['form' => $form, 'is_new' => true]);
    }

    #[Route('/ferias/{id}', name: 'fair_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Fair $fair, SupplierRepository $suppliers): Response
    {
        $alreadyIn = [];
        foreach ($fair->getParticipations() as $participation) {
            $alreadyIn[] = $participation->getSupplier()->getId();
        }

        $available = array_filter(
            $suppliers->findBy(['status' => SupplierStatus::ACTIVE], ['brandName' => 'ASC']),
            fn ($s) => !\in_array($s->getId(), $alreadyIn, true),
        );

        return $this->render('fair/show.html.twig', [
            'fair' => $fair,
            'available_suppliers' => $available,
        ]);
    }

    #[Route('/ferias/{id}/editar', name: 'fair_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Fair $fair, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FairType::class, $fair);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Feria actualizada.');

            return $this->redirectToRoute('fair_show', ['id' => $fair->getId()]);
        }

        return $this->render('fair/form.html.twig', ['form' => $form, 'fair' => $fair, 'is_new' => false]);
    }

    #[Route('/ferias/{id}/borrar', name: 'fair_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Fair $fair, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_fair'.$fair->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($fair);
        $em->flush();
        $this->addFlash('success', 'Feria eliminada.');

        return $this->redirectToRoute('fair_index');
    }
}
