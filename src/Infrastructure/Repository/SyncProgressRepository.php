<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\SyncProgress;
use App\Domain\Repository\SyncProgressRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class SyncProgressRepository implements SyncProgressRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(SyncProgress $syncProgress): void
    {
        $this->entityManager->persist($syncProgress);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?SyncProgress
    {
        return $this->entityManager->find(SyncProgress::class, $id);
    }

    public function findActiveByCustomer(string $customerId, string $salesOrg): ?SyncProgress
    {
        return $this->entityManager->getRepository(SyncProgress::class)
            ->createQueryBuilder('sp')
            ->where('sp.customerId = :customerId')
            ->andWhere('sp.salesOrg = :salesOrg')
            ->andWhere('sp.status = :status')
            ->setParameter('customerId', $customerId)
            ->setParameter('salesOrg', $salesOrg)
            ->setParameter('status', 'in_progress')
            ->orderBy('sp.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function delete(SyncProgress $syncProgress): void
    {
        $this->entityManager->remove($syncProgress);
        $this->entityManager->flush();
    }
}
