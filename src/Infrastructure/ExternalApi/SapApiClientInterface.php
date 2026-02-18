<?php

declare(strict_types=1);

namespace App\Infrastructure\ExternalApi;

/**
 * Interface for SAP API Client
 * 
 * This interface defines the contract for communicating with SAP ERP system.
 * It allows for mocking in unit tests since the concrete implementation is final.
 */
interface SapApiClientInterface
{
    /**
     * Get customer data from SAP
     * 
     * @param string $salesOrg Sales organization code
     * @param string $customerId SAP customer ID
     * @return array Customer data from SAP
     */
    public function getCustomerData(string $salesOrg, string $customerId): array;

    /**
     * Load materials from SAP for a customer
     * 
     * @param array $requestData Request payload with TVKO, TVAK, AG, WE, RG data
     * @return array Materials data from SAP
     */
    public function loadMaterials(array $requestData): array;

    /**
     * Get material price from SAP for a specific customer
     * 
     * @param string $customerId SAP customer ID
     * @param string $materialNumber SAP material number
     * @param array $tvkoData Sales organization data
     * @param array $tvakData Distribution channel data
     * @param array $customerData Customer master data
     * @param array $weData Plant data
     * @param array $rgData Region data
     * @return array Price data from SAP
     */
    public function getMaterialPrice(
        string $customerId,
        string $materialNumber,
        array $tvkoData,
        array $tvakData,
        array $customerData,
        array $weData,
        array $rgData
    ): array;
}
