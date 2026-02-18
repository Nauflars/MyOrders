<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Sync materials from SAP for a specific customer
 */
final readonly class SyncMaterialsFromSapCommand
{
    public function __construct(
        public string $customerId,
        public string $salesOrg,
        public array $tvkoData,
        public array $tvakData,
        public array $customerData,
        public array $weData,
        public array $rgData
    ) {
    }
}
