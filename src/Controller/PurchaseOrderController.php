<?php

namespace App\Controller;

use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Enum\OrderStatus;
use App\Form\PurchaseOrderType;
use App\Repository\PurchaseOrderRepository;
use App\Repository\SupplierRepository;
use App\Service\CsvExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PurchaseOrderController extends AbstractController
{
    #[Route('/pedidos', name: 'purchase_order_index', methods: ['GET'])]
    public function index(Request $request, PurchaseOrderRepository $orders, SupplierRepository $suppliers): Response
    {
        $status = OrderStatus::tryFrom((string) $request->query->get('status', ''));
        $season = trim((string) $request->query->get('season', ''));
        $supplierId = $request->query->getInt('supplier');
        $supplier = $supplierId > 0 ? $suppliers->find($supplierId) : null;
        $page = $request->query->getInt('page', 1);

        $paginator = $orders->findForIndex($supplier, $status, $season ?: null, $page);
        $total = \count($paginator);

        return $this->render('purchase_order/index.html.twig', [
            'orders' => $paginator,
            'total' => $total,
            'page' => max(1, $page),
            'pages' => (int) ceil($total / $orders->perPage()),
            'status' => $status,
            'season' => $season,
            'supplier' => $supplier,
        ]);
    }

    #[Route('/pedidos/exportar', name: 'purchase_order_export', methods: ['GET'])]
    public function export(Request $request, PurchaseOrderRepository $orders, SupplierRepository $suppliers, CsvExporter $csv): Response
    {
        $status = OrderStatus::tryFrom((string) $request->query->get('status', ''));
        $season = trim((string) $request->query->get('season', ''));
        $supplierId = $request->query->getInt('supplier');
        $supplier = $supplierId > 0 ? $suppliers->find($supplierId) : null;

        $rows = [];
        foreach ($orders->findAllFiltered($supplier, $status, $season ?: null) as $o) {
            $rows[] = [
                $o->getOrderedAt()?->format('d/m/Y'),
                $o->getNumber(),
                $o->getSupplier()->getBrandName(),
                $o->getSeason(),
                $o->getCampaign(),
                $o->getStatus()->label(),
                $o->getContact()?->getFullName(),
                $o->getEstimatedAmount(),
                $o->getFinalAmount(),
                $o->getExpectedDeliveryAt()?->format('d/m/Y'),
                $o->getReceivedAt()?->format('d/m/Y'),
            ];
        }

        return $csv->stream('pedidos.csv',
            ['Fecha', 'Número', 'Proveedor', 'Temporada', 'Campaña', 'Estado', 'Comercial', 'Importe estimado', 'Importe final', 'Entrega prevista', 'Recepción'],
            $rows,
        );
    }

    #[Route('/proveedores/{id}/pedidos/nuevo', name: 'purchase_order_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new(Supplier $supplier, Request $request, PurchaseOrderRepository $orders): Response
    {
        $order = new PurchaseOrder();
        $order->setSupplier($supplier);
        $order->setOrderedAt(new \DateTimeImmutable());

        $form = $this->createForm(PurchaseOrderType::class, $order, ['supplier' => $supplier]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $orders->save($order);
            $this->addFlash('success', 'Pedido creado.');

            return $this->redirectToRoute('purchase_order_show', ['id' => $order->getId()]);
        }

        return $this->render('purchase_order/form.html.twig', [
            'form' => $form,
            'order' => $order,
            'supplier' => $supplier,
            'is_new' => true,
        ]);
    }

    #[Route('/pedidos/{id}', name: 'purchase_order_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(PurchaseOrder $order): Response
    {
        return $this->render('purchase_order/show.html.twig', ['order' => $order]);
    }

    #[Route('/pedidos/{id}/editar', name: 'purchase_order_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(PurchaseOrder $order, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PurchaseOrderType::class, $order, ['supplier' => $order->getSupplier()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Pedido actualizado.');

            return $this->redirectToRoute('purchase_order_show', ['id' => $order->getId()]);
        }

        return $this->render('purchase_order/form.html.twig', [
            'form' => $form,
            'order' => $order,
            'supplier' => $order->getSupplier(),
            'is_new' => false,
        ]);
    }
}
