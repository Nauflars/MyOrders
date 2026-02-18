<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\Customer;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    public function testCanCreateCustomerWithRequiredFields(): void
    {
        $id = 'customer-123';
        $sapCustomerId = 'SAP001';
        $salesOrg = '1000';
        $name1 = 'Test Customer Inc.';
        $country = 'US';

        $customer = new Customer(
            id: $id,
            sapCustomerId: $sapCustomerId,
            salesOrg: $salesOrg,
            name1: $name1,
            country: $country
        );

        $this->assertSame($id, $customer->getId());
        $this->assertSame($sapCustomerId, $customer->getSapCustomerId());
        $this->assertSame($salesOrg, $customer->getSalesOrg());
        $this->assertSame($name1, $customer->getName());
    }

    public function testGetNameReturnsOnlyName1WhenName2IsNull(): void
    {
        $customer = new Customer(
            id: 'customer-456',
            sapCustomerId: 'SAP002',
            salesOrg: '1000',
            name1: 'Customer Name',
            country: 'DE'
        );

        $this->assertSame('Customer Name', $customer->getName());
    }

    public function testGetNameCombinesName1AndName2(): void
    {
        $customer = new Customer(
            id: 'customer-789',
            sapCustomerId: 'SAP003',
            salesOrg: '1000',
            name1: 'Customer',
            country: 'FR'
        );

        $sapData = [
            'NAME2' => 'GmbH',
            'STRAS' => 'Main Street 123',
            'ORT01' => 'Berlin',
            'PSTLZ' => '10115',
            'REGIO' => 'BE',
            'WAERK' => 'EUR',
            'INCO1' => 'EXW',
            'VSBED' => '01',
            'ZTERM' => '0001',
            'TAXK1' => 'TAXEU',
            'STCEG' => 'DE123456789'
        ];

        $customer->updateFromSapData($sapData);

        $this->assertSame('Customer GmbH', $customer->getName());
    }

    public function testUpdateFromSapDataUpdatesAllFields(): void
    {
        $customer = new Customer(
            id: 'customer-101',
            sapCustomerId: 'SAP004',
            salesOrg: '2000',
            name1: 'Test Corp',
            country: 'GB'
        );

        $sapData = [
            'NAME2' => 'Limited',
            'STRAS' => 'Oxford Street 456',
            'ORT01' => 'London',
            'PSTLZ' => 'SW1A 1AA',
            'REGIO' => 'LDN',
            'WAERK' => 'GBP',
            'INCO1' => 'FOB',
            'VSBED' => '02',
            'ZTERM' => '0014',
            'TAXK1' => 'TAXGB',
            'STCEG' => 'GB987654321'
        ];

        $customer->updateFromSapData($sapData);

        // Verify all fields were updated correctly via reflection
        $reflection = new \ReflectionClass($customer);
        
        $this->assertSame('Limited', $this->getPropertyValue($reflection, $customer, 'name2'));
        $this->assertSame('Oxford Street 456', $this->getPropertyValue($reflection, $customer, 'street'));
        $this->assertSame('London', $this->getPropertyValue($reflection, $customer, 'city'));
        $this->assertSame('SW1A 1AA', $this->getPropertyValue($reflection, $customer, 'postalCode'));
        $this->assertSame('LDN', $this->getPropertyValue($reflection, $customer, 'region'));
        $this->assertSame('GBP', $this->getPropertyValue($reflection, $customer, 'currency'));
        $this->assertSame('FOB', $this->getPropertyValue($reflection, $customer, 'incoterms'));
        $this->assertSame('02', $this->getPropertyValue($reflection, $customer, 'shippingCondition'));
        $this->assertSame('0014', $this->getPropertyValue($reflection, $customer, 'paymentTerms'));
        $this->assertSame('TAXGB', $this->getPropertyValue($reflection, $customer, 'taxClass'));
        $this->assertSame('GB987654321', $this->getPropertyValue($reflection, $customer, 'vatNumber'));
        $this->assertSame($sapData, $this->getPropertyValue($reflection, $customer, 'sapData'));
    }

    public function testUpdateFromSapDataHandlesMissingFields(): void
    {
        $customer = new Customer(
            id: 'customer-202',
            sapCustomerId: 'SAP005',
            salesOrg: '3000',
            name1: 'Minimal Customer',
            country: 'ES'
        );

        $sapData = [
            'WAERK' => 'EUR'
        ];

        $customer->updateFromSapData($sapData);

        $reflection = new \ReflectionClass($customer);
        
        $this->assertNull($this->getPropertyValue($reflection, $customer, 'name2'));
        $this->assertNull($this->getPropertyValue($reflection, $customer, 'street'));
        $this->assertNull($this->getPropertyValue($reflection, $customer, 'city'));
        $this->assertSame('EUR', $this->getPropertyValue($reflection, $customer, 'currency'));
    }

    public function testUpdateFromSapDataUpdatesTimestamps(): void
    {
        $customer = new Customer(
            id: 'customer-303',
            sapCustomerId: 'SAP006',
            salesOrg: '4000',
            name1: 'Timestamp Test',
            country: 'IT'
        );

        $reflection = new \ReflectionClass($customer);
        $initialLastSync = $this->getPropertyValue($reflection, $customer, 'lastSyncAt');
        $initialUpdatedAt = $this->getPropertyValue($reflection, $customer, 'updatedAt');

        usleep(1000); // Ensure time difference

        $customer->updateFromSapData(['WAERK' => 'EUR']);

        $newLastSync = $this->getPropertyValue($reflection, $customer, 'lastSyncAt');
        $newUpdatedAt = $this->getPropertyValue($reflection, $customer, 'updatedAt');

        $this->assertGreaterThan($initialLastSync, $newLastSync);
        $this->assertGreaterThan($initialUpdatedAt, $newUpdatedAt);
    }

    public function testGetMaterialsReturnsEmptyCollection(): void
    {
        $customer = new Customer(
            id: 'customer-404',
            sapCustomerId: 'SAP007',
            salesOrg: '5000',
            name1: 'Materials Test',
            country: 'FR'
        );

        $materials = $customer->getMaterials();

        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $materials);
        $this->assertCount(0, $materials);
    }

    public function testCustomerHasProperSalesOrgAndCustomerId(): void
    {
        $sapCustomerId = '0000123456';
        $salesOrg = '1000';
        
        $customer = new Customer(
            id: 'customer-505',
            sapCustomerId: $sapCustomerId,
            salesOrg: $salesOrg,
            name1: 'SAP Test Customer',
            country: 'DE'
        );

        $this->assertSame($sapCustomerId, $customer->getSapCustomerId());
        $this->assertSame($salesOrg, $customer->getSalesOrg());
    }

    private function getPropertyValue(\ReflectionClass $reflection, object $object, string $propertyName): mixed
    {
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
