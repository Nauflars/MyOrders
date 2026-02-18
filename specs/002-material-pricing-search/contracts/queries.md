# Queries Contract

**Feature**: 002-material-pricing-search  
**Purpose**: Define query schemas, validation rules, and response formats

## Query Overview

Queries represent read-only operations. All queries are synchronous (never dispatched to RabbitMQ) and return data immediately.

---

## SearchMaterialsQuery ✨ NEW

**Purpose**: Search materials by keyword (material number or description)  
**Transport**: `sync` (synchronous, no queue)  
**Handler**: `App\Application\QueryHandler\SearchMaterialsHandler`

### Schema

```php
final class SearchMaterialsQuery
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $salesOrg,
        public readonly string $searchTerm,
        public readonly int $limit = 20,
        public readonly int $offset = 0
    ) {}
}
```

### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| customerId | string | Yes | Must exist in customers table |
| salesOrg | string | Yes | 4-character sales organization code |
| searchTerm | string | Yes | Non-empty, max 200 characters |
| limit | int | No | 1-100 (default 20) |
| offset | int | No | ≥0 (default 0) |

### Response Format

```php
// Returns: MaterialSearchResult[]
class MaterialSearchResult
{
    public string $materialNumber;
    public string $description;
    public ?float $priceAmount;
    public ?string $priceCurrency;
    public bool $isAvailable;
    public string $posnr;
}
```

### Query Execution Flow

```php
class SearchMaterialsHandler
{
    public function __invoke(SearchMaterialsQuery $query): array
    {
        // 1. Get allowed material IDs from MySQL (access control)
        $allowedIds = $this->customerMaterialRepository
            ->findMaterialIdsByCustomer($query->customerId, $query->salesOrg);
        
        // 2. Query MongoDB with keyword search, filtered by allowed IDs
        $results = $this->mongoMaterialRepository->keywordSearch(
            searchTerm: $query->searchTerm,
            allowedMaterialIds: $allowedIds,
            limit: $query->limit
        );
        
        // 3. Transform to MaterialSearchResult DTOs
        return array_map(
            fn($doc) => MaterialSearchResult::fromDocument($doc),
            $results
        );
    }
}
```

### MongoDB Query

```php
// Keyword search implementation
$cursor = $this->documentManager->createQueryBuilder(MaterialView::class)
    ->addOr(
        $qb->expr()->field('materialNumber')->equals($searchTerm)
    )
    ->addOr(
        $qb->expr()->field('description')->equals(new \MongoRegex("/$searchTerm/i"))
    )
    ->field('materialNumber')->in($allowedMaterialIds)
    ->limit($limit)
    ->skip($offset)
    ->getQuery()
    ->execute();
```

### Performance Targets

- Response time: <100ms for 10,000 materials
- MongoDB indexes ensure efficient filtering

### Example

```php
// Request
$query = new SearchMaterialsQuery(
    customerId: '0000210839',
    salesOrg: '185',
    searchTerm: 'HEMOSIL',
    limit: 10
);

// Response
[
    MaterialSearchResult {
        materialNumber: "00020006800",
        description: "HEMOSIL QC Normal Level 2",
        priceAmount: 125.50,
        priceCurrency: "EUR",
        isAvailable: true,
        posnr: "000010"
    },
    // ... more results
]
```

---

## SemanticSearchQuery ✨ NEW

**Purpose**: Search materials using AI-powered semantic similarity  
**Transport**: `sync` (synchronous)  
**Handler**: `App\Application\QueryHandler\SemanticSearchHandler`

### Schema

```php
final class SemanticSearchQuery
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $salesOrg,
        public readonly string $naturalLanguageQuery,
        public readonly int $limit = 20
    ) {}
}
```

### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| customerId | string | Yes | Must exist in customers table |
| salesOrg | string | Yes | 4-character sales organization code |
| naturalLanguageQuery | string | Yes | Non-empty, max 500 characters |
| limit | int | No | 1-50 (default 20) |

### Response Format

```php
// Returns: SemanticSearchResult[]
class SemanticSearchResult
{
    public string $materialNumber;
    public string $description;
    public ?float $priceAmount;
    public ?string $priceCurrency;
    public bool $isAvailable;
    public float $relevanceScore;  // 0.0 to 1.0 (cosine similarity)
}
```

### Query Execution Flow

```php
class SemanticSearchHandler
{
    public function __invoke(SemanticSearchQuery $query): array
    {
        // 1. Generate embedding for query using OpenAI
        $queryEmbedding = $this->embeddingClient->generateEmbedding(
            $query->naturalLanguageQuery
        );
        
        // 2. Get allowed material IDs from MySQL (access control)
        $allowedIds = $this->customerMaterialRepository
            ->findMaterialIdsByCustomer($query->customerId, $query->salesOrg);
        
        // 3. Perform vector similarity search in MongoDB
        $results = $this->mongoMaterialRepository->vectorSearch(
            queryEmbedding: new EmbeddingVector($queryEmbedding),
            allowedMaterialIds: $allowedIds,
            limit: $query->limit
        );
        
        // 4. Transform to SemanticSearchResult DTOs (includes relevance score)
        return array_map(
            fn($doc) => SemanticSearchResult::fromDocument($doc),
            $results
        );
    }
}
```

### MongoDB Vector Search

```php
// Atlas Search vector similarity
$pipeline = [
    [
        '$search' => [
            'index' => 'material_vector_index',
            'knnBeta' => [
                'vector' => $queryEmbedding->toArray(),
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

$cursor = $this->documentManager
    ->getDocumentCollection(MaterialView::class)
    ->aggregate($pipeline);
```

### Performance Targets

- Response time: <1s including embedding generation
- OpenAI API call: ~200ms
- MongoDB vector search: ~300ms

### Example

```php
// Request
$query = new SemanticSearchQuery(
    customerId: '0000210839',
    salesOrg: '185',
    naturalLanguageQuery: 'blood coagulation test kits',
    limit: 5
);

// Response
[
    SemanticSearchResult {
        materialNumber: "00020006800",
        description: "HEMOSIL QC Normal Level 2",
        priceAmount: 125.50,
        priceCurrency: "EUR",
        isAvailable: true,
        relevanceScore: 0.87
    },
    SemanticSearchResult {
        materialNumber: "00020006801",
        description: "Coagulation Reagent PT",
        priceAmount: 98.00,
        priceCurrency: "EUR",
        isAvailable: true,
        relevanceScore: 0.82
    },
    // ... more results
]
```

---

## GetCatalogQuery ✨ NEW

**Purpose**: Retrieve materials for catalog page display  
**Transport**: `sync` (synchronous)  
**Handler**: `App\Application\QueryHandler\GetCatalogHandler`

### Schema

```php
final class GetCatalogQuery
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $salesOrg,
        public readonly int $limit = 100,
        public readonly int $offset = 0
    ) {}
}
```

### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| customerId | string | Yes | Must exist in customers table |
| salesOrg | string | Yes | 4-character sales organization code |
| limit | int | No | 1-500 (default 100) |
| offset | int | No | ≥0 (default 0) |

### Response Format

```php
class CatalogResult
{
    public array $materials;        // MaterialCatalogItem[]
    public int $totalCount;
    public ?SyncProgressDTO $syncProgress;
}

class MaterialCatalogItem
{
    public string $materialNumber;
    public string $description;
    public ?float $priceAmount;
    public ?string $priceCurrency;
    public bool $isAvailable;
    public string $lastUpdated;  // ISO 8601 timestamp
}

class SyncProgressDTO
{
    public int $totalMaterials;
    public int $processedMaterials;
    public string $status;  // in_progress, completed, failed
    public ?string $estimatedCompletion;  // ISO 8601 timestamp
}
```

### Query Execution Flow

```php
class GetCatalogHandler
{
    public function __invoke(GetCatalogQuery $query): CatalogResult
    {
        // 1. Get materials from MongoDB (fast read model)
        $materials = $this->mongoMaterialRepository->findByCustomerAndSalesOrg(
            customerId: $query->customerId,
            salesOrg: $query->salesOrg,
            limit: $query->limit,
            offset: $query->offset
        );
        
        // 2. Get total count for pagination
        $totalCount = $this->mongoMaterialRepository->countByCustomer(
            $query->customerId,
            $query->salesOrg
        );
        
        // 3. Get sync progress (if any active sync)
        $syncProgress = $this->syncProgressRepository->findByCustomer(
            $query->customerId,
            $query->salesOrg
        );
        
        return new CatalogResult(
            materials: array_map(
                fn($doc) => MaterialCatalogItem::fromDocument($doc),
                $materials
            ),
            totalCount: $totalCount,
            syncProgress: $syncProgress ? SyncProgressDTO::fromEntity($syncProgress) : null
        );
    }
}
```

### Performance Targets

- Response time: <2s for 5,000 materials
- MongoDB query optimized with indexes
- Pagination prevents loading entire catalog

### Example

```php
// Request
$query = new GetCatalogQuery(
    customerId: '0000210839',
    salesOrg: '185',
    limit: 50,
    offset: 0
);

// Response
CatalogResult {
    materials: [
        MaterialCatalogItem {
            materialNumber: "00020006800",
            description: "HEMOSIL QC Normal Level 2",
            priceAmount: 125.50,
            priceCurrency: "EUR",
            isAvailable: true,
            lastUpdated: "2026-02-18T10:30:00Z"
        },
        // ... 49 more materials
    ],
    totalCount: 1250,
    syncProgress: SyncProgressDTO {
        totalMaterials: 1300,
        processedMaterials: 1250,
        status: "in_progress",
        estimatedCompletion: "2026-02-18T10:35:00Z"
    }
}
```

---

## GetSyncProgressQuery ✨ NEW

**Purpose**: Get current sync progress for progress bar updates  
**Transport**: `sync` (synchronous, polled from frontend)  
**Handler**: `App\Application\QueryHandler\GetSyncProgressHandler`

### Schema

```php
final class GetSyncProgressQuery
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $salesOrg
    ) {}
}
```

### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| customerId | string | Yes | Must exist in customers table |
| salesOrg | string | Yes | 4-character sales organization code |

### Response Format

```php
class SyncProgressResult
{
    public int $totalMaterials;
    public int $processedMaterials;
    public float $percentage;         // 0.0 to 100.0
    public string $status;            // in_progress, completed, failed
    public string $startedAt;         // ISO 8601
    public ?string $completedAt;      // ISO 8601
    public ?string $estimatedCompletion;  // ISO 8601
    public ?string $errorMessage;
}
```

### Query Execution Flow

```php
class GetSyncProgressHandler
{
    public function __invoke(GetSyncProgressQuery $query): ?SyncProgressResult
    {
        // 1. Query sync_progress table
        $progress = $this->syncProgressRepository->findByCustomer(
            $query->customerId,
            $query->salesOrg
        );
        
        if (!$progress) {
            return null;  // No sync in progress or completed
        }
        
        // 2. Calculate percentage and estimated completion
        $percentage = ($progress->totalMaterials > 0)
            ? ($progress->processedMaterials / $progress->totalMaterials) * 100
            : 0;
        
        $estimatedCompletion = $this->calculateEstimatedCompletion($progress);
        
        return SyncProgressResult::fromEntity($progress, $percentage, $estimatedCompletion);
    }
}
```

### Performance Targets

- Response time: <50ms (simple MySQL query)
- Polled every 2 seconds from frontend

### Example

```php
// Request (called every 2 seconds via AJAX)
$query = new GetSyncProgressQuery(
    customerId: '0000210839',
    salesOrg: '185'
);

// Response
SyncProgressResult {
    totalMaterials: 1300,
    processedMaterials: 650,
    percentage: 50.0,
    status: "in_progress",
    startedAt: "2026-02-18T10:20:00Z",
    completedAt: null,
    estimatedCompletion: "2026-02-18T10:35:00Z",
    errorMessage: null
}
```

---

## Query Routing Configuration

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            # All queries are synchronous (sync transport)
            'App\Application\Query\SearchMaterialsQuery': sync
            'App\Application\Query\SemanticSearchQuery': sync
            'App\Application\Query\GetCatalogQuery': sync
            'App\Application\Query\GetSyncProgressQuery': sync
```

---

## Access Control Pattern

**ALL queries enforce access control by filtering allowed material IDs from MySQL:**

```php
// Standard access control flow
public function handle(Query $query): array
{
    // Step 1: Get allowed material IDs from source of truth (MySQL)
    $allowedIds = $this->customerMaterialRepository
        ->findMaterialIdsByCustomer($query->customerId, $query->salesOrg);
    
    // Step 2: Query MongoDB with filter
    return $this->mongoMaterialRepository->query(
        /* query params */,
        allowedMaterialIds: $allowedIds
    );
}
```

**Security Guarantee**: MongoDB can never be queried without prior MySQL authorization

---

## Testing Contracts

### Unit Tests

```php
class SearchMaterialsQueryTest extends TestCase
{
    public function testQueryCreation(): void
    {
        $query = new SearchMaterialsQuery(
            customerId: '0000210839',
            salesOrg: '185',
            searchTerm: 'HEMOSIL',
            limit: 10
        );
        
        $this->assertSame('HEMOSIL', $query->searchTerm);
        $this->assertSame(10, $query->limit);
    }
    
    public function testLimitValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchMaterialsQuery(
            customerId: '0000210839',
            salesOrg: '185',
            searchTerm: 'test',
            limit: 1000  // Exceeds max of 100
        );
    }
}
```

### Integration Tests

```php
class SearchMaterialsHandlerTest extends KernelTestCase
{
    public function testHandlerReturnsFilteredResults(): void
    {
        // Arrange: Seed MongoDB with test data
        $this->seedMaterials([
            ['materialNumber' => 'MAT001', 'customerId' => 'CUST001'],
            ['materialNumber' => 'MAT002', 'customerId' => 'CUST002'],
        ]);
        
        $query = new SearchMaterialsQuery(
            customerId: 'CUST001',
            salesOrg: '185',
            searchTerm: 'MAT'
        );
        
        // Act
        $results = $this->queryBus->query($query);
        
        // Assert: Only materials for CUST001 returned
        $this->assertCount(1, $results);
        $this->assertSame('MAT001', $results[0]->materialNumber);
    }
}
```

---

## Summary

### New Queries
- `SearchMaterialsQuery`: Keyword search (material number, description)
- `SemanticSearchQuery`: AI-powered natural language search
- `GetCatalogQuery`: Paginated catalog display with sync progress
- `GetSyncProgressQuery`: Real-time sync progress for progress bar

### Response Formats
- All queries return typed DTOs (Data Transfer Objects)
- Timestamps in ISO 8601 format
- Prices as nullable float + currency string
- Pagination metadata included where applicable

### Performance
- All queries synchronous (no queue delays)
- Target: <200ms for keyword search, <1s for semantic search
- MongoDB indexes ensure fast filtering
- Access control enforced via MySQL → MongoDB pattern
