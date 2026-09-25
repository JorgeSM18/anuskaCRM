<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PaymentController extends AbstractController
{
    #[Route('/facturas/{id}/pagos', name: 'payment_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function add(Invoice $invoice, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('add_payment'.$invoice->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $amount = str_replace(',', '.', trim((string) $request->request->get('amount')));
        if (!is_numeric($amount) || (float) $amount <= 0) {
            $this->addFlash('error', 'Introduce un importe válido mayor que cero.');

            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        $dateStr = trim((string) $request->request->get('paidAt'));
        $paidAt = '' !== $dateStr ? new \DateTimeImmutable($dateStr) : new \DateTimeImmutable();

        $payment = (new Payment())
            ->setAmount(bcadd($amount, '0', 2))
            ->setPaidAt($paidAt)
            ->setMethod(trim((string) $request->request->get('method')) ?: null);

        $invoice->addPayment($payment);
        $this->refreshStatus($invoice);
        $em->flush();

        $this->addFlash('success', 'Pago registrado.');

        return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/pagos/{id}/borrar', name: 'payment_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Payment $payment, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_payment'.$payment->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $invoice = $payment->getInvoice();
        $invoice->removePayment($payment);
        $this->refreshStatus($invoice);
        $em->flush();

        $this->addFlash('success', 'Pago eliminado.');

        return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
    }

    /** Ajusta el estado de la factura según los pagos (no toca las anuladas). */
    private function refreshStatus(Invoice $invoice): void
    {
        if (InvoiceStatus::CANCELLED === $invoice->getStatus()) {
            return;
        }

        if ($invoice->isFullyPaid()) {
            $invoice->setStatus(InvoiceStatus::PAID);
            if (null === $invoice->getPaidAt()) {
                $invoice->setPaidAt(new \DateTimeImmutable());
            }
        } else {
            $invoice->setStatus(InvoiceStatus::PENDING);
            $invoice->setPaidAt(null);
        }
    }
}
