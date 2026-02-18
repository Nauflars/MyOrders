<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Middleware;

use App\Domain\ValueObject\SyncLockId;
use App\Infrastructure\Persistence\Redis\RedisSyncLockRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/**
 * SyncLockMiddleware - Middleware to check distributed locks before sync
 * 
 * Prevents duplicate sync operations by checking if a lock exists before
 * processing sync commands. Only applies to commands that implement SyncCommandInterface.
 */
final class SyncLockMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RedisSyncLockRepository $lockRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        
        // Only check locks for consumed messages (not when dispatching)
        if (!$envelope->last(ConsumedByWorkerStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Check if this is a sync command that requires locking
        if ($this->requiresLock($message)) {
            $lockId = $this->extractLockId($message);
            
            if ($lockId === null) {
                $this->logger->warning('Cannot extract lock ID from message', [
                    'message_class' => get_class($message),
                ]);
                return $stack->next()->handle($envelope, $stack);
            }

            // Check if lock exists
            if ($this->lockRepository->isLocked($lockId)) {
                $this->logger->info('Message blocked by existing lock', [
                    'message_class' => get_class($message),
                    'lock_key' => $lockId->toLockKey(),
                ]);
                
                // Drop the message - do not process
                // The lock holder will complete the work
                return $envelope;
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }

    /**
     * Check if message requires locking
     */
    private function requiresLock(object $message): bool
    {
        // Check for commands that need locking
        $className = get_class($message);
        
        return match (true) {
            str_contains($className, 'SyncMaterialsFromSapCommand') => true,
            str_contains($className, 'SyncCustomerFromSapCommand') => true,
            str_contains($className, 'SyncUserMaterialsCommand') => true,
            default => false,
        };
    }

    /**
     * Extract lock ID from message
     */
    private function extractLockId(object $message): ?SyncLockId
    {
        // Try to get customerId and salesOrg from message
        $customerId = $message->customerId ?? null;
        $salesOrg = $message->salesOrg ?? null;
        
        // Try alternative properties
        if ($salesOrg === null && isset($message->tvkoData['VKORG'])) {
            $salesOrg = $message->tvkoData['VKORG'];
        }

        if ($customerId === null || $salesOrg === null) {
            return null;
        }

        return SyncLockId::create($salesOrg, $customerId);
    }
}
