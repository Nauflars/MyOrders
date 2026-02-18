<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Redis;

use App\Domain\ValueObject\SyncLockId;
use Psr\Log\LoggerInterface;

/**
 * RedisSyncLockRepository - File-based locking for sync operations
 * 
 * Prevents duplicate sync operations for the same customer/sales org
 * using file-based locks with TTL for automatic expiration.
 */
final class RedisSyncLockRepository
{
    private const LOCK_TTL = 600; // 10 minutes
    
    private array $locks = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $lockDir = '/tmp/sync-locks'
    ) {
        if (!is_dir($this->lockDir)) {
            @mkdir($this->lockDir, 0777, true);
        }
    }

    public function acquireLock(SyncLockId $lockId): bool
    {
        $lockKey = $lockId->toLockKey();
        $lockFile = $this->lockDir . '/' . md5($lockKey) . '.lock';
        
        $this->logger->debug('Attempting to acquire sync lock', [
            'lock_key' => $lockKey,
            'sales_org' => $lockId->salesOrg(),
            'customer_id' => $lockId->customerId(),
        ]);

        if (file_exists($lockFile)) {
            $lockTime = filemtime($lockFile);
            if ($lockTime && (time() - $lockTime) < self::LOCK_TTL) {
                $this->logger->warning('Sync lock already held', [
                    'lock_key' => $lockKey,
                    'lock_age_seconds' => time() - $lockTime,
                ]);
                return false;
            }
            @unlink($lockFile);
        }

        file_put_contents($lockFile, json_encode([
            'lock_key' => $lockKey,
            'acquired_at' => date('Y-m-d H:i:s'),
            'pid' => getmypid(),
        ]));
        
        $this->locks[$lockKey] = $lockFile;
        $this->logger->info('Sync lock acquired', ['lock_key' => $lockKey]);
        
        return true;
    }

    public function releaseLock(SyncLockId $lockId): void
    {
        $lockKey = $lockId->toLockKey();
        $lockFile = $this->locks[$lockKey] ?? ($this->lockDir . '/' . md5($lockKey) . '.lock');
        
        if (file_exists($lockFile)) {
            @unlink($lockFile);
            unset($this->locks[$lockKey]);
            $this->logger->debug('Sync lock released', ['lock_key' => $lockKey]);
        }
    }

    public function isLocked(SyncLockId $lockId): bool
    {
        $lockKey = $lockId->toLockKey();
        $lockFile = $this->lockDir . '/' . md5($lockKey) . '.lock';
        
        if (!file_exists($lockFile)) {
            return false;
        }
        
        $lockTime = filemtime($lockFile);
        if ($lockTime && (time() - $lockTime) >= self::LOCK_TTL) {
            @unlink($lockFile);
            return false;
        }
        
        return true;
    }
}
