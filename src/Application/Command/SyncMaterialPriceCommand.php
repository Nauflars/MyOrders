<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Sync material price for a specific customer from SAP
 * 
 * Includes POSNR (SAP position number) for accurate pricing retrieval
 */
final readonly class SyncMaterialPriceCommand
{
    public function __construct(
        public string $customerId,
        public string $materialNumber,
        public string $salesOrg,
        public ?string $posnr = null
    ) {
    }
}
