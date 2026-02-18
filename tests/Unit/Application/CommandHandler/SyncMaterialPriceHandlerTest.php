<?php

declare(strict_types=1);

namespace Tests\Unit\Application\CommandHandler;

use App\Application\Command\SyncMaterialPriceCommand;
use App\Application\CommandHandler\SyncMaterialPriceHandler;
use App\Domain\Entity\Customer;
use App\Domain\Entity\Material;
use App\Domain\Entity\CustomerMaterial;
use App\Domain\Repository\CustomerMaterialRepositoryInterface;
use App\Domain\Repository\CustomerRepositoryInterface;
use App\Domain\Repository\MaterialRepositoryInterface;
use App\Infrastructure\ExternalApi\SapApiClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final class SyncMaterialPriceHandlerTest extends TestCase
{
    private SapApiClientInterface $sapApiClient;
    private CustomerRepositoryInterface $customerRepository;
    private MaterialRepositoryInterface $materialRepository;
    private CustomerMaterialRepositoryInterface $customerMaterialRepository;
    private LoggerInterface $logger;
    private SyncMaterialPriceHandler $handler;

    protected function setUp(): void
    {
        $this->sapApiClient = $this->createMock(SapApiClientInterface::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->materialRepository = $this->createMock(MaterialRepositoryInterface::class);
        $this->customerMaterialRepository = $this->createMock(CustomerMaterialRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SyncMaterialPriceHandler(
            $this->sapApiClient,
            $this->customerRepository,
            $this->materialRepository,
            $this->customerMaterialRepository,
            $this->logger
        );
    }

    public function testCreatesNewCustomerMaterialWithPrice(): void
    {
        $command = new SyncMaterialPriceCommand(
            customerId: '1000',
            materialNumber: 'MAT001',
            tvkoData: ['VKORG' => '0001'],
            tvakData: [],
            customerData: [],
            weData: [],
            rgData: []
        );

        $customer = new Customer('uuid1', '1000', '0001', 'Customer', 'ES');
        $material = new Material('uuid2', 'MAT001', 'Material');

        $sapData = [
            'OUT_WA_MATNR' => [
                'NETPR' => '99.99',
                'WAERS' => 'EUR',
                'VRKME' => 'PCE',
            ],
        ];

        $this->sapApiClient->expects($this->once())
            ->method('getMaterialPrice')
            ->willReturn($sapData);

        $this->customerRepository->expects($this->once())
            ->method('findBySapId')
            ->with('1000', '0001')
            ->willReturn($customer);

        $this->materialRepository->expects($this->once())
            ->method('findBySapMaterialNumber')
            ->with('MAT001')
            ->willReturn($material);

        $this->customerMaterialRepository->expects($this->once())
            ->method('findByCustomerAndMaterial')
            ->with($customer, $material)
            ->willReturn(null);

        $this->customerMaterialRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($cm) {
                return $cm instanceof CustomerMaterial
                    && $cm->getPrice() === '99.99';
            }));

        ($this->handler)($command);
    }

    public function testUpdatesExistingCustomerMaterialPrice(): void
    {
        $command = new SyncMaterialPriceCommand(
            customerId: '1000',
            materialNumber: 'MAT001',
            tvkoData: ['VKORG' => '0001'],
            tvakData: [],
            customerData: [],
            weData: [],
            rgData: []
        );

        $customer = new Customer('uuid1', '1000', '0001', 'Customer', 'ES');
        $material = new Material('uuid2', 'MAT001', 'Material');
        $existingCM = new CustomerMaterial('uuid3', $customer, $material);
        $existingCM->updatePrice('50.00', 'EUR', ['VRKME' => 'PCE']);

        $sapData = [
            'OUT_WA_MATNR' => [
                'NETPR' => '75.50',
                'WAERS' => 'EUR',
                'VRKME' => 'BOX',
            ],
        ];

        $this->sapApiClient->method('getMaterialPrice')->willReturn($sapData);
        $this->customerRepository->method('findBySapId')->willReturn($customer);
        $this->materialRepository->method('findBySapMaterialNumber')->willReturn($material);
        
        $this->customerMaterialRepository->expects($this->once())
            ->method('findByCustomerAndMaterial')
            ->willReturn($existingCM);

        $this->customerMaterialRepository->expects($this->once())
            ->method('save');

        ($this->handler)($command);

        $this->assertEquals('75.50', $existingCM->getPrice());
    }

    public function testReturnsEarlyWhenSalesOrgMissing(): void
    {
        $command = new SyncMaterialPriceCommand(
            customerId: '1000',
            materialNumber: 'MAT001',
            tvkoData: [],
            tvakData: [],
            customerData: [],
            weData: [],
            rgData: []
        );

        $sapData = ['OUT_WA_MATNR' => []];
        $this->sapApiClient->method('getMaterialPrice')->willReturn($sapData);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Sales org not found in TVKO data');

        $this->customerRepository->expects($this->never())->method('findBySapId');

        ($this->handler)($command);
    }

    public function testReturnsEarlyWhenCustomerNotFound(): void
    {
        $command = new SyncMaterialPriceCommand(
            customerId: '1000',
            materialNumber: 'MAT001',
            tvkoData: ['VKORG' => '0001'],
            tvakData: [],
            customerData: [],
            weData: [],
            rgData: []
        );

        $sapData = ['OUT_WA_MATNR' => []];
        $this->sapApiClient->method('getMaterialPrice')->willReturn($sapData);

        $this->customerRepository->expects($this->once())
            ->method('findBySapId')
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Customer not found', $this->anything());

        $this->materialRepository->expects($this->never())->method('findBySapMaterialNumber');

        ($this->handler)($command);
    }

    public function testReturnsEarlyWhenMaterialNotFound(): void
    {
        $command = new SyncMaterialPriceCommand(
            customerId: '1000',
            materialNumber: 'MAT001',
            tvkoData: ['VKORG' => '0001'],
            tvakData: [],
            customerData: [],
            weData: [],
            rgData: []
        );

        $customer = new Customer('uuid1', '1000', '0001', 'Customer', 'ES');

        $sapData = ['OUT_WA_MATNR' => []];
        $this->sapApiClient->method('getMaterialPrice')->willReturn($sapData);
        $this->customerRepository->method('findBySapId')->willReturn($customer);

        $this->materialRepository->expects($this->once())
            ->method('findBySapMaterialNumber')
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Material not found', $this->anything());

        $this->customerMaterialRepository->expects($this->never())->method('findByCustomerAndMaterial');

        ($this->handler)($command);
    }

    public function testHandlesEmptyPriceData(): void
    {
        $command = new SyncMaterialPriceCommand(
            customerId: '1000',
            materialNumber: 'MAT001',
            tvkoData: ['VKORG' => '0001'],
            tvakData: [],
            customerData: [],
            weData: [],
            rgData: []
        );

        $customer = new Customer('uuid1', '1000', '0001', 'Customer', 'ES');
        $material = new Material('uuid2', 'MAT001', 'Material');

        $sapData = ['OUT_WA_MATNR' => []];

        $this->sapApiClient->method('getMaterialPrice')->willReturn($sapData);
        $this->customerRepository->method('findBySapId')->willReturn($customer);
        $this->materialRepository->method('findBySapMaterialNumber')->willReturn($material);
        $this->customerMaterialRepository->method('findByCustomerAndMaterial')->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('No material price data in SAP response', $this->anything());

        $this->customerMaterialRepository->expects($this->never())->method('save');

        ($this->handler)($command);
    }

    public function testHandlerIsReadonly(): void
    {
        $reflection = new ReflectionClass(SyncMaterialPriceHandler::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testHandlerIsFinal(): void
    {
        $reflection = new ReflectionClass(SyncMaterialPriceHandler::class);
        $this->assertTrue($reflection->isFinal());
    }
}