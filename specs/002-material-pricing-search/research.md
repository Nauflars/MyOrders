# Research: Material Pricing & Semantic Search System

**Feature**: 002-material-pricing-search  
**Date**: 2026-02-18  
**Purpose**: Document technical research, design decisions, and best practices for implementation

## Overview

This document captures research findings for implementing accurate material pricing with POSNR field, sync deduplication, catalog with progress tracking, material search, MongoDB integration, and semantic search using OpenAI embeddings.

## Technology Decisions

### 1. POSNR Field Handling in SAP API Calls

**Decision**: Persist POSNR from `ZSDO_EBU_LOAD_MATERIALS` response and pass it to `ZSDO_EBU_SHOW_MATERIAL_PRICE` request

**Rationale**: 
- SAP requires POSNR (position number) for accurate price calculation
- POSNR comes from X_MAT_FOUND array in materials list response
- Each material has unique POSNR per customer/sales org combination
- Omitting POSNR results in incorrect or missing prices

**Implementation Approach**:
- Add `posnr` column to `customer_materials` table (VARCHAR(6))
- Extract POSNR in `SyncCustomerFromSapHandler` when processing X_MAT_FOUND
- Store POSNR in CustomerMaterial entity as Value Object
- Include POSNR in IN_WA_MATNR structure when calling getMaterialPrice()

**Best Practices**:
- Validate POSNR format (typically 6 characters, zero-padded)
- Handle missing POSNR gracefully (log warning, skip price fetch)
- Update POSNR if material list is re-synced (SAP may reassign)

**Alternatives Considered**:
- Fetching materials and prices in single call: SAP API doesn't support this
- Generating POSNR locally: SAP assigns POSNR, cannot be generated client-side
- Omitting POSNR: Results in incorrect prices (rejected)

**Testing Strategy**:
- Unit test: Posnr value object validation
- Integration test: Full SAP flow (load materials → extract POSNR → fetch price)
- E2E test: Verify prices match SAP GUI when POSNR is used

---

### 2. Distributed Locking for Sync Deduplication

**Decision**: Use Redis with Symfony Cache Component for distributed locks

**Rationale**:
- Prevents duplicate sync operations for same customer/sales org
- Works across multiple worker processes
- Built-in expiration prevents deadlocks
- Symfony Cache Component provides lock abstraction

**Implementation Approach**:
```php
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

$lockKey = "sync_lock_{$salesOrg}_{$customerId}";
$lock = $lockFactory->createLock($lockKey, ttl: 600); // 10 minutes

if ($lock->acquire(blocking: false)) {
    try {
        // Perform sync operation
    } finally {
        $lock->release();
    }
} else {
    // Sync already in progress, skip or queue
}
```

**Best Practices**:
- Use semantic lock keys: `sync_lock_{salesOrg}_{customerId}`
- Set appropriate TTL (10 minutes covers typical sync duration)
- Always release locks in finally block
- Log when locks are acquired/released for observability
- Use non-blocking mode to immediately reject duplicate requests

**Alternatives Considered**:
- Database locks: Slower, doesn't scale across multiple databases
- File locks: Not available in containerized environments
- Pessimistic locking in MySQL: Doesn't prevent RabbitMQ message duplication
- Optimistic locking: Race conditions possible, rejected

**Testing Strategy**:
- Integration test: Acquire lock, verify second attempt fails
- E2E test: Dispatch multiple sync commands, verify only one executes
- Test lock release on process crash (TTL expiration)

---

### 3. MongoDB Vector Search for Semantic Search

**Decision**: Use MongoDB Atlas Search with vector embeddings (text-embedding-3-small)

**Rationale**:
- Native vector similarity search (cosine similarity)
- Indexes optimize search performance
- Supports hybrid search (keyword + semantic)
- Works with existing Doctrine MongoDB ODM

**Implementation Approach**:
```php
// MaterialView document
#[MongoDB\Document(collection: 'material_view')]
class MaterialView
{
    #[MongoDB\Id]
    private string $id;
    
    #[MongoDB\Field(type: 'string')]
    private string $materialNumber;
    
    #[MongoDB\Field(type: 'string')]
    private string $description;
    
    #[MongoDB\Field(type: 'collection')]
    private array $embedding = []; // 1536 dimensions
    
    #[MongoDB\Index(keys: ['materialNumber' => 1])]
    #[MongoDB\Index(keys: ['customerId' => 1, 'salesOrg' => 1])]
    private string $customerId;
}

// Vector search query
$pipeline = [
    [
        '$search' => [
            'index' => 'material_vector_index',
            'knnBeta' => [
                'vector' => $queryEmbedding,
                'path' => 'embedding',
                'k' => 10,
                'filter' => ['customerId' => $customerId]
            ]
        ]
    ]
];
```

