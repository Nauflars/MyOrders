# MongoDB Contract

**Feature**: 002-material-pricing-search  
**Purpose**: Define MongoDB document schemas, indexes, and query patterns

## Database Configuration

**Database Name**: `myorders`  
**Collection Name**: `material_view`  
**ODM**: Doctrine MongoDB ODM 5.0  
**Connection**: `mongodb://mongodb:27017` (Docker service)

---

## MaterialView Document ✨ NEW

**Purpose**: Denormalized read model for fast catalog display and search  
**Sync Strategy**: Eventual consistency from MySQL via domain events

### Document Schema

```php
<?php

namespace App\Infrastructure\Persistence\MongoDB\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: 'material_view')]
#[MongoDB\HasLifecycleCallbacks]
class MaterialView
{
    #[MongoDB\Id]
    private string $id;
    
    #[MongoDB\Field(type: 'string')]
    #[MongoDB\Index]
    private string $materialNumber;  // SAP material number (e.g., "00020006800")
    
    #[MongoDB\Field(type: 'string')]
    private string $description;  // Material description (e.g., "HEMOSIL QC Normal Level 2")
    
    #[MongoDB\Field(type: 'string')]
    #[MongoDB\Index]
    private string $customerId;  // Customer ID (e.g., "0000210839")
    
    #[MongoDB\Field(type: 'string')]
    #[MongoDB\Index]
    private string $salesOrg;  // Sales organization (e.g., "185")
    
    #[MongoDB\Field(type: 'string')]
    private string $posnr;  // SAP position number (e.g., "000010")
    
    #[MongoDB\Field(type: 'float', nullable: true)]
    private ?float $priceAmount;  // Price value (e.g., 125.50)
    
    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $priceCurrency;  // Currency code (e.g., "EUR")
    
    #[MongoDB\Field(type: 'bool')]
    private bool $isAvailable;
    
    #[MongoDB\Field(type: 'collection', nullable: true)]
    private ?array $embedding = null;  // Float array[1536] - OpenAI vector
    
    #[MongoDB\Field(type: 'date')]
    private \DateTimeImmutable $lastUpdatedAt;
    
    #[MongoDB\PrePersist]
    #[MongoDB\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->lastUpdatedAt = new \DateTimeImmutable();
    }
}
```

### Field Specifications

| Field | Type | Indexed | Nullable | Description | Max Length |
|-------|------|---------|----------|-------------|------------|
| id | ObjectId | Yes (PK) | No | MongoDB generated ID | - |
| materialNumber | string | Yes | No | SAP material number | 18 chars |
| description | string | No | No | Material description | 255 chars |
| customerId | string | Yes | No | Customer ID | 10 chars |
| salesOrg | string | Yes | No | Sales organization | 4 chars |
| posnr | string | No | No | SAP position number | 6 chars |
| priceAmount | float | No | Yes | Price value | - |
| priceCurrency | string | No | Yes | Currency code | 3 chars |
| isAvailable | bool | No | No | Availability flag | - |
| embedding | array(float) | No* | Yes | OpenAI vector (1536 dims) | 1536 floats |
| lastUpdatedAt | date | No | No | Last sync timestamp | - |

*embedding indexed via Atlas Search vector index (separate from regular indexes)

---

## Indexes

### Standard Indexes

```javascript
// Create via Doctrine ODM or MongoDB shell

// 1. Unique constraint: One document per customer+material
db.material_view.createIndex(
    { "customerId": 1, "materialNumber": 1 },
    { unique: true, name: "idx_customer_material_unique" }
);

// 2. Query by customer and sales org (catalog page)
db.material_view.createIndex(
    { "customerId": 1, "salesOrg": 1 },
    { name: "idx_customer_salesorg" }
);

// 3. Query by material number (material lookup)
db.material_view.createIndex(
    { "materialNumber": 1 },
    { name: "idx_material_number" }
);

// 4. Query by customer ID (access control)
db.material_view.createIndex(
    { "customerId": 1 },
    { name: "idx_customer_id" }
);

// 5. Sort by last updated (staleness detection)
db.material_view.createIndex(
    { "lastUpdatedAt": -1 },
    { name: "idx_last_updated" }
);
```

### Vector Search Index (Atlas Search)

**Requires**: MongoDB Atlas or self-hosted with Atlas Search

```javascript
// Create via Atlas UI or API
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
      },
      {
        "type": "filter",
        "path": "isAvailable"
      }
    ]
  }
}
```

**Index Properties**:
- **numDimensions**: 1536 (matches text-embedding-3-small output)
- **similarity**: cosine (range: -1 to 1, higher = more similar)
- **filters**: Allow filtering by customer, sales org, availability before vector search

---

## Query Patterns

### 1. Keyword Search (Material Number or Description)

```php
public function keywordSearch(
    string $searchTerm,
    array $allowedMaterialIds,
    int $limit = 20
): array {
    $qb = $this->documentManager->createQueryBuilder(MaterialView::class);
    
    return $qb
        ->addOr(
            $qb->expr()->field('materialNumber')->equals($searchTerm)
        )
        ->addOr(
            $qb->expr()->field('description')->equals(
                new \MongoDB\BSON\Regex($searchTerm, 'i')  // Case-insensitive
            )
        )
        ->field('materialNumber')->in($allowedMaterialIds)
        ->limit($limit)
        ->getQuery()
        ->execute()
        ->toArray();
}
```

**MongoDB Query**:
```javascript
db.material_view.find({
  "$or": [
    { "materialNumber": "00020006800" },
    { "description": /hemosil/i }
  ],
  "materialNumber": { "$in": ["00020006800", "00020006801", ...] }
}).limit(20)
```

**Performance**: <100ms for 10,000 documents (materialNumber indexed)

---

### 2. Vector Similarity Search (Semantic)

```php
public function vectorSearch(
    EmbeddingVector $queryEmbedding,
    array $allowedMaterialIds,
    int $limit = 20
): array {
    $pipeline = [
        [
            '$search' => [
                'index' => 'material_vector_index',
                'knnBeta' => [
                    'vector' => $queryEmbedding->toArray(),  // Array of 1536 floats
                    'path' => 'embedding',
                    'k' => $limit * 2,  // Over-fetch for filtering
                    'filter' => [
                        'materialNumber' => ['$in' => $allowedMaterialIds]
                    ]
                ]
            ]
        ],
        [
            '$addFields' => [
                'relevanceScore' => ['$meta' => 'searchScore']
            ]
        ],
        ['$limit' => $limit]
    ];
    
    return $this->documentManager
        ->getDocumentCollection(MaterialView::class)
        ->aggregate($pipeline)
        ->toArray();
}
```

**MongoDB Aggregation**:
```javascript
db.material_view.aggregate([
  {
    "$search": {
      "index": "material_vector_index",
      "knnBeta": {
        "vector": [0.123, -0.456, ...],  // 1536 floats
        "path": "embedding",
        "k": 40,  // Over-fetch
        "filter": {
          "materialNumber": { "$in": ["00020006800", "00020006801", ...] }
        }
      }
    }
  },
  {
    "$addFields": {
      "relevanceScore": { "$meta": "searchScore" }
    }
  },
  { "$limit": 20 }
])
```

**Performance**: <500ms including filter (Atlas Search optimized)

---

### 3. Get Catalog Materials

```php
public function findByCustomerAndSalesOrg(
    string $customerId,
    string $salesOrg,
    int $limit = 100,
    int $offset = 0
): array {
    return $this->documentManager
        ->createQueryBuilder(MaterialView::class)
        ->field('customerId')->equals($customerId)
        ->field('salesOrg')->equals($salesOrg)
        ->limit($limit)
        ->skip($offset)
        ->sort('materialNumber', 'ASC')
        ->getQuery()
        ->execute()
        ->toArray();
}
```

**MongoDB Query**:
```javascript
db.material_view.find({
  "customerId": "0000210839",
  "salesOrg": "185"
}).sort({ "materialNumber": 1 }).limit(100).skip(0)
```

**Performance**: <2s for 5,000 documents (compound index on customerId+salesOrg)

---

### 4. Count Materials for Pagination

```php
public function countByCustomer(
    string $customerId,
    string $salesOrg
): int {
    return $this->documentManager
        ->createQueryBuilder(MaterialView::class)
        ->field('customerId')->equals($customerId)
        ->field('salesOrg')->equals($salesOrg)
        ->count()
        ->getQuery()
        ->execute();
}
```

