<!--
Sync Impact Report - Constitution Update
Version: None → 1.0.0
Ratification: 2026-02-17 (initial adoption)
Last Amended: 2026-02-17

CHANGE SUMMARY:
- Initial constitution ratification
- 12 core architectural principles established
- Domain-Driven Design (DDD) + Hexagonal Architecture + CQRS foundation
- Polyglot persistence strategy defined
- Asynchronous processing patterns established

PRINCIPLES ADDED:
1. Architectural Principles (DDD + Hexagonal + CQRS)
2. Messaging and CQRS (Commands, Queries, Events)
3. Asynchronous Processing (RabbitMQ)
4. External System Integration (SAP)
5. Data Architecture (Polyglot Persistence)
6. Embedding and Semantic Search
7. Performance and Scalability
8. Code Quality and Design Principles (SOLID)
9. Testing Requirements
10. Consistency Model (Eventual Consistency)
11. Maintainability and Extensibility
12. Operational Principles

TEMPLATES STATUS:
✅ plan-template.md - Reviewed, aligned with DDD/Hexagonal/CQRS
✅ spec-template.md - Reviewed, aligned with async patterns
✅ tasks-template.md - Reviewed, aligned with testing discipline
✅ All command files - Reviewed, no agent-specific references found

DEFERRED ITEMS: None
-->

# MyOrders Constitution

## Core Principles

### I. Architectural Principles

This project SHALL follow a strict Domain-Driven Design (DDD) approach combined with Hexagonal Architecture (Ports and Adapters) and CQRS (Command Query Responsibility Segregation).

**Architecture Layers (Mandatory Separation):**
- **Domain Layer**: Business logic and rules — MUST remain independent from infrastructure and framework concerns
- **Application Layer**: Use cases, commands, queries, events
- **Infrastructure Layer**: External systems (SAP, databases, MongoDB, RabbitMQ)
- **Interface Layer**: HTTP controllers, CLI, APIs

**Technology Stack:**
- Symfony framework
- Symfony Messenger component as Message Bus implementation

**Rationale**: This architecture ensures maintainability, testability, and clear separation of concerns. The Domain layer's independence enables business logic evolution without coupling to infrastructure changes.

---

### II. Messaging and CQRS

The system SHALL implement CQRS using three distinct message types: Commands, Queries, and Events.

**Commands** (State Mutation):
- Represent an intention to change system state
- MUST have exactly one handler
- MAY be synchronous or asynchronous
- SHALL be asynchronous when involving slow operations (SAP integration, embedding generation)
- Examples: `SyncUserMaterialsCommand`, `SyncMaterialCommand`
- Dispatched through the Command Bus

**Queries** (Read Operations):
- Represent read-only operations
- SHALL NOT mutate system state
- SHALL always be synchronous
- SHALL return data immediately
- SHALL use optimized read models when necessary
- Examples: `SearchMaterialsQuery`, `GetUserMaterialsQuery`
- Dispatched through the Query Bus

**Events** (Notifications):
- Represent something that has already occurred
- SHALL NOT mutate core state directly
- MAY trigger secondary operations
- SHALL support multiple handlers
- SHALL be asynchronous
- Examples: `MaterialSyncedEvent`, `EmbeddingGeneratedEvent`
- Dispatched through the Event Bus

**Rationale**: CQRS enables independent optimization of read and write paths, supporting scalability and eventual consistency patterns essential for distributed systems.

---

### III. Asynchronous Processing

The system SHALL use RabbitMQ as the asynchronous message transport.

**Asynchronous Use Cases (Mandatory):**
- SAP material synchronization
- Material price synchronization
- Embedding generation
- Heavy or slow operations that would block user interaction

**Processing Requirements:**
- RabbitMQ consumers SHALL process messages in parallel using workers
- The system SHALL guarantee eventual consistency
- User operations SHALL never be blocked by slow external integrations

**Rationale**: Asynchronous processing maximizes performance, scalability, and system resilience. Parallel worker execution ensures optimal resource utilization.

---

### IV. External System Integration (SAP)

SAP SHALL be treated as an external system and accessed exclusively through infrastructure adapters.

**SAP Integration Responsibilities:**
- Fetch base materials
- Fetch offers and prices
- Synchronize materials asynchronously
- Never block user interaction

**Synchronization Requirements:**
- SHALL be performed through commands dispatched to RabbitMQ
- SHALL support both incremental and full synchronization
- SHALL handle temporary SAP unavailability gracefully

**Rationale**: Isolating external system dependencies through adapters protects the domain layer and enables independent testing and evolution of integration logic.

---

### V. Data Architecture

The system SHALL use a polyglot persistence strategy with clearly defined responsibilities.

**Relational Database (PostgreSQL or equivalent) — Source of Truth:**
- Materials
- UserMaterial relationships
- Offers
- Prices
- Synchronization state
- Used for write operations and access control

**MongoDB — Read Model:**
- Material embeddings
- Material searchable fields
- Optimized for semantic search
- SHALL never be used as the source of truth

**Access Control Enforcement:**
- Embeddings SHALL be generated once per Material (not per User)
- User access SHALL be enforced by filtering materialIds from relational database
- MongoDB queries SHALL be filtered by allowed materialIds

**Rationale**: Polyglot persistence enables optimized data models for write vs. read workloads. Relational databases ensure ACID properties for the source of truth, while MongoDB provides vector search capabilities for semantic operations.

---

### VI. Embedding and Semantic Search

The system SHALL generate vector embeddings for each material to enable semantic search.

