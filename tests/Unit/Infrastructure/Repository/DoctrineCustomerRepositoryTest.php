<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Repository;

use App\Domain\Entity\Customer;
use App\Infrastructure\Repository\DoctrineCustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class DoctrineCustomerRepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ManagerRegistry $registry;
    private DoctrineCustomerRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = Customer::class;

        $this->entityManager->method('getClassMetadata')
            ->willReturn($classMetadata);

        $this->registry->method('getManagerForClass')
            ->willReturn($this->entityManager);

        $this->repository = new DoctrineCustomerRepository($this->registry);
    }

    public function testSaveCallsPersistAndFlush(): void
    {
        $customer = new Customer(
            id: 'customer-123',
            sapCustomerId: 'SAP001',
            salesOrg: '1000',
            name1: 'Test Customer',
            country: 'US'
        );

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($customer);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->repository->save($customer);
    }

    public function testSavePersistsCustomerToDatabase(): void
    {
        $customer = new Customer(
            id: 'customer-456',
            sapCustomerId: 'SAP002',
            salesOrg: '2000',
            name1: 'Another Customer',
            country: 'DE'
        );

        $persistedCustomer = null;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedCustomer) {
                $persistedCustomer = $entity;
            });

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->repository->save($customer);

        $this->assertSame($customer, $persistedCustomer);
    }

    public function testSaveFlushesChangesToDatabase(): void
    {
        $customer = new Customer(
            id: 'customer-789',
            sapCustomerId: 'SAP003',
            salesOrg: '3000',
            name1: 'Flush Test Customer',
            country: 'FR'
        );

        $flushCalled = false;

        $this->entityManager->method('persist');
        
        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () use (&$flushCalled) {
                $flushCalled = true;
            });

        $this->repository->save($customer);

        $this->assertTrue($flushCalled);
    }

    public function testRepositoryImplementsCustomerRepositoryInterface(): void
    {
        $this->assertInstanceOf(
            \App\Domain\Repository\CustomerRepositoryInterface::class,
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
}
