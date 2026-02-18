<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\CommandHandler;

use App\Application\Command\CreateOrderCommand;
use App\Application\CommandHandler\CreateOrderCommandHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final class CreateOrderCommandHandlerTest extends TestCase
{
    private LoggerInterface $logger;
    private CreateOrderCommandHandler $handler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new CreateOrderCommandHandler($this->logger);
    }

    public function testHandlerLogsOrderCreation(): void
    {
        $command = new CreateOrderCommand(
            orderId: 'ORDER-123',
            customerName: 'Test Customer',
            totalAmount: 1500.50,
            items: ['item1', 'item2']
        );

        $callCount = 0;
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->with(
                $this->callback(function ($message) use (&$callCount) {
                    $callCount++;
                    if ($callCount === 1) {
                        return $message === 'Processing CreateOrderCommand asynchronously';
                    }
                    if ($callCount === 2) {
                        return $message === 'CreateOrderCommand processed successfully';
                    }
                    return false;
                }),
                $this->callback(function ($context) use (&$callCount) {
                    if ($callCount === 1) {
                        return isset($context['orderId']) 
                            && isset($context['customerName'])
                            && isset($context['totalAmount'])
                            && isset($context['itemCount'])
                            && $context['itemCount'] === 2;
                    }
                    if ($callCount === 2) {
                        return isset($context['orderId']);
                    }
                    return false;
                })
            );

        ($this->handler)($command);
    }

    public function testHandlerProcessesCommandWithEmptyItems(): void
    {
        $command = new CreateOrderCommand(
            orderId: 'ORDER-456',
            customerName: 'Another Customer',
            totalAmount: 500.00
        );

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->with(
                $this->anything(),
                $this->callback(function ($context) {
                    static $firstCall = true;
                    if ($firstCall && isset($context['itemCount'])) {
                        $firstCall = false;
                        return $context['itemCount'] === 0;
                    }
                    return true;
                })
            );

        ($this->handler)($command);
    }

    public function testHandlerIsReadonly(): void
    {
        $reflection = new ReflectionClass(CreateOrderCommandHandler::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testHandlerIsFinal(): void
    {
        $reflection = new ReflectionClass(CreateOrderCommandHandler::class);
        $this->assertTrue($reflection->isFinal());
    }
}