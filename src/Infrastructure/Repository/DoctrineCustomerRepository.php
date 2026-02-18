<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Customer;
use App\Domain\Repository\CustomerRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class DoctrineCustomerRepository extends ServiceEntityRepository implements CustomerRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function findBySapId(string $sapCustomerId, string $salesOrg): ?Customer
    {
        return $this->findOneBy([
            'sapCustomerId' => $sapCustomerId,
            'salesOrg' => $salesOrg,
        ]);
    }

    public function save(Customer $customer): void
    {
        $em = $this->getEntityManager();
        $em->persist($customer);
        $em->flush();
    }

    public function findById(int $id): ?Customer
    {
        return $this->find($id);
    }
}
