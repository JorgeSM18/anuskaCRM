<?php

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseOrder>
 */
class PurchaseOrderRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 25;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseOrder::class);
    }

    public function save(PurchaseOrder $order): void
    {
        $this->getEntityManager()->persist($order);
        $this->getEntityManager()->flush();
    }

    /**
     * Listado global con filtros y paginación.
     *
     * @return Paginator<PurchaseOrder>
     */
    public function findForIndex(?Supplier $supplier, ?OrderStatus $status, ?string $season, int $page): Paginator
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.supplier', 's')->addSelect('s')
            ->orderBy('o.orderedAt', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);
        $this->applyFilters($qb, $supplier, $status, $season);

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    /**
     * @return list<PurchaseOrder>
     */
    public function findAllFiltered(?Supplier $supplier, ?OrderStatus $status, ?string $season): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.supplier', 's')->addSelect('s')
            ->orderBy('o.orderedAt', 'DESC')
            ->setMaxResults(10000);
        $this->applyFilters($qb, $supplier, $status, $season);

        return $qb->getQuery()->getResult();
    }

    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, ?Supplier $supplier, ?OrderStatus $status, ?string $season): void
    {
        if ($supplier instanceof Supplier) {
            $qb->andWhere('o.supplier = :supplier')->setParameter('supplier', $supplier);
        }
        if ($status instanceof OrderStatus) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }
        if ($season) {
            $qb->andWhere('o.season LIKE :season')->setParameter('season', '%'.$season.'%');
        }
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }

    /** Pedidos abiertos (ni recibidos ni cancelados). */
    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status NOT IN (:closed)')
            ->setParameter('closed', [OrderStatus::RECEIVED, OrderStatus::CANCELLED])
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * Pedidos abiertos con entrega prevista en el rango.
     *
     * @return list<PurchaseOrder>
     */
    public function findUpcomingDeliveries(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.supplier', 's')->addSelect('s')
            ->where('o.expectedDeliveryAt >= :from AND o.expectedDeliveryAt <= :to')
            ->andWhere('o.status NOT IN (:closed)')
            ->setParameter('from', $from)->setParameter('to', $to)
            ->setParameter('closed', [OrderStatus::RECEIVED, OrderStatus::CANCELLED])
            ->orderBy('o.expectedDeliveryAt', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return list<PurchaseOrder> */
    public function findRecent(int $limit): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.supplier', 's')->addSelect('s')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /**
     * Pedidos no cancelados a partir de una fecha (para el gráfico de gasto).
     *
     * @return list<PurchaseOrder>
     */
    public function findSince(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.orderedAt >= :since')->setParameter('since', $since)
            ->andWhere('o.status != :cancelled')->setParameter('cancelled', OrderStatus::CANCELLED)
            ->orderBy('o.orderedAt', 'ASC')
            ->getQuery()->getResult();
    }
}
