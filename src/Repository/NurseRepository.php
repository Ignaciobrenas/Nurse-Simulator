<?php

namespace App\Repository;

use App\Entity\Nurse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Nurse>
 */
class NurseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Nurse::class);
    }

    public function save(Nurse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Nurse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Finds a nurse by case-insensitive name.
     */
    public function findByName(string $name): ?Nurse
    {
        return $this->createQueryBuilder('n')
            ->where('LOWER(n.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds a nurse by email or name (case-insensitive) for login purposes.
     */
    public function findByUsername(string $username): ?Nurse
    {
        return $this->createQueryBuilder('n')
            ->where('LOWER(n.email) = LOWER(:username) OR LOWER(n.name) = LOWER(:username)')
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