---

### 5. Find by Material Number

```php
public function findByMaterialNumber(
    string $materialNumber,
    string $customerId
): ?MaterialView {
    return $this->documentManager
        ->createQueryBuilder(MaterialView::class)
        ->field('materialNumber')->equals($materialNumber)
        ->field('customerId')->equals($customerId)
        ->getQuery()
        ->getSingleResult();
}
```

---

## Document Lifecycle

### Create Document

```php
$materialView = new MaterialView();
$materialView->setMaterialNumber('00020006800');
$materialView->setDescription('HEMOSIL QC Normal Level 2');
$materialView->setCustomerId('0000210839');
$materialView->setSalesOrg('185');
$materialView->setPosnr('000010');
$materialView->setPriceAmount(125.50);
$materialView->setPriceCurrency('EUR');
$materialView->setIsAvailable(true);
$materialView->setEmbedding(null);  // Generated later

$this->documentManager->persist($materialView);
$this->documentManager->flush();
```

### Update Document

```php
$materialView = $this->findByMaterialNumber('00020006800', '0000210839');

if ($materialView) {
    $materialView->setPriceAmount(130.00);  // Price updated
    $materialView->setPriceCurrency('EUR');
    // lastUpdatedAt auto-updated via @PreUpdate
    
    $this->documentManager->flush();
}
```

### Add Embedding

```php
$materialView = $this->findByMaterialNumber('00020006800', '0000210839');

if ($materialView && !$materialView->hasEmbedding()) {
    $embedding = $this->embeddingClient->generateEmbedding(
        $materialView->getDescription()
    );
    
    $materialView->setEmbedding($embedding);  // Array of 1536 floats
    
    $this->documentManager->flush();
}
```

---

## Eventual Consistency

### Sync Flow: MySQL → MongoDB

```
1. CustomerMaterial updated in MySQL (source of truth)
   ↓
2. MaterialSyncedEvent dispatched to RabbitMQ (async)
   ↓
3. UpdateMongoOnMaterialSyncedHandler processes event
   ↓
4. MaterialView created/updated in MongoDB
   ↓
5. User query reads from MongoDB (may be slightly stale)
```

### Acceptable Lag

- **Target**: <5 seconds from MySQL write to MongoDB availability
- **Monitoring**: Track lag via lastUpdatedAt vs MySQL updated_at
- **Fallback**: If MongoDB unavailable, fall back to MySQL queries (slower)

### Staleness Detection

```php
public function isStale(MaterialView $materialView, int $maxAgeSeconds = 300): bool
{
    $age = time() - $materialView->getLastUpdatedAt()->getTimestamp();
    return $age > $maxAgeSeconds;
}
```

---

## Data Validation

### Embedding Validation

```php
public function setEmbedding(?array $embedding): void
{
    if ($embedding !== null) {
        if (count($embedding) !== 1536) {
            throw new \InvalidArgumentException(
                'Embedding must have exactly 1536 dimensions'
            );
        }
        
        foreach ($embedding as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new \InvalidArgumentException(
                    'All embedding values must be numeric'
                );
            }
        }
    }
    
    $this->embedding = $embedding;
}
```

---

## Testing Contracts

### Integration Tests with Real MongoDB

