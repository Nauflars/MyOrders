# Data Model: Material Pricing & Semantic Search System

**Feature**: 002-material-pricing-search  
**Date**: 2026-02-18  
**Purpose**: Document domain entities, value objects, aggregates, and data relationships

## Domain Model Overview

This feature extends existing domain model with POSNR support, adds MongoDB read models for search optimization, and introduces sync coordination entities.

```
┌─────────────────────────────────────────────────────────────┐
│                    WRITE MODEL (MySQL)                      │
│                    Source of Truth                          │
└─────────────────────────────────────────────────────────────┘
         │
         │ Domain Events
         │ (MaterialSyncedEvent)
         ↓
┌─────────────────────────────────────────────────────────────┐
│                    READ MODEL (MongoDB)                     │
│                    Search & Performance                     │
└─────────────────────────────────────────────────────────────┘
```

## Entities & Aggregates

### CustomerMaterial (Aggregate Root) ⚡ MODIFIED
**Layer**: Domain  
**Purpose**: Represents customer-specific material with pricing and availability  
**Persistence**: MySQL (`customer_materials` table)

**Attributes**:
```php
class CustomerMaterial
{
    private Uuid $id;
    private string $customerId;         // FK to customers
    private string $materialId;         // FK to materials
    private string $salesOrg;
    private Posnr $posnr;               // ⚡ NEW - SAP position number
    private ?Money $price;              // Amount + currency
    private bool $isAvailable;
    private \DateTimeImmutable $lastSyncedAt;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
}
```

**Value Objects**:
- `Posnr`: 6-character SAP position number
- `Money`: Price amount + currency code

**Business Rules**:
- POSNR must be present for price retrieval
- POSNR is customer+salesOrg+material specific
- Price can be null (material exists but no pricing available)
- lastSyncedAt tracks staleness for refresh logic

**Relationships**:
- Many-to-One with Customer
- Many-to-One with Material
- One-to-One with MaterialView (MongoDB, eventual consistency)

**Invariants**:
- (customerId, materialId, salesOrg) must be unique
- POSNR cannot change without full re-sync from SAP
- Price updates must record timestamp

---

### SyncProgress (Entity) ✨ NEW
**Layer**: Domain  
**Purpose**: Tracks progress of material synchronization operations  
**Persistence**: MySQL (`sync_progress` table)

**Attributes**:
```php
class SyncProgress
{
    private SyncLockId $id;            // Composite: salesOrg + customerId
    private string $customerId;
    private string $salesOrg;
    private int $totalMaterials;       // Expected materials from SAP
    private int $processedMaterials;   // Materials synced so far
    private SyncStatus $status;        // in_progress, completed, failed
    private \DateTimeImmutable $startedAt;
    private ?\DateTimeImmutable $completedAt;
    private ?string $errorMessage;     // Set if status=failed
}
```

**Value Objects**:
- `SyncLockId`: Composite key (salesOrg + customerId)
- `SyncStatus`: Enum (in_progress, completed, failed)

**Business Rules**:
- Progress can only move forward (no decrement)
- Completed status is immutable
- Failed status includes error message for debugging
- New sync overwrites existing progress for same customer/salesOrg

**Methods**:
```php
public function incrementProcessed(int $count = 1): void
public function markCompleted(): void
public function markFailed(string $error): void
public function getPercentage(): float  // (processed / total) * 100
```

---

### MaterialView (Document) ✨ NEW
**Layer**: Infrastructure (Read Model)  
**Purpose**: Denormalized material data optimized for search and catalog display  
**Persistence**: MongoDB (`material_view` collection)

**Attributes**:
```php
#[MongoDB\Document(collection: 'material_view')]
class MaterialView
{
    #[MongoDB\Id]
    private string $id;                // MongoDB ObjectId
    
    #[MongoDB\Field(type: 'string')]
    private string $materialNumber;    // SAP material number
    
    #[MongoDB\Field(type: 'string')]
    private string $description;       // Material description
    
    #[MongoDB\Field(type: 'string')]
    private string $customerId;
    
    #[MongoDB\Field(type: 'string')]
    private string $salesOrg;
    
    #[MongoDB\Field(type: 'string')]
    private string $posnr;             // POSNR from CustomerMaterial
    
    #[MongoDB\Field(type: 'float')]
    private ?float $priceAmount;
    
    #[MongoDB\Field(type: 'string')]
    private ?string $priceCurrency;
    
    #[MongoDB\Field(type: 'bool')]
    private bool $isAvailable;
    
    #[MongoDB\Field(type: 'collection')]
    private array $embedding;          // Float array[1536] - OpenAI vector
    
    #[MongoDB\Field(type: 'date')]
    private \DateTimeImmutable $lastUpdatedAt;
}
```

**Indexes**:
```javascript
// MongoDB indexes
db.material_view.createIndex({ "materialNumber": 1 })
db.material_view.createIndex({ "customerId": 1, "salesOrg": 1 })
db.material_view.createIndex({ "customerId": 1, "materialNumber": 1 }, { unique: true })

// Vector search index (Atlas Search)
{
  "mappings": {
    "dynamic": false,
    "fields": {
      "embedding": {
        "type": "knnVector",
        "dimensions": 1536,
        "similarity": "cosine"
      },
      "customerId": {
        "type": "string"
      }
    }
  }
}
```

**Business Rules**:
- Read-only from application perspective (updated via events)
- Eventual consistency with MySQL (up to 5s delay acceptable)
- Embedding generated asynchronously after material sync
- Access control enforced by filtering customerId

**Sync Trigger**: Updated when MaterialSyncedEvent is handled

---

### Material (Entity) - EXISTING, NO CHANGES
**Layer**: Domain  
**Purpose**: Master material data from SAP  
**Persistence**: MySQL (`materials` table)

**Attributes** (relevant):
```php
class Material
{
    private string $materialNumber;
    private string $description;
    private string $baseUnitOfMeasure;
    private bool $isActive;
}
```

---

### Customer (Entity) - EXISTING, NO CHANGES
**Layer**: Domain  
**Purpose**: Customer master data from SAP  
**Persistence**: MySQL (`customers` table)

---

## Value Objects

### Posnr ✨ NEW
**Purpose**: SAP position number for price retrieval  
**Validation**: Must be 6 characters, alphanumeric

```php
final class Posnr
{
    private string $value;
    
    public function __construct(string $value)
    {
        if (!preg_match('/^[A-Z0-9]{6}$/', $value)) {
            throw new InvalidPosnrException(
                "POSNR must be 6 alphanumeric characters, got: {$value}"
            );
        }
        $this->value = $value;
    }
    
    public function toString(): string
    {
        return $this->value;
    }
    
    public function equals(Posnr $other): bool
    {
        return $this->value === $other->value;
    }
}
```

**Rationale**: Encapsulates validation, prevents invalid POSNR from entering domain

---

### SyncLockId ✨ NEW
**Purpose**: Composite identifier for sync locks (salesOrg + customerId)  
**Validation**: Both components required

```php
final class SyncLockId
{
    private string $salesOrg;
    private string $customerId;
    
    public function __construct(string $salesOrg, string $customerId)
    {
        if (empty($salesOrg) || empty($customerId)) {
            throw new InvalidArgumentException('SalesOrg and CustomerId required');
        }
        $this->salesOrg = $salesOrg;
        $this->customerId = $customerId;
    }
    
    public function toLockKey(): string
    {
        return "sync_lock_{$this->salesOrg}_{$this->customerId}";
    }
    
    public function equals(SyncLockId $other): bool
    {
        return $this->salesOrg === $other->salesOrg
            && $this->customerId === $other->customerId;
    }
}
```

**Rationale**: Ensures consistent lock key generation, prevents typos

---

### EmbeddingVector ✨ NEW
**Purpose**: OpenAI embedding (1536-dimensional vector)  
**Validation**: Must be array of 1536 floats

```php
final class EmbeddingVector
{
    private const DIMENSIONS = 1536;
    private array $values; // float[]
    
    public function __construct(array $values)
    {
        if (count($values) !== self::DIMENSIONS) {
            throw new InvalidEmbeddingException(
                "Embedding must have " . self::DIMENSIONS . " dimensions"
            );
        }
        
        foreach ($values as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new InvalidEmbeddingException('All values must be numeric');
            }
        }
        
        $this->values = array_map('floatval', $values);
    }
    
    public function toArray(): array
    {
        return $this->values;
    }
    
    public function cosineSimilarity(EmbeddingVector $other): float
    {
        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;
        
        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $dotProduct += $this->values[$i] * $other->values[$i];
            $magnitudeA += $this->values[$i] ** 2;
            $magnitudeB += $other->values[$i] ** 2;
        }
        
        return $dotProduct / (sqrt($magnitudeA) * sqrt($magnitudeB));
    }
}
```

**Rationale**: Type-safe embedding handling, includes similarity calculation for testing

---

### SyncStatus (Enum) ✨ NEW
**Purpose**: Enumeration of sync operation states

```php
enum SyncStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    
    public function isTerminal(): bool
    {
        return $this === self::COMPLETED || $this === self::FAILED;
    }
}
```

---

## Domain Events

### MaterialSyncedEvent ✨ NEW
**Purpose**: Notifies system that material data has been synchronized from SAP  
**Triggers**: MongoDB update, embedding generation

```php
final class MaterialSyncedEvent
{
    public function __construct(
        public readonly string $materialId,
        public readonly string $customerId,
        public readonly string $salesOrg,
        public readonly Posnr $posnr,
        public readonly \DateTimeImmutable $occuredAt
    ) {}
}
```

**Handlers**:
- `UpdateMongoOnMaterialSyncedHandler`: Updates MaterialView in MongoDB
- `GenerateEmbeddingOnMaterialSyncedHandler`: Dispatches GenerateEmbeddingCommand

---

### PriceFetchedEvent ✨ NEW
**Purpose**: Notifies that price has been retrieved from SAP  
**Triggers**: Embedding generation (if not already generated)

```php
final class PriceFetchedEvent
{
    public function __construct(
        public readonly string $materialId,
        public readonly string $customerId,
        public readonly Money $price,
        public readonly \DateTimeImmutable $occuredAt
    ) {}
}
```

**Handler**:
- `GenerateEmbeddingOnPriceFetchedHandler`: Optionally dispatches embedding generation

---

## Repository Interfaces

### CustomerMaterialRepositoryInterface ⚡ EXTENDED
```php
interface CustomerMaterialRepositoryInterface
{
    public function save(CustomerMaterial $customerMaterial): void;
    public function findByCustomerAndMaterial(
        string $customerId,
        string $materialId,
        string $salesOrg
    ): ?CustomerMaterial;
    
    // ⚡ NEW METHODS
    public function findByPosnr(
        string $customerId,
        string $salesOrg,
        Posnr $posnr
    ): ?CustomerMaterial;
    
    public function findMaterialIdsByCustomer(
        string $customerId,
        string $salesOrg
    ): array; // Returns string[] of material IDs for access control
}
```

---

### MaterialReadRepositoryInterface ✨ NEW
```php
interface MaterialReadRepositoryInterface
{
    public function findByCustomerAndSalesOrg(
        string $customerId,
        string $salesOrg,
        int $limit = 100,
        int $offset = 0
    ): array; // Returns MaterialView[]
    
    public function keywordSearch(
        string $searchTerm,
        array $allowedMaterialIds,
        int $limit = 20
    ): array; // Returns MaterialView[]
    
    public function vectorSearch(
        EmbeddingVector $queryEmbedding,
        array $allowedMaterialIds,
        int $limit = 20
    ): array; // Returns MaterialView[]
    
    public function save(MaterialView $materialView): void;
    public function findByMaterialNumber(
        string $materialNumber,
        string $customerId
    ): ?MaterialView;
}
```

