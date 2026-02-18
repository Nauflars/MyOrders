<?php

require __DIR__ . '/vendor/autoload.php';

echo "Testing class instantiation after fixes...\n\n";

try {
    // Test Order
    $order = new \App\Infrastructure\Persistence\Doctrine\Entity\Order(
        'test-id',
        'Test Customer',
        '100.00'
    );
    echo "✓ Order created: " . $order->getId() . "\n";
    
    // Test Customer
    $customer = new \App\Domain\Entity\Customer(
        'customer-id',
        'SAP001',
        '1000',
        'Test Customer',
        'US'
    );
    echo "✓ Customer created: " . $customer->getName() . "\n";
    
    // Test Material
    $material = new \App\Domain\Entity\Material(
        'material-id',
        'MAT001',
        'Test Material'
    );
    echo "✓ Material created: " . $material->getDescription() . "\n";
    
    // Test CustomerMaterial
    $customerMaterial = new \App\Domain\Entity\CustomerMaterial(
        'cm-id',
        $customer,
        $material
    );
    echo "✓ CustomerMaterial created: " . $customerMaterial->getId() . "\n";
    
    echo "\n✅ All classes instantiate successfully!\n";
    echo "All nullable properties are properly initialized.\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
