# Feature Specification: Material Pricing & Semantic Search System

**Feature Branch**: `002-material-pricing-search`  
**Created**: 2026-02-18  
**Status**: Draft  
**Input**: User description: "Implement material pricing with POSNR, sync deduplication, catalog with progress bar, MongoDB integration with semantic search using OpenAI embeddings, and Makefile"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Accurate Material Pricing Retrieval (Priority: P1)

Users need to see accurate prices for materials that are retrieved from SAP, including the POSNR field which is essential for correct pricing calculation.

**Why this priority**: Core business functionality - without accurate prices, users cannot make purchasing decisions. The POSNR field from the materials list must be passed to the pricing call for SAP to return correct prices.

**Independent Test**: Can be fully tested by triggering a material sync for a customer/sales org, verifying that POSNR values from X_MAT_FOUND are stored and used in subsequent price retrieval calls, and confirming accurate prices are returned and displayed.

**Acceptance Scenarios**:

1. **Given** materials list is retrieved from SAP with POSNR values, **When** system requests prices for these materials, **Then** POSNR from materials list is included in price request
2. **Given** a material has POSNR "000010", **When** price is requested, **Then** SAP receives POSNR in IN_WA_MATNR structure
3. **Given** pricing call completes successfully, **When** price data contains NETPR and WAERK, **Then** price is stored in customer_materials table with correct values

---

### User Story 2 - Prevent Duplicate Sync Operations (Priority: P1)

System must prevent multiple sync operations for the same customer and sales organization from queueing simultaneously, avoiding message queue overflow and redundant SAP calls.

**Why this priority**: Critical for system stability - prevents queue flooding with 20,000+ messages and unnecessary load on SAP systems. Ensures efficient resource usage.

**Independent Test**: Can be fully tested by triggering multiple sync requests for the same customer/sales org in rapid succession and verifying only one sync operation executes while others are skipped or queued appropriately.

**Acceptance Scenarios**:

1. **Given** a sync is in progress for customer X and sales org Y, **When** another sync request arrives for same customer/sales org, **Then** second request is rejected or waits for first to complete
2. **Given** a successful sync completed 5 minutes ago for customer X, **When** new sync request arrives for same customer, **Then** system checks sync timestamp and skips if within cooldown period
3. **Given** sync operations for different customers, **When** multiple requests arrive, **Then** all proceed independently without blocking each other

---

### User Story 3 - Catalog Page with Real-Time Sync Progress (Priority: P2)

Users accessing the catalog page need to see materials currently in the database with accurate prices and a progress indicator showing sync completion status.

**Why this priority**: Essential for user experience - users need to know what data is available and whether sync is complete before making decisions.

**Independent Test**: Can be fully tested by accessing catalog page during and after sync, verifying materials display with prices, and confirming progress bar shows accurate completion percentage.

**Acceptance Scenarios**:

1. **Given** user navigates to /catalog/{salesOrg}/{customerId}, **When** page loads, **Then** all synchronized materials display with their prices
2. **Given** sync is in progress, **When** user views catalog, **Then** progress bar shows X of Y materials synchronized
3. **Given** sync is complete, **When** user views catalog, **Then** progress bar shows 100% and all materials have prices

---

### User Story 4 - Material Search by Name (Priority: P2)

Users need to quickly find specific materials by searching the material description or number, with search results appearing instantly.

**Why this priority**: Improves usability for catalogs with hundreds or thousands of materials - users shouldn't need to scroll through entire catalog.

**Independent Test**: Can be fully tested by entering search terms in catalog search box and verifying filtered results match search criteria in material number or description.

**Acceptance Scenarios**:

1. **Given** catalog contains 1000 materials, **When** user types "HEMOSIL" in search box, **Then** only materials containing "HEMOSIL" in description display
2. **Given** user searches by material number "00020006800", **When** search executes, **Then** exact matching material appears in results
3. **Given** search term matches multiple materials, **When** results display, **Then** materials are ranked by relevance

---

### User Story 5 - MongoDB-Based Fast Search (Priority: P3)

System stores material data in MongoDB for rapid search operations, with automatic synchronization when materials are created or updated in MySQL.

**Why this priority**: Performance optimization - enables sub-second search responses for large catalogs without impacting transactional database.

**Independent Test**: Can be fully tested by creating materials in MySQL, verifying they appear in MongoDB, and measuring search response times (should be <100ms for 10,000 materials).

**Acceptance Scenarios**:

1. **Given** new material is saved to MySQL, **When** save completes, **Then** material document is created in MongoDB with same data
2. **Given** material is updated in MySQL, **When** update completes, **Then** MongoDB document is updated with new values
3. **Given** user performs search, **When** search query executes, **Then** MongoDB is queried and results return in under 100ms

---

### User Story 6 - Semantic Search with AI Embeddings (Priority: P3)

Users can search for materials using natural language descriptions, with the system using OpenAI embeddings to find semantically similar materials even when exact keywords don't match.

**Why this priority**: Advanced feature that significantly improves search UX - users can find "blood coagulation test kit" even if material description says "hemostasis diagnostic reagent".

**Independent Test**: Can be fully tested by searching for conceptually related terms and verifying relevant materials appear in results based on semantic similarity rather than keyword matching.

**Acceptance Scenarios**:

1. **Given** materials have embedding vectors generated from descriptions, **When** user enters natural language query, **Then** query is converted to embedding vector using OpenAI
2. **Given** search embedding is generated, **When** similarity search runs, **Then** materials with closest vector distance appear in results
3. **Given** user searches "blood testing supplies", **When** search executes, **Then** relevant materials appear even if description uses different terminology

---

### Edge Cases

- What happens when SAP returns materials without POSNR field?
- How does system handle pricing requests that timeout?
- What if sync is interrupted mid-process (server restart, network failure)?
- How does system behave when MongoDB is unavailable but MySQL is up?
- What happens when OpenAI API rate limits are hit during embedding generation?
- How are materials handled that have been deleted from SAP but exist in local database?
- What if two sync operations start simultaneously before deduplication check completes?
- How does search behave when user enters very long search queries (1000+ characters)?

## Requirements *(mandatory)*

### Functional Requirements

#### Price Retrieval

- **FR-001**: System MUST include POSNR field from materials list (X_MAT_FOUND) when requesting prices from SAP
- **FR-002**: System MUST store relationship between Customer, Material, and POSNR in database
- **FR-003**: System MUST pass correct SAP parameters (I_WA_TVKO, I_WA_TVAK, I_WA_AG, I_WA_WE, I_WA_RG, IN_WA_MATNR) for price requests
- **FR-004**: System MUST extract price (NETPR) and currency (WAERK) from SAP response
- **FR-005**: System MUST handle materials where price data is unavailable (mark as unavailable, log for review)

#### Sync Deduplication

- **FR-006**: System MUST track sync operations by customer ID and sales organization
- **FR-007**: System MUST prevent concurrent sync operations for same customer/sales org combination
- **FR-008**: System MUST use distributed locking mechanism to coordinate across multiple worker processes
- **FR-009**: System MUST store last successful sync timestamp for each customer/sales org
- **FR-010**: System MUST implement configurable cooldown period (default 5 minutes) before allowing re-sync
- **FR-011**: System MUST clear locks automatically if process crashes or times out (max lock duration: 10 minutes)

#### Catalog Page

- **FR-012**: System MUST display catalog at URL pattern /catalog/{salesOrg}/{customerId}
- **FR-013**: Page MUST show all materials synchronized for specified customer/sales org
- **FR-014**: Each material MUST display: material number, description, price, currency, availability
- **FR-015**: Page MUST display sync progress bar showing: materials synced / total materials expected
- **FR-016**: Progress bar MUST update in real-time during active sync operations
- **FR-017**: Page MUST indicate when no materials are available (not yet synced)
- **FR-018**: Page MUST handle cases where sync is in progress vs complete vs failed

#### Material Search

- **FR-019**: Catalog page MUST provide search input field
- **FR-020**: Search MUST filter materials by material number (exact or partial match)
- **FR-021**: Search MUST filter materials by description (case-insensitive substring match)
- **FR-022**: Search results MUST appear without requiring page refresh
- **FR-023**: Search MUST work on materials dataset exceeding 10,000 items with acceptable performance

#### MongoDB Integration

- **FR-024**: System MUST store material documents in MongoDB when created/updated in MySQL
- **FR-025**: MongoDB document MUST include: material number, description, customer ID, sales org, price, currency, POSNR, sync timestamp
- **FR-026**: System MUST maintain consistency between MySQL and MongoDB (eventual consistency acceptable)
- **FR-027**: System MUST use MongoDB for search operations to avoid loading transactional database
- **FR-028**: System MUST gracefully handle MongoDB unavailability (fallback to MySQL search with performance warning)

#### Semantic Search

- **FR-029**: System MUST generate embedding vectors for material descriptions using OpenAI API
- **FR-030**: System MUST use OpenAI model: text-embedding-3-small (configurable via environment)
- **FR-031**: Embedding vectors MUST be stored in MongoDB alongside material documents
- **FR-032**: System MUST generate embedding for search queries using same model
- **FR-033**: Semantic search MUST use vector similarity (cosine similarity or equivalent) to rank results
- **FR-034**: System MUST provide toggle allowing users to switch between keyword and semantic search
- **FR-035**: System MUST handle OpenAI API failures gracefully (fall back to keyword search, log errors)
- **FR-036**: System MUST cache embeddings to avoid regenerating for unchanged material descriptions

#### Development Tools

- **FR-037**: System MUST provide Makefile with common commands: install, test, start, stop, sync-materials, clear-cache, db-migrate, etc.
- **FR-038**: Makefile commands MUST be documented with descriptions
- **FR-039**: System MUST include command to manually trigger sync for specific customer/sales org
- **FR-040**: System MUST include command to regenerate embeddings for all materials

### Key Entities

- **CustomerMaterial**: Extended to include POSNR field from SAP materials list, representing the position number needed for accurate price retrieval
- **SyncLock**: Tracks active sync operations by customer ID and sales organization to prevent duplicates; includes lock timestamp, process ID, expiration time
- **MaterialView (MongoDB)**: Read-optimized denormalized view of material data including customer-specific prices, embeddings for search; includes: material number, description, customer ID, sales org, price, currency, POSNR, embedding vector (1536 dimensions for text-embedding-3-small), last updated timestamp
- **SyncProgress**: Tracks sync operation progress including total materials expected, materials processed, current status (in-progress, completed, failed), start time, end time

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Material prices retrieved from SAP must match expected values when POSNR is correctly included (100% accuracy for test dataset)
- **SC-002**: Duplicate sync attempts for same customer/sales org must be prevented with <1% failure rate (lock bypasses)
- **SC-003**: Catalog page must load and display materials with prices in under 2 seconds for catalogs with up to 5,000 materials
- **SC-004**: Sync progress bar must show accurate progress within 5 seconds of actual completion percentage
- **SC-005**: Material search must return results in under 200ms for keyword search on database of 10,000 materials
- **SC-006**: MongoDB synchronization must achieve <5 second delay between MySQL write and MongoDB availability
- **SC-007**: Semantic search must return relevant results within 1 second including embedding generation time
- **SC-008**: Semantic search relevance must be rated 7/10 or higher by users compared to keyword search for ambiguous queries
- **SC-009**: Makefile commands must execute successfully with clear output and error messages
- **SC-010**: System must handle SAP API failures gracefully with appropriate user feedback within 10 seconds of timeout

## Assumptions

- SAP API structure for ZSDO_EBU_LOAD_MATERIALS and ZSDO_EBU_SHOW_MATERIAL_PRICE remains consistent with legacy implementation
- POSNR field is always present in X_MAT_FOUND results from SAP (empty string acceptable)
- MongoDB 7.0+ with vector search capabilities is available
- OpenAI API key has sufficient quota for embedding generation (approximately 1 embedding per material)  
- Redis or similar distributed cache is available for sync locking mechanism
- Catalog page users have modern browsers supporting JavaScript for real-time progress updates
- Maximum 50,000 materials per customer/sales org combination
- Search queries typically under 200 characters
- Acceptable eventual consistency delay between MySQL and MongoDB is 5 seconds

## Out of Scope

- Real-time price updates from SAP (prices refresh only during explicit sync operations)
- Multi-language semantic search (initial implementation supports single language)
- Image-based material search
- Voice-based search interface
- Offline catalog access
- Export catalog to PDF/Excel
- Price history tracking
- Automated scheduled syncs (manual trigger only in initial version)
- User authentication/authorization (assumes existing auth system)
- Material comparison features

## Technical Constraints

- Must integrate with existing CQRS/DDD/Hexagonal architecture
- Must use Symfony Messenger for async operations
- Must maintain MySQL as source of truth for transactional data
- Must use MongoDB for read-optimized queries only
- OpenAI API usage must be monitored and rate-limited to prevent excessive costs
- Embedding dimension must match OpenAI model output (1536 for text-embedding-3-small)

## Dependencies

- Symfony HTTP Client for SAP API communication
- Symfony Messenger for async job processing
- Doctrine ORM for MySQL operations
- Doctrine MongoDB ODM for MongoDB operations
- Symfony/AI component for OpenAI integration
- Redis/Symfony Cache for distributed locking
- RabbitMQ for message queue
- OpenAI API access with valid API key

