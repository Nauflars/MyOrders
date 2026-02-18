<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Repository;

use App\Domain\Entity\Material;
use App\Infrastructure\Repository\DoctrineMaterialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class DoctrineMaterialRepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ManagerRegistry $registry;
    private DoctrineMaterialRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = Material::class;

        $this->entityManager->method('getClassMetadata')
            ->willReturn($classMetadata);

        $this->registry->method('getManagerForClass')
            ->willReturn($this->entityManager);

        $this->repository = new DoctrineMaterialRepository($this->registry);
    }

    public function testSaveCallsPersistAndFlush(): void
    {
        $material = new Material(
            id: 'material-123',
            sapMaterialNumber: 'MAT001',
            description: 'Test Material'
        );

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($material);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->repository->save($material);
    }

    public function testSavePersistsMaterialToDatabase(): void
    {
        $material = new Material(
            id: 'material-456',
            sapMaterialNumber: 'MAT002',
            description: 'Another Material'
        );

        $persistedMaterial = null;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedMaterial) {
                $persistedMaterial = $entity;
            });

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->repository->save($material);

        $this->assertSame($material, $persistedMaterial);
    }

    public function testSaveFlushesChangesToDatabase(): void
    {
        $material = new Material(
            id: 'material-789',
            sapMaterialNumber: 'MAT003',
            description: 'Flush Test Material'
        );

        $flushCalled = false;

        $this->entityManager->method('persist');
        
        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () use (&$flushCalled) {
                $flushCalled = true;
            });

        $this->repository->save($material);

        $this->assertTrue($flushCalled);
    }

    public function testRepositoryImplementsMaterialRepositoryInterface(): void
    {
        $this->assertInstanceOf(
            \App\Domain\Repository\MaterialRepositoryInterface::class,
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

    public function testSaveHandlesMultipleMaterials(): void
    {
        $material1 = new Material(
            id: 'material-101',
            sapMaterialNumber: 'MAT101',
            description: 'Material One'
        );

        $material2 = new Material(
            id: 'material-102',
            sapMaterialNumber: 'MAT102',
            description: 'Material Two'
        );

        $this->entityManager->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $this->repository->save($material1);
        $this->repository->save($material2);
    }
}
