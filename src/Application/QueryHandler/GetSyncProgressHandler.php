<?php

declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Query\GetSyncProgressQuery;
use App\Domain\Repository\SyncProgressRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * GetSyncProgressHandler - Retrieve sync progress from database
 * 
 * Returns current sync status by querying the sync_progress table.
 * Each customer has their own isolated progress tracking.
 */
final readonly class GetSyncProgressHandler
{
    public function __construct(
        private SyncProgressRepositoryInterface $syncProgressRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(GetSyncProgressQuery $query): ?array
    {
        try {
            // Find active sync for this customer
            $syncProgress = $this->syncProgressRepository->findActiveByCustomer(
                $query->customerId,
                $query->salesOrg
            );
            
            if (!$syncProgress) {
                return null; // No sync in progress
            }
            
            return [
                'status' => $syncProgress->getStatus(),
                'total_materials' => $syncProgress->getTotalMaterials(),
                'processed_materials' => $syncProgress->getProcessedMaterials(),
                'percentage_complete' => $syncProgress->getPercentageComplete(),
                'elapsed_seconds' => $syncProgress->getElapsedSeconds(),
                'estimated_time_remaining' => $syncProgress->getEstimatedTimeRemaining(),
                'customer_id' => $query->customerId,
                'sales_org' => $query->salesOrg,
                'started_at' => $syncProgress->getStartedAt()->format('Y-m-d H:i:s'),
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get sync progress', [
                'error' => $e->getMessage(),
                'customer_id' => $query->customerId,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
}
