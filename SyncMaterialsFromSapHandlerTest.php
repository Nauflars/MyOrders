<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\CommandHandler;

use App\Application\Command\SyncMaterialPriceCommand;
use App\Application\Command\SyncMaterialsFromSapCommand;
use App\Application\CommandHandler\SyncMaterialsFromSapHandler;
use App\Domain\Entity\Material;
use App\Domain\Repository\MaterialRepositoryInterface;
use App\Infrastructure\ExternalApi\SapApiClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SyncMaterialsFromSapHandlerTest extends TestCase
{
    private SapApiClientInterface $sapApiClient;
    private MaterialRepositoryInterface $materialRepository;
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;
    private SyncMaterialsFromSapHandler $handler;

    protected function setUp(): void
    {
        $this->sapApiClient = $this->createMock(SapApiClientInterface::class);
        $this->materialRepository = $this->createMock(MaterialRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SyncMaterialsFromSapHandler(
            $this->sapApiClient,
            $this->materialRepository,
            $this->messageBus,
            $this->logger
        );
    }

    public function testHandlerCreatesNewMaterials(): void
    {
        $command = new SyncMaterialsFromSapCommand(
            customerId: '0000210839',
            salesOrg: '1850',
            tvkoData: ['VKORG' => '1850'],
            tvakData: ['VTWEG' => '01'],
            customerData: ['KUNNR' => '0000210839'],
            weData: [],
            rgData: []
        );

        $sapData = [
            'X_MAT_FOUND' => [
                ['MATNR' => 'MAT001', 'MAKTG' => 'Material 1'],
                ['MATNR' => 'MAT002', 'MAKTG' => 'Material 2'],
            ],
        ];

        $this->sapApiClient
            ->expects($this->once())
            ->method('loadMaterials')
            ->willReturn($sapData);

        $this->materialRepository
            ->expects($this->exactly(2))
            ->method('findBySapMaterialNumber')
            ->willReturn(null);

        $this->materialRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->isInstanceOf(Material::class));

        $this->messageBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(SyncMaterialPriceCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($command);
    }

    public function testHandlerUpdatesExistingMaterials(): void
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

        $sapData = [
            'X_MAT_FOUND' => [
                ['MATNR' => 'MAT001', 'MAKTG' => 'Updated Material'],
            ],
        ];

        $existingMaterial = $this->createMock(Material::class);

        $this->sapApiClient->method('loadMaterials')->willReturn($sapData);

        $this->materialRepository
            ->expects($this->once())
            ->method('findBySapMaterialNumber')
            ->with('MAT001')
            ->willReturn($existingMaterial);

        $existingMaterial
            ->expects($this->once())
            ->method('updateFromSapData')
            ->with($sapData['X_MAT_FOUND'][0]);

        $this->materialRepository
            ->expects($this->once())
            ->method('save')
            ->with($existingMaterial);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($command);
    }

    public function testHandlerDispatchesPriceSyncForEachMaterial(): void
    {
        $command = new SyncMaterialsFromSapCommand(
            customerId: '0000210839',
            salesOrg: '1850',
            tvkoData: ['test' => 'tvko'],
            tvakData: ['test' => 'tvak'],
            customerData: ['test' => 'customer'],
            weData: ['test' => 'we'],
            rgData: ['test' => 'rg']
        );

        $sapData = [
            'X_MAT_FOUND' => [
                ['MATNR' => 'MAT001'],
                ['MATNR' => 'MAT002'],
                ['MATNR' => 'MAT003'],
            ],
        ];

        $this->sapApiClient->method('loadMaterials')->willReturn($sapData);
        $this->materialRepository->method('findBySapMaterialNumber')->willReturn(null);

        $dispatchedMaterials = [];
        $this->messageBus
            ->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->callback(function ($cmd) use (&$dispatchedMaterials, $command) {
                if ($cmd instanceof SyncMaterialPriceCommand) {
                    $dispatchedMaterials[] = $cmd->materialNumber;
                    return $cmd->customerId === '0000210839'
                        && $cmd->tvkoData === $command->tvkoData
                        && $cmd->tvakData === $command->tvakData;
                }
                return false;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($command);

        $this->assertCount(3, $dispatchedMaterials);
        $this->assertContains('MAT001', $dispatchedMaterials);
        $this->assertContains('MAT002', $dispatchedMaterials);
        $this->assertContains('MAT003', $dispatchedMaterials);
    }

    public function testHandlerSkipsMaterialsWithoutMatnr(): void
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

        $sapData = [
            'X_MAT_FOUND' => [
                ['MATNR' => 'MAT001', 'MAKTG' => 'Valid Material'],
                ['MAKTG' => 'Invalid Material without MATNR'],
                ['MATNR' => '', 'MAKTG' => 'Empty MATNR'],
            ],
        ];

        $this->sapApiClient->method('loadMaterials')->willReturn($sapData);
        $this->materialRepository->method('findBySapMaterialNumber')->willReturn(null);

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('warning')
            ->with('Material without MATNR, skipping', $this->anything());

        // Should only process 1 material (MAT001)
        $this->materialRepository
            ->expects($this->once())
            ->method('save');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($command);
    }

    public function testHandlerLogsWarningWhenNoMaterialsReturned(): void
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

        $sapData = []; // No X_MAT_FOUND

        $this->sapApiClient->method('loadMaterials')->willReturn($sapData);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('No materials returned from SAP', $this->anything());

        $this->materialRepository
            ->expects($this->never())
            ->method('save');

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        ($this->handler)($command);
    }

    public function testHandlerLogsErrorAndRethrowsException(): void
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

        $exception = new \RuntimeException('SAP API Error');

        $this->sapApiClient
            ->expects($this->once())
            ->method('loadMaterials')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Materials sync failed',
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
}
