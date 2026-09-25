<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Supplier;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route('/proveedores/{id}/contactos/nuevo', name: 'contact_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new(Supplier $supplier, Request $request, EntityManagerInterface $em): Response
    {
        $contact = new Contact();
        $contact->setSupplier($supplier);
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($contact);
            // Si el proveedor aún no tenía comercial principal, este pasa a serlo.
            if (null === $supplier->getPrimaryContact()) {
                $supplier->setPrimaryContact($contact);
            }
            $em->flush();
            $this->addFlash('success', 'Contacto añadido.');

            return $this->redirectToRoute('supplier_show', ['id' => $supplier->getId()]);
        }

        return $this->render('contact/form.html.twig', [
            'form' => $form,
            'supplier' => $supplier,
            'is_new' => true,
        ]);
    }

    #[Route('/contactos/{id}/editar', name: 'contact_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Contact $contact, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Contacto actualizado.');

            return $this->redirectToRoute('supplier_show', ['id' => $contact->getSupplier()->getId()]);
        }

        return $this->render('contact/form.html.twig', [
            'form' => $form,
            'supplier' => $contact->getSupplier(),
            'is_new' => false,
        ]);
    }

    #[Route('/contactos/{id}/estado', name: 'contact_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Contact $contact, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('contact'.$contact->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $contact->setIsActive(!$contact->isActive());
        $em->flush();
        $this->addFlash('success', $contact->isActive() ? 'Contacto activado.' : 'Contacto desactivado.');

        return $this->redirectToRoute('supplier_show', ['id' => $contact->getSupplier()->getId()]);
    }
}
