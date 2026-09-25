<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Supplier;
use App\Enum\AppointmentStatus;
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use App\Repository\SupplierRepository;
use App\Service\MonthCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppointmentController extends AbstractController
{
    #[Route('/citas', name: 'appointment_index', methods: ['GET'])]
    public function index(Request $request, AppointmentRepository $appointments, SupplierRepository $suppliers, MonthCalendar $calendar): Response
    {
        $view = 'calendar' === $request->query->get('view') ? 'calendar' : 'list';

        if ('calendar' === $view) {
            $year = $request->query->getInt('year') ?: (int) date('Y');
            $month = $request->query->getInt('month') ?: (int) date('n');
            $from = new \DateTimeImmutable(\sprintf('%04d-%02d-01', $year, $month));
            $to = $from->modify('first day of next month');
            $weeks = $calendar->build($year, $month, $appointments->findBetween($from, $to));

            return $this->render('appointment/calendar.html.twig', [
                'weeks' => $weeks,
                'current' => $from,
                'prev' => $from->modify('-1 month'),
                'next' => $from->modify('+1 month'),
            ]);
        }

        $status = AppointmentStatus::tryFrom((string) $request->query->get('status', ''));
        $supplierId = $request->query->getInt('supplier');
        $supplier = $supplierId > 0 ? $suppliers->find($supplierId) : null;
        $page = $request->query->getInt('page', 1);

        $paginator = $appointments->findForIndex($supplier, $status, $page);
        $total = \count($paginator);

        return $this->render('appointment/index.html.twig', [
            'appointments' => $paginator,
            'total' => $total,
            'page' => max(1, $page),
            'pages' => (int) ceil($total / $appointments->perPage()),
            'status' => $status,
            'supplier' => $supplier,
        ]);
    }

    #[Route('/citas/nueva', name: 'appointment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, AppointmentRepository $appointments): Response
    {
        $appointment = new Appointment();
        $appointment->setStartsAt(new \DateTimeImmutable('+1 day 10:00'));

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $appointments->save($appointment);
            $this->addFlash('success', 'Cita creada.');

            return $this->redirectToRoute('appointment_index');
        }

        return $this->render('appointment/form.html.twig', ['form' => $form, 'supplier' => null, 'is_new' => true]);
    }

    #[Route('/proveedores/{id}/citas/nueva', name: 'appointment_new_for_supplier', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function newForSupplier(Supplier $supplier, Request $request, AppointmentRepository $appointments): Response
    {
        $appointment = new Appointment();
        $appointment->setSupplier($supplier);
        $appointment->setStartsAt(new \DateTimeImmutable('+1 day 10:00'));

        $form = $this->createForm(AppointmentType::class, $appointment, ['supplier' => $supplier]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $appointments->save($appointment);
            $this->addFlash('success', 'Cita creada.');

            return $this->redirectToRoute('supplier_tab', ['id' => $supplier->getId(), 'tab' => 'citas']);
        }

        return $this->render('appointment/form.html.twig', ['form' => $form, 'supplier' => $supplier, 'is_new' => true]);
    }

    #[Route('/citas/{id}/editar', name: 'appointment_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Appointment $appointment, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AppointmentType::class, $appointment, ['supplier' => $appointment->getSupplier()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Cita actualizada.');

            return $this->redirectToRoute('appointment_index');
        }

        return $this->render('appointment/form.html.twig', [
            'form' => $form,
            'supplier' => $appointment->getSupplier(),
            'is_new' => false,
        ]);
    }

    #[Route('/citas/{id}/estado/{status}', name: 'appointment_set_status', methods: ['POST'], requirements: ['id' => '\d+', 'status' => 'done|cancelled'])]
    public function setStatus(Appointment $appointment, string $status, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('status'.$appointment->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $appointment->setStatus(AppointmentStatus::from($status));
        $em->flush();
        $this->addFlash('success', 'done' === $status ? 'Cita marcada como realizada.' : 'Cita cancelada.');

        return $this->redirectToRoute('appointment_index');
    }
}
