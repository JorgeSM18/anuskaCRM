<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/configuracion/categorias')]
#[IsGranted('ROLE_ADMIN')]
class CategoryController extends AbstractController
{
    #[Route('', name: 'category_index', methods: ['GET', 'POST'])]
    public function index(Request $request, CategoryRepository $categories): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $categories->save($category);
            $this->addFlash('success', 'Categoría creada.');

            return $this->redirectToRoute('category_index');
        }

        return $this->render('config/category/index.html.twig', [
            'categories' => $categories->findAllOrdered(),
            'form' => $form,
        ]);
    }

    #[Route('/{id}/editar', name: 'category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Category $category, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Categoría actualizada.');

            return $this->redirectToRoute('category_index');
        }

        return $this->render('config/category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/borrar', name: 'category_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Category $category, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_category'.$category->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // Las filas de la tabla de enlace se borran en cascada (JoinColumn onDelete CASCADE).
        $em->remove($category);
        $em->flush();
        $this->addFlash('success', 'Categoría eliminada.');

        return $this->redirectToRoute('category_index');
    }
}