**Best Practices**:
- Create vector search index in MongoDB Atlas
- Filter by customer_id for access control
- Use k=10-20 for initial results, then re-rank
- Cache embeddings to avoid redundant OpenAI calls
- Fallback to keyword search if vector search fails

**Alternatives Considered**:
- PostgreSQL pgvector: Requires PostgreSQL 15+, adds complexity
- Elasticsearch with dense_vector: Additional service to manage
- Pure keyword search: Misses semantic matches, rejected

**Testing Strategy**:
- Unit test: MongoDB document mapping
- Integration test: Insert document with embedding, verify retrieval
- E2E test: Search for "blood testing", verify "hemostasis" materials appear

---

### 4. OpenAI Embeddings Integration

**Decision**: Use `text-embedding-3-small` model via Symfony HTTP Client

**Rationale**:
- Cost-effective (62% cheaper than text-embedding-ada-002)
- 1536 dimensions (compatible with most vector DBs)
- High quality semantic representations
- Fast response times (<200ms typically)

**Implementation Approach**:
```php
class OpenAiEmbeddingClient
{
    public function generateEmbedding(string $text): array
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ],
        ]);
        
        $data = $response->toArray();
        return $data['data'][0]['embedding']; // Array of 1536 floats
    }
}
```

**Best Practices**:
- Combine material number and description for embedding input
- Normalize text (lowercase, remove special characters)
- Cache embeddings (avoid regenerating for unchanged materials)
- Handle rate limits (429 errors) with exponential backoff
- Batch embed multiple materials (up to 8192 tokens per request)
- Store API usage metrics for cost monitoring

**Alternatives Considered**:
- text-embedding-ada-002: More expensive, similar quality
- text-embedding-3-large: Higher quality but 3x cost, overkill for materials
- Self-hosted models (BERT, etc.): Lower quality, complex deployment

**Testing Strategy**:
- Unit test: Mock OpenAI client, verify request format
- Integration test: Real API call (rate limited in CI)
- Test error handling: Rate limits, timeouts, invalid responses

---

### 5. Symfony Messenger Routing for CQRS

**Decision**: Separate RabbitMQ queues for Commands, Queries, and Events with routing by priority

**Rationale**:
- Queries never async (always handled synchronously)
- Commands/Events routed to appropriate queues
- Priority routing ensures critical operations process first
- Separate queues enable independent scaling

**Implementation Approach**:
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
            async_priority_low:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: priority_low
        
        routing:
            # Queries always sync
            'App\Application\Query\*': sync
            
            # High priority commands
            'App\Application\Command\SyncMaterialPriceCommand': async_priority_high
            'App\Application\Command\AcquireSyncLockCommand': async_priority_high
            
            # Normal priority commands
            'App\Application\Command\GenerateEmbeddingCommand': async_priority_normal
            
            # Low priority events
            'App\Application\Event\*': async_priority_low
```

**Best Practices**:
- Queries MUST always be synchronous (returns data immediately)
- Commands that mutate critical data → high priority
- Background operations (embeddings) → normal priority
- Notification events → low priority
- Configure retry policy per queue (3 retries with exponential backoff)
- Monitor queue depths for backpressure detection

**Alternatives Considered**:
- Single queue: No prioritization, critical ops wait behind background tasks
- Sync commands: Blocks user requests, unacceptable UX
- Pull-based polling: Less efficient than push-based messaging

**Testing Strategy**:
- Unit test: Message routing configuration
- Integration test: Dispatch command, verify lands in correct queue
- E2E test: Full flow with queue consumers running

---

### 6. Eventual Consistency Pattern

**Decision**: Write-ahead to MySQL, async propagation to MongoDB via Domain Events

**Rationale**:
- MySQL remains source of truth (ACID guarantees)
- MongoDB updated asynchronously for read performance
- Acceptable <5s delay for catalog/search data
- Scales read workload independently from writes

**Implementation Approach**:
```php
// After price update in MySQL
$customerMaterial->updatePrice($price, $currency);
$this->customerMaterialRepository->save($customerMaterial);

// Dispatch event (async)
$event = new MaterialSyncedEvent(
    $customerMaterial->getMaterialId(),
    $customerMaterial->getCustomerId(),
    $customerMaterial->getSalesOrg()
);
$this->eventBus->dispatch($event);
```

**Event Handler** (async):
```php
class UpdateMongoOnMaterialSyncedHandler
{
    public function __invoke(MaterialSyncedEvent $event): void
    {
        $material = $this->customerMaterialRepository->find(
            $event->materialId,
            $event->customerId,
            $event->salesOrg
        );
        
        $materialView = $this->mongoMaterialRepository->findOrCreate(
            $event->materialId,
            $event->customerId
        );
        
        $materialView->updateFrom($material);
        $this->mongoMaterialRepository->save($materialView);
    }
}
```

**Best Practices**:
- Always write to MySQL first (source of truth)
- Use domain events to trigger MongoDB updates
- Include timestamp in MongoDB document for staleness detection
- Provide "refresh" mechanism for manual sync if needed
- Log sync failures for monitoring
- Accept eventual consistency (don't block on MongoDB availability)

**Alternatives Considered**:
- Two-phase commit: Complex, unnecessary for read models
- MongoDB as source of truth: Loses ACID guarantees, rejected
- Synchronous updates to both: Couples write performance to MongoDB, rejected

**Testing Strategy**:
- Integration test: Write to MySQL, verify event dispatched
- E2E test: Full flow, verify MongoDB updated within 5 seconds
- Test MongoDB unavailability: Verify MySQL writes still succeed

---

### 7. Catalog Progress Bar Implementation

**Decision**: Store sync progress in MySQL, poll from frontend via AJAX

**Rationale**:
- Simple implementation with existing tools
- No WebSocket infrastructure needed
- Acceptable UX with 2-second polling interval
- Progress data queryable for debugging

**Implementation Approach**:
```php
// Domain entity
class SyncProgress
{
    private string $id;
    private string $customerId;
    private string $salesOrg;
    private int $totalMaterials;
    private int $processedMaterials;
    private string $status; // in_progress, completed, failed
    private \DateTimeImmutable $startedAt;
    private ?\DateTimeImmutable $completedAt;
}

// Controller
#[Route('/catalog/{salesOrg}/{customerId}/progress', name: 'catalog_progress')]
public function progress(string $salesOrg, string $customerId): JsonResponse
{
    $progress = $this->queryBus->query(
        new GetSyncProgressQuery($salesOrg, $customerId)
    );
    
    return $this->json([
        'total' => $progress->totalMaterials,
        'processed' => $progress->processedMaterials,
        'percentage' => $progress->getPercentage(),
        'status' => $progress->status,
    ]);
}

// Frontend (Twig + JavaScript)
setInterval(() => {
    fetch('/catalog/{{ salesOrg }}/{{ customerId }}/progress')
        .then(response => response.json())
        .then(data => {
            document.getElementById('progress-bar').style.width = data.percentage + '%';
            document.getElementById('progress-text').textContent = 
                `${data.processed} / ${data.total} materials`;
            
            if (data.status === 'completed') {
                location.reload(); // Refresh to show final results
            }
        });
}, 2000); // Poll every 2 seconds
```

**Best Practices**:
- Update progress after each material batch (not individual materials)
- Include estimated completion time based on processing rate
- Stop polling when status is completed or failed
- Show spinner during initial load (before first poll)
- Handle network errors gracefully (retry with backoff)

**Alternatives Considered**:
- WebSockets (Mercure, Socket.IO): Over-engineered for this use case
- Server-Sent Events (SSE): Requires additional server setup
- Long polling: More complex than simple polling, minimal benefit

**Testing Strategy**:
- Unit test: SyncProgress entity calculations
- Functional test: Progress endpoint returns correct data
- E2E test: Trigger sync, poll progress, verify increments

---

### 8. Material Search Implementation

**Decision**: Dual-mode search (keyword + semantic) with toggle in UI

**Rationale**:
- Keyword search for exact matches (material numbers, specific terms)
- Semantic search for conceptual queries (natural language)
- Users choose mode based on query type
- Fallback to keyword if semantic fails

**Implementation Approach**:
```php
class SearchMaterialsHandler
{
    public function __invoke(SearchMaterialsQuery $query): array
    {
        // Get allowed material IDs from MySQL (access control)
        $allowedMaterialIds = $this->customerMaterialRepository
            ->findMaterialIdsByCustomer($query->customerId);
        
        if ($query->mode === 'semantic') {
            // Generate embedding for search query
            $queryEmbedding = $this->embeddingClient->generateEmbedding($query->term);
            
            // Vector similarity search
            return $this->mongoMaterialRepository->vectorSearch(
                $queryEmbedding,
                $allowedMaterialIds,
                limit: $query->limit
            );
        } else {
            // Keyword search (material number or description)
            return $this->mongoMaterialRepository->keywordSearch(
                $query->term,
                $allowedMaterialIds,
                limit: $query->limit
            );
        }
    }
}
```

**MongoDB Keyword Search**:
```php
$cursor = $this->documentManager->createQueryBuilder(MaterialView::class)
    ->field('materialNumber')->equals($term)
    ->field('customerId')->in($allowedMaterialIds)
    ->limit($limit)
    ->getQuery()
    ->execute();
```

**MongoDB Vector Search**:
```php
$pipeline = [
    [
        '$search' => [
            'index' => 'material_vector_index',
            'knnBeta' => [
                'vector' => $queryEmbedding,
                'path' => 'embedding',
                'k' => $limit,
                'filter' => [
                    'customerId' => ['$in' => $allowedMaterialIds]
                ]
            ]
        ]
    ],
    ['$limit' => $limit]
];

$cursor = $this->documentManager->getDocumentCollection(MaterialView::class)
    ->aggregate($pipeline);
```

**Best Practices**:
- Always filter by allowed material IDs (security)
- Cache query embeddings for repeated searches
- Highlight matching terms in results
- Show relevance scores to users
- Log search queries for analytics

**Alternatives Considered**:
- Auto-detect mode based on query: Complex, users prefer explicit control
- Semantic-only search: Misses exact ID lookups, rejected
- Hybrid scoring (keyword + semantic): Over-engineered for v1

**Testing Strategy**:
- Unit test: Query handlers with mocked repositories
- Integration test: Real MongoDB keyword and vector searches
- E2E test: User enters search term, verifies results appear

---

## Implementation Sequence

### Phase 1: Foundation (P1 - Core Pricing Fix)
1. Add POSNR field to CustomerMaterial entity and database
2. Update SapApiClient to extract and pass POSNR
3. Modify sync handlers to persist POSNR
4. Add distributed locking with Redis
5. Test SAP price retrieval with POSNR

**Rationale**: Fixes immediate business need (accurate pricing)

### Phase 2: Catalog & Progress (P2 - User Experience)
6. Create SyncProgress entity and repository
7. Update sync handlers to track progress
8. Add progress endpoint to catalog controller
9. Implement progress bar UI with polling
10. Add keyword search to catalog

**Rationale**: Improves user visibility into sync operations

### Phase 3: MongoDB Integration (P2 - Performance)
11. Create MaterialView MongoDB document
12. Implement MongoMaterialRepository
13. Set up event handlers for MySQL → MongoDB sync
14. Update catalog to query MongoDB
15. Implement keyword search in MongoDB

**Rationale**: Optimizes read performance for large catalogs

### Phase 4: Semantic Search (P3 - Advanced Feature)
16. Implement OpenAiEmbeddingClient
17. Create GenerateEmbeddingCommand/Handler
18. Add embedding vector to MaterialView
19. Set up MongoDB vector search index
20. Implement semantic search UI toggle
21. Create Makefile with common commands

**Rationale**: Advanced feature, non-blocking for core functionality

---

## Risk Mitigation

### OpenAI API Costs
- **Risk**: High embedding generation costs for large material catalogs
- **Mitigation**: 
  - Cache embeddings (generate once, reuse forever)
  - Only regenerate when description changes
  - Monitor API usage with alerts
  - Set monthly budget cap in OpenAI dashboard

### MongoDB Sync Failures
- **Risk**: MongoDB unavailable causes event handler failures
- **Mitigation**:
  - MySQL remains source of truth (reads fall back)
  - Retry failed MongoDB updates (Messenger retry policy)
  - Manual "refresh" command to rebuild MongoDB from MySQL
  - Alert on persistent failures

### Redis Lock Expiration
- **Risk**: Process crashes while holding lock, blocks syncs for 10 minutes
- **Mitigation**:
  - TTL auto-releases lock after 10 minutes
  - Monitor lock acquisition failures
  - Manual "clear_locks" command for emergencies
  - Log lock acquisitions/releases for debugging

### SAP API POSNR Changes
- **Risk**: SAP reassigns POSNR, stored value becomes stale
- **Mitigation**:
  - Re-fetch materials list during each full sync
  - Update POSNR in database if changed
  - Log POSNR changes for auditing

---

## Performance Benchmarks

### Target Metrics
- SAP material list fetch: <5s for 1000 materials
- SAP price fetch (with POSNR): <500ms per material (parallelized)
- MongoDB keyword search: <100ms for 10,000 documents
- MongoDB vector search: <500ms for 10,000 documents (including embedding generation)
- Catalog page load: <2s for 5,000 materials
- Progress bar update: <2s latency from actual completion

### Load Testing Plan
- Simulate 10 concurrent sync operations
- Query catalog with 50 concurrent users
- Search with 100 queries/second
- Measure queue depth under load
- Verify worker auto-scaling

---

## Documentation Deliverables

1. **Quickstart Guide**: Step-by-step setup for developers
2. **API Contracts**: Command/Query/Event schemas
3. **Data Model**: Entity relationships and MongoDB documents
4. **Makefile**: Common development commands
5. **Testing Guide**: How to run each test type
6. **Operational Playbook**: Troubleshooting common issues

---

## Conclusion

All technical decisions documented with clear rationale. No clarifications needed - ready for Phase 1 (Data Model & Contracts generation).
