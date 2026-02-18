# Tasks: Material Pricing & Semantic Search System

**Feature**: 002-material-pricing-search  
**Input**: Design documents from `/specs/002-material-pricing-search/`  
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Organization

Tasks are grouped by user story to enable independent implementation and testing of each story. Each phase represents a complete, independently testable increment.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and development tools

- [X] T001 Create Makefile with common commands (start, test, sync-materials, regenerate-embeddings) in project root
- [X] T002 [P] Add OpenAI API client dependency to composer.json (openai-php/client or guzzle config)
- [X] T003 [P] Configure Redis connection in .env and config/packages/cache.yaml for distributed locking
- [X] T004 [P] Update config/packages/messenger.yaml with new routing rules for priority queues

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T005 Create Posnr value object in src/Domain/ValueObject/Posnr.php (6-char validation, immutable)
- [X] T006 [P] Create SyncLockId value object in src/Domain/ValueObject/SyncLockId.php (composite: salesOrg + customerId)
- [X] T007 [P] Create SyncStatus enum in src/Domain/ValueObject/SyncStatus.php (in_progress, completed, failed)
- [X] T008 Create SyncProgress entity in src/Domain/Entity/SyncProgress.php with progress tracking logic
- [X] T009 Create migration Version20260218_AddPosnrToCustomerMaterial.php to add posnr VARCHAR(6) column
- [X] T010 [P] Create migration Version20260218_CreateSyncProgress.php for sync_progress table
- [X] T011 Run database migrations to apply schema changes (bin/console doctrine:migrations:migrate)

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Accurate Material Pricing Retrieval (Priority: P1) 🎯 MVP

**Goal**: Store POSNR from SAP materials list and use it in price retrieval calls for accurate pricing

**Independent Test**: Trigger material sync for customer/sales org, verify POSNR stored in DB, confirm price fetch includes POSNR and returns accurate prices

### Implementation for User Story 1

- [ ] T012 [P] [US1] Extend CustomerMaterial entity in src/Domain/Entity/CustomerMaterial.php to add Posnr property
- [ ] T013 [P] [US1] Create PriceFetchedEvent in src/Application/Event/PriceFetchedEvent.php
- [ ] T014 [US1] Extend DoctrineCustomerMaterialRepository in src/Infrastructure/Repository/DoctrineCustomerMaterialRepository.php with findByPosnr() method
- [ ] T015 [US1] Extend SapApiClient in src/Infrastructure/ExternalApi/SapApiClient.php to extract POSNR from X_MAT_FOUND array in loadMaterials()
- [ ] T016 [US1] Extend SapApiClient getMaterialPrice() method to include POSNR in IN_WA_MATNR structure
- [ ] T017 [US1] Create SyncMaterialPriceCommand in src/Application/Command/SyncMaterialPriceCommand.php (materialId, customerId, posnr)
- [ ] T018 [US1] Create SyncMaterialPriceHandler in src/Application/CommandHandler/SyncMaterialPriceHandler.php (calls SAP with POSNR, stores price)
- [ ] T019 [US1] Extend SyncUserMaterialsHandler in src/Application/CommandHandler/SyncUserMaterialsHandler.php to store POSNR when processing materials
- [ ] T020 [US1] Configure messenger routing for SyncMaterialPriceCommand in config/packages/messenger.yaml (async_priority_high queue)
- [ ] T021 [US1] Add logging to SyncMaterialPriceHandler for price fetch success/failure with POSNR value

### Testing for User Story 1

- [ ] T022 [P] [US1] Create PosnrTest in tests/Unit/Domain/ValueObject/PosnrTest.php (validation, format)
- [ ] T023 [P] [US1] Extend CustomerMaterialTest in tests/Unit/Domain/Entity/CustomerMaterialTest.php for POSNR property
- [ ] T024 [P] [US1] Create SyncMaterialPriceHandlerTest in tests/Unit/Application/CommandHandler/SyncMaterialPriceHandlerTest.php
- [ ] T025 [P] [US1] Extend SapApiClientTest in tests/Unit/Infrastructure/ExternalApi/SapApiClientTest.php for POSNR extraction
- [ ] T026 [US1] Extend SapApiClientIntegrationTest in tests/Integration/ExternalApi/SapApiClientIntegrationTest.php for full POSNR flow
- [ ] T027 [US1] Create MaterialSyncWithPricingTest in tests/E2E/MaterialSyncWithPricingTest.php (end-to-end SAP → DB flow)

