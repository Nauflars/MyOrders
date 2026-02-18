<?php

declare(strict_types=1);

namespace App\Infrastructure\ExternalApi;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * SAP API Client
 * 
 * Handles communication with SAP ERP system via REST APIs.
 */
final readonly class SapApiClient
{
    private const BASE_URL = 'https://erpqas.werfen.com/zsapui5_json';
    private const USERNAME = 'ZWEBSERVICE';
    private const PASSWORD = '4YVj745z';
    
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get customer data from SAP
     */
    public function getCustomerData(string $salesOrg, string $customerId): array
    {
        $this->logger->info('Fetching customer data from SAP', [
            'sales_org' => $salesOrg,
            'customer_id' => $customerId,
        ]);

        return $this->post('/ZSDO_EBU_ORDERS_ACCESS', [
            'I_VKORG' => $salesOrg,
            'I_FORCE_KUNNR' => $customerId,
        ]);
    }

    /**
     * Load materials for a customer
     */
    public function loadMaterials(array $requestData): array
    {
        $this->logger->info('Loading materials from SAP', [
            'customer' => $requestData['I_WA_AG']['KUNNR'] ?? 'N/A',
        ]);

        return $this->post('/ZSDO_EBU_LOAD_MATERIALS', $requestData);
    }

    /**
     * Get material price for specific customer
     */
    public function getMaterialPrice(
        string $customerId,
        string $materialNumber,
        array $tvkoData,
        array $tvakData,
        array $customerData,
        array $weData,
        array $rgData
    ): array {
        $this->logger->info('Fetching material price from SAP', [
            'customer_id' => $customerId,
            'material_number' => $materialNumber,
        ]);

        return $this->post('/ZSDO_EBU_SHOW_MATERIAL_PRICE', [
            'I_WA_TVKO' => $tvkoData,
            'I_WA_TVAK' => $tvakData,
            'I_WA_AG' => $customerData,
            'I_WA_WE' => $weData,
            'I_WA_RG' => $rgData,
            'IN_WA_MATNR' => [
                'MATNR' => $materialNumber,
            ],
        ]);
    }

    /**
     * Make POST request to SAP API
     */
    private function post(string $endpoint, array $payload): array
    {
        $url = self::BASE_URL . $endpoint;

        try {
            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [self::USERNAME, self::PASSWORD],
                'json' => $payload,
                'timeout' => 30,
                'verify_peer' => false, // For QAS environment
                'verify_host' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            $this->logger->debug('SAP API response', [
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'response_size' => strlen(json_encode($content)),
            ]);

            return $content;

        } catch (\Exception $e) {
            $this->logger->error('SAP API call failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            throw new \RuntimeException(
                "SAP API call failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
