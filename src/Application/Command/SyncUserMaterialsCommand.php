<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * SyncUserMaterialsCommand - Sync all materials for a user/customer
 * 
 * Fetches materials from SAP and updates database with pricing information.
 * Uses POSNR field for accurate pricing.
 */
final readonly class SyncUserMaterialsCommand
{
    public function __construct(
        public string $customerId,
        public string $salesOrg,
        public bool $forcePriceUpdate = false
    ) {
    }
}
