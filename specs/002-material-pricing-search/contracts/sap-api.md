# SAP API Contract

**Feature**: 002-material-pricing-search  
**Purpose**: Document SAP RFC function module contracts and integration patterns

## SAP System Connection

**Protocol**: SAP RFC (Remote Function Call)  
**Client**: Symfony HTTP Client with XML/SOAP support  
**Authentication**: Basic Auth (username/password from environment)  
**Endpoint**: `http://sap-server:8000/sap/bc/soap/rfc` (configurable)

---

## ZSDO_EBU_LOAD_MATERIALS (Existing, Extended)

**Purpose**: Load list of available materials for customer and sales organization  
**Usage**: Called during material synchronization to get all materials with POSNR

### Request Structure

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" 
                  xmlns:urn="urn:sap-com:document:sap:rfc:functions">
    <soapenv:Header/>
    <soapenv:Body>
        <urn:ZSDO_EBU_LOAD_MATERIALS>
            <I_VKORG>185</I_VKORG>  <!-- Sales Organization (4 chars) -->
            <I_VTWEG>00</I_VTWEG>    <!-- Distribution Channel (2 chars) -->
            <I_KUNNR>0000210839</I_KUNNR>  <!-- Customer ID (10 chars, zero-padded) -->
            <I_VKBUR></I_VKBUR>      <!-- Sales Office (optional) -->
        </urn:ZSDO_EBU_LOAD_MATERIALS>
    </soapenv:Body>
</soapenv:Envelope>
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| I_VKORG | string | Yes | Sales organization code (4 chars) |
| I_VTWEG | string | Yes | Distribution channel (2 chars, usually "00") |
| I_KUNNR | string | Yes | Customer number (10 chars, zero-padded) |
| I_VKBUR | string | No | Sales office (optional filter) |

### Response Structure

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <n0:ZSDO_EBU_LOAD_MATERIALSResponse xmlns:n0="urn:sap-com:document:sap:rfc:functions">
            <X_MAT_FOUND>
                <item>
                    <MATNR>00020006800</MATNR>           <!-- Material Number -->
                    <MAKTX>HEMOSIL QC Normal Level 2</MAKTX>  <!-- Description -->
                    <MEINS>EA</MEINS>                     <!-- Unit of Measure -->
                    <POSNR>000010</POSNR>                 <!-- ⚡ CRITICAL: Position Number -->
                    <STATUS>ACTIVE</STATUS>
                </item>
                <item>
                    <MATNR>00020006801</MATNR>
                    <MAKTX>Coagulation Reagent PT</MAKTX>
                    <MEINS>EA</MEINS>
                    <POSNR>000020</POSNR>                 <!-- ⚡ POSNR varies per material -->
                    <STATUS>ACTIVE</STATUS>
                </item>
                <!-- ... more items -->
            </X_MAT_FOUND>
        </n0:ZSDO_EBU_LOAD_MATERIALSResponse>
    </soapenv:Body>
</soapenv:Envelope>
```

### Response Fields

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| MATNR | string | Material number (18 chars) | SAP material ID |
| MAKTX | string | Material description (40 chars) | Display text |
| MEINS | string | Base unit of measure (3 chars) | e.g., EA, KG, L |
| POSNR | string | Position number (6 chars) | ⚡ **Required for price retrieval** |
| STATUS | string | Material status | ACTIVE, INACTIVE |

### PHP Implementation

```php
class SapApiClient
{
    public function loadMaterials(
        string $salesOrg,
        string $distributionChannel,
        string $customerId,
        ?string $salesOffice = null
    ): array {
        $xml = $this->buildLoadMaterialsRequest(
            $salesOrg,
            $distributionChannel,
            $customerId,
            $salesOffice
        );
        
        $response = $this->httpClient->request('POST', $this->sapEndpoint, [
            'body' => $xml,
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => 'urn:sap-com:document:sap:rfc:functions:ZSDO_EBU_LOAD_MATERIALS'
            ],
            'auth_basic' => [$this->username, $this->password],
            'timeout' => 30
        ]);
        
