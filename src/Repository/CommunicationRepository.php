<?php

namespace App\Repository;

use App\Entity\Communication;
use App\Entity\Supplier;
use App\Enum\CommunicationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Communication>
 */
class CommunicationRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 30;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Communication::class);
    }

    public function save(Communication $communication): void
    {
        $this->getEntityManager()->persist($communication);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Paginator<Communication>
     */
    public function findForIndex(?Supplier $supplier, ?CommunicationType $type, bool $pendingOnly, int $page): Paginator
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.supplier', 's')->addSelect('s')
            ->leftJoin('c.contact', 'ct')->addSelect('ct')
            ->orderBy('c.occurredAt', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        if ($supplier instanceof Supplier) {
            $qb->andWhere('c.supplier = :supplier')->setParameter('supplier', $supplier);
        }
        if ($type instanceof CommunicationType) {
            $qb->andWhere('c.type = :type')->setParameter('type', $type);
        }
        if ($pendingOnly) {
            $qb->andWhere('c.pendingReply = true');
        }

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    public function lastContactAt(Supplier $supplier): ?\DateTimeImmutable
    {
        $value = $this->createQueryBuilder('c')
            ->select('MAX(c.occurredAt)')
            ->where('c.supplier = :supplier')->setParameter('supplier', $supplier)
            ->getQuery()
            ->getSingleScalarResult();

        return \is_string($value) ? new \DateTimeImmutable($value) : null;
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }

    /** @return list<Communication> */
    public function findPendingReplies(int $limit): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.supplier', 's')->addSelect('s')
            ->where('c.pendingReply = true')
            ->orderBy('c.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** @return list<Communication> */
    public function findRecent(int $limit): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.supplier', 's')->addSelect('s')
            ->orderBy('c.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
