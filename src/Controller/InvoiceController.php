<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Supplier;
use App\Enum\InvoiceStatus;
use App\Form\InvoiceType;
use App\Repository\InvoiceRepository;
use App\Repository\SupplierRepository;
use App\Service\CsvExporter;
use App\Service\InvoiceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InvoiceController extends AbstractController
{
    #[Route('/facturas', name: 'invoice_index', methods: ['GET'])]
    public function index(Request $request, InvoiceRepository $invoices, SupplierRepository $suppliers): Response
    {
        $status = InvoiceStatus::tryFrom((string) $request->query->get('status', ''));
        $q = trim((string) $request->query->get('q', ''));
        $supplierId = $request->query->getInt('supplier');
        $supplier = $supplierId > 0 ? $suppliers->find($supplierId) : null;
        $page = $request->query->getInt('page', 1);

        $paginator = $invoices->findForIndex($supplier, $status, $q ?: null, $page);
        $total = \count($paginator);

        return $this->render('invoice/index.html.twig', [
            'invoices' => $paginator,
            'total' => $total,
            'page' => max(1, $page),
            'pages' => (int) ceil($total / $invoices->perPage()),
            'status' => $status,
            'q' => $q,
            'supplier' => $supplier,
        ]);
    }

    #[Route('/facturas/exportar', name: 'invoice_export', methods: ['GET'])]
    public function export(Request $request, InvoiceRepository $invoices, SupplierRepository $suppliers, CsvExporter $csv): Response
    {
        $status = InvoiceStatus::tryFrom((string) $request->query->get('status', ''));
        $q = trim((string) $request->query->get('q', ''));
        $supplierId = $request->query->getInt('supplier');
        $supplier = $supplierId > 0 ? $suppliers->find($supplierId) : null;

        $rows = [];
        foreach ($invoices->findAllFiltered($supplier, $status, $q ?: null) as $i) {
            $rows[] = [
                $i->getIssuedAt()?->format('d/m/Y'),
                $i->getNumber(),
                $i->getSupplier()->getBrandName(),
                $i->getDueAt()?->format('d/m/Y'),
                $i->isOverdue() ? 'Vencida' : $i->getStatus()->label(),
                $i->getBaseAmount(),
                $i->getVatAmount(),
                $i->getTotal(),
                $i->getPaidAt()?->format('d/m/Y'),
                $i->getPurchaseOrder()?->getNumber(),
            ];
        }

        return $csv->stream('facturas.csv',
            ['Emisión', 'Número', 'Proveedor', 'Vencimiento', 'Estado', 'Base', 'IVA', 'Total', 'Pagada el', 'Pedido'],
            $rows,
        );
    }

    #[Route('/proveedores/{id}/facturas/nueva', name: 'invoice_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new(Supplier $supplier, Request $request, InvoiceRepository $invoices, InvoiceCalculator $calc): Response
    {
        $invoice = new Invoice();
        $invoice->setSupplier($supplier);
        $invoice->setIssuedAt(new \DateTimeImmutable());

        $form = $this->createForm(InvoiceType::class, $invoice, ['supplier' => $supplier]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyTotals($invoice, $calc);
            $invoices->save($invoice);
            $this->addFlash('success', 'Factura creada.');

            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        return $this->render('invoice/form.html.twig', [
            'form' => $form,
            'invoice' => $invoice,
            'supplier' => $supplier,
            'is_new' => true,
        ]);
    }

    #[Route('/facturas/{id}', name: 'invoice_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Invoice $invoice): Response
    {
        return $this->render('invoice/show.html.twig', ['invoice' => $invoice]);
    }

    #[Route('/facturas/{id}/editar', name: 'invoice_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Invoice $invoice, Request $request, EntityManagerInterface $em, InvoiceCalculator $calc): Response
    {
        $form = $this->createForm(InvoiceType::class, $invoice, ['supplier' => $invoice->getSupplier()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyTotals($invoice, $calc);
            $em->flush();
            $this->addFlash('success', 'Factura actualizada.');

            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        return $this->render('invoice/form.html.twig', [
            'form' => $form,
            'invoice' => $invoice,
            'supplier' => $invoice->getSupplier(),
            'is_new' => false,
        ]);
    }

    private function applyTotals(Invoice $invoice, InvoiceCalculator $calc): void
    {
        $base = $invoice->getBaseAmount();
        $rate = $invoice->getVatRate();
        $base = is_numeric($base) ? $base : '0';
        $rate = is_numeric($rate) ? $rate : '0';
        $vat = $calc->vatAmount($base, $rate);
        $invoice->setVatAmount($vat);
        $invoice->setTotal($calc->total($base, $vat));
    }
}
