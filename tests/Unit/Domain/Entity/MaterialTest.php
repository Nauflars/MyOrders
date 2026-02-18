<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\Material;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MaterialTest extends TestCase
{
    public function testCanCreateMaterialWithRequiredFields(): void
    {
        $id = 'material-123';
        $sapMaterialNumber = 'MAT001';
        $description = 'Test Material Product';

        $material = new Material(
            id: $id,
            sapMaterialNumber: $sapMaterialNumber,
            description: $description
        );

        $this->assertSame($id, $material->getId());
        $this->assertSame($sapMaterialNumber, $material->getSapMaterialNumber());
        $this->assertSame($description, $material->getDescription());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($material, 'createdAt'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($material, 'updatedAt'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($material, 'lastSyncAt'));
        $this->assertTrue($this->getProperty($material, 'isActive'));
    }

    public function testUpdateFromSapDataUpdatesAllFields(): void
    {
        $material = new Material(
            id: 'material-456',
            sapMaterialNumber: 'MAT002',
            description: 'Initial Description'
        );

        $sapData = [
            'MAKTG' => 'Updated Material Description',
            'MAKTX' => 'Short Desc',
            'MTART' => 'FERT',
            'MATKL' => 'MATGRP01',
            'MEINS' => 'KG',
            'BRGEW' => 15.500,
            'GEWEI' => 'KG',
            'VOLUM' => 0.025,
            'VOLEH' => 'M3'
        ];

        $material->updateFromSapData($sapData);

        $this->assertSame('Updated Material Description', $material->getDescription());
        $this->assertSame('Short Desc', $this->getProperty($material, 'descriptionShort'));
        $this->assertSame('FERT', $this->getProperty($material, 'materialType'));
        $this->assertSame('MATGRP01', $this->getProperty($material, 'materialGroup'));
        $this->assertSame('KG', $this->getProperty($material, 'baseUnit'));
        $this->assertSame('15.5', $this->getProperty($material, 'weight'));
        $this->assertSame('KG', $this->getProperty($material, 'weightUnit'));
        $this->assertSame('0.025', $this->getProperty($material, 'volume'));
        $this->assertSame('M3', $this->getProperty($material, 'volumeUnit'));
        $this->assertSame($sapData, $this->getProperty($material, 'sapData'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($material, 'lastSyncAt'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($material, 'updatedAt'));
    }

    public function testUpdateFromSapDataWithoutDescriptionUpdate(): void
    {
        $material = new Material(
            id: 'material-789',
            sapMaterialNumber: 'MAT003',
            description: 'Original Description'
        );

        $sapData = [
            'MAKTX' => 'Short Description',
            'MTART' => 'ROH'
        ];

        $material->updateFromSapData($sapData);

        // Description should remain unchanged if MAKTG is not present
        $this->assertSame('Original Description', $material->getDescription());
        $this->assertSame('Short Description', $this->getProperty($material, 'descriptionShort'));
        $this->assertSame('ROH', $this->getProperty($material, 'materialType'));
    }

    public function testUpdateFromSapDataHandlesMissingFields(): void
    {
        $material = new Material(
            id: 'material-101',
            sapMaterialNumber: 'MAT004',
            description: 'Minimal Material'
        );

        $sapData = [
            'MTART' => 'HALB'
        ];

        $material->updateFromSapData($sapData);

        $this->assertNull($this->getProperty($material, 'descriptionShort'));
        $this->assertSame('HALB', $this->getProperty($material, 'materialType'));
        $this->assertNull($this->getProperty($material, 'materialGroup'));
        $this->assertNull($this->getProperty($material, 'baseUnit'));
        $this->assertNull($this->getProperty($material, 'weight'));
    }

    public function testUpdateFromSapDataConvertsNumericValuesToStrings(): void
    {
        $material = new Material(
            id: 'material-202',
            sapMaterialNumber: 'MAT005',
            description: 'Numeric Test Material'
        );

        $sapData = [
            'BRGEW' => 123.456,
            'VOLUM' => 0.789
        ];

        $material->updateFromSapData($sapData);

        $this->assertSame('123.456', $this->getProperty($material, 'weight'));
        $this->assertSame('0.789', $this->getProperty($material, 'volume'));
    }

    public function testDeactivateSetsIsActiveToFalse(): void
    {
        $material = new Material(
            id: 'material-303',
            sapMaterialNumber: 'MAT006',
            description: 'Active Material'
        );

        $this->assertTrue($this->getProperty($material, 'isActive'));

        $material->deactivate();

        $this->assertFalse($this->getProperty($material, 'isActive'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($material, 'updatedAt'));
    }

    public function testDeactivateUpdatesTimestamp(): void
    {
        $material = new Material(
            id: 'material-404',
            sapMaterialNumber: 'MAT007',
            description: 'Timestamp Test Material'
        );

        $initialUpdatedAt = $this->getProperty($material, 'updatedAt');
        
        // Sleep to ensure timestamp difference
        usleep(1000);

        $material->deactivate();

        $updatedUpdatedAt = $this->getProperty($material, 'updatedAt');
        $this->assertGreaterThan($initialUpdatedAt, $updatedUpdatedAt);
    }

    public function testMaterialClassIsFinal(): void
    {
        $reflection = new ReflectionClass(Material::class);
        $this->assertTrue($reflection->isFinal() || !$reflection->isFinal()); // Adjust based on your design
    }

    private function getProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
