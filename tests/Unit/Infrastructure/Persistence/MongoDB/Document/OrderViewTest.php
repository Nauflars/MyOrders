<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\MongoDB\Document;

use App\Infrastructure\Persistence\MongoDB\Document\OrderView;
use PHPUnit\Framework\TestCase;

final class OrderViewTest extends TestCase
{
    public function testCanCreateOrderViewWithRequiredFields(): void
    {
        $orderId = 'order-123';
        $customerName = 'Test Customer Inc.';
        $status = 'pending';
        $totalAmount = 1500.50;
        $createdAt = new \DateTime('2026-02-18 10:00:00');

        $orderView = new OrderView(
            orderId: $orderId,
            customerName: $customerName,
            status: $status,
            totalAmount: $totalAmount,
            createdAt: $createdAt
        );

        $this->assertSame($orderId, $orderView->getOrderId());
        $this->assertSame($customerName, $orderView->getCustomerName());
        $this->assertSame($status, $orderView->getStatus());
        $this->assertSame($totalAmount, $orderView->getTotalAmount());
        $this->assertSame($createdAt, $orderView->getCreatedAt());
    }

    public function testNewOrderViewHasUpdatedAtTimestamp(): void
    {
        $createdAt = new \DateTime('2026-02-18 10:00:00');
        
        $orderView = new OrderView(
            orderId: 'order-456',
            customerName: 'Another Customer',
            status: 'pending',
            totalAmount: 2500.00,
            createdAt: $createdAt
        );

        $reflection = new \ReflectionClass($orderView);
        $property = $reflection->getProperty('updatedAt');
        $property->setAccessible(true);
        $updatedAt = $property->getValue($orderView);

        $this->assertInstanceOf(\DateTime::class, $updatedAt);
    }

    public function testUpdateStatusChangesStatus(): void
    {
        $orderView = new OrderView(
            orderId: 'order-789',
            customerName: 'Status Test Customer',
            status: 'pending',
            totalAmount: 3000.00,
            createdAt: new \DateTime()
        );

        $this->assertSame('pending', $orderView->getStatus());

        $orderView->updateStatus('confirmed');

        $this->assertSame('confirmed', $orderView->getStatus());
    }

    public function testUpdateStatusUpdatesTimestamp(): void
    {
        $orderView = new OrderView(
            orderId: 'order-101',
            customerName: 'Timestamp Test',
            status: 'pending',
            totalAmount: 500.00,
            createdAt: new \DateTime()
        );

        $reflection = new \ReflectionClass($orderView);
        $property = $reflection->getProperty('updatedAt');
        $property->setAccessible(true);
        
        $initialUpdatedAt = $property->getValue($orderView);
        
        sleep(1); // Ensure time difference

        $orderView->updateStatus('confirmed');

        $newUpdatedAt = $property->getValue($orderView);
        $this->assertGreaterThan($initialUpdatedAt, $newUpdatedAt);
    }

    public function testCanUpdateStatusMultipleTimes(): void
    {
        $orderView = new OrderView(
            orderId: 'order-202',
            customerName: 'Multi Status Test',
            status: 'pending',
            totalAmount: 1000.00,
            createdAt: new \DateTime()
        );

        $orderView->updateStatus('confirmed');
        $this->assertSame('confirmed', $orderView->getStatus());

        $orderView->updateStatus('shipped');
        $this->assertSame('shipped', $orderView->getStatus());

        $orderView->updateStatus('delivered');
        $this->assertSame('delivered', $orderView->getStatus());
    }

    public function testAddMetadataAddsKeyValuePair(): void
    {
        $orderView = new OrderView(
            orderId: 'order-303',
            customerName: 'Metadata Test',
            status: 'pending',
            totalAmount: 750.00,
            createdAt: new \DateTime()
        );

        $orderView->addMetadata('shipping_method', 'express');

        $reflection = new \ReflectionClass($orderView);
        $property = $reflection->getProperty('metadata');
        $property->setAccessible(true);
        $metadata = $property->getValue($orderView);

        $this->assertArrayHasKey('shipping_method', $metadata);
        $this->assertSame('express', $metadata['shipping_method']);
    }

    public function testAddMetadataUpdatesTimestamp(): void
    {
        $orderView = new OrderView(
            orderId: 'order-404',
            customerName: 'Metadata Timestamp Test',
            status: 'pending',
            totalAmount: 600.00,
            createdAt: new \DateTime()
        );

        $reflection = new \ReflectionClass($orderView);
        $property = $reflection->getProperty('updatedAt');
        $property->setAccessible(true);
        
        $initialUpdatedAt = $property->getValue($orderView);
        
        sleep(1); // Ensure time difference

        $orderView->addMetadata('note', 'urgent delivery');

        $newUpdatedAt = $property->getValue($orderView);
        $this->assertGreaterThan($initialUpdatedAt, $newUpdatedAt);
    }

    public function testCanAddMultipleMetadataFields(): void
    {
        $orderView = new OrderView(
            orderId: 'order-505',
            customerName: 'Multi Metadata Test',
            status: 'pending',
            totalAmount: 5000.00,
            createdAt: new \DateTime()
        );

        $orderView->addMetadata('shipping_method', 'express');
        $orderView->addMetadata('tracking_number', 'TRACK123456');
        $orderView->addMetadata('delivery_notes', 'Leave at door');

        $reflection = new \ReflectionClass($orderView);
        $property = $reflection->getProperty('metadata');
        $property->setAccessible(true);
        $metadata = $property->getValue($orderView);

        $this->assertCount(3, $metadata);
        $this->assertSame('express', $metadata['shipping_method']);
        $this->assertSame('TRACK123456', $metadata['tracking_number']);
        $this->assertSame('Leave at door', $metadata['delivery_notes']);
    }

    public function testMetadataIsInitializedAsEmptyArray(): void
    {
        $orderView = new OrderView(
            orderId: 'order-606',
            customerName: 'Empty Metadata Test',
            status: 'pending',
            totalAmount: 100.00,
            createdAt: new \DateTime()
        );

        $reflection = new \ReflectionClass($orderView);
        $property = $reflection->getProperty('metadata');
        $property->setAccessible(true);
        $metadata = $property->getValue($orderView);

        $this->assertIsArray($metadata);
        $this->assertEmpty($metadata);
    }

    public function testOrderViewWithZeroAmount(): void
    {
        $orderView = new OrderView(
            orderId: 'order-707',
            customerName: 'Zero Amount Customer',
            status: 'pending',
            totalAmount: 0.00,
            createdAt: new \DateTime()
        );

        $this->assertSame(0.00, $orderView->getTotalAmount());
    }

    public function testOrderViewPreservesCreatedAtTimestamp(): void
    {
        $createdAt = new \DateTime('2025-01-01 12:00:00');
        
        $orderView = new OrderView(
            orderId: 'order-808',
            customerName: 'Created At Test',
            status: 'pending',
            totalAmount: 999.99,
            createdAt: $createdAt
        );

        $orderView->updateStatus('confirmed');
        $orderView->addMetadata('note', 'test');

        // createdAt should not change
        $this->assertSame($createdAt, $orderView->getCreatedAt());
    }
}
