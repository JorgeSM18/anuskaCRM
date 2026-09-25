<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Supplier;
use App\Enum\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 25;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function save(Invoice $invoice): void
    {
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Paginator<Invoice>
     */
    public function findForIndex(?Supplier $supplier, ?InvoiceStatus $status, ?string $q, int $page): Paginator
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.supplier', 's')->addSelect('s')
            ->orderBy('i.issuedAt', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);
        $this->applyFilters($qb, $supplier, $status, $q);

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    /**
     * @return list<Invoice>
     */
    public function findAllFiltered(?Supplier $supplier, ?InvoiceStatus $status, ?string $q): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.supplier', 's')->addSelect('s')
            ->orderBy('i.issuedAt', 'DESC')
            ->setMaxResults(10000);
        $this->applyFilters($qb, $supplier, $status, $q);

        return $qb->getQuery()->getResult();
    }

    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, ?Supplier $supplier, ?InvoiceStatus $status, ?string $q): void
    {
        if ($supplier instanceof Supplier) {
            $qb->andWhere('i.supplier = :supplier')->setParameter('supplier', $supplier);
        }
        if ($status instanceof InvoiceStatus) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }
        if ($q) {
            $qb->andWhere('i.number LIKE :q')->setParameter('q', '%'.$q.'%');
        }
    }

    /** Total facturado (excluye anuladas). */
    public function sumTotalBySupplier(Supplier $supplier): string
    {
        return $this->sum($supplier, [InvoiceStatus::PENDING, InvoiceStatus::PAID]);
    }

    /** Importe pendiente de pago. */
    public function sumPendingBySupplier(Supplier $supplier): string
    {
        return $this->sum($supplier, [InvoiceStatus::PENDING]);
    }

    /**
     * @param list<InvoiceStatus> $statuses
     */
    private function sum(Supplier $supplier, array $statuses): string
    {
        $value = $this->createQueryBuilder('i')
            ->select('COALESCE(SUM(i.total), 0)')
            ->where('i.supplier = :supplier')->setParameter('supplier', $supplier)
            ->andWhere('i.status IN (:statuses)')->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) $value;
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }

    public function countPending(): int
    {
        return $this->count(['status' => InvoiceStatus::PENDING]);
    }

    /** Importe total pendiente de pago (todas las facturas pendientes). */
    public function sumPendingTotal(): string
    {
        $value = $this->createQueryBuilder('i')
            ->select('COALESCE(SUM(i.total), 0)')
            ->where('i.status = :pending')->setParameter('pending', InvoiceStatus::PENDING)
            ->getQuery()->getSingleScalarResult();

        return (string) $value;
    }

    /**
     * Facturas pendientes con vencimiento hasta la fecha dada (incluye vencidas).
     *
     * @return list<Invoice>
     */
    public function findDueBefore(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.supplier', 's')->addSelect('s')
            ->where('i.status = :pending')->setParameter('pending', InvoiceStatus::PENDING)
            ->andWhere('i.dueAt IS NOT NULL AND i.dueAt <= :date')->setParameter('date', $date)
            ->orderBy('i.dueAt', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return list<Invoice> */
    public function findRecent(int $limit): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.supplier', 's')->addSelect('s')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
