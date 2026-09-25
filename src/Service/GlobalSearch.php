<?php

namespace App\Service;

use App\Dto\SearchGroup;
use App\Dto\SearchHit;
use App\Entity\Appointment;
use App\Entity\Communication;
use App\Entity\Contact;
use App\Entity\Invoice;
use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Buscador global: consulta las entidades principales por texto y agrupa los
 * resultados. Las consultas de búsqueda se centralizan aquí por ser una única
 * funcionalidad transversal.
 */
class GlobalSearch
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @return list<SearchGroup>
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }
        $like = '%'.mb_strtolower($query).'%';

        $groups = [
            new SearchGroup('Proveedores', $this->suppliers($like, $limit)),
            new SearchGroup('Contactos', $this->contacts($like, $limit)),
            new SearchGroup('Pedidos', $this->orders($like, $limit)),
            new SearchGroup('Facturas', $this->invoices($like, $limit)),
            new SearchGroup('Comunicaciones', $this->communications($like, $limit)),
            new SearchGroup('Citas', $this->appointments($like, $limit)),
        ];

        return array_values(array_filter($groups, fn (SearchGroup $g) => [] !== $g->hits));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function run(string $class, string $where, string $order, string $like, int $limit): array
    {
        /** @var list<T> $rows */
        $rows = $this->em->createQuery(\sprintf('SELECT e FROM %s e WHERE %s ORDER BY %s', $class, $where, $order))
            ->setParameter('q', $like)
            ->setMaxResults($limit)
            ->getResult();

        return $rows;
    }

    /** @return list<SearchHit> */
    private function suppliers(string $like, int $limit): array
    {
        $rows = $this->run(Supplier::class, 'LOWER(e.brandName) LIKE :q OR LOWER(e.legalName) LIKE :q OR LOWER(e.taxId) LIKE :q OR LOWER(e.email) LIKE :q', 'e.brandName ASC', $like, $limit);

        return array_map(fn (Supplier $s) => new SearchHit(
            $s->getBrandName(),
            $s->getStatus()->label(),
            $this->router->generate('supplier_show', ['id' => $s->getId()]),
        ), $rows);
    }

    /** @return list<SearchHit> */
    private function contacts(string $like, int $limit): array
    {
        $rows = $this->run(Contact::class, 'LOWER(e.firstName) LIKE :q OR LOWER(e.lastName) LIKE :q OR LOWER(e.email) LIKE :q OR LOWER(e.phone) LIKE :q', 'e.firstName ASC', $like, $limit);

        return array_map(fn (Contact $c) => new SearchHit(
            $c->getFullName(),
            $c->getSupplier()->getBrandName(),
            $this->router->generate('supplier_show', ['id' => $c->getSupplier()->getId()]),
        ), $rows);
    }

    /** @return list<SearchHit> */
    private function orders(string $like, int $limit): array
    {
        $rows = $this->run(PurchaseOrder::class, 'LOWER(e.number) LIKE :q OR LOWER(e.season) LIKE :q', 'e.orderedAt DESC', $like, $limit);

        return array_map(fn (PurchaseOrder $o) => new SearchHit(
            'Pedido '.$o->getNumber(),
            $o->getSupplier()->getBrandName(),
            $this->router->generate('purchase_order_show', ['id' => $o->getId()]),
        ), $rows);
    }

    /** @return list<SearchHit> */
    private function invoices(string $like, int $limit): array
    {
        $rows = $this->run(Invoice::class, 'LOWER(e.number) LIKE :q', 'e.issuedAt DESC', $like, $limit);

        return array_map(fn (Invoice $i) => new SearchHit(
            'Factura '.$i->getNumber(),
            $i->getSupplier()->getBrandName(),
            $this->router->generate('invoice_show', ['id' => $i->getId()]),
        ), $rows);
    }

    /** @return list<SearchHit> */
    private function communications(string $like, int $limit): array
    {
        $rows = $this->run(Communication::class, 'LOWER(e.subject) LIKE :q', 'e.occurredAt DESC', $like, $limit);

        return array_map(fn (Communication $c) => new SearchHit(
            $c->getSubject(),
            $c->getSupplier()->getBrandName(),
            $this->router->generate('supplier_tab', ['id' => $c->getSupplier()->getId(), 'tab' => 'comunicaciones']),
        ), $rows);
    }

    /** @return list<SearchHit> */
    private function appointments(string $like, int $limit): array
    {
        $rows = $this->run(Appointment::class, 'LOWER(e.title) LIKE :q', 'e.startsAt DESC', $like, $limit);

        return array_map(fn (Appointment $a) => new SearchHit(
            $a->getTitle(),
            $a->getStartsAt()?->format('d/m/Y H:i'),
            $this->router->generate('appointment_edit', ['id' => $a->getId()]),
        ), $rows);
    }
}
