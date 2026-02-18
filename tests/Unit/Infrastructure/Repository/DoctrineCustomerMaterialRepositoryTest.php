<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Repository;

use App\Domain\Entity\Customer;
use App\Domain\Entity\CustomerMaterial;
use App\Domain\Entity\Material;
use App\Infrastructure\Repository\DoctrineCustomerMaterialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class DoctrineCustomerMaterialRepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ManagerRegistry $registry;
    private DoctrineCustomerMaterialRepository $repository;
    private Customer $customer;
    private Material $material;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = CustomerMaterial::class;

        $this->entityManager->method('getClassMetadata')
            ->willReturn($classMetadata);

        $this->registry->method('getManagerForClass')
            ->willReturn($this->entityManager);

        $this->repository = new DoctrineCustomerMaterialRepository($this->registry);

        // Setup test entities
        $this->customer = new Customer(
            id: 'customer-123',
            sapCustomerId: 'SAP001',
            salesOrg: '1000',
            name1: 'Test Customer',
            country: 'US'
        );

        $this->material = new Material(
            id: 'material-456',
            sapMaterialNumber: 'MAT001',
            description: 'Test Material'
        );
    }

    public function testSaveCallsPersistAndFlush(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-789',
            customer: $this->customer,
            material: $this->material
        );

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($customerMaterial);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->repository->save($customerMaterial);
    }

    public function testSavePersistsCustomerMaterialToDatabase(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-101',
            customer: $this->customer,
            material: $this->material
        );

        $persistedEntity = null;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedEntity) {
                $persistedEntity = $entity;
            });

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->repository->save($customerMaterial);

        $this->assertSame($customerMaterial, $persistedEntity);
        $this->assertSame($this->customer, $persistedEntity->getCustomer());
        $this->assertSame($this->material, $persistedEntity->getMaterial());
    }

    public function testSaveFlushesChangesToDatabase(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-202',
            customer: $this->customer,
            material: $this->material
        );

        $flushCalled = false;

        $this->entityManager->method('persist');
        
        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () use (&$flushCalled) {
                $flushCalled = true;
            });

        $this->repository->save($customerMaterial);

        $this->assertTrue($flushCalled);
    }

    public function testSaveHandlesCustomerMaterialWithPrice(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-303',
            customer: $this->customer,
            material: $this->material
        );

        $customerMaterial->updatePrice('99.99', 'EUR', [
            'VRKME' => 'EA',
            'BRGEW' => 5.250,
            'GEWEI' => 'KG',
        ]);

        $persistedEntity = null;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedEntity) {
                $persistedEntity = $entity;
            });

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->repository->save($customerMaterial);

        $this->assertSame('99.99', $persistedEntity->getPrice());
        $this->assertSame('EUR', $persistedEntity->getCurrency());
    }

    public function testRepositoryImplementsCustomerMaterialRepositoryInterface(): void
    {
        $this->assertInstanceOf(
            \App\Domain\Repository\CustomerMaterialRepositoryInterface::class,
            $this->repository
        );
    }

    public function testRepositoryExtendsServiceEntityRepository(): void
    {
        $this->assertInstanceOf(
            \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository::class,
            $this->repository
        );
    }

    public function testSaveHandlesMultipleCustomerMaterials(): void
    {
        $material2 = new Material(
            id: 'material-999',
            sapMaterialNumber: 'MAT999',
            description: 'Another Material'
        );

        $customerMaterial1 = new CustomerMaterial(
            id: 'cm-401',
            customer: $this->customer,
            material: $this->material
        );

        $customerMaterial2 = new CustomerMaterial(
            id: 'cm-402',
            customer: $this->customer,
            material: $material2
        );

        $this->entityManager->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $this->repository->save($customerMaterial1);
        $this->repository->save($customerMaterial2);
    }

    public function testSavePreservesCustomerMaterialRelationships(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-505',
            customer: $this->customer,
            material: $this->material
        );

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entity) {
                return $entity instanceof CustomerMaterial
                    && $entity->getCustomer() instanceof Customer
                    && $entity->getMaterial() instanceof Material;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->repository->save($customerMaterial);
    }
}
