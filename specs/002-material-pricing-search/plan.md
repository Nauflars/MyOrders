# Implementation Plan: Material Pricing & Semantic Search System

**Branch**: `002-material-pricing-search` | **Date**: 2026-02-18 | **Spec**: [spec.md](spec.md)  
**Input**: Feature specification from `/specs/002-material-pricing-search/spec.md`

## Summary

This feature implements accurate material pricing retrieval from SAP using POSNR field, prevents duplicate synchronization operations, provides a catalog page with real-time progress tracking, enables material search functionality, integrates MongoDB for optimized read performance, and implements semantic search using OpenAI embeddings. The system follows DDD/CQRS/Hexagonal architecture with eventual consistency and asynchronous processing patterns.

## Technical Context

**Language/Version**: PHP 8.3+  
**Primary Dependencies**: Symfony 7.4 (Framework, Messenger, HTTP Client), Doctrine ORM 3.2, Doctrine MongoDB ODM 5.0  
**Storage**: MySQL (source of truth for transactional data), MongoDB (read model for search and embeddings), Redis (distributed locking)  
**Testing**: PHPUnit 11.0 (Unit, Integration, Functional, E2E tests)  
**Target Platform**: Linux server (Docker containers), Web application  
**Project Type**: Web application (PHP backend with Twig templates)  
**Performance Goals**: <200ms search response time for 10,000 materials, <2s catalog page load time, <100ms MongoDB queries  
**Constraints**: <5s eventual consistency delay between MySQL and MongoDB, sub-second semantic search with embedding generation, <1% lock failure rate for deduplication  
**Scale/Scope**: 50,000 materials per customer/sales org, OpenAI API rate limits, RabbitMQ queue management for async operations

**Architecture Notes**:
- Existing DDD/CQRS/Hexagonal architecture
- SAP integration through Infrastructure adapters
- Symfony Messenger with RabbitMQ transport
- Polyglot persistence (MySQL + MongoDB)
- Eventual consistency between write and read models

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### ✅ PASS: Architectural Principles (DDD + Hexagonal + CQRS)
- **Requirement**: Domain layer independent from infrastructure, CQRS with Commands/Queries/Events
- **Compliance**: Feature uses existing architecture - Commands for sync operations, Queries for catalog/search, Events for MongoDB updates
- **Rationale**: All new components follow established patterns (SyncMaterialPriceCommand, SearchMaterialsQuery, MaterialSyncedEvent)

### ✅ PASS: Messaging and CQRS
- **Requirement**: Commands (async for slow ops), Queries (sync, read-only), Events (async, multiple handlers)
- **Compliance**: 
  - Commands: SyncMaterialPriceCommand, SyncUserMaterialsCommand (async via RabbitMQ)
  - Queries: SearchMaterialsQuery, GetCatalogProgressQuery (sync, read-only)
  - Events: MaterialSyncedEvent, PriceFetchedEvent (async, trigger MongoDB updates)
- **Rationale**: Separates write operations (SAP sync) from read operations (catalog/search), enables independent scaling

### ✅ PASS: Asynchronous Processing
- **Requirement**: SAP integration, heavy operations must be async via RabbitMQ
- **Compliance**: Material sync, price fetching, embedding generation all dispatched to RabbitMQ queues
- **Rationale**: Prevents user-facing operations from blocking on slow SAP API calls or OpenAI embedding generation

### ✅ PASS: External System Integration (SAP)
- **Requirement**: SAP accessed exclusively through infrastructure adapters, never blocks user interaction
- **Compliance**: Existing SapApiClient in Infrastructure layer, all SAP calls async through commands
- **Rationale**: Maintains domain layer independence, enables testing without SAP connectivity

### ✅ PASS: Data Architecture (Polyglot Persistence)
- **Requirement**: Relational DB as source of truth, MongoDB for read models, access control via relational DB
- **Compliance**: 
  - MySQL: customer_materials table stores POSNR, prices (source of truth)
  - MongoDB: MaterialView document with embeddings (read-only)
  - Access control: Queries filter by customer_id from MySQL before MongoDB search
- **Rationale**: Separates transactional consistency (MySQL) from search performance (MongoDB), maintains security through source of truth

### ✅ PASS: Embedding and Semantic Search
- **Requirement**: One embedding per material (not per user), async generation, access control via source of truth
- **Compliance**: 
  - Embeddings generated once per material via GenerateEmbeddingCommand (async)
  - Stored in MongoDB MaterialView document
  - SearchMaterialsQuery first gets allowed material IDs from MySQL, then filters MongoDB
