<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command;

use App\Application\Command\CreateOrderCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CreateOrderCommandTest extends TestCase
{
    public function testCanCreateCommandWithAllProperties(): void
    {
        $orderId = 'ORDER-123';
        $customerName = 'Test Customer';
        $totalAmount = 1500.50;
        $items = ['item1', 'item2'];

        $command = new CreateOrderCommand(
            orderId: $orderId,
            customerName: $customerName,
            totalAmount: $totalAmount,
            items: $items
        );

        $this->assertSame($orderId, $command->orderId);
        $this->assertSame($customerName, $command->customerName);
        $this->assertSame($totalAmount, $command->totalAmount);
        $this->assertSame($items, $command->items);
    }

    public function testCanCreateCommandWithEmptyItems(): void
    {
        $command = new CreateOrderCommand(
            orderId: 'ORDER-456',
            customerName: 'Another Customer',
            totalAmount: 500.00
        );

        $this->assertSame([], $command->items);
    }

    public function testCommandIsReadonly(): void
    {
        $reflection = new ReflectionClass(CreateOrderCommand::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testCommandIsFinal(): void
    {
        $reflection = new ReflectionClass(CreateOrderCommand::class);
        $this->assertTrue($reflection->isFinal());
    }
}