<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Supplier;
use App\Enum\SupplierStatus;
use App\Form\SupplierType;
use App\Repository\CategoryRepository;
use App\Repository\SupplierRepository;
use App\Service\CsvExporter;
use App\Service\SupplierSummary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/proveedores')]
class SupplierController extends AbstractController
{
    #[Route('', name: 'supplier_index', methods: ['GET'])]
    public function index(Request $request, SupplierRepository $suppliers, CategoryRepository $categories): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $status = SupplierStatus::tryFrom((string) $request->query->get('status', ''));
        $categoryId = $request->query->getInt('category');
        $category = $categoryId > 0 ? $categories->find($categoryId) : null;
        $sort = (string) $request->query->get('sort', 'brandName');
        $dir = (string) $request->query->get('dir', 'asc');
        $page = $request->query->getInt('page', 1);

        $paginator = $suppliers->findForIndex($q ?: null, $status, $category, $sort, $dir, $page);
        $total = \count($paginator);

        return $this->render('supplier/index.html.twig', [
            'suppliers' => $paginator,
            'total' => $total,
            'page' => max(1, $page),
            'pages' => (int) ceil($total / $suppliers->perPage()),
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'dir' => $dir,
            'category' => $category,
            'allCategories' => $categories->findAllOrdered(),
        ]);
    }

    #[Route('/exportar', name: 'supplier_export', methods: ['GET'])]
    public function export(Request $request, SupplierRepository $suppliers, CategoryRepository $categories, CsvExporter $csv): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $status = SupplierStatus::tryFrom((string) $request->query->get('status', ''));
        $categoryId = $request->query->getInt('category');
        $category = $categoryId > 0 ? $categories->find($categoryId) : null;

        $rows = [];
        foreach ($suppliers->findAllFiltered($q ?: null, $status, $category) as $s) {
            $cats = [];
            foreach ($s->getCategories() as $c) {
                $cats[] = $c->getName();
            }
            $rows[] = [
                $s->getBrandName(),
                $s->getLegalName(),
                $s->getTaxId(),
                $s->getStatus()->label(),
                $s->getPrimaryContact()?->getFullName(),
                $s->getPhone(),
                $s->getEmail(),
                $s->getWebsite(),
                implode(', ', $cats),
            ];
        }

        return $csv->stream('proveedores.csv',
            ['Marca', 'Nombre fiscal', 'CIF/NIF', 'Estado', 'Comercial principal', 'Teléfono', 'Email', 'Web', 'Categorías'],
            $rows,
        );
    }

    #[Route('/nuevo', name: 'supplier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SupplierRepository $suppliers): Response
    {
        $supplier = new Supplier();
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $suppliers->save($supplier);
            $this->addFlash('success', 'Proveedor creado. Añade ahora sus contactos.');

            return $this->redirectToRoute('supplier_show', ['id' => $supplier->getId()]);
        }

        return $this->render('supplier/form.html.twig', ['form' => $form, 'supplier' => $supplier, 'is_new' => true]);
    }

    #[Route('/{id}', name: 'supplier_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Supplier $supplier, SupplierSummary $summary): Response
    {
        return $this->render('supplier/show.html.twig', [
            'supplier' => $supplier,
            'tab' => 'resumen',
            'stats' => $summary->for($supplier),
        ]);
    }

    #[Route('/{id}/ficha/{tab}', name: 'supplier_tab', methods: ['GET'], requirements: ['id' => '\d+', 'tab' => 'pedidos|facturas|comunicaciones|citas|documentos'])]
    public function tab(Supplier $supplier, string $tab, SupplierSummary $summary): Response
    {
        return $this->render('supplier/show.html.twig', [
            'supplier' => $supplier,
            'tab' => $tab,
            'stats' => $summary->for($supplier),
        ]);
    }

    #[Route('/{id}/editar', name: 'supplier_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Supplier $supplier, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Proveedor actualizado.');

            return $this->redirectToRoute('supplier_show', ['id' => $supplier->getId()]);
        }

        return $this->render('supplier/form.html.twig', ['form' => $form, 'supplier' => $supplier, 'is_new' => false]);
    }

    #[Route('/{id}/estado', name: 'supplier_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Supplier $supplier, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('toggle'.$supplier->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $supplier->setStatus($supplier->isActive() ? SupplierStatus::INACTIVE : SupplierStatus::ACTIVE);
        $em->flush();
        $this->addFlash('success', $supplier->isActive() ? 'Proveedor activado.' : 'Proveedor desactivado.');

        return $this->redirectToRoute('supplier_show', ['id' => $supplier->getId()]);
    }

    #[Route('/{id}/comercial/{contactId}', name: 'supplier_set_primary', methods: ['POST'], requirements: ['id' => '\d+', 'contactId' => '\d+'])]
    public function setPrimaryContact(Supplier $supplier, int $contactId, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('primary'.$supplier->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $contact = $em->getRepository(Contact::class)->find($contactId);
        if (!$contact || $contact->getSupplier() !== $supplier) {
            throw $this->createNotFoundException();
        }

        $supplier->setPrimaryContact($contact);
        $em->flush();
        $this->addFlash('success', \sprintf('%s es ahora el comercial principal.', $contact->getFullName()));

        return $this->redirectToRoute('supplier_show', ['id' => $supplier->getId()]);
    }
}
