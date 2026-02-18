<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Sync material price for a specific customer from SAP
 */
final readonly class SyncMaterialPriceCommand
{
    public function __construct(
        public string $customerId,
        public string $materialNumber,
        public array $tvkoData,
        public array $tvakData,
        public array $customerData,
        public array $weData,
        public array $rgData
    ) {
    }
}
