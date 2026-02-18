<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Material;
use App\Domain\Repository\MaterialRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Material>
 */
class DoctrineMaterialRepository extends ServiceEntityRepository implements MaterialRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Material::class);
    }

    public function findBySapMaterialNumber(string $materialNumber): ?Material
    {
        return $this->findOneBy([
            'sapMaterialNumber' => $materialNumber,
        ]);
    }

    public function save(Material $material): void
    {
        $em = $this->getEntityManager();
        $em->persist($material);
        $em->flush();
    }

    public function findById(int $id): ?Material
    {
        return $this->find($id);
    }
}