- **Rationale**: Prevents embedding duplication, maintains access control, optimal performance

### ✅ PASS: Performance and Scalability
- **Requirement**: User operations never blocked by external systems, horizontal scaling via workers
- **Compliance**: All SAP/OpenAI calls async, catalog page uses MongoDB read model, workers scale independently
- **Rationale**: Catalog loads immediately from MongoDB even during active SAP syncs

### ✅ PASS: Code Quality and Design Principles (SOLID)
- **Requirement**: Dependency injection, business logic in Domain layer, infrastructure implements domain interfaces
- **Compliance**: Existing repository pattern, services injected via Symfony DI, Domain entities contain business rules
- **Rationale**: New repositories (MongoMaterialRepository) implement domain interfaces (MaterialReadRepositoryInterface)

### ✅ PASS: Testing Requirements
- **Requirement**: Unit, Integration, Functional, E2E tests required, automated in CI/CD
- **Compliance**: Comprehensive test plan includes all layers (see Phase 1 contracts)
- **Rationale**: Existing test structure expanded with new test cases for pricing, deduplication, search, embeddings

### ✅ PASS: Consistency Model (Eventual Consistency)
- **Requirement**: Write to relational DB first, read models updated async via events, eventual consistency acceptable
- **Compliance**: 
  - Price updates write to MySQL customer_materials immediately
  - MaterialSyncedEvent triggers async MongoDB update
  - <5s acceptable delay documented in spec
- **Rationale**: Catalog may show slightly stale data during active sync, but source of truth always consistent

### ✅ PASS: Maintainability and Extensibility
- **Requirement**: Loose coupling, Open/Closed principle, framework-independent domain
- **Compliance**: New features added through new commands/queries/events without modifying existing domain logic
- **Rationale**: Pricing fix updates SapApiClient (infrastructure), doesn't modify domain entities

### ✅ PASS: Operational Principles
- **Requirement**: Horizontal scaling, retry mechanisms, fault tolerance, observability
- **Compliance**: 
  - Workers scale via Docker replicas
  - Symfony Messenger retry policies configured
  - Distributed locking (Redis) prevents duplicate syncs
  - Sync progress tracking for observability
- **Rationale**: System handles SAP/OpenAI failures gracefully, retries with exponential backoff

**Overall Assessment**: ✅ **ALL GATES PASS** - Feature fully compliant with constitution

## Project Structure

### Documentation (this feature)

```text
specs/002-material-pricing-search/
├── spec.md              # Feature specification (completed)
├── plan.md              # This file (in progress)
├── research.md          # Phase 0 output (pending)
├── data-model.md        # Phase 1 output (pending)
├── quickstart.md        # Phase 1 output (pending)
├── contracts/           # Phase 1 output (pending)
│   ├── commands.md      # Command schemas with validation rules
│   ├── queries.md       # Query schemas with response formats
│   ├── events.md        # Event schemas
│   ├── sap-api.md       # SAP API request/response contracts
│   └── mongodb.md       # MongoDB document schemas
├── tasks.md             # Phase 2 output (/speckit.tasks command)
└── checklists/
    └── requirements.md  # Quality validation (completed)
```

### Source Code (repository root)

