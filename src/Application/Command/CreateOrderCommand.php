<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Example Command Message for Async Processing
 * 
 * This message will be sent to RabbitMQ and processed asynchronously.
 * Demonstrates the CQRS command pattern with async messaging.
 */
final readonly class CreateOrderCommand
{
    public function __construct(
        public string $orderId,
        public string $customerName,
        public float $totalAmount,
        public array $items = []
    ) {
    }
}
