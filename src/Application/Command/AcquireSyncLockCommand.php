<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * AcquireSyncLockCommand - Acquire distributed lock for sync operation
 * 
 * Ensures only one sync operation can run for a given customer/sales org
 * combination at a time across all workers.
 */
final readonly class AcquireSyncLockCommand
{
    public function __construct(
        public string $customerId,
        public string $salesOrg
    ) {
    }
}
