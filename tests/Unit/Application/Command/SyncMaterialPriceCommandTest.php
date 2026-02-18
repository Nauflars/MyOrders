<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command;

use App\Application\Command\SyncMaterialPriceCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SyncMaterialPriceCommandTest extends TestCase
{
    public function testCanCreateCommandWithAllProperties(): void
    {
        $customerId = '0000210839';
        $materialNumber = 'MAT001';
        $tvkoData = ['test' => 'tvko'];
        $tvakData = ['test' => 'tvak'];
        $customerData = ['test' => 'customer'];
        $weData = ['test' => 'we'];
        $rgData = ['test' => 'rg'];

        $command = new SyncMaterialPriceCommand(
            customerId: $customerId,
            materialNumber: $materialNumber,
            tvkoData: $tvkoData,
            tvakData: $tvakData,
            customerData: $customerData,
            weData: $weData,
            rgData: $rgData
        );

        $this->assertSame($customerId, $command->customerId);
        $this->assertSame($materialNumber, $command->materialNumber);
        $this->assertSame($tvkoData, $command->tvkoData);
        $this->assertSame($tvakData, $command->tvakData);
        $this->assertSame($customerData, $command->customerData);
        $this->assertSame($weData, $command->weData);
        $this->assertSame($rgData, $command->rgData);
    }

    public function testStringPropertiesAreStringType(): void
    {
        $command = new SyncMaterialPriceCommand(
            customerId: '0000210839',
            materialNumber: 'MAT001',
            tvkoData: [],
            tvakData: [],
            customerData: [],
            weData: [],
            rgData: []
        );

        $this->assertIsString($command->customerId);
        $this->assertIsString($command->materialNumber);
    }

    public function testDataPropertiesAreArrayType(): void
    {
        $command = new SyncMaterialPriceCommand(
            customerId: '0000210839',
            materialNumber: 'MAT001',
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
        $reflection = new ReflectionClass(SyncMaterialPriceCommand::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testCommandIsFinal(): void
    {
        $reflection = new ReflectionClass(SyncMaterialPriceCommand::class);
        $this->assertTrue($reflection->isFinal());
    }
}