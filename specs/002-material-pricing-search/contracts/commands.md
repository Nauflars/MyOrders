# Commands Contract

**Feature**: 002-material-pricing-search  
**Purpose**: Define command schemas, validation rules, and handling contracts

## Command Overview

Commands represent intentions to change system state. All commands are dispatched through Symfony Messenger and handled by exactly one handler.

---

## SyncMaterialPriceCommand ✨ NEW

**Purpose**: Fetch and store material price from SAP using POSNR  
**Transport**: `async_priority_high` (RabbitMQ)  
**Handler**: `App\Application\CommandHandler\SyncMaterialPriceHandler`

### Schema

```php
final class SyncMaterialPriceCommand
{
    public function __construct(
        public readonly string $materialId,
        public readonly string $customerId,
        public readonly string $salesOrg,
        public readonly string $posnr
    ) {}
}
```

### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| materialId | string | Yes | Must be valid Material UUID |
| customerId | string | Yes | Must exist in customers table |
| salesOrg | string | Yes | 4-character sales organization code |
| posnr | string | Yes | 6 alphanumeric characters |

### Handler Contract

```php
class SyncMaterialPriceHandler
{
    public function __invoke(SyncMaterialPriceCommand $command): void
    {
        // 1. Retrieve CustomerMaterial entity
        // 2. Call SapApiClient->getMaterialPrice() with POSNR
        // 3. Update CustomerMaterial price
        // 4. Persist to database
        // 5. Dispatch PriceFetchedEvent
    }
}
```

### Error Scenarios

- `MaterialNotFoundException`: materialId not found
- `CustomerNotFoundException`: customerId not found
- `SapApiException`: SAP API call failed (retry 3x with exponential backoff)
- `InvalidPosnrException`: POSNR format invalid

### Success Criteria

- Price stored in customer_materials table
- lastSyncedAt timestamp updated
- PriceFetchedEvent dispatched to event bus

---

## GenerateEmbeddingCommand ✨ NEW

**Purpose**: Generate OpenAI embedding vector for material description  
**Transport**: `async_priority_normal` (RabbitMQ)  
**Handler**: `App\Application\CommandHandler\GenerateEmbeddingHandler`

### Schema

```php
final class GenerateEmbeddingCommand
{
    public function __construct(
        public readonly string $materialId,
        public readonly string $customerId,
        public readonly string $description
    ) {}
}
```

### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| materialId | string | Yes | Must be valid Material UUID |
| customerId | string | Yes | Must exist in customers table |
| description | string | Yes | Non-empty, max 8192 characters (OpenAI limit) |

### Handler Contract

```php
class GenerateEmbeddingHandler
{
    public function __invoke(GenerateEmbeddingCommand $command): void
    {
        // 1. Check if embedding already exists (avoid duplicate generation)
        // 2. Call OpenAiEmbeddingClient->generateEmbedding()
        // 3. Retrieve MaterialView from MongoDB
        // 4. Update embedding field
        // 5. Persist to MongoDB
    }
}
```

### Error Scenarios

- `OpenAiApiException`: API call failed (retry 3x with exponential backoff)
- `RateLimitException`: OpenAI rate limit hit (retry after delay)
- `InvalidEmbeddingException`: Response doesn't contain 1536 dimensions
- `MongoDBException`: MongoDB unavailable (retry with backoff)

### Success Criteria

- Embedding vector (1536 floats) stored in MaterialView
- lastUpdatedAt timestamp updated
- Embedding cached to avoid regeneration

### Cost Optimization

- Cache check before generation: Skip if embedding exists and description unchanged
- Batch multiple materials in single API call (up to 8192 tokens)
- Monitor daily API usage with alerts

---

## AcquireSyncLockCommand ✨ NEW

**Purpose**: Acquire distributed lock to prevent duplicate sync operations  
**Transport**: `async_priority_high` (RabbitMQ)  
**Handler**: `App\Application\CommandHandler\AcquireSyncLockHandler`

### Schema

```php
final class AcquireSyncLockCommand
{
    public function __construct(
        public readonly string $salesOrg,
        public readonly string $customerId,
        public readonly int $ttl = 600  // 10 minutes default
    ) {}
}
```

### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| salesOrg | string | Yes | 4-character sales organization code |
| customerId | string | Yes | Must exist in customers table |
| ttl | int | No | Seconds, must be 60-3600 (default 600) |

### Handler Contract

```php
class AcquireSyncLockHandler
{
    public function __invoke(AcquireSyncLockCommand $command): bool
    {
        // 1. Create SyncLockId from salesOrg + customerId
        // 2. Attempt to acquire Redis lock (non-blocking)
        // 3. If acquired: Create SyncProgress entity, return true
        // 4. If not acquired: Log skip, return false
    }
}
```

### Error Scenarios

- `RedisConnectionException`: Redis unavailable (fail-safe: allow sync to proceed)
- `LockAcquisitionException`: Lock held by another process (expected, not error)

### Success Criteria

