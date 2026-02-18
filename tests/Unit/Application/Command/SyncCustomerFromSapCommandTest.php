<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command;

use App\Application\Command\SyncCustomerFromSapCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SyncCustomerFromSapCommandTest extends TestCase
{
    public function testCanCreateCommandWithRequiredProperties(): void
    {
        $salesOrg = '1850';
        $customerId = '0000210839';

        $command = new SyncCustomerFromSapCommand(
            salesOrg: $salesOrg,
            customerId: $customerId
        );

        $this->assertSame($salesOrg, $command->salesOrg);
        $this->assertSame($customerId, $command->customerId);
    }

    public function testCanCreateCommandWithDifferentSalesOrgs(): void
    {
        $command1 = new SyncCustomerFromSapCommand('1850', '0000210839');
        $command2 = new SyncCustomerFromSapCommand('2000', '0000210839');

        $this->assertSame('1850', $command1->salesOrg);
        $this->assertSame('2000', $command2->salesOrg);
    }

    public function testCommandIsReadonly(): void
    {
        $reflection = new ReflectionClass(SyncCustomerFromSapCommand::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testCommandIsFinal(): void
    {
        $reflection = new ReflectionClass(SyncCustomerFromSapCommand::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function testPropertiesArePublic(): void
    {
        $reflection = new ReflectionClass(SyncCustomerFromSapCommand::class);
        
        $salesOrgProp = $reflection->getProperty('salesOrg');
        $customerIdProp = $reflection->getProperty('customerId');
        
        $this->assertTrue($salesOrgProp->isPublic());
        $this->assertTrue($customerIdProp->isPublic());
    }
}