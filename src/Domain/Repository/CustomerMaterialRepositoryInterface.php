<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Customer;
use App\Domain\Entity\CustomerMaterial;
use App\Domain\Entity\Material;

interface CustomerMaterialRepositoryInterface
{
    public function findByCustomerAndMaterial(Customer $customer, Material $material): ?CustomerMaterial;
    
    public function save(CustomerMaterial $customerMaterial): void;
    
    public function findById(int $id): ?CustomerMaterial;
}