```php
class MongoMaterialRepositoryTest extends KernelTestCase
{
    private DocumentManager $documentManager;
    private MongoMaterialRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->documentManager = self::getContainer()->get(DocumentManager::class);
        $this->repository = self::getContainer()->get(MongoMaterialRepository::class);
        
        // Clean collection before each test
        $this->documentManager
            ->getDocumentCollection(MaterialView::class)
            ->deleteMany([]);
    }
    
    public function testSaveAndFindMaterialView(): void
    {
        // Arrange
        $materialView = new MaterialView();
        $materialView->setMaterialNumber('TEST001');
        $materialView->setDescription('Test Material');
        $materialView->setCustomerId('CUST001');
        $materialView->setSalesOrg('185');
        $materialView->setPosnr('000010');
        $materialView->setPriceAmount(100.00);
        $materialView->setPriceCurrency('EUR');
        $materialView->setIsAvailable(true);
        
        // Act
        $this->documentManager->persist($materialView);
        $this->documentManager->flush();
        $this->documentManager->clear();  // Clear identity map
        
        // Assert
        $found = $this->repository->findByMaterialNumber('TEST001', 'CUST001');
        $this->assertNotNull($found);
        $this->assertSame('Test Material', $found->getDescription());
        $this->assertSame(100.00, $found->getPriceAmount());
    }
    
    public function testVectorSearch(): void
    {
        // Arrange: Seed with documents with embeddings
        $this->seedMaterialsWithEmbeddings([
            ['materialNumber' => 'MAT001', 'embedding' => $this->generateMockEmbedding()],
            ['materialNumber' => 'MAT002', 'embedding' => $this->generateMockEmbedding()],
        ]);
        
        $queryEmbedding = new EmbeddingVector($this->generateMockEmbedding());
        
        // Act
        $results = $this->repository->vectorSearch(
            queryEmbedding: $queryEmbedding,
            allowedMaterialIds: ['MAT001', 'MAT002'],
            limit: 10
        );
        
        // Assert
        $this->assertGreaterThan(0, count($results));
    }
    
    private function generateMockEmbedding(): array
    {
        // Generate random 1536-dimensional vector for testing
        return array_map(fn() => (float) rand(-100, 100) / 100, range(1, 1536));
    }
}
```

---

## Performance Optimization

### Index Usage Analysis

```javascript
// Check if query uses indexes
db.material_view.find({
  "customerId": "0000210839",
  "salesOrg": "185"
}).explain("executionStats")

// Look for "IXSCAN" in winningPlan (good)
// Avoid "COLLSCAN" (bad - full collection scan)
```

### Query Performance Metrics

```javascript
// Enable profiling
db.setProfilingLevel(1, { slowms: 100 });  // Log queries >100ms

// View slow queries
db.system.profile.find().sort({ ts: -1 }).limit(10);
```

### Embedding Storage Optimization

**Option 1**: Store embeddings in MongoDB (current approach)
- **Pros**: Simple, single data store
- **Cons**: Large documents (~6KB per material)

**Option 2**: Store embeddings in external vector DB (e.g., Pinecone, Weaviate)
- **Pros**: Optimized for vector operations
- **Cons**: Additional service, eventual consistency complexity

**Decision**: Use MongoDB for v1, evaluate external vector DB if scale requires

---

## Backup & Recovery

### Backup Strategy

```bash
# Full dump
mongodump --db myorders --collection material_view --out /backup/

# Restore
mongorestore --db myorders /backup/myorders/
```

### Rebuild from MySQL

If MongoDB data corruption occurs, rebuild from source of truth:

```php
// CLI command: bin/console app:rebuild-mongo-materials
class RebuildMongoMaterialsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. Drop material_view collection
        $this->documentManager
            ->getDocumentCollection(MaterialView::class)
            ->drop();
        
        // 2. Recreate indexes
        $this->createIndexes();
        
        // 3. For each CustomerMaterial in MySQL:
        $customerMaterials = $this->customerMaterialRepository->findAll();
        
        foreach ($customerMaterials as $cm) {
            // Create MaterialView from CustomerMaterial
            $materialView = MaterialView::fromCustomerMaterial($cm);
            $this->documentManager->persist($materialView);
        }
        
        $this->documentManager->flush();
        
        $output->writeln('Rebuild complete');
        return Command::SUCCESS;
    }
}
```

---

## Summary

### Document Schema
- **MaterialView**: Denormalized read model with embedding vector
- **Fields**: Material number, description, customer, sales org, POSNR, price, availability, embedding (1536 floats)
- **Indexes**: Unique (customer+material), compound (customer+salesOrg), vector search index

### Query Patterns
- Keyword search: <100ms for 10,000 documents
- Vector search: <500ms including embedding generation
- Catalog display: <2s for 5,000 materials

### Consistency
- Eventual consistency from MySQL via domain events
- Target lag: <5s
- Fallback to MySQL if MongoDB unavailable

### Testing
- Integration tests with real MongoDB
- Mock embeddings for vector search tests
- Performance benchmarks for queries
