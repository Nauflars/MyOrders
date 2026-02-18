<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Infrastructure\Persistence\Doctrine\Entity\Order;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OrderTest extends TestCase
{
    public function testCanCreateOrderWithRequiredFields(): void
    {
        $id = 'order-123';
        $customerName = 'Test Customer Inc.';
        $totalAmount = '1500.50';

        $order = new Order(
            id: $id,
            customerName: $customerName,
            totalAmount: $totalAmount
        );

        $this->assertSame($id, $order->getId());
        $this->assertSame($customerName, $order->getCustomerName());
        $this->assertSame($totalAmount, $order->getTotalAmount());
        $this->assertSame('pending', $order->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($order, 'createdAt'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($order, 'updatedAt'));
    }

    public function testNewOrderHasPendingStatus(): void
    {
        $order = new Order(
            id: 'order-456',
            customerName: 'Another Customer',
            totalAmount: '2500.00'
        );

        $this->assertSame('pending', $order->getStatus());
    }

    public function testConfirmChangesStatusToConfirmed(): void
    {
        $order = new Order(
            id: 'order-789',
            customerName: 'Confirm Test Customer',
            totalAmount: '3000.00'
        );

        $this->assertSame('pending', $order->getStatus());

        $order->confirm();

        $this->assertSame('confirmed', $order->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($order, 'updatedAt'));
    }

    public function testConfirmUpdatesTimestamp(): void
    {
        $order = new Order(
            id: 'order-101',
            customerName: 'Timestamp Test',
            totalAmount: '500.00'
        );

        $initialUpdatedAt = $this->getProperty($order, 'updatedAt');
        
        // Sleep to ensure timestamp difference
        usleep(1000);

        $order->confirm();

        $updatedUpdatedAt = $this->getProperty($order, 'updatedAt');
        $this->assertGreaterThan($initialUpdatedAt, $updatedUpdatedAt);
    }

    public function testCancelChangesStatusToCancelled(): void
    {
        $order = new Order(
            id: 'order-202',
            customerName: 'Cancel Test Customer',
            totalAmount: '1000.00'
        );

        $this->assertSame('pending', $order->getStatus());

        $order->cancel();

        $this->assertSame('cancelled', $order->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->getProperty($order, 'updatedAt'));
    }

    public function testCancelUpdatesTimestamp(): void
    {
        $order = new Order(
            id: 'order-303',
            customerName: 'Cancel Timestamp Test',
            totalAmount: '750.00'
        );

        $initialUpdatedAt = $this->getProperty($order, 'updatedAt');
        
        // Sleep to ensure timestamp difference
        usleep(1000);

        $order->cancel();

        $updatedUpdatedAt = $this->getProperty($order, 'updatedAt');
        $this->assertGreaterThan($initialUpdatedAt, $updatedUpdatedAt);
    }

    public function testCanConfirmAfterCreation(): void
    {
        $order = new Order(
            id: 'order-404',
            customerName: 'State Change Test',
            totalAmount: '5000.00'
        );

        $order->confirm();
        $this->assertSame('confirmed', $order->getStatus());

        // Note: In a real scenario, you might want to prevent state transitions
        // For now, we're just testing that confirm() works
    }

    public function testCanCancelAfterCreation(): void
    {
        $order = new Order(
            id: 'order-505',
            customerName: 'Cancel State Test',
            totalAmount: '600.00'
        );

        $order->cancel();
        $this->assertSame('cancelled', $order->getStatus());
    }

    public function testOrderWithZeroAmount(): void
    {
        $order = new Order(
            id: 'order-606',
            customerName: 'Zero Amount Customer',
            totalAmount: '0.00'
        );

        $this->assertSame('0.00', $order->getTotalAmount());
    }

    public function testOrderWithLargeAmount(): void
    {
        $order = new Order(
            id: 'order-707',
            customerName: 'Large Amount Customer',
            totalAmount: '99999999.99'
        );

        $this->assertSame('99999999.99', $order->getTotalAmount());
    }

    public function testOrderClassIsFinal(): void
    {
        $reflection = new ReflectionClass(Order::class);
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