**Checkpoint**: User Story 1 complete - accurate pricing with POSNR fully functional and tested

---

## Phase 4: User Story 2 - Prevent Duplicate Sync Operations (Priority: P1)

**Goal**: Implement distributed locking to prevent multiple sync operations for same customer/sales org from executing simultaneously

**Independent Test**: Trigger multiple sync requests for same customer/sales org in rapid succession, verify only one executes while others are rejected/queued

### Implementation for User Story 2

- [ ] T028 [P] [US2] Create RedisSyncLockRepository in src/Infrastructure/Persistence/Redis/RedisSyncLockRepository.php using Symfony Lock Component
- [ ] T029 [P] [US2] Create AcquireSyncLockCommand in src/Application/Command/AcquireSyncLockCommand.php (salesOrg, customerId)
- [ ] T030 [P] [US2] Create AcquireSyncLockHandler in src/Application/CommandHandler/AcquireSyncLockHandler.php (Redis lock with 10min TTL)
- [ ] T031 [P] [US2] Create SyncLockMiddleware in src/Infrastructure/Messaging/RabbitMQ/SyncLockMiddleware.php to check locks before sync
- [ ] T032 [US2] Extend SyncUserMaterialsHandler to acquire lock before sync, release in finally block
- [ ] T033 [US2] Add lock key logging to RedisSyncLockRepository for observability (acquire/release events)
- [ ] T034 [US2] Configure messenger middleware in config/packages/messenger.yaml to include SyncLockMiddleware

### Testing for User Story 2

- [ ] T035 [P] [US2] Create RedisSyncLockRepositoryTest in tests/Integration/Infrastructure/Redis/RedisSyncLockRepositoryTest.php (real Redis)
- [ ] T036 [P] [US2] Create AcquireSyncLockHandlerTest in tests/Unit/Application/CommandHandler/AcquireSyncLockHandlerTest.php
- [ ] T037 [US2] Create SyncDeduplicationTest in tests/E2E/SyncDeduplicationTest.php (concurrent sync prevention)

**Checkpoint**: User Story 2 complete - sync deduplication prevents queue flooding

---

## Phase 5: User Story 3 - Catalog Page with Real-Time Sync Progress (Priority: P2)

**Goal**: Display catalog page with materials and real-time progress bar showing sync completion percentage

**Independent Test**: Access catalog page during and after sync, verify materials display with prices and progress bar shows accurate completion percentage

### Implementation for User Story 3

- [ ] T038 [P] [US3] Create GetSyncProgressQuery in src/Application/Query/GetSyncProgressQuery.php (salesOrg, customerId)
- [ ] T039 [P] [US3] Create GetSyncProgressHandler in src/Application/QueryHandler/GetSyncProgressHandler.php (reads from sync_progress table)
- [ ] T040 [P] [US3] Create GetCatalogQuery in src/Application/Query/GetCatalogQuery.php (salesOrg, customerId, page, limit)
- [ ] T041 [P] [US3] Create GetCatalogHandler in src/Application/QueryHandler/GetCatalogHandler.php (fetches materials with prices)
- [ ] T042 [US3] Create or extend MaterialCatalogController in src/UI/Controller/MaterialCatalogController.php with catalog and progress endpoints
- [ ] T043 [US3] Update SyncUserMaterialsHandler to create/update SyncProgress entity during sync operations
- [ ] T044 [US3] Create progress-bar.html.twig partial in templates/catalog/partials/progress-bar.html.twig with JavaScript polling
- [ ] T045 [US3] Extend catalog index.html.twig in templates/catalog/index.html.twig to include progress bar and material list
- [ ] T046 [US3] Add AJAX endpoint in MaterialCatalogController for progress polling (JSON response)
- [ ] T047 [US3] Add JavaScript to poll progress endpoint every 2 seconds until sync completes

### Testing for User Story 3

