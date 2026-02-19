<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\SyncProgress;

interface SyncProgressRepositoryInterface
{
    public function save(SyncProgress $syncProgress): void;
    
    public function findById(string $id): ?SyncProgress;
    
    public function findActiveByCustomer(string $customerId, string $salesOrg): ?SyncProgress;
    
    public function delete(SyncProgress $syncProgress): void;
}
