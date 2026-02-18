# Events Contract

**Feature**: 002-material-pricing-search  
**Purpose**: Define event schemas and handler contracts

## Event Overview

Events represent things that have already occurred in the system. Events are dispatched asynchronously and can have multiple handlers.

---

## MaterialSyncedEvent ✨ NEW

**Purpose**: Material data has been synchronized from SAP to MySQL  
**Transport**: `async_priority_low` (RabbitMQ)  
**Handlers**: 
- `UpdateMongoOnMaterialSyncedHandler` (updates MongoDB read model)
- `GenerateEmbeddingOnMaterialSyncedHandler` (triggers embedding generation)

### Schema

```php
final class MaterialSyncedEvent
{
    public function __construct(
        public readonly string $materialId,
        public readonly string $customerId,
        public readonly string $salesOrg,
        public readonly string $posnr,
        public readonly \DateTimeImmutable $occurredAt
    ) {}
}
```

### Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| materialId | string | Material UUID from materials table |
| customerId | string | Customer ID (10 chars) |
| salesOrg | string | Sales organization code (4 chars) |
| posnr | string | SAP position number (6 chars) |
| occurredAt | DateTimeImmutable | Event timestamp |

### Handler 1: UpdateMongoOnMaterialSyncedHandler

**Purpose**: Update MongoDB MaterialView with latest data from MySQL

```php
class UpdateMongoOnMaterialSyncedHandler
{
    public function __invoke(MaterialSyncedEvent $event): void
    {
        // 1. Retrieve CustomerMaterial from MySQL
        $customerMaterial = $this->customerMaterialRepository->findByCustomerAndMaterial(
            $event->customerId,
            $event->materialId,
            $event->salesOrg
        );
        
        if (!$customerMaterial) {
            $this->logger->warning('Material not found for MaterialSyncedEvent', [
                'materialId' => $event->materialId,
                'customerId' => $event->customerId
            ]);
            return;
        }
        
        // 2. Find or create MaterialView in MongoDB
        $materialView = $this->mongoMaterialRepository->findByMaterialNumber(
            $customerMaterial->getMaterial()->getMaterialNumber(),
            $event->customerId
        );
        
        if (!$materialView) {
            $materialView = MaterialView::create(
                materialNumber: $customerMaterial->getMaterial()->getMaterialNumber(),
                description: $customerMaterial->getMaterial()->getDescription(),
                customerId: $event->customerId,
                salesOrg: $event->salesOrg
            );
        }
        
        // 3. Update with latest data
        $materialView->updatePrice(
            $customerMaterial->getPrice()?->getAmount(),
            $customerMaterial->getPrice()?->getCurrency()
        );
        $materialView->updatePosnr($event->posnr);
        $materialView->updateAvailability($customerMaterial->isAvailable());
        
        // 4. Persist to MongoDB
        $this->mongoMaterialRepository->save($materialView);
    }
}
```

### Handler 2: GenerateEmbeddingOnMaterialSyncedHandler

**Purpose**: Trigger embedding generation for newly synced material

```php
class GenerateEmbeddingOnMaterialSyncedHandler
{
    public function __invoke(MaterialSyncedEvent $event): void
    {
        // 1. Check if material already has embedding
        $materialView = $this->mongoMaterialRepository->findByMaterialNumber(
            /* material number from event */,
            $event->customerId
        );
        
        if ($materialView && $materialView->hasEmbedding()) {
            // Skip: Embedding already exists
            return;
        }
        
        // 2. Dispatch command to generate embedding
        $this->commandBus->dispatch(
            new GenerateEmbeddingCommand(
                materialId: $event->materialId,
                customerId: $event->customerId,
                description: $materialView->getDescription()
            )
        );
    }
}
```

### Error Handling

- MongoDB unavailable: Retry via Messenger retry policy (3x with backoff)
- Material not found: Log warning, skip update
- Idempotent: Safe to process same event multiple times

---

## Price FetchedEvent ✨ NEW

**Purpose**: Price has been fetched from SAP and stored in MySQL  
**Transport**: `async_priority_low` (RabbitMQ)  
**Handler**: `UpdateMongoOnPriceFetchedHandler`

