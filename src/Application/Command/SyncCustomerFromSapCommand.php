<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Sync customer data from SAP ERP
 */
final readonly class SyncCustomerFromSapCommand
{
    public function __construct(
        public string $salesOrg,
        public string $customerId
    ) {
    }
}