**Embedding Generation:**
- SHALL be performed asynchronously
- SHALL be stored once per material (not duplicated per user)
- SHALL be triggered by material synchronization events

**Semantic Search Flow:**
1. Retrieve allowed materialIds from relational storage (user access control)
2. Query MongoDB vector search filtered by allowed materialIds
3. Return results respecting user permissions

**Rationale**: This approach ensures performance (no embedding duplication), security (relational DB enforces access control), and scalability (asynchronous generation doesn't block operations).

---

### VII. Performance and Scalability

The system SHALL support high scalability through architectural patterns.

**Scalability Mechanisms:**
- Asynchronous processing using RabbitMQ
- Parallel worker execution
- CQRS read/write separation
- Dedicated read models
- Stateless application services

**Performance Guarantee:**
- User operations SHALL never be blocked by slow external integrations
- The system SHALL remain responsive even when SAP or external services are slow or unavailable

**Rationale**: These patterns enable horizontal scaling and ensure system responsiveness remains independent of external system performance.

---

### VIII. Code Quality and Design Principles

The system SHALL follow strict Clean Code and SOLID principles.

**SOLID Principles (Mandatory):**
- **Single Responsibility Principle**: Each class has one reason to change
- **Open/Closed Principle**: Open for extension, closed for modification
- **Liskov Substitution Principle**: Subtypes must be substitutable for base types
- **Interface Segregation Principle**: No client should depend on unused interfaces
- **Dependency Inversion Principle**: Depend on abstractions, not concretions

**Architectural Rules:**
- All dependencies SHALL be injected
- Business logic SHALL reside exclusively in the Domain layer
- Application services SHALL orchestrate use cases without containing business logic
- Infrastructure SHALL implement interfaces defined by the Domain layer

**Rationale**: SOLID principles ensure maintainability, testability, and extensibility. Dependency injection enables loose coupling and facilitates testing.

---

### IX. Testing Requirements

The system SHALL maintain high test coverage across all layers.

**Unit Tests (Required):**
- Domain entities
- Value objects
- Domain services
- Application handlers

**Integration Tests (Required):**
- Database repositories
- SAP integration adapters
- MongoDB repositories

**Functional Tests (Required):**
- API endpoints
- Application workflows

**End-to-End Tests (Required):**
- Complete flows from SAP synchronization to search availability

**Test Execution:**
- Testing SHALL be automated
- Testing SHALL be executed in CI/CD pipelines
- Tests MUST pass before deployment

**Rationale**: Comprehensive testing ensures system reliability, enables confident refactoring, and serves as executable documentation of system behavior.

---

### X. Consistency Model

The system SHALL follow eventual consistency.

**Consistency Rules:**
- Write operations SHALL update the relational database first (source of truth)
- Read models SHALL be updated asynchronously via events
- The system SHALL always guarantee consistency of the Source of Truth

**Eventual Consistency Scope:**
- MongoDB read models may lag behind relational database writes
- Asynchronous operations (embeddings, SAP sync) complete eventually
- User-facing operations prioritize availability over immediate consistency

**Rationale**: Eventual consistency enables scalability and availability while maintaining strong consistency for the source of truth. This trade-off is appropriate for the system's asynchronous nature.

---

### XI. Maintainability and Extensibility

The architecture SHALL ensure long-term maintainability and extensibility.

**Architectural Qualities (Mandatory):**
- Loose coupling between components
- High cohesion within components
- Clear separation of concerns
- Framework independence of the Domain layer

**Extension Mechanism:**
- The system SHALL be extensible without modifying existing domain logic
- New features SHALL be implemented through new commands, queries, or events
- Existing behavior changes SHALL follow Open/Closed Principle

**Rationale**: These qualities enable the system to evolve without accumulating technical debt. Framework independence protects domain logic from technology churn.

---

### XII. Operational Principles

The system SHALL support robust operational characteristics.

**Operational Requirements:**
- Horizontal scaling via worker replication
- Retry mechanisms for failed messages
- Fault tolerance in external integrations
- Observability and monitoring of asynchronous processes

**Resilience:**
- The system SHALL remain stable when external services are slow or unavailable
- Failed operations SHALL be retried with exponential backoff
- System health SHALL be observable through metrics and logs

**Rationale**: Operational resilience ensures system reliability in production. Observability enables rapid diagnosis and resolution of issues.

---

## Governance

### Amendment Procedure

1. Proposed amendments MUST be documented with rationale
2. Amendment impact on existing codebase MUST be assessed
3. Breaking changes require major version increment
4. All amendments MUST be reviewed and approved
5. Migration plans MUST be provided for principle modifications

### Versioning Policy

This constitution follows semantic versioning (MAJOR.MINOR.PATCH):
- **MAJOR**: Backward incompatible governance or principle removals/redefinitions
- **MINOR**: New principles/sections added or materially expanded guidance
- **PATCH**: Clarifications, wording refinements, typo fixes, non-semantic changes

### Compliance Review

- All code reviews MUST verify compliance with these principles
- New features MUST be evaluated against architectural principles
- Technical debt introduced MUST be explicitly justified and tracked
- Complexity additions MUST demonstrate clear necessity

### Guidance Integration

For runtime development guidance and implementation details, consult:
- `.specify/templates/plan-template.md` for project planning
- `.specify/templates/spec-template.md` for requirements specification
- `.specify/templates/tasks-template.md` for implementation tasks
- `.github/prompts/*.prompt.md` for agent-specific workflows

---

**Version**: 1.0.0 | **Ratified**: 2026-02-17 | **Last Amended**: 2026-02-17
