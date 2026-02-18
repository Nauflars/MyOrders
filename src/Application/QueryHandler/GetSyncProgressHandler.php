<?php

declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Query\GetSyncProgressQuery;
use App\Domain\Entity\SyncProgress;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * GetSyncProgressHandler - Retrieve sync progress from MySQL
 * 
 * Returns current sync status, percentage, and estimated time remaining.
 */
final readonly class GetSyncProgressHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(GetSyncProgressQuery $query): ?array
    {
        $this->logger->debug('Getting sync progress', [
            'customer_id' => $query->customerId,
            'sales_org' => $query->salesOrg,
        ]);

        $repository = $this->entityManager->getRepository(SyncProgress::class);
        
        $syncProgress = $repository->findOneBy([
            'customerId' => $query->customerId,
            'salesOrg' => $query->salesOrg,
        ], ['startedAt' => 'DESC']); // Get most recent

        if (!$syncProgress) {
            return null;
        }

        return $syncProgress->toArray();
    }
}