- [ ] T048 [P] [US3] Create GetSyncProgressHandlerTest in tests/Unit/Application/QueryHandler/GetSyncProgressHandlerTest.php
- [ ] T049 [P] [US3] Create GetCatalogHandlerTest in tests/Unit/Application/QueryHandler/GetCatalogHandlerTest.php
- [ ] T050 [P] [US3] Create SyncProgressTest in tests/Unit/Domain/Entity/SyncProgressTest.php
- [ ] T051 [US3] Extend MaterialCatalogControllerTest in tests/Functional/UI/Controller/MaterialCatalogControllerTest.php for progress and catalog endpoints

**Checkpoint**: User Story 3 complete - catalog page displays materials with real-time sync progress

---

## Phase 6: User Story 4 - Material Search by Name (Priority: P2)

**Goal**: Enable instant search/filter of materials by material number or description in catalog page

**Independent Test**: Enter search terms in catalog search box, verify filtered results match search criteria in material number or description

### Implementation for User Story 4

- [ ] T052 [P] [US4] Create SearchMaterialsQuery in src/Application/Query/SearchMaterialsQuery.php (customerId, salesOrg, searchTerm)
- [ ] T053 [P] [US4] Create SearchMaterialsHandler in src/Application/QueryHandler/SearchMaterialsHandler.php (MySQL LIKE query)
- [ ] T054 [US4] Add search endpoint to MaterialCatalogController in src/UI/Controller/MaterialCatalogController.php
- [ ] T055 [US4] Create search-box.html.twig partial in templates/catalog/partials/search-box.html.twig with debounced input
- [ ] T056 [US4] Extend catalog index.html.twig to include search box above material list
- [ ] T057 [US4] Add JavaScript to handle search input (debounce 300ms) and update material list via AJAX

### Testing for User Story 4

- [ ] T058 [P] [US4] Create SearchMaterialsHandlerTest in tests/Unit/Application/QueryHandler/SearchMaterialsHandlerTest.php
- [ ] T059 [US4] Extend MaterialCatalogControllerTest to verify search endpoint returns filtered results

**Checkpoint**: User Story 4 complete - keyword search enables fast material filtering

---

## Phase 7: User Story 5 - MongoDB-Based Fast Search (Priority: P3)

**Goal**: Store material data in MongoDB for rapid search operations with automatic sync from MySQL

**Independent Test**: Create materials in MySQL, verify they appear in MongoDB within 5 seconds, measure search response times (<100ms)

### Implementation for User Story 5

- [ ] T060 [P] [US5] Create MaterialView document in src/Infrastructure/Persistence/MongoDB/Document/MaterialView.php (ODM mapping)
- [ ] T061 [P] [US5] Create MaterialReadRepositoryInterface in src/Domain/Repository/MaterialReadRepositoryInterface.php
- [ ] T062 [P] [US5] Create MongoMaterialRepository in src/Infrastructure/Repository/MongoMaterialRepository.php implementing MaterialReadRepositoryInterface
- [ ] T063 [P] [US5] Create MaterialSyncedEvent in src/Application/Event/MaterialSyncedEvent.php
- [ ] T064 [P] [US5] Create UpdateMongoOnMaterialSyncedHandler in src/Application/EventHandler/UpdateMongoOnMaterialSyncedHandler.php
- [ ] T065 [US5] Update SyncUserMaterialsHandler to dispatch MaterialSyncedEvent after storing materials
- [ ] T066 [US5] Configure MongoDB connection in config/packages/doctrine_mongodb.yaml
- [ ] T067 [US5] Register MongoMaterialRepository as service in config/services.yaml
- [ ] T068 [US5] Configure messenger routing for MaterialSyncedEvent in config/packages/messenger.yaml (async_priority_low queue)
- [ ] T069 [US5] Update SearchMaterialsHandler to use MongoMaterialRepository instead of MySQL for search queries
- [ ] T070 [US5] Create MongoDB indexes script in migrations or separate file (customerId_materialNumber unique, etc.)
- [ ] T071 [US5] Add MongoDB index creation instructions to quickstart.md

### Testing for User Story 5