### Schema

```php
final class PriceFetchedEvent
{
    public function __construct(
        public readonly string $materialId,
        public readonly string $customerId,
        public readonly string $salesOrg,
        public readonly float $priceAmount,
        public readonly string $priceCurrency,
        public readonly \DateTimeImmutable $occurredAt
    ) {}
}
```

### Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| materialId | string | Material UUID |
| customerId | string | Customer ID |
| salesOrg | string | Sales organization code |
| priceAmount | float | Price value |
| priceCurrency | string | Currency code (e.g., EUR, USD) |
| occurredAt | DateTimeImmutable | Event timestamp |

### Handler: UpdateMongoOnPriceFetchedHandler

**Purpose**: Update MaterialView with price information

```php
class UpdateMongoOnPriceFetchedHandler
{
    public function __invoke(PriceFetchedEvent $event): void
    {
        // 1. Find MaterialView in MongoDB
        $materialView = $this->mongoMaterialRepository->findByMaterialAndCustomer(
            materialId: $event->materialId,
            customerId: $event->customerId
        );
        
        if (!$materialView) {
            $this->logger->error('MaterialView not found for PriceFetchedEvent', [
                'materialId' => $event->materialId,
                'customerId' => $event->customerId
            ]);
            return;
        }
        
        // 2. Update price
        $materialView->updatePrice(
            $event->priceAmount,
            $event->priceCurrency
        );
        
        // 3. Persist
        $this->mongoMaterialRepository->save($materialView);
    }
}
```

### Error Handling

- MaterialView not found: Log error (should exist from MaterialSyncedEvent)
- MongoDB unavailable: Retry via Messenger
- Idempotent: Safe to replay

---

## Event Routing Configuration

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async_priority_low:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: priority_low
        
        routing:
            'App\Application\Event\MaterialSyncedEvent': async_priority_low
            'App\Application\Event\PriceFetchedEvent': async_priority_low
```

---

## Event Sequencing

### Typical Flow

```
1. SyncUserMaterialsCommand executed
   ↓
2. CustomerMaterial persisted to MySQL with POSNR
   ↓
3. MaterialSyncedEvent dispatched
   ↓
4. UpdateMongoOnMaterialSyncedHandler: Creates MaterialView in MongoDB
   ↓
5. GenerateEmbeddingOnMaterialSyncedHandler: Dispatches GenerateEmbeddingCommand
   ↓
6. SyncMaterialPriceCommand executed (async)
   ↓
7. Price fetched from SAP, stored in MySQL
   ↓
8. PriceFetchedEvent dispatched
   ↓
9. UpdateMongoOnPriceFetchedHandler: Updates MaterialView with price
   ↓
10. GenerateEmbeddingCommand executed (async)
   ↓
11. Embedding generated, stored in MaterialView
```

### Event Ordering Guarantees

- Events processed in order per material (single-threaded worker per material)
- No ordering guarantee across different materials (parallel processing)
- Idempotent handlers ensure safety even with out-of-order delivery

---

## Event Versioning

### Current Version: v1

All events include implicit version via class name. Future breaking changes require new event class.

### Migration Strategy

```php
// If breaking change needed in future
final class MaterialSyncedEventV2
{
    public function __construct(
        public readonly string $materialId,
        public readonly string $customerId,
        public readonly string $salesOrg,
        public readonly string $posnr,
        public readonly array $additionalData,  // NEW FIELD
        public readonly \DateTimeImmutable $occurredAt
    ) {}
}