---

### SyncProgressRepositoryInterface ✨ NEW
```php
interface SyncProgressRepositoryInterface
{
    public function save(SyncProgress $progress): void;
    public function findByLockId(SyncLockId $lockId): ?SyncProgress;
    public function findByCustomer(
        string $customerId,
        string $salesOrg
    ): ?SyncProgress;
}
```

---

### SyncLockRepositoryInterface ✨ NEW
```php
interface SyncLockRepositoryInterface
{
    public function acquire(SyncLockId $lockId, int $ttl = 600): bool;
    public function release(SyncLockId $lockId): void;
    public function isLocked(SyncLockId $lockId): bool;
    public function getRemainingTtl(SyncLockId $lockId): ?int;
}
```

**Implementation**: RedisSyncLockRepository using Symfony Lock Component

---

## Database Schema Changes

### Migration: Add POSNR to customer_materials

```sql
-- Migration: Version20260218_AddPosnrToCustomerMaterial.php

ALTER TABLE customer_materials 
ADD COLUMN posnr VARCHAR(6) NULL COMMENT 'SAP position number for price retrieval';

CREATE INDEX idx_customer_materials_posnr 
ON customer_materials(customer_id, sales_org, posnr);

-- Existing customers get NULL posnr, will be populated on next sync
```

---

### Migration: Create sync_progress table

```sql
-- Migration: Version20260218_CreateSyncProgress.php

CREATE TABLE sync_progress (
    id VARCHAR(100) PRIMARY KEY,  -- Composite: {salesOrg}_{customerId}
    customer_id VARCHAR(10) NOT NULL,
    sales_org VARCHAR(4) NOT NULL,
    total_materials INT NOT NULL DEFAULT 0,
    processed_materials INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT chk_status CHECK (status IN ('in_progress', 'completed', 'failed')),
    CONSTRAINT chk_progress CHECK (processed_materials <= total_materials),
    
    INDEX idx_customer_sales_org (customer_id, sales_org),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## MongoDB Collections

### material_view Collection

```javascript
// Document structure
{
  "_id": ObjectId("..."),
  "materialNumber": "00020006800",
  "description": "HEMOSIL QC Normal Level 2",
  "customerId": "0000210839",
  "salesOrg": "185",
  "posnr": "000010",
  "priceAmount": 125.50,
  "priceCurrency": "EUR",
  "isAvailable": true,
  "embedding": [0.123, -0.456, ...], // 1536 floats
  "lastUpdatedAt": ISODate("2026-02-18T10:30:00Z")
}

// Indexes (created via Doctrine ODM or MongoDB shell)
db.material_view.createIndex(
  { "customerId": 1, "materialNumber": 1 },
  { unique: true, name: "idx_customer_material" }
);

db.material_view.createIndex(
  { "customerId": 1, "salesOrg": 1 },
  { name: "idx_customer_salesorg" }
);

db.material_view.createIndex(
  { "materialNumber": 1 },
  { name: "idx_material_number" }
);