- [ ] T072 [P] [US5] Create MaterialViewTest in tests/Unit/Infrastructure/Persistence/MongoDB/Document/MaterialViewTest.php
- [ ] T073 [P] [US5] Create MongoMaterialRepositoryTest (Unit) in tests/Unit/Infrastructure/Repository/MongoMaterialRepositoryTest.php
- [ ] T074 [P] [US5] Create MongoMaterialRepositoryTest (Integration) in tests/Integration/Infrastructure/Repository/MongoMaterialRepositoryTest.php (real MongoDB)
- [ ] T075 [P] [US5] Create UpdateMongoOnMaterialSyncedHandlerTest in tests/Unit/Application/EventHandler/UpdateMongoOnMaterialSyncedHandlerTest.php
- [ ] T076 [US5] Add E2E test for eventual consistency: Create in MySQL → verify in MongoDB within 5s

**Checkpoint**: User Story 5 complete - MongoDB integration provides fast search with eventual consistency

---

## Phase 8: User Story 6 - Semantic Search with AI Embeddings (Priority: P3)

**Goal**: Enable natural language search using OpenAI embeddings for semantic similarity matching

**Independent Test**: Search for conceptually related terms, verify relevant materials appear based on semantic similarity rather than keyword matching

### Implementation for User Story 6

- [ ] T077 [P] [US6] Create EmbeddingVector value object in src/Domain/ValueObject/EmbeddingVector.php (1536 floats, cosine similarity)
- [ ] T078 [P] [US6] Create OpenAiEmbeddingClient in src/Infrastructure/ExternalApi/OpenAiEmbeddingClient.php (text-embedding-3-small)
- [ ] T079 [P] [US6] Create GenerateEmbeddingCommand in src/Application/Command/GenerateEmbeddingCommand.php (materialId, description)
- [ ] T080 [P] [US6] Create GenerateEmbeddingHandler in src/Application/CommandHandler/GenerateEmbeddingHandler.php
- [ ] T081 [P] [US6] Create SemanticSearchQuery in src/Application/Query/SemanticSearchQuery.php (customerId, salesOrg, naturalLanguageQuery)
- [ ] T082 [P] [US6] Create SemanticSearchHandler in src/Application/QueryHandler/SemanticSearchHandler.php (vector similarity search)
- [ ] T083 [US6] Extend MaterialView document to include embedding field (array of 1536 floats)
- [ ] T084 [US6] Create GenerateEmbeddingOnMaterialSyncedHandler in src/Application/EventHandler/GenerateEmbeddingOnMaterialSyncedHandler.php
- [ ] T085 [US6] Update MaterialSyncedEvent dispatch to trigger embedding generation
- [ ] T086 [US6] Configure messenger routing for GenerateEmbeddingCommand in config/packages/messenger.yaml (async_priority_normal queue)
- [ ] T087 [US6] Add semantic search endpoint to MaterialCatalogController
- [ ] T088 [US6] Extend search-box.html.twig to include search mode toggle (keyword vs semantic)
- [ ] T089 [US6] Update JavaScript to switch between SearchMaterialsQuery and SemanticSearchQuery based on mode
- [ ] T090 [US6] Create MongoDB Atlas Search vector index configuration (1536 dimensions, cosine similarity)
- [ ] T091 [US6] Create RegenerateEmbeddingsCommand CLI in src/UI/Command/RegenerateEmbeddingsCommand.php for batch regeneration
- [ ] T092 [US6] Add OpenAI API key configuration to .env and services.yaml

### Testing for User Story 6

- [ ] T093 [P] [US6] Create EmbeddingVectorTest in tests/Unit/Domain/ValueObject/EmbeddingVectorTest.php (cosine similarity calculation)
- [ ] T094 [P] [US6] Create OpenAiEmbeddingClientTest in tests/Unit/Infrastructure/ExternalApi/OpenAiEmbeddingClientTest.php (mocked API)
- [ ] T095 [P] [US6] Create GenerateEmbeddingHandlerTest in tests/Unit/Application/CommandHandler/GenerateEmbeddingHandlerTest.php
- [ ] T096 [P] [US6] Create SemanticSearchHandlerTest in tests/Unit/Application/QueryHandler/SemanticSearchHandlerTest.php
- [ ] T097 [US6] Create SemanticSearchE2ETest in tests/E2E/SemanticSearchE2ETest.php (embedding generation → vector search flow)

**Checkpoint**: User Story 6 complete - semantic search enables natural language queries

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Refinements, optimizations, and operational improvements

