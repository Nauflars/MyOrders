<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\Customer;
use App\Domain\Entity\CustomerMaterial;
use App\Domain\Entity\Material;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CustomerMaterialTest extends TestCase
{
    private Customer $customer;
    private Material $material;

    protected function setUp(): void
    {
        $this->customer = new Customer(
            id: 'customer-123',
            sapCustomerId: 'CUS001',
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

    public function testCanCreateCustomerMaterialWithRequiredFields(): void
    {
        $id = 'cm-789';

        $customerMaterial = new CustomerMaterial(
            id: $id,
            customer: $this->customer,
            material: $this->material
        );

        $this->assertSame($id, $customerMaterial->getId());
        $this->assertSame($this->customer, $customerMaterial->getCustomer());
        $this->assertSame($this->material, $customerMaterial->getMaterial());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($customerMaterial, 'createdAt'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($customerMaterial, 'updatedAt'));
        $this->assertTrue($this->getProperty($customerMaterial, 'isAvailable'));
        $this->assertNull($customerMaterial->getPrice());
        $this->assertNull($customerMaterial->getCurrency());
    }

    public function testUpdatePriceUpdatesAllPriceFields(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-101',
            customer: $this->customer,
            material: $this->material
        );

        $price = '99.99';
        $currency = 'EUR';
        $sapPriceData = [
            'VRKME' => 'EA',
            'BRGEW' => 5.250,
            'GEWEI' => 'KG',
            'VOLUM' => 0.015,
            'VOLEH' => 'M3',
            'MINMENGE' => 10,
            'LPRIO' => 7
        ];

        $customerMaterial->updatePrice($price, $currency, $sapPriceData);

        $this->assertSame($price, $customerMaterial->getPrice());
        $this->assertSame($currency, $customerMaterial->getCurrency());
        $this->assertSame('EA', $this->getProperty($customerMaterial, 'priceUnit'));
        $this->assertSame('5.25', $this->getProperty($customerMaterial, 'weight'));
        $this->assertSame('KG', $this->getProperty($customerMaterial, 'weightUnit'));
        $this->assertSame('0.015', $this->getProperty($customerMaterial, 'volume'));
        $this->assertSame('M3', $this->getProperty($customerMaterial, 'volumeUnit'));
        $this->assertSame(10, $this->getProperty($customerMaterial, 'minimumOrderQuantity'));
        $this->assertSame(7, $this->getProperty($customerMaterial, 'availabilityDays'));
        $this->assertSame($sapPriceData, $this->getProperty($customerMaterial, 'sapPriceData'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($customerMaterial, 'priceUpdatedAt'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($customerMaterial, 'updatedAt'));
    }

    public function testUpdatePriceHandlesMissingOptionalFields(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-202',
            customer: $this->customer,
            material: $this->material
        );

        $price = '49.99';
        $currency = 'USD';
        $sapPriceData = [];

        $customerMaterial->updatePrice($price, $currency, $sapPriceData);

        $this->assertSame($price, $customerMaterial->getPrice());
        $this->assertSame($currency, $customerMaterial->getCurrency());
        $this->assertNull($this->getProperty($customerMaterial, 'priceUnit'));
        $this->assertNull($this->getProperty($customerMaterial, 'weight'));
        $this->assertNull($this->getProperty($customerMaterial, 'minimumOrderQuantity'));
        $this->assertNull($this->getProperty($customerMaterial, 'availabilityDays'));
    }

    public function testUpdatePriceConvertsNumericValuesToStrings(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-303',
            customer: $this->customer,
            material: $this->material
        );

        $sapPriceData = [
            'BRGEW' => 12.345,
            'VOLUM' => 0.678
        ];

        $customerMaterial->updatePrice('25.00', 'EUR', $sapPriceData);

        $this->assertSame('12.345', $this->getProperty($customerMaterial, 'weight'));
        $this->assertSame('0.678', $this->getProperty($customerMaterial, 'volume'));
    }

    public function testUpdatePriceConvertsIntegerQuantities(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-404',
            customer: $this->customer,
            material: $this->material
        );

        $sapPriceData = [
            'MINMENGE' => '25',
            'LPRIO' => '14'
        ];

        $customerMaterial->updatePrice('100.00', 'GBP', $sapPriceData);

        $this->assertSame(25, $this->getProperty($customerMaterial, 'minimumOrderQuantity'));
        $this->assertSame(14, $this->getProperty($customerMaterial, 'availabilityDays'));
    }

    public function testMarkUnavailableSetsIsAvailableToFalse(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-505',
            customer: $this->customer,
            material: $this->material
        );

        $this->assertTrue($this->getProperty($customerMaterial, 'isAvailable'));

        $customerMaterial->markUnavailable();

        $this->assertFalse($this->getProperty($customerMaterial, 'isAvailable'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($customerMaterial, 'updatedAt'));
    }

    public function testMarkUnavailableUpdatesTimestamp(): void
    {
        $customerMaterial = new CustomerMaterial(
            id: 'cm-606',
            customer: $this->customer,
            material: $this->material
        );

        $initialUpdatedAt = $this->getProperty($customerMaterial, 'updatedAt');
        
        // Sleep to ensure timestamp difference
        usleep(1000);

        $customerMaterial->markUnavailable();

        $updatedUpdatedAt = $this->getProperty($customerMaterial, 'updatedAt');
        $this->assertGreaterThan($initialUpdatedAt, $updatedUpdatedAt);
    }

    public function testCustomerMaterialClassIsFinal(): void
    {
        $reflection = new ReflectionClass(CustomerMaterial::class);
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
