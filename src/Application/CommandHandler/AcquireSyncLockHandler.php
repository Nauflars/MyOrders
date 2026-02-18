<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\AcquireSyncLockCommand;
use App\Domain\ValueObject\SyncLockId;
use App\Infrastructure\Persistence\Redis\RedisSyncLockRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * AcquireSyncLockHandler - Handles sync lock acquisition
 * 
 * Attempts to acquire a distributed lock for the given customer/sales org.
 * Lock prevents concurrent sync operations from running.
 */
#[AsMessageHandler]
final readonly class AcquireSyncLockHandler
{
    public function __construct(
        private RedisSyncLockRepository $lockRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(AcquireSyncLockCommand $command): bool
    {
        $lockId = SyncLockId::create($command->salesOrg, $command->customerId);
        
        $this->logger->info('Attempting to acquire sync lock', [
            'customer_id' => $command->customerId,
            'sales_org' => $command->salesOrg,
            'lock_key' => $lockId->toLockKey(),
        ]);

        $lock = $this->lockRepository->acquireLock($lockId);

        if ($lock === null) {
            $this->logger->warning('Failed to acquire sync lock - already locked', [
                'customer_id' => $command->customerId,
                'sales_org' => $command->salesOrg,
                'lock_key' => $lockId->toLockKey(),
            ]);
            return false;
        }

        $this->logger->info('Sync lock acquired successfully', [
            'customer_id' => $command->customerId,
            'sales_org' => $command->salesOrg,
            'lock_key' => $lockId->toLockKey(),
        ]);

        return true;
    }
}
