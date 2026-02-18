<?php

declare(strict_types=1);

namespace App\Application\Query;

/**
 * GetSyncProgressQuery - Get sync progress for customer
 * 
 * Returns current sync operation status and progress percentage.
 */
final readonly class GetSyncProgressQuery
{
    public function __construct(
        public string $customerId,
        public string $salesOrg
    ) {
    }
}