        return $this->parseLoadMaterialsResponse($response->getContent());
    }
    
    private function parseLoadMaterialsResponse(string $xmlContent): array
    {
        $xml = simplexml_load_string($xmlContent);
        $xml->registerXPathNamespace('n0', 'urn:sap-com:document:sap:rfc:functions');
        
        $materials = [];
        foreach ($xml->xpath('//n0:X_MAT_FOUND/item') as $item) {
            $materials[] = [
                'materialNumber' => (string) $item->MATNR,
                'description' => (string) $item->MAKTX,
                'baseUnitOfMeasure' => (string) $item->MEINS,
                'posnr' => (string) $item->POSNR,  // ⚡ Extract POSNR
                'status' => (string) $item->STATUS
            ];
        }
        
        return $materials;
    }
}
```

### Error Scenarios

| Error | SAP Response | Handling |
|-------|--------------|----------|
| Customer not found | E_RETURN with type 'E' | Throw CustomerNotFoundException |
| No materials | X_MAT_FOUND empty array | Return empty array (valid) |
| Timeout | HTTP timeout | Retry 3x with exponential backoff |
| Invalid credentials | HTTP 401 | Throw AuthenticationException, alert ops |

---

## ZSDO_EBU_SHOW_MATERIAL_PRICE (Modified)

**Purpose**: Retrieve price for specific material  
**Change**: ⚡ **Now requires POSNR parameter for accurate pricing**

### Request Structure

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" 
                  xmlns:urn="urn:sap-com:document:sap:rfc:functions">
    <soapenv:Header/>
    <soapenv:Body>
        <urn:ZSDO_EBU_SHOW_MATERIAL_PRICE>
            <I_WA_TVKO>
                <VKORG>185</VKORG>           <!-- Sales Organization -->
                <VTWEG>00</VTWEG>            <!-- Distribution Channel -->
            </I_WA_TVKO>
            <I_WA_TVAK>
                <VKORG>185</VKORG>           <!-- Sales Organization (repeated) -->
                <VTWEG>00</VTWEG>            <!-- Distribution Channel (repeated) -->
                <SPART>00</SPART>            <!-- Division -->
            </I_WA_TVAK>
            <I_WA_AG>
                <KUNNR>0000210839</KUNNR>    <!-- Sold-to Party (Customer) -->
            </I_WA_AG>
            <I_WA_WE>
                <KUNNR>0000210839</KUNNR>    <!-- Ship-to Party (usually same) -->
            </I_WA_WE>
            <I_WA_RG>
                <KUNNR>0000210839</KUNNR>    <!-- Bill-to Party (usually same) -->
            </I_WA_RG>
            <IN_WA_MATNR>
                <MATNR>00020006800</MATNR>   <!-- Material Number -->
                <POSNR>000010</POSNR>        <!-- ⚡ CRITICAL: Position Number from loadMaterials -->
            </IN_WA_MATNR>
        </urn:ZSDO_EBU_SHOW_MATERIAL_PRICE>
    </soapenv:Body>
</soapenv:Envelope>
```

### Request Parameters

| Parameter | Type | Required | Description | Source |
|-----------|------|----------|-------------|--------|
| I_WA_TVKO.VKORG | string | Yes | Sales organization (4 chars) | Configuration |
| I_WA_TVKO.VTWEG | string | Yes | Distribution channel (2 chars) | Configuration |
| I_WA_TVAK.VKORG | string | Yes | Sales organization (same as above) | Configuration |
| I_WA_TVAK.VTWEG | string | Yes | Distribution channel (same as above) | Configuration |
| I_WA_TVAK.SPART | string | Yes | Division (2 chars, usually "00") | Configuration |
| I_WA_AG.KUNNR | string | Yes | Sold-to party customer ID | Database |
| I_WA_WE.KUNNR | string | Yes | Ship-to party customer ID | Database |
| I_WA_RG.KUNNR | string | Yes | Bill-to party customer ID | Database |
| IN_WA_MATNR.MATNR | string | Yes | Material number | Database |
| IN_WA_MATNR.POSNR | string | **Yes** | ⚡ Position number | **From loadMaterials response** |

### Response Structure

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <n0:ZSDO_EBU_SHOW_MATERIAL_PRICEResponse xmlns:n0="urn:sap-com:document:sap:rfc:functions">
            <E_WA_PRICING>
                <NETPR>125.50</NETPR>        <!-- Net Price -->
                <WAERK>EUR</WAERK>           <!-- Currency -->
                <KPEIN>1</KPEIN>             <!-- Price Unit (usually 1) -->
                <KMEIN>EA</KMEIN>            <!-- Price Unit of Measure -->
            </E_WA_PRICING>
        </n0:ZSDO_EBU_SHOW_MATERIAL_PRICEResponse>
    </soapenv:Body>
</soapenv:Envelope>
```

### Response Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| NETPR | decimal | Net price | 125.50 |
| WAERK | string | Currency code (3 chars) | EUR, USD, GBP |
| KPEIN | int | Price unit (usually 1) | 1 |
| KMEIN | string | Price unit of measure (3 chars) | EA, KG, L |

### PHP Implementation

```php
class SapApiClient
{
    public function getMaterialPrice(
        string $salesOrg,
        string $distributionChannel,
        string $division,
        string $customerId,
        string $materialNumber,
        string $posnr  // ⚡ NEW PARAMETER - CRITICAL
    ): array {
        $xml = $this->buildMaterialPriceRequest(
            salesOrg: $salesOrg,
            distributionChannel: $distributionChannel,
            division: $division,
            customerId: $customerId,
            materialNumber: $materialNumber,
            posnr: $posnr  // ⚡ Pass POSNR to SAP
        );
        
        $response = $this->httpClient->request('POST', $this->sapEndpoint, [
            'body' => $xml,
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => 'urn:sap-com:document:sap:rfc:functions:ZSDO_EBU_SHOW_MATERIAL_PRICE'
            ],
            'auth_basic' => [$this->username, $this->password],
            'timeout' => 10
        ]);
        
        return $this->parseMaterialPriceResponse($response->getContent());
    }
    
    private function parseMaterialPriceResponse(string $xmlContent): array
    {
        $xml = simplexml_load_string($xmlContent);
        $xml->registerXPathNamespace('n0', 'urn:sap-com:document:sap:rfc:functions');
        
        $pricing = $xml->xpath('//n0:E_WA_PRICING')[0] ?? null;
        
        if (!$pricing) {
            throw new PriceNotFoundException('No pricing data returned from SAP');
        }
        
        return [
            'netPrice' => (float) $pricing->NETPR,
            'currency' => (string) $pricing->WAERK,
            'priceUnit' => (int) $pricing->KPEIN,
            'unitOfMeasure' => (string) $pricing->KMEIN
        ];
    }
}
```

### Error Scenarios

| Error | SAP Response | Handling |
|-------|--------------|----------|
| Material not found | Empty E_WA_PRICING | Log warning, mark as unavailable |
| Invalid POSNR | E_RETURN with type 'E' | Throw InvalidPosnrException |
| Pricing condition missing | Empty NETPR | Log warning, mark as unavailable |
| Timeout | HTTP timeout | Retry 3x with exponential backoff |

### Critical: POSNR Flow

```
1. Call ZSDO_EBU_LOAD_MATERIALS → Get materials with POSNR
2. Store POSNR in customer_materials table
3. Call ZSDO_EBU_SHOW_MATERIAL_PRICE with stored POSNR
4. Receive accurate price for that specific material position
```

**Without POSNR**: SAP may return incorrect price or no price at all

---

## Error Handling

### SAP Error Structure

```xml
<E_RETURN>
    <TYPE>E</TYPE>              <!-- E=Error, W=Warning, S=Success, I=Info -->
    <ID>ZSDO</ID>
    <NUMBER>001</NUMBER>
    <MESSAGE>Customer not found</MESSAGE>
    <LOG_NO></LOG_NO>
    <LOG_MSG_NO>000000</LOG_MSG_NO>
    <MESSAGE_V1></MESSAGE_V1>
    <MESSAGE_V2></MESSAGE_V2>
    <MESSAGE_V3></MESSAGE_V3>
    <MESSAGE_V4></MESSAGE_V4>
    <PARAMETER></PARAMETER>
    <ROW>0</ROW>
    <FIELD></FIELD>
    <SYSTEM>SAP</SYSTEM>
