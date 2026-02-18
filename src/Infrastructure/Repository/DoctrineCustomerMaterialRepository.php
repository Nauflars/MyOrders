<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Customer;
use App\Domain\Entity\CustomerMaterial;
use App\Domain\Entity\Material;
use App\Domain\Repository\CustomerMaterialRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerMaterial>
 */
class DoctrineCustomerMaterialRepository extends ServiceEntityRepository implements CustomerMaterialRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerMaterial::class);
    }

    public function findByCustomerAndMaterial(Customer $customer, Material $material): ?CustomerMaterial
    {
        return $this->findOneBy([
            'customer' => $customer,
            'material' => $material,
        ]);
    }

    public function save(CustomerMaterial $customerMaterial): void
    {
        $em = $this->getEntityManager();
        $em->persist($customerMaterial);
        $em->flush();
    }

    public function findById(int $id): ?CustomerMaterial
    {
        return $this->find($id);
    }
}