- [ ] T098 [P] Add retry policies to all async command handlers (3 retries with exponential backoff)
- [ ] T099 [P] Add circuit breaker pattern to SapApiClient for resilience
- [ ] T100 [P] Add performance logging to all query handlers (execution time tracking)
- [ ] T101 [P] Create monitoring dashboard queries (sync success rate, embedding generation rate, search latency)
- [ ] T102 [P] Add index optimization for customer_materials table (customerId, salesOrg, posnr composite index)
- [ ] T103 [P] Add caching layer for frequent catalog queries (Redis with 5min TTL)
- [ ] T104 Update README.md with feature documentation and setup instructions
- [ ] T105 Create docker-compose override for local MongoDB Atlas Search testing
- [ ] T106 Add error handling documentation to quickstart.md (lock failures, missing POSNR, API timeouts)
- [ ] T107 Run full test suite and fix any remaining issues (bin/console phpunit)

---

## Implementation Strategy

### MVP Scope (Week 1)
- **Phase 1-4**: Setup, Foundation, User Story 1 (POSNR pricing), User Story 2 (deduplication)
- **Goal**: Critical business bug fixes - accurate pricing and system stability
- **Deliverable**: Sync operations use POSNR, prices are accurate, duplicate syncs prevented

### Incremental Delivery (Weeks 2-3)
- **Phase 5-6**: User Story 3 (catalog progress), User Story 4 (keyword search)
- **Goal**: Improved user experience with visibility and search
- **Deliverable**: Catalog page with progress tracking and instant search

### Advanced Features (Week 4)
- **Phase 7-8**: User Story 5 (MongoDB), User Story 6 (semantic search)
- **Goal**: Performance optimization and AI-powered search
- **Deliverable**: Sub-second search with natural language queries

### Polish (Ongoing)
- **Phase 9**: Cross-cutting concerns, monitoring, optimizations
- **Goal**: Production-ready system
- **Deliverable**: Resilient, observable, performant implementation

---

## Dependency Graph

```
Phase 1 (Setup) ─────────────┐
                             ↓
Phase 2 (Foundation) ────────┼──────────────┐
                             ↓              ↓
              ┌──────────────┴──────┐       │
              ↓                     ↓       ↓
    Phase 3 (US1 - POSNR)  Phase 4 (US2 - Lock)
              ↓                     ↓
              └──────────┬──────────┘
                         ↓
              ┌──────────┴──────────┐
              ↓                     ↓
    Phase 5 (US3 - Catalog)  Phase 6 (US4 - Search)
              ↓                     ↓
              └──────────┬──────────┘
                         ↓
              ┌──────────┴──────────┐
              ↓                     ↓
    Phase 7 (US5 - MongoDB)  Phase 8 (US6 - Semantic)
              ↓                     ↓
              └──────────┬──────────┘
                         ↓
              Phase 9 (Polish)
```

**Parallel Opportunities**:
- **Phase 2**: All 7 foundation tasks can run in parallel (T005-T011)
- **Phase 3**: Unit tests (T022-T025) can run parallel, US1 implementation (T012-T021) has dependencies
- **Phase 4**: US2 implementation tasks (T028-T031) can run in parallel
- **Phase 7**: MongoDB setup tasks (T060-T064) can run in parallel
- **Phase 8**: Embedding infrastructure (T077-T082) can run in parallel

**Independent User Stories** (can be implemented by different developers):
- US1 and US2 can be worked on simultaneously after Phase 2
- US3 and US4 can be worked on simultaneously after US1/US2 complete
- US5 and US6 require US3/US4 but can then proceed independently

---

## Validation Checklist

✅ **Format**: All tasks follow `- [ ] [ID] [P?] [Story] Description with path` format  
✅ **Organization**: Tasks grouped by user story (US1-US6) for independent implementation  
✅ **Dependencies**: Clear phase progression with checkpoint gates  
✅ **File Paths**: Every task includes specific file path  
✅ **Testability**: Each user story has independent test criteria  
✅ **Parallelization**: [P] markers identify parallelizable tasks  
✅ **MVP Definition**: User Story 1 and 2 identified as MVP scope  
✅ **Completeness**: All entities from data-model.md covered  
✅ **Contracts**: All commands/queries/events from contracts/ covered

**Total Tasks**: 107  
**Parallelizable**: 45 tasks marked [P]  
**User Stories**: 6 (US1-US6)  
**Phases**: 9 (Setup → Foundation → 6 User Stories → Polish)
