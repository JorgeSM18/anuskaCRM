<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Supplier;
use App\Enum\AppointmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 30;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    public function save(Appointment $appointment): void
    {
        $this->getEntityManager()->persist($appointment);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Paginator<Appointment>
     */
    public function findForIndex(?Supplier $supplier, ?AppointmentStatus $status, int $page): Paginator
    {
        $qb = $this->baseQuery()
            ->orderBy('a.startsAt', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        if ($supplier instanceof Supplier) {
            $qb->andWhere('a.supplier = :supplier')->setParameter('supplier', $supplier);
        }
        if ($status instanceof AppointmentStatus) {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    /**
     * Citas cuyo inicio cae en el rango [desde, hasta).
     *
     * @return list<Appointment>
     */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->baseQuery()
            ->andWhere('a.startsAt >= :from AND a.startsAt < :to')
            ->setParameter('from', $from)->setParameter('to', $to)
            ->orderBy('a.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Próxima cita pendiente del proveedor (a partir de ahora). */
    public function nextForSupplier(Supplier $supplier): ?Appointment
    {
        return $this->createQueryBuilder('a')
            ->where('a.supplier = :supplier')->setParameter('supplier', $supplier)
            ->andWhere('a.status = :pending')->setParameter('pending', AppointmentStatus::PENDING)
            ->andWhere('a.startsAt >= :now')->setParameter('now', new \DateTimeImmutable())
            ->orderBy('a.startsAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }

    /**
     * Citas pendientes de hoy.
     *
     * @return list<Appointment>
     */
    public function findToday(): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->baseQuery()
            ->andWhere('a.startsAt >= :today AND a.startsAt < :tomorrow')
            ->andWhere('a.status = :pending')
            ->setParameter('today', $today)->setParameter('tomorrow', $today->modify('+1 day'))
            ->setParameter('pending', AppointmentStatus::PENDING)
            ->orderBy('a.startsAt', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Próximas citas pendientes en (desde, hasta].
     *
     * @return list<Appointment>
     */
    public function findUpcoming(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->baseQuery()
            ->andWhere('a.startsAt > :from AND a.startsAt <= :to')
            ->andWhere('a.status = :pending')
            ->setParameter('from', $from)->setParameter('to', $to)
            ->setParameter('pending', AppointmentStatus::PENDING)
            ->orderBy('a.startsAt', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Recordatorios activos: cita pendiente futura cuyo aviso ya toca.
     *
     * @return list<Appointment>
     */
    public function findActiveReminders(\DateTimeImmutable $now): array
    {
        return $this->baseQuery()
            ->andWhere('a.reminderAt IS NOT NULL AND a.reminderAt <= :now')
            ->andWhere('a.startsAt >= :now')
            ->andWhere('a.status = :pending')
            ->setParameter('now', $now)
            ->setParameter('pending', AppointmentStatus::PENDING)
            ->orderBy('a.startsAt', 'ASC')
            ->getQuery()->getResult();
    }

    public function countUpcoming(\DateTimeImmutable $now): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.startsAt >= :now')->setParameter('now', $now)
            ->andWhere('a.status = :pending')->setParameter('pending', AppointmentStatus::PENDING)
            ->getQuery()->getSingleScalarResult();
    }

    private function baseQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.supplier', 's')->addSelect('s')
            ->leftJoin('a.contact', 'c')->addSelect('c');
    }
}
