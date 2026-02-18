<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Material;

interface MaterialRepositoryInterface
{
    public function findBySapMaterialNumber(string $materialNumber): ?Material;
    
    public function save(Material $material): void;
    
    public function findById(int $id): ?Material;
}
