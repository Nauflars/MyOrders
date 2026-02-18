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

    /**
     * Find customer material by POSNR (SAP position number)
     * 
     * @param string $posnr 6-digit SAP position number
     * @param string|null $customerId Optional customer filter
     * @param string|null $salesOrg Optional sales organization filter
     * @return CustomerMaterial|null
     */
    public function findByPosnr(
        string $posnr,
        ?string $customerId = null,
        ?string $salesOrg = null
    ): ?CustomerMaterial {
        $qb = $this->createQueryBuilder('cm')
            ->where('cm.posnr = :posnr')
            ->setParameter('posnr', $posnr);

        if ($customerId !== null) {
            $qb->andWhere('cm.customer_id = :customerId')
               ->setParameter('customerId', $customerId);
        }

        if ($salesOrg !== null) {
            $qb->andWhere('cm.sales_org = :salesOrg')
               ->setParameter('salesOrg', $salesOrg);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find all materials for customer and sales organization
     * 
     * @param string $customerId
     * @param string $salesOrg
     * @return CustomerMaterial[]
     */
    public function findByCustomerAndSalesOrg(
        string $customerId,
        string $salesOrg
    ): array {
        return $this->createQueryBuilder('cm')
            ->where('cm.customer_id = :customerId')
            ->andWhere('cm.sales_org = :salesOrg')
            ->setParameter('customerId', $customerId)
            ->setParameter('salesOrg', $salesOrg)
            ->orderBy('cm.material_number', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
