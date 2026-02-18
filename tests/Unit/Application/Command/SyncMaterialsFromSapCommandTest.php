<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command;

use App\Application\Command\SyncMaterialsFromSapCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SyncMaterialsFromSapCommandTest extends TestCase
{
    public function testCanCreateCommandWithAllProperties(): void
    {
        $customerId = '0000210839';
        $salesOrg = '1850';
        $tvkoData = ['test' => 'tvko'];
        $tvakData = ['test' => 'tvak'];
        $customerData = ['test' => 'customer'];
        $weData = ['test' => 'we'];
        $rgData = ['test' => 'rg'];

        $command = new SyncMaterialsFromSapCommand(
            customerId: $customerId,
            salesOrg: $salesOrg,
            tvkoData: $tvkoData,
            tvakData: $tvakData,
            customerData: $customerData,
            weData: $weData,
            rgData: $rgData
        );

        $this->assertSame($customerId, $command->customerId);
        $this->assertSame($salesOrg, $command->salesOrg);
        $this->assertSame($tvkoData, $command->tvkoData);
        $this->assertSame($tvakData, $command->tvakData);
        $this->assertSame($customerData, $command->customerData);
        $this->assertSame($weData, $command->weData);
        $this->assertSame($rgData, $command->rgData);
    }

    public function testCanCreateCommandWithEmptyArrays(): void
    {
        $command = new SyncMaterialsFromSapCommand(
            customerId: '0000210839',
            salesOrg: '1850',
            tvkoData: [],
            tvakData: [],
            customerData: [],
            weData: [],
            rgData: []
        );

        $this->assertSame([], $command->tvkoData);
        $this->assertSame([], $command->tvakData);
        $this->assertSame([], $command->customerData);
        $this->assertSame([], $command->weData);
        $this->assertSame([], $command->rgData);
    }

    public function testDataPropertiesAreArrayType(): void
    {
        $command = new SyncMaterialsFromSapCommand(
            customerId: '0000210839',
            salesOrg: '1850',
            tvkoData: ['a' => 'b'],
            tvakData: ['c' => 'd'],
            customerData: ['e' => 'f'],
            weData: ['g' => 'h'],
            rgData: ['i' => 'j']
        );

        $this->assertIsArray($command->tvkoData);
        $this->assertIsArray($command->tvakData);
        $this->assertIsArray($command->customerData);
        $this->assertIsArray($command->weData);
        $this->assertIsArray($command->rgData);
    }

    public function testCommandIsReadonly(): void
    {
        $reflection = new ReflectionClass(SyncMaterialsFromSapCommand::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testCommandIsFinal(): void
    {
        $reflection = new ReflectionClass(SyncMaterialsFromSapCommand::class);
        $this->assertTrue($reflection->isFinal());
    }
}