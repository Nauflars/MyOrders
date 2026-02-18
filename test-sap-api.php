#!/usr/bin/env php
<?php
/**
 * Test script for SAP API calls
 * 
 * Usage: php test-sap-api.php
 */

// SAP API Configuration
const SAP_BASE_URL = 'https://erpqas.werfen.com/zsapui5_json';
const SAP_USERNAME = 'ZWEBSERVICE';
const SAP_PASSWORD = '4YVj745z';

// Test data
const SALES_ORG = '101';
const TEST_CUSTOMER_ID = '0000185851';

function makeApiCall(string $endpoint, array $payload): array
{
    $url = SAP_BASE_URL . $endpoint;
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(SAP_USERNAME . ':' . SAP_PASSWORD),
        ],
        CURLOPT_SSL_VERIFYPEER => false, // For testing only
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        throw new Exception("cURL Error: {$error}");
    }
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP Error {$httpCode}: {$response}");
    }
    
    return json_decode($response, true);
}

echo "=== SAP API Test Script ===" . PHP_EOL;
echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// Test 1: Get customer data
echo "[1/3] Testing ZSDO_EBU_ORDERS_ACCESS (Customer Data)..." . PHP_EOL;
try {
    $customerPayload = [
        'I_VKORG' => SALES_ORG,
        'I_FORCE_KUNNR' => TEST_CUSTOMER_ID,
    ];
    
    echo "Request: " . json_encode($customerPayload, JSON_PRETTY_PRINT) . PHP_EOL;
    
    $customerData = makeApiCall('/ZSDO_EBU_ORDERS_ACCESS', $customerPayload);
    
    echo "✓ Success!" . PHP_EOL;
    echo "Response: " . json_encode($customerData, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
    
    file_put_contents(__DIR__ . '/sap-customer-response.json', json_encode($customerData, JSON_PRETTY_PRINT));
    echo "Saved to: sap-customer-response.json" . PHP_EOL . PHP_EOL;
    
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    $customerData = null;
}

// Test 2: Get materials
echo "[2/3] Testing ZSDO_EBU_LOAD_MATERIALS..." . PHP_EOL;
try {
    // Example material load payload
    $materialPayload = [
        'I_WA_TVKO' => [
            'VKORG' => '101',
            'BUKRS' => '101',
            'WERKS' => 'AA01',
            'LAND1' => 'IT',
            'WAERS' => 'EUR',
            'BOAVO' => '',
        ],
        'I_WA_TVAK' => [
            'AUART' => 'TA',
            'KALAU' => 'B00001',
            'KALSU' => 'A00001',
            'KALVG' => 'A',
            'KALLI' => 'A00001',
            'PARGR' => 'TA',
            'VBTYP' => 'C',
            'VERLI' => '',
            'KALSM' => 'ZVAA01',
        ],
        'I_WA_AG' => [
            'KUNNR' => '0000256900',
            'NAME1' => 'CARDIA BIOMEDICAL',
            'NAME2' => 'Imm. EL FAOUZ - 1er Etage Appt N°3',
            'STRAS' => 'Les Berges du Lac',
            'ORT01' => 'TUNIS',
            'PSTLZ' => '1053',
            'REGIO' => 'TN',
            'LAND1' => 'TN',
            'INCO1' => 'EXW',
            'BEZEI' => 'Ex Works',
            'VSBED' => '28',
            'VTEXT' => 'AIRFREIGHT',
            'WAERK' => 'USD',
            'SPRAS' => 'E',
            'TXTPA' => 'CARDIA BIOMEDICAL / Les Berges du Lac / 1053 TUNIS',
            'PLTYP' => '14',
            'BZIRK' => 'AAI15',
            'VKGRP' => '',
            'VKBUR' => '',
            'STCEG' => '',
            'TAXK1' => '1',
            'BRSCH' => 'I082',
            'KALKS' => '1',
            'CHSPL' => '',
            'ADRNR' => '0000941315',
            'CIFSHIP' => '',
        ],
        'I_WA_WE' => [
            'KUNNR' => '0000256900',
            'NAME1' => 'CARDIA BIOMEDICAL',
            'NAME2' => 'Imm. EL FAOUZ - 1er Etage Appt N°3',
            'STRAS' => 'Les Berges du Lac',
            'ORT01' => 'TUNIS',
            'PSTLZ' => '1053',
            'REGIO' => 'TN',
            'LAND1' => 'TN',
            'TXTPA' => 'CARDIA BIOMEDICAL / Les Berges du Lac / 1053 TUNIS',
            'STCEG' => '',
            'TAXK1' => '1',
            'DWERK' => '',
            'COUNC' => '',
            'CITYC' => '',
            'ADRNR' => '0000941315',
        ],
        'I_WA_RG' => [
            'KUNNR' => '0000256900',
            'NAME1' => 'CARDIA BIOMEDICAL',
            'NAME2' => 'Imm. EL FAOUZ - 1er Etage Appt N°3',
            'STRAS' => 'Les Berges du Lac',
            'ORT01' => 'TUNIS',
            'PSTLZ' => '1053',
            'REGIO' => 'TN',
            'LAND1' => 'TN',
            'TXTPA' => 'CARDIA BIOMEDICAL / Les Berges du Lac / 1053 TUNIS',
            'STCEG' => '',
            'TAXK1' => '1',
            'ZTERM' => 'IT46',
            'BOKRE' => '',
            'PERRL' => '',
            'ADRNR' => '0000941315',
        ],
        'I_VBELN' => '',
        'IT_ORD_MATNR' => '',
        'IN_POSNR' => '',
    ];
    
    echo "Request payload size: " . strlen(json_encode($materialPayload)) . " bytes" . PHP_EOL;
    
    $materialsData = makeApiCall('/ZSDO_EBU_LOAD_MATERIALS', $materialPayload);
    
    echo "✓ Success!" . PHP_EOL;
    echo "Response: " . json_encode($materialsData, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
    
    file_put_contents(__DIR__ . '/sap-materials-response.json', json_encode($materialsData, JSON_PRETTY_PRINT));
    echo "Saved to: sap-materials-response.json" . PHP_EOL . PHP_EOL;
    
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    $materialsData = null;
}

// Test 3: Get material price
echo "[3/3] Testing ZSDO_EBU_SHOW_MATERIAL_PRICE..." . PHP_EOL;
try {
    $pricePayload = [
        'I_WA_TVKO' => [
            'VKORG' => '165',
            'LAND1' => 'KR',
            'WERKS' => 'AZ01',
        ],
        'I_AG_KUNNR' => '0000323254',
        'I_WA_AG' => [
            'KUNNR' => '0000323254',
            'WAERK' => 'KRW',
            'PLTYP' => '',
        ],
        'IN_WA_MATNR' => [
            'MATNR' => '000J201',
            'POSNR' => '044690',
        ],
    ];
    
    echo "Request: " . json_encode($pricePayload, JSON_PRETTY_PRINT) . PHP_EOL;
    
    $priceData = makeApiCall('/ZSDO_EBU_SHOW_MATERIAL_PRICE', $pricePayload);
    
    echo "✓ Success!" . PHP_EOL;
    echo "Response: " . json_encode($priceData, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
    
    file_put_contents(__DIR__ . '/sap-price-response.json', json_encode($priceData, JSON_PRETTY_PRINT));
    echo "Saved to: sap-price-response.json" . PHP_EOL . PHP_EOL;
    
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . PHP_EOL . PHP_EOL;
}

echo "=== Test Complete ===" . PHP_EOL;
echo "Check the generated JSON files for detailed responses" . PHP_EOL;
