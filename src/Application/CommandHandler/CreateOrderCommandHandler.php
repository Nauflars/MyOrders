<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\CreateOrderCommand;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for CreateOrderCommand
 * 
 * This handler will be invoked asynchronously by Symfony Messenger
 * when a CreateOrderCommand is dispatched to the message bus.
 */
#[AsMessageHandler]
final readonly class CreateOrderCommandHandler
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(CreateOrderCommand $command): void
    {
        $this->logger->info('Processing CreateOrderCommand asynchronously', [
            'orderId' => $command->orderId,
            'customerName' => $command->customerName,
            'totalAmount' => $command->totalAmount,
            'itemCount' => count($command->items),
        ]);

        // TODO: Implement actual order creation logic
        // 1. Create domain entity
        // 2. Persist to MySQL (write model)
        // 3. Emit domain event
        // 4. Update MongoDB read model (via event listener)

        $this->logger->info('CreateOrderCommand processed successfully', [
            'orderId' => $command->orderId,
        ]);
    }
}
