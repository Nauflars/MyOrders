<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SyncUserMaterialsCommand;
use App\Application\Command\SyncMaterialPriceCommand;
use App\Domain\ValueObject\SyncLockId;
use App\Domain\Entity\SyncProgress;
use App\Infrastructure\Persistence\Redis\RedisSyncLockRepository;
use App\Infrastructure\ExternalApi\SapApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * SyncUserMaterialsHandler - Handle bulk material synchronization
 * 
 * Acquires lock, fetches materials from SAP, dispatches price fetch commands,
 * tracks progress and releases lock when complete.
 */
#[AsMessageHandler]
final readonly class SyncUserMaterialsHandler
{
    public function __construct(
        private SapApiClient $sapApiClient,
        private EntityManagerInterface $entityManager,
        private RedisSyncLockRepository $lockRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SyncUserMaterialsCommand $command): void
    {
        $lockId = SyncLockId::create($command->salesOrg, $command->customerId);

        $this->logger->info('Starting materials sync for customer', [
            'customer_id' => $command->customerId,
            'sales_org' => $command->salesOrg,
        ]);

        // Acquire lock
        if (!$this->lockRepository->acquireLock($lockId)) {
            $this->logger->warning('Cannot sync - lock already held', [
                'customer_id' => $command->customerId,
                'sales_org' => $command->salesOrg,
            ]);
            return; // Silently skip if already syncing
        }

        try {
            // Load materials from SAP
            $response = $this->sapApiClient->loadMaterials($command->customerId);
            $materials = $response['X_MAT_FOUND'] ?? [];
            $totalMaterials = count($materials);

            $this->logger->info('Loaded materials from SAP', [
                'customer_id' => $command->customerId,
                'total_materials' => $totalMaterials,
            ]);

            if ($totalMaterials === 0) {
                $this->logger->warning('No materials found for customer', [
                    'customer_id' => $command->customerId,
                ]);
                return;
            }

            // Create sync progress tracker
            $syncProgress = SyncProgress::start(
                $command->customerId,
                $command->salesOrg,
                $totalMaterials
            );
            $this->entityManager->persist($syncProgress);
            $this->entityManager->flush();

            // Dispatch price fetch commands for each material
            $dispatched = 0;
            foreach ($materials as $materialData) {
                $materialId = $materialData['MATNR'] ?? null;
                $posnr = $materialData['POSNR'] ?? null;

                if (!$materialId) {
                    continue;
                }

                $this->messageBus->dispatch(
                    new SyncMaterialPriceCommand(
                        $command->customerId,
                        $materialId,
                        $command->salesOrg,
                        $posnr
                    )
                );

                $dispatched++;

                // Update progress in batches
                if ($dispatched % 100 === 0) {
                    $syncProgress->incrementProgressBy(100);
                    $this->entityManager->flush();
                }
            }

            // Final progress update
            if ($dispatched % 100 !== 0) {
                $syncProgress->incrementProgressBy($dispatched % 100);
            }
            $syncProgress->markAsCompleted();
            $this->entityManager->flush();

            $this->logger->info('Materials sync completed', [
                'customer_id' => $command->customerId,
                'dispatched' => $dispatched,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Materials sync failed', [
                'customer_id' => $command->customerId,
                'error' => $e->getMessage(),
            ]);

            // Mark sync as failed
            $repository = $this->entityManager->getRepository(SyncProgress::class);
            $syncProgress = $repository->findOneBy([
                'customerId' => $command->customerId,
                'salesOrg' => $command->salesOrg,
            ], ['startedAt' => 'DESC']);

            if ($syncProgress) {
                $syncProgress->markAsFailed($e->getMessage());
                $this->entityManager->flush();
            }

            throw $e;

        } finally {
            // Always release lock
            $this->lockRepository->releaseLock($lockId);
            
            $this->logger->debug('Sync lock released', [
                'customer_id' => $command->customerId,
            ]);
        }
    }
}