// Support both versions during migration
class UpdateMongoOnMaterialSyncedHandler
{
    public function __invoke(MaterialSyncedEvent|MaterialSyncedEventV2 $event): void
    {
        if ($event instanceof MaterialSyncedEventV2) {
            // Handle new version
        } else {
            // Handle old version
        }
    }
}
```

---

## Testing Contracts

### Unit Tests

```php
class MaterialSyncedEventTest extends TestCase
{
    public function testEventCreation(): void
    {
        $event = new MaterialSyncedEvent(
            materialId: '123e4567-e89b-12d3-a456-426614174000',
            customerId: '0000210839',
            salesOrg: '185',
            posnr: 'ABC123',
            occurredAt: new \DateTimeImmutable()
        );
        
        $this->assertSame('ABC123', $event->posnr);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredAt);
    }
}
```

### Integration Tests

```php
class UpdateMongoOnMaterialSyncedHandlerTest extends KernelTestCase
{
    public function testHandlerUpdatesMongoDocument(): void
    {
        // Arrange: Create CustomerMaterial in MySQL
        $customerMaterial = new CustomerMaterial(
            customerId: '0000210839',
            materialId: 'mat-123',
            salesOrg: '185',
            posnr: new Posnr('ABC123'),
            price: new Money(125.50, 'EUR')
        );
        $this->entityManager->persist($customerMaterial);
        $this->entityManager->flush();
        
        // Act: Dispatch event
        $event = new MaterialSyncedEvent(
            materialId: 'mat-123',
            customerId: '0000210839',
            salesOrg: '185',
            posnr: 'ABC123',
            occurredAt: new \DateTimeImmutable()
        );
        
        $handler = $this->getContainer()->get(UpdateMongoOnMaterialSyncedHandler::class);
        $handler($event);
        
        // Assert: MaterialView exists in MongoDB
        $materialView = $this->mongoRepository->findByMaterialNumber(
            '00020006800',  // Material number from CustomerMaterial
            '0000210839'
        );
        
        $this->assertNotNull($materialView);
        $this->assertSame('ABC123', $materialView->getPosnr());
        $this->assertSame(125.50, $materialView->getPriceAmount());
    }
}
```

### E2E Tests

```php
class MaterialSyncEventFlowTest extends WebTestCase
{
    public function testFullEventFlow(): void
    {
        // 1. Dispatch SyncUserMaterialsCommand
        $this->commandBus->dispatch(
            new SyncUserMaterialsCommand(
                salesOrg: '185',
                customerId: '0000210839'
            )
        );
        
        // 2. Wait for async processing
        $this->waitForMessengerQueueToProcess();
        
        // 3. Assert MaterialView created in MongoDB
        $materialView = $this->mongoRepository->findByCustomer(
            '0000210839',
            '185'
        );
        
        $this->assertGreaterThan(0, count($materialView));
        
        // 4. Assert embedding generated
        $firstMaterial = $materialView[0];
        $this->assertTrue($firstMaterial->hasEmbedding());
        $this->assertCount(1536, $firstMaterial->getEmbedding());
    }
}
```

---

## Monitoring & Observability

### Event Metrics

Track in production:
- Event dispatch rate (events/second)
- Handler execution time (ms)
- Handler failure rate (%)
- MongoDB sync lag (seconds between MySQL write and MongoDB update)

### Logging

```php
class UpdateMongoOnMaterialSyncedHandler
{
    public function __invoke(MaterialSyncedEvent $event): void
    {
        $startTime = microtime(true);
        
        try {
            // ... handler logic ...
            
            $this->logger->info('MaterialView updated', [
                'materialId' => $event->materialId,
                'customerId' => $event->customerId,
                'duration_ms' => (microtime(true) - $startTime) * 1000
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update MaterialView', [
                'materialId' => $event->materialId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
```

---

## Summary

### New Events
- `MaterialSyncedEvent`: Triggered after material sync from SAP, updates MongoDB and triggers embedding generation
- `PriceFetchedEvent`: Triggered after price fetch from SAP, updates MongoDB with price

### Event Handlers
- `UpdateMongoOnMaterialSyncedHandler`: Syncs MySQL → MongoDB
- `GenerateEmbeddingOnMaterialSyncedHandler`: Triggers embedding generation
- `UpdateMongoOnPriceFetchedHandler`: Updates price in MongoDB

### Guarantees
- At-least-once delivery (Messenger retries)
- Idempotent handlers (safe to replay)
- Eventual consistency (MongoDB lags MySQL by <5s)
- Low priority (doesn't block critical operations)