- Redis lock key created: `sync_lock_{salesOrg}_{customerId}`
- TTL set correctly (auto-expires after duration)
- SyncProgress entity created with status=in_progress
- Returns true if lock acquired, false if already locked

### Lock Release

Locks released via:
- Explicit call in `finally` block after sync completes
- Automatic expiration via TTL (handles crashed processes)

---

## SyncUserMaterialsCommand - EXISTING, EXTENDED

**Purpose**: Synchronize materials for customer from SAP (existing command, now uses locks)  
**Transport**: `async_priority_high` (RabbitMQ)  
**Handler**: `App\Application\CommandHandler\SyncUserMaterialsHandler`

### Schema (unchanged)

```php
final class SyncUserMaterialsCommand
{
    public function __construct(
        public readonly string $salesOrg,
        public readonly string $customerId
    ) {}
}
```

### Handler Contract (extended)

```php
class SyncUserMaterialsHandler
{
    public function __invoke(SyncUserMaterialsCommand $command): void
    {
        // ⚡ NEW: Acquire lock before proceeding
        if (!$this->syncLockRepository->acquire($lockId, ttl: 600)) {
            $this->logger->info('Sync already in progress, skipping');
            return;
        }
        
        try {
            // 1. Call SapApiClient->loadMaterials()
            // 2. For each material in X_MAT_FOUND:
            //    - Extract POSNR ⚡ NEW
            //    - Create/update CustomerMaterial with POSNR ⚡ NEW
            //    - Update SyncProgress (increment processed) ⚡ NEW
            //    - Dispatch SyncMaterialPriceCommand with POSNR ⚡ NEW
            // 3. Mark SyncProgress as completed ⚡ NEW
            // 4. Dispatch MaterialSyncedEvent for each material
        } finally {
            $this->syncLockRepository->release($lockId); // ⚡ NEW
        }
    }
}
```

### Changes from Original

- ⚡ Acquires distributed lock before sync
- ⚡ Extracts and persists POSNR field
- ⚡ Updates SyncProgress throughout operation
- ⚡ Releases lock in finally block

---

## Command Routing Configuration

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            sync: 'sync://'
            async_priority_high:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: priority_high
            async_priority_normal:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: priority_normal
        
        routing:
            'App\Application\Command\SyncMaterialPriceCommand': async_priority_high
            'App\Application\Command\AcquireSyncLockCommand': async_priority_high
            'App\Application\Command\SyncUserMaterialsCommand': async_priority_high
            'App\Application\Command\GenerateEmbeddingCommand': async_priority_normal
```

---

## Retry Policy

```yaml
# config/packages/messenger.yaml (failure transport)
framework:
    messenger:
        failure_transport: failed
        
        transports:
            failed: 'doctrine://default?queue_name=failed'
        
        # Retry policy
        default_bus: command.bus
        buses:
            command.bus:
                middleware:
                    - doctrine_transaction
                    - retry:
                        max_retries: 3
                        delay: 1000  # 1 second
                        multiplier: 2  # Exponential backoff
                        max_delay: 30000  # 30 seconds max
```

---

## Testing Contracts

### Unit Tests

```php
// Test command creation and validation
class SyncMaterialPriceCommandTest extends TestCase
{
    public function testValidCommandCreation(): void
    {
        $command = new SyncMaterialPriceCommand(
            materialId: '123e4567-e89b-12d3-a456-426614174000',
            customerId: '0000210839',
            salesOrg: '185',
            posnr: 'ABC123'
        );
        
        $this->assertSame('185', $command->salesOrg);
        $this->assertSame('ABC123', $command->posnr);
    }
}
```

### Integration Tests

```php
// Test handler execution with real SAP client (mocked)
class SyncMaterialPriceHandlerTest extends KernelTestCase
{
    public function testHandlerFetchesPriceWithPosnr(): void
    {
        $sapClient = $this->createMock(SapApiClient::class);
        $sapClient->expects($this->once())
            ->method('getMaterialPrice')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->equalTo('ABC123') // Verify POSNR passed
            )
            ->willReturn(['NETPR' => '125.50', 'WAERK' => 'EUR']);
        
        $handler = new SyncMaterialPriceHandler($sapClient, ...);
        $command = new SyncMaterialPriceCommand(..., posnr: 'ABC123');
        
        $handler($command);
        
        // Assert price saved to database
    }
}
```

---

## Summary

### New Commands
- `SyncMaterialPriceCommand`: Fetch price with POSNR (high priority)
- `GenerateEmbeddingCommand`: Generate OpenAI embedding (normal priority)
- `AcquireSyncLockCommand`: Distributed locking (high priority)

### Extended Commands
- `SyncUserMaterialsCommand`: Now extracts POSNR, uses locks, tracks progress

### Routing Strategy
- High priority: Sync operations (price fetch, lock acquisition)
- Normal priority: Background operations (embedding generation)
- Low priority: Events (MongoDB updates, notifications)

### Error Handling
- Retry 3x with exponential backoff for transient failures
- Fallback to fail queue after max retries
- Dead letter queue for manual inspection
