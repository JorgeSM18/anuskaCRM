<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Supplier;
use App\Enum\SupplierStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Supplier>
 */
class SupplierRepository extends ServiceEntityRepository
{
    public const array SORTABLE = ['brandName', 'status'];
    private const int PER_PAGE = 20;
    private const int EXPORT_LIMIT = 10000;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplier::class);
    }

    public function save(Supplier $supplier): void
    {
        $this->getEntityManager()->persist($supplier);
        $this->getEntityManager()->flush();
    }

    /**
     * Listado con búsqueda, filtro por estado, orden y paginación.
     *
     * @return Paginator<Supplier>
     */
    public function findForIndex(
        ?string $q,
        ?SupplierStatus $status,
        ?Category $category,
        string $sort,
        string $direction,
        int $page,
    ): Paginator {
        $sort = \in_array($sort, self::SORTABLE, true) ? $sort : 'brandName';
        $direction = 'desc' === strtolower($direction) ? 'DESC' : 'ASC';
        $page = max(1, $page);

        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.primaryContact', 'pc')->addSelect('pc')
            ->orderBy('s.'.$sort, $direction)
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);
        $this->applyFilters($qb, $q, $status, $category);

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    /**
     * Todos los proveedores que cumplen los filtros (para exportar), sin paginar.
     *
     * @return list<Supplier>
     */
    public function findAllFiltered(?string $q, ?SupplierStatus $status, ?Category $category): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.primaryContact', 'pc')->addSelect('pc')
            ->orderBy('s.brandName', 'ASC')
            ->setMaxResults(self::EXPORT_LIMIT);
        $this->applyFilters($qb, $q, $status, $category);

        return $qb->getQuery()->getResult();
    }

    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, ?string $q, ?SupplierStatus $status, ?Category $category): void
    {
        if ($q) {
            $qb->andWhere('s.brandName LIKE :q OR s.legalName LIKE :q OR s.taxId LIKE :q OR s.email LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }
        if ($status instanceof SupplierStatus) {
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }
        if ($category instanceof Category) {
            $qb->andWhere(':category MEMBER OF s.categories')->setParameter('category', $category);
        }
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }
}
