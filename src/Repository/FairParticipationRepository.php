<?php

namespace App\Repository;

use App\Entity\FairParticipation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FairParticipation>
 */
class FairParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FairParticipation::class);
    }

    public function save(FairParticipation $participation): void
    {
        $this->getEntityManager()->persist($participation);
        $this->getEntityManager()->flush();
    }
}
