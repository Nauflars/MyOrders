<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\ExternalApi;

use App\Infrastructure\ExternalApi\SapApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SapApiClientTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private SapApiClient $sapApiClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->sapApiClient = new SapApiClient($this->httpClient, $this->logger);
    }

    public function testGetCustomerDataCallsSapApiWithCorrectParameters(): void
    {
        $salesOrg = '1000';
        $customerId = 'CUS001';
        $expectedResponse = [
            'KUNNR' => $customerId,
            'NAME1' => 'Test Customer',
            'VKORG' => $salesOrg,
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedResponse);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://erpqas.werfen.com/zsapui5_json/ZSDO_EBU_ORDERS_ACCESS',
                $this->callback(function ($options) use ($salesOrg, $customerId) {
                    return isset($options['json'])
                        && $options['json']['I_VKORG'] === $salesOrg
                        && $options['json']['I_FORCE_KUNNR'] === $customerId
                        && $options['auth_basic'] === ['ZWEBSERVICE', '4YVj745z']
                        && $options['timeout'] === 30;
                })
            )
            ->willReturn($response);

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        $result = $this->sapApiClient->getCustomerData($salesOrg, $customerId);

        $this->assertSame($expectedResponse, $result);
    }

    public function testLoadMaterialsCallsSapApiWithRequestData(): void
    {
        $requestData = [
            'I_WA_TVKO' => ['VKORG' => '1000'],
            'I_WA_TVAK' => ['VKORG' => '1000', 'VTWEG' => '10'],
            'I_WA_AG' => ['KUNNR' => 'CUS001'],
            'I_WA_WE' => ['KUNNR' => 'CUS001'],
            'I_WA_RG' => ['KUNNR' => 'CUS001'],
        ];

        $expectedResponse = [
            'E_T_MATNR_LIST' => [
                ['MATNR' => 'MAT001', 'MAKTG' => 'Material 1'],
                ['MATNR' => 'MAT002', 'MAKTG' => 'Material 2'],
            ],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedResponse);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://erpqas.werfen.com/zsapui5_json/ZSDO_EBU_LOAD_MATERIALS',
                $this->callback(function ($options) use ($requestData) {
                    return isset($options['json']) && $options['json'] === $requestData;
                })
            )
            ->willReturn($response);

        $result = $this->sapApiClient->loadMaterials($requestData);

        $this->assertSame($expectedResponse, $result);
    }

    public function testGetMaterialPriceCallsSapApiWithPriceParameters(): void
    {
        $customerId = 'CUS001';
        $materialNumber = 'MAT001';
        $tvkoData = ['VKORG' => '1000'];
        $tvakData = ['VKORG' => '1000', 'VTWEG' => '10'];
        $customerData = ['KUNNR' => $customerId];
        $weData = ['KUNNR' => $customerId];
        $rgData = ['KUNNR' => $customerId];

        $expectedResponse = [
            'E_WA_MATERIAL' => [
                'MATNR' => $materialNumber,
                'PRICE' => '99.99',
                'WAERK' => 'EUR',
            ],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedResponse);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://erpqas.werfen.com/zsapui5_json/ZSDO_EBU_SHOW_MATERIAL_PRICE',
                $this->callback(function ($options) use ($materialNumber) {
                    return isset($options['json'])
                        && isset($options['json']['IN_WA_MATNR'])
                        && $options['json']['IN_WA_MATNR']['MATNR'] === $materialNumber;
                })
            )
            ->willReturn($response);

        $result = $this->sapApiClient->getMaterialPrice(
            $customerId,
            $materialNumber,
            $tvkoData,
            $tvakData,
            $customerData,
            $weData,
            $rgData
        );

        $this->assertSame($expectedResponse, $result);
    }

    public function testGetCustomerDataLogsInfoBeforeRequest(): void
    {
        $salesOrg = '1000';
        $customerId = 'CUS999';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([]);

        $this->httpClient->method('request')->willReturn($response);

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('Fetching customer data from SAP'),
                $this->callback(function ($context) use ($salesOrg, $customerId) {
                    return $context['sales_org'] === $salesOrg
                        && $context['customer_id'] === $customerId;
                })
            );

        $this->sapApiClient->getCustomerData($salesOrg, $customerId);
    }

    public function testLoadMaterialsLogsInfoWithCustomerId(): void
    {
        $requestData = [
            'I_WA_AG' => ['KUNNR' => 'CUS123'],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([]);

        $this->httpClient->method('request')->willReturn($response);

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('Loading materials from SAP'),
                $this->callback(function ($context) {
                    return $context['customer'] === 'CUS123';
                })
            );

        $this->sapApiClient->loadMaterials($requestData);
    }

    public function testGetMaterialPriceLogsInfoWithParameters(): void
    {
        $customerId = 'CUS456';
        $materialNumber = 'MAT999';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([]);

        $this->httpClient->method('request')->willReturn($response);

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('Fetching material price from SAP'),
                $this->callback(function ($context) use ($customerId, $materialNumber) {
                    return $context['customer_id'] === $customerId
                        && $context['material_number'] === $materialNumber;
                })
            );

        $this->sapApiClient->getMaterialPrice(
            $customerId,
            $materialNumber,
            [],
            [],
            [],
            [],
            []
        );
    }

    public function testGetCustomerDataThrowsExceptionOnHttpFailure(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Connection timeout'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('SAP API call failed'),
                $this->callback(function ($context) {
                    return isset($context['error']) && str_contains($context['error'], 'Connection timeout');
                })
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SAP API call failed: Connection timeout');

        $this->sapApiClient->getCustomerData('1000', 'CUS001');
    }

    public function testLoadMaterialsThrowsExceptionOnHttpFailure(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Server error'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SAP API call failed: Server error');

        $this->sapApiClient->loadMaterials([]);
    }

    public function testGetMaterialPriceThrowsExceptionOnHttpFailure(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Authentication failed'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SAP API call failed: Authentication failed');

        $this->sapApiClient->getMaterialPrice('CUS001', 'MAT001', [], [], [], [], []);
    }
}
