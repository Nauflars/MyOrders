<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\CommandHandler;

use App\Application\Command\SyncCustomerFromSapCommand;
use App\Application\Command\SyncMaterialsFromSapCommand;
use App\Application\CommandHandler\SyncCustomerFromSapHandler;
use App\Domain\Entity\Customer;
use App\Domain\Repository\CustomerRepositoryInterface;
use App\Infrastructure\ExternalApi\SapApiClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SyncCustomerFromSapHandlerTest extends TestCase
{
    private SapApiClientInterface $sapApiClient;
    private CustomerRepositoryInterface $customerRepository;
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;
    private SyncCustomerFromSapHandler $handler;

    protected function setUp(): void
    {
        $this->sapApiClient = $this->createMock(SapApiClientInterface::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SyncCustomerFromSapHandler(
            $this->sapApiClient,
            $this->customerRepository,
            $this->messageBus,
            $this->logger
        );
    }

    public function testHandlerCreatesNewCustomerWhenNotExists(): void
    {
        $command = new SyncCustomerFromSapCommand(
            salesOrg: '1850',
            customerId: '0000210839'
        );

        $sapData = [
            'NAME1' => 'Test Customer',
            'LAND1' => 'ES',
            'WA_TVKO' => ['VKORG' => '1850'],
            'WA_TVAK' => ['VTWEG' => '01'],
            'WA_AG' => ['KUNNR' => '0000210839'],
        ];

        $this->sapApiClient
            ->expects($this->once())
            ->method('getCustomerData')
            ->with('1850', '0000210839')
            ->willReturn($sapData);

        $this->customerRepository
            ->expects($this->once())
            ->method('findBySapId')
            ->with('0000210839', '1850')
            ->willReturn(null);

        $this->customerRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Customer $customer) {
                return $customer->getSapCustomerId() === '0000210839'
                    && $customer->getSalesOrg() === '1850';
            }));

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SyncMaterialsFromSapCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($command);
    }

    public function testHandlerUpdatesExistingCustomer(): void
    {
        $command = new SyncCustomerFromSapCommand(
            salesOrg: '1850',
            customerId: '0000210839'
        );

        $sapData = [
            'NAME1' => 'Updated Customer Name',
            'LAND1' => 'FR',
            'WA_TVKO' => ['VKORG' => '1850'],
            'WA_TVAK' => ['VTWEG' => '01'],
            'WA_AG' => ['KUNNR' => '0000210839'],
        ];

        $existingCustomer = $this->createMock(Customer::class);
        $existingCustomer->method('getName')->willReturn('Updated Customer Name');

        $this->sapApiClient
            ->expects($this->once())
            ->method('getCustomerData')
            ->willReturn($sapData);

        $this->customerRepository
            ->expects($this->once())
            ->method('findBySapId')
            ->willReturn($existingCustomer);

        $existingCustomer
            ->expects($this->once())
            ->method('updateFromSapData')
            ->with($sapData);

        $this->customerRepository
            ->expects($this->once())
            ->method('save')
            ->with($existingCustomer);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($command);
    }

    public function testHandlerDispatchesMaterialsSyncCommand(): void
    {
        $command = new SyncCustomerFromSapCommand(
            salesOrg: '1850',
            customerId: '0000210839'
        );

        $sapData = [
            'NAME1' => 'Test Customer',
            'WA_TVKO' => ['VKORG' => '1850'],
            'WA_TVAK' => ['VTWEG' => '01'],
            'WA_AG' => ['KUNNR' => '0000210839'],
            'WA_WE' => ['WERKS' => 'W001'],
            'WA_RG' => ['REGION' => 'R001'],
        ];

        $this->sapApiClient->method('getCustomerData')->willReturn($sapData);
        $this->customerRepository->method('findBySapId')->willReturn(null);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($sapData) {
                return $command instanceof SyncMaterialsFromSapCommand
                    && $command->customerId === '0000210839'
                    && $command->salesOrg === '1850'
                    && $command->tvkoData === $sapData['WA_TVKO']
                    && $command->tvakData === $sapData['WA_TVAK']
                    && $command->customerData === $sapData['WA_AG']
                    && $command->weData === $sapData['WA_WE']
                    && $command->rgData === $sapData['WA_RG'];
            }))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($command);
    }

    public function testHandlerLogsErrorAndRethrowsException(): void
    {
        $command = new SyncCustomerFromSapCommand(
            salesOrg: '1850',
            customerId: '0000210839'
        );

        $exception = new \RuntimeException('SAP API Error');

        $this->sapApiClient
            ->expects($this->once())
            ->method('getCustomerData')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Customer sync failed',
                $this->callback(function ($context) {
                    return isset($context['customer_id'])
                        && isset($context['error'])
                        && isset($context['trace']);
                })
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SAP API Error');

        ($this->handler)($command);
    }

    public function testHandlerDoesNotDispatchMaterialsSyncWhenDataMissing(): void
    {
        $command = new SyncCustomerFromSapCommand(
            salesOrg: '1850',
            customerId: '0000210839'
        );

        // SAP data missing WA_TVKO, WA_TVAK, or WA_AG
        $sapData = [
            'NAME1' => 'Test Customer',
            'WA_TVKO' => ['VKORG' => '1850'],
            // WA_TVAK missing
            // WA_AG missing
        ];

        $this->sapApiClient->method('getCustomerData')->willReturn($sapData);
        $this->customerRepository->method('findBySapId')->willReturn(null);

        // MessageBus should NOT be called
        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        ($this->handler)($command);
    }
}