```text
src/
├── Domain/
│   ├── Entity/
│   │   ├── Customer.php                    # Existing
│   │   ├── Material.php                    # Existing
│   │   └── CustomerMaterial.php            # Extend with POSNR field
│   ├── Repository/
│   │   ├── CustomerMaterialRepositoryInterface.php  # Extend with findByPosnr()
│   │   └── MaterialReadRepositoryInterface.php      # New interface for MongoDB
│   └── ValueObject/
│       ├── Posnr.php                       # New value object
│       ├── EmbeddingVector.php             # New value object
│       └── SyncLockId.php                  # New value object
├── Application/
│   ├── Command/
│   │   ├── SyncMaterialPriceCommand.php    # New - fetch price with POSNR
│   │   ├── GenerateEmbeddingCommand.php    # New - generate material embedding
│   │   └── AcquireSyncLockCommand.php      # New - distributed locking
│   ├── CommandHandler/
│   │   ├── SyncMaterialPriceHandler.php    # New
│   │   ├── GenerateEmbeddingHandler.php    # New
│   │   └── AcquireSyncLockHandler.php      # New
│   ├── Query/
│   │   ├── SearchMaterialsQuery.php        # New - keyword search
│   │   ├── SemanticSearchQuery.php         # New - AI-powered search
│   │   ├── GetCatalogQuery.php             # New - fetch materials for catalog
│   │   └── GetSyncProgressQuery.php        # New - progress tracking
│   ├── QueryHandler/
│   │   ├── SearchMaterialsHandler.php      # New
│   │   ├── SemanticSearchHandler.php       # New
│   │   ├── GetCatalogHandler.php           # New
│   │   └── GetSyncProgressHandler.php      # New
│   ├── Event/
│   │   ├── MaterialSyncedEvent.php         # New - triggers MongoDB update
│   │   └── PriceFetchedEvent.php           # New - triggers embedding generation
│   └── EventHandler/
│       ├── UpdateMongoOnMaterialSyncedHandler.php   # New
│       └── GenerateEmbeddingOnPriceFetchedHandler.php # New
├── Infrastructure/
│   ├── ExternalApi/
│   │   ├── SapApiClient.php                # Extend with POSNR support
│   │   └── OpenAiEmbeddingClient.php       # New - OpenAI API client
│   ├── Repository/
│   │   ├── DoctrineCustomerMaterialRepository.php  # Extend for POSNR
│   │   └── MongoMaterialRepository.php     # New - MongoDB read model
│   ├── Persistence/
│   │   ├── MongoDB/
│   │   │   └── Document/
│   │   │       └── MaterialView.php        # New - MongoDB document
│   │   └── Redis/
│   │       └── RedisSyncLockRepository.php # New - distributed locking
│   └── Messaging/
│       └── RabbitMQ/
│           └── SyncLockMiddleware.php      # New - prevent duplicate messages
└── UI/
    ├── Controller/
    │   └── MaterialCatalogController.php   # Extend with progress bar, search
    └── Command/
        └── RegenerateEmbeddingsCommand.php # New - CLI utility

templates/
└── catalog/
    ├── index.html.twig                     # Extend with search, progress
    └── partials/
        ├── search-box.html.twig            # New
        └── progress-bar.html.twig          # New

tests/
├── Unit/
│   ├── Domain/
│   │   ├── Entity/CustomerMaterialTest.php # Extend for POSNR
│   │   └── ValueObject/
│   │       ├── PosnrTest.php               # New
│   │       └── EmbeddingVectorTest.php     # New
│   ├── Application/
│   │   ├── CommandHandler/
│   │   │   ├── SyncMaterialPriceHandlerTest.php  # New
│   │   │   └── GenerateEmbeddingHandlerTest.php  # New
│   │   └── QueryHandler/
│   │       ├── SearchMaterialsHandlerTest.php    # New
│   │       └── SemanticSearchHandlerTest.php     # New
│   └── Infrastructure/
│       ├── ExternalApi/
│       │   ├── SapApiClientTest.php        # Extend for POSNR
│       │   └── OpenAiEmbeddingClientTest.php # New
│       └── Repository/
│           └── MongoMaterialRepositoryTest.php   # New
├── Integration/
│   ├── Infrastructure/
│   │   ├── Repository/
│   │   │   └── MongoMaterialRepositoryTest.php   # New - real MongoDB
│   │   └── Redis/
│   │       └── RedisSyncLockRepositoryTest.php   # New - real Redis
│   └── ExternalApi/
│       └── SapApiClientIntegrationTest.php # Extend for POSNR flow
├── Functional/
│   └── UI/
│       └── Controller/
│           └── MaterialCatalogControllerTest.php  # Extend for search, progress
└── E2E/
    ├── MaterialSyncWithPricingTest.php     # New - full SAP → DB → MongoDB flow
    ├── SemanticSearchE2ETest.php           # New - embedding generation → search
    └── SyncDeduplicationTest.php           # New - concurrent sync prevention

config/
├── packages/
│   └── messenger.yaml                      # Extend with new queues, routing
└── services.yaml                           # Register new services

migrations/
└── Version20260218_AddPosnrToCustomerMaterial.php  # New migration

Makefile                                    # New - development commands
```

**Structure Decision**: Web application using existing DDD/Hexagonal/CQRS architecture. Backend-only implementation with Twig templates for UI. No separate frontend framework required. Tests organized by layer (Unit → Integration → Functional → E2E) following existing structure. New components integrate seamlessly with existing Domain/Application/Infrastructure/UI layers.

## Complexity Tracking

No constitutional violations requiring justification. All features align with established architecture.
