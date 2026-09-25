<?php

namespace App\Repository;

use App\Entity\Fair;
use App\Enum\FairStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fair>
 */
class FairRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fair::class);
    }

    public function save(Fair $fair): void
    {
        $this->getEntityManager()->persist($fair);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Paginator<Fair>
     */
    public function findForIndex(?FairStatus $status, int $page): Paginator
    {
        $qb = $this->createQueryBuilder('f')
            ->orderBy('f.startsAt', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        if ($status instanceof FairStatus) {
            $qb->andWhere('f.status = :status')->setParameter('status', $status);
        }

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }
}