</E_RETURN>
```

### Error Classification

```php
class SapApiClient
{
    private function handleSapError(SimpleXMLElement $error): void
    {
        $type = (string) $error->TYPE;
        $message = (string) $error->MESSAGE;
        
        switch ($type) {
            case 'E':  // Error
                $this->logger->error('SAP API error', [
                    'message' => $message,
                    'id' => (string) $error->ID,
                    'number' => (string) $error->NUMBER
                ]);
                throw new SapApiException($message);
                
            case 'W':  // Warning
                $this->logger->warning('SAP API warning', [
                    'message' => $message
                ]);
                break;
                
            case 'S':  // Success
            case 'I':  // Info
                $this->logger->info('SAP API info', [
                    'message' => $message
                ]);
                break;
        }
    }
}
```

---

## Performance & Reliability

### Timeout Configuration

```php
// config/services.yaml
services:
    App\Infrastructure\ExternalApi\SapApiClient:
        arguments:
            $httpClient: '@http_client.sap'
            $sapEndpoint: '%env(SAP_API_ENDPOINT)%'
            $username: '%env(SAP_USERNAME)%'
            $password: '%env(SAP_PASSWORD)%'

// config/packages/framework.yaml
framework:
    http_client:
        scoped_clients:
            http_client.sap:
                base_uri: '%env(SAP_API_ENDPOINT)%'
                timeout: 30
                retry_failed:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
                    jitter: 0.1
```

### Retry Strategy

- **Max retries**: 3
- **Initial delay**: 1 second
- **Backoff multiplier**: 2x (1s, 2s, 4s)
- **Jitter**: ±10% randomization to avoid thundering herd

### Circuit Breaker (Optional)

Consider implementing circuit breaker pattern if SAP instability causes cascading failures:

```php
class SapApiClient
{
    private function callWithCircuitBreaker(callable $apiCall): mixed
    {
        if ($this->circuitBreaker->isOpen()) {
            throw new SapUnavailableException('Circuit breaker open');
        }
        
        try {
            $result = $apiCall();
            $this->circuitBreaker->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->circuitBreaker->recordFailure();
            throw $e;
        }
    }
}
```

---

## Testing Contracts

### Mock SAP Responses

```php
class MockSapApiClient extends SapApiClient
{
    public function loadMaterials(...$args): array
    {
        return [
            [
                'materialNumber' => '00020006800',
                'description' => 'HEMOSIL QC Normal Level 2',
                'baseUnitOfMeasure' => 'EA',
                'posnr' => '000010',  // Mock POSNR
                'status' => 'ACTIVE'
            ]
        ];
    }
    
    public function getMaterialPrice(...$args, string $posnr): array
    {
        // Verify POSNR is passed
        if ($posnr !== '000010') {
            throw new InvalidPosnrException('Invalid POSNR in test');
        }
        
        return [
            'netPrice' => 125.50,
            'currency' => 'EUR',
            'priceUnit' => 1,
            'unitOfMeasure' => 'EA'
        ];
    }
}
```

---

## Summary

### Key Changes
- ⚡ **POSNR now required** in `ZSDO_EBU_SHOW_MATERIAL_PRICE` request
- POSNR extracted from `ZSDO_EBU_LOAD_MATERIALS` response
- Stored in database for subsequent price calls

### Critical Flow
1. Load materials → Get POSNR per material
2. Store POSNR in customer_materials
3. Fetch price → Include POSNR from storage
4. Accurate pricing returned

### Error Handling
- Retry 3x with exponential backoff
- Log all SAP errors with context
- Graceful degradation (mark unavailable if pricing fails)

### Performance
- Timeouts: 30s for loadMaterials, 10s for getMaterialPrice
- Parallel price fetching (multiple workers)
- Circuit breaker optional for high-volume scenarios
