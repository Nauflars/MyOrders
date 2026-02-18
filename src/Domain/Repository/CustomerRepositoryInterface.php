<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Customer;

interface CustomerRepositoryInterface
{
    public function findBySapId(string $sapCustomerId, string $salesOrg): ?Customer;
    
    public function save(Customer $customer): void;
    
    public function findById(int $id): ?Customer;
}