db.material_view.createIndex(
  { "lastUpdatedAt": -1 },
  { name: "idx_last_updated" }
);
```

**Vector Search Index** (MongoDB Atlas):
```javascript
{
  "name": "material_vector_index",
  "type": "vectorSearch",
  "definition": {
    "fields": [
      {
        "type": "vector",
        "path": "embedding",
        "numDimensions": 1536,
        "similarity": "cosine"
      },
      {
        "type": "filter",
        "path": "customerId"
      },
      {
        "type": "filter",
        "path": "salesOrg"
      }
    ]
  }
}
```

---

## Entity Relationships Diagram

```
┌──────────────┐         ┌──────────────┐
│  Customer    │         │  Material    │
│              │         │              │
│ - id         │         │ - id         │
│ - name       │         │ - number     │
│ - salesOrg   │         │ - desc       │
└──────┬───────┘         └──────┬───────┘
       │                        │
       │                        │
       │    ┌──────────────────────────────┐
       ├───▶│  CustomerMaterial (Aggregate)│
       │    │                              │
       └───▶│ - id                         │
            │ - customerId (FK)            │
            │ - materialId (FK)            │
            │ - salesOrg                   │
            │ - posnr ⚡NEW                 │
            │ - price                      │
            │ - isAvailable                │
            │ - lastSyncedAt               │
            └────────────┬─────────────────┘
                         │
                         │ MaterialSyncedEvent
                         │ (async, eventual)
                         ↓
            ┌────────────────────────────┐
            │  MaterialView (MongoDB)    │
            │                            │
            │ - materialNumber           │
            │ - description              │
            │ - customerId               │
            │ - salesOrg                 │
            │ - posnr                    │
            │ - priceAmount              │
            │ - priceCurrency            │
            │ - embedding[1536] ✨NEW     │
            │ - lastUpdatedAt            │
            └────────────────────────────┘

┌──────────────────────┐
│  SyncProgress ✨NEW   │
│                      │
│ - id (composite)     │
│ - customerId         │
│ - salesOrg           │
│ - totalMaterials     │
│ - processedMaterials │
│ - status             │
│ - startedAt          │
│ - completedAt        │
└──────────────────────┘
```

---

## Data Flow: Material Sync with POSNR

```
1. SAP ZSDO_EBU_LOAD_MATERIALS
   ↓
   Returns: X_MAT_FOUND[] with POSNR field
   
2. SyncCustomerFromSapHandler
   ↓
   Extract each material + POSNR
   
3. CustomerMaterial Entity
   ↓
   Persist to MySQL with POSNR
   
4. MaterialSyncedEvent (dispatched)
   ↓
   [Async] UpdateMongoOnMaterialSyncedHandler
   
5. MaterialView (MongoDB)
   ↓
   Document created/updated with POSNR
   
6. SAP ZSDO_EBU_SHOW_MATERIAL_PRICE
   ↓
   Request includes POSNR from step 3
   
7. Price returned, stored in CustomerMaterial
   ↓
   8. MaterialSyncedEvent (price update)
   
9. MaterialView updated with price
   ↓
   10. GenerateEmbeddingCommand (dispatched)
   
11. OpenAiEmbeddingClient
   ↓
   Generates 1536-dim vector
   
12. MaterialView updated with embedding
```

---

## Access Control Model

**Rule**: MongoDB queries ALWAYS filtered by allowed material IDs from MySQL

```php
// Security flow
1. User requests catalog/search
2. Query MySQL: "SELECT material_id FROM customer_materials WHERE customer_id = ?"
3. Get array of allowed material IDs: ["00020006800", "00020006801", ...]
4. Query MongoDB: db.material_view.find({ materialNumber: { $in: allowedIds } })
5. Return results
```

**Rationale**: 
- MySQL enforces access control (source of truth)
- MongoDB cannot be queried directly without MySQL authorization
- Prevents unauthorized access to materials via MongoDB
- Scales well (allowed IDs list cached per request)

---

## Summary

### New Entities
- SyncProgress: Tracks sync operation progress
- MaterialView: MongoDB read model with embeddings

### Modified Entities
- CustomerMaterial: Added POSNR field

### New Value Objects
- Posnr: SAP position number (6 chars)
- EmbeddingVector: OpenAI embedding (1536 floats)
- SyncLockId: Composite lock identifier
- SyncStatus: Enum for sync states

### New Repositories
- MaterialReadRepositoryInterface (MongoDB)
- SyncProgressRepositoryInterface (MySQL)
- SyncLockRepositoryInterface (Redis)

### Database Changes
- customer_materials.posnr column (VARCHAR(6))
- sync_progress table (new)
- material_view collection (MongoDB, new)

All changes maintain backward compatibility. Existing CustomerMaterial records get NULL posnr, populated on next sync.
