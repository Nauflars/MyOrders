# Feature Specification: Initial Project Setup and Technology Stack

**Feature Branch**: `001-project-setup`  
**Created**: 2026-02-17  
**Status**: Draft  
**Input**: User description: "Initial project setup with Symfony 7.4, PHP 8.3, Docker stack (Nginx, PHP-FPM, RabbitMQ, MongoDB, MySQL), and welcome page"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Welcome Page Accessible (Priority: P1)

As a developer, I want to access a working Symfony welcome page so that I can verify the basic web stack (Nginx + PHP-FPM + Symfony) is properly configured and operational.

**Why this priority**: This is the minimal viable proof that the web application stack works. Without this, no further development can proceed. It validates the most critical path: web request → Nginx → PHP-FPM → Symfony → response.

**Independent Test**: Navigate to http://localhost (or configured domain) in a browser and see "Welcome to MyOrders" rendered via Twig template. This can be tested immediately after container startup without any other dependencies.

**Acceptance Scenarios**:

1. **Given** all Docker containers are started, **When** a developer navigates to http://localhost, **Then** the welcome page displays "Welcome to MyOrders"
2. **Given** the welcome page is accessible, **When** the page source is viewed, **Then** it shows HTML generated from a Twig template
3. **Given** the application is running, **When** accessing an undefined route, **Then** Symfony's error handling responds appropriately

---

### User Story 2 - All Infrastructure Services Running (Priority: P2)

As a developer, I want all infrastructure services (MySQL, MongoDB, RabbitMQ) running in containers so that I can begin implementing the CQRS and async processing patterns defined in the project constitution.

**Why this priority**: While not immediately visible to end users, these services form the foundation for all future features. Having them containerized and orchestrated ensures consistent development environments and prepares for the polyglot persistence and async messaging architecture.

**Independent Test**: Execute `docker compose ps` and verify all services (nginx, php, mysql, mongodb, rabbitmq) show "running" status. Access service management interfaces: RabbitMQ management UI (http://localhost:15672), and verify connectivity from the PHP container to each service.

**Acceptance Scenarios**:

1. **Given** Docker Compose configuration exists, **When** `docker compose up -d` is executed, **Then** all five services (nginx, php, mysql, mongodb, rabbitmq) start successfully
2. **Given** all containers are running, **When** checking container health status, **Then** all services report healthy
3. **Given** RabbitMQ is running, **When** accessing the management interface at http://localhost:15672, **Then** the login page is accessible with default credentials
4. **Given** the PHP container is running, **When** attempting database connections from PHP, **Then** both MySQL and MongoDB connections succeed

---

### User Story 3 - Symfony Project Structure Initialized (Priority: P3)

As a developer, I want a properly structured Symfony project that follows DDD/Hexagonal architecture conventions so that future feature implementations align with the project constitution from day one.

**Why this priority**: Establishing the correct directory structure and architectural boundaries early prevents technical debt. This ensures Domain, Application, Infrastructure, and Interface layers are clearly separated.

**Independent Test**: Review the project directory structure and verify the presence of domain-aligned directories: `src/Domain/`, `src/Application/`, `src/Infrastructure/`, `src/UI/`. Check that Symfony's default bundle structure does not leak into the domain layer.

**Acceptance Scenarios**:

1. **Given** Symfony 7.4 is installed, **When** reviewing the src/ directory, **Then** it contains separate subdirectories for Domain, Application, Infrastructure, and UI layers
2. **Given** the project structure exists, **When** examining the composer.json, **Then** it declares PHP 8.3 and Symfony 7.4 as requirements
3. **Given** the directory structure is established, **When** checking PSR-4 autoloading configuration, **Then** each architectural layer has its own namespace prefix

---

### Edge Cases

- What happens when a required container fails to start? The application should provide clear error messages indicating which service is unavailable
- How does the system handle port conflicts? Docker Compose should fail fast with clear indication of which port is already in use
- What if environment variables are missing? The application should validate required configuration at startup and fail with descriptive errors
- How does the system behave when database credentials are incorrect? Connection attempts should fail gracefully with security-appropriate error messages (not exposing credentials)

## Requirements *(mandatory)*

### Functional Requirements

**Docker Infrastructure:**

- **FR-001**: System MUST provide a docker-compose.yml file that defines all five services: nginx, php-fpm, mysql, mongodb, and rabbitmq
- **FR-002**: System MUST configure Nginx to forward PHP requests to the php-fpm container on the appropriate port
- **FR-003**: System MUST expose MySQL on port 3306, MongoDB on port 27017, RabbitMQ on ports 5672 (AMQP) and 15672 (management UI), and Nginx on port 80
- **FR-004**: System MUST configure persistent volumes for MySQL, MongoDB, and RabbitMQ to retain data across container restarts
- **FR-005**: System MUST define a custom network for inter-container communication

**Symfony Configuration:**

- **FR-006**: System MUST use Symfony 7.4 with PHP 8.3 as the runtime
- **FR-007**: System MUST configure database connections for both MySQL (Doctrine ORM) and MongoDB (doctrine/mongodb-odm-bundle) in the Symfony configuration
- **FR-008**: System MUST configure Symfony Messenger with RabbitMQ transport for future async command/event handling
- **FR-009**: System MUST enable Twig template engine for rendering views
- **FR-010**: System MUST configure environment variables for database credentials, RabbitMQ connection, and application secrets

**Application Structure:**

- **FR-011**: System MUST organize source code following DDD/Hexagonal architecture with separate directories: src/Domain/, src/Application/, src/Infrastructure/, src/UI/
- **FR-012**: System MUST create a default controller in src/UI/Controller/ that handles the root route (/)
- **FR-013**: System MUST create a Twig template that displays "Welcome to MyOrders" with basic HTML structure
- **FR-014**: System MUST configure routing to map the root URL to the welcome controller action

**Development Experience:**

- **FR-015**: System MUST provide a README.md with clear instructions for starting the Docker stack and accessing the application
- **FR-016**: System MUST configure Docker services with meaningful container names for easy identification
- **FR-017**: System MUST include .env configuration file template with all required environment variables documented

### Key Entities *(include if feature involves data)*

While this setup feature doesn't implement domain entities yet, it establishes the infrastructure for future entities:

- **Material**: Future entity representing products/materials synced from SAP (will reside in Domain layer)
- **UserMaterial**: Future entity representing user access relationships (will reside in Domain layer)
- **Configuration**: Environment and service connection settings (will reside in Infrastructure layer)

### Architectural Alignment

This setup establishes the foundation for the 12 constitutional principles:

- **Principle I**: Directory structure separates Domain, Application, Infrastructure, and UI layers
- **Principle II**: Symfony Messenger configured (ready for Commands, Queries, Events)
- **Principle III**: RabbitMQ container prepared for async processing
- **Principle IV**: Infrastructure layer will contain SAP adapters (structure ready)
- **Principle V**: MySQL and MongoDB containers establish polyglot persistence
- **Principle VIII**: PSR-4 autoloading and dependency injection container configured
- **Principle XII**: Health checks and service orchestration via Docker Compose

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Developer can execute `docker compose up -d` and see all five containers reach "running" state within 60 seconds
- **SC-002**: Developer can access http://localhost in a browser and see the "Welcome to MyOrders" page within 2 seconds
- **SC-003**: Developer can verify database connectivity by running `docker compose exec php bin/console doctrine:database:create` successfully for MySQL
- **SC-004**: Developer can access RabbitMQ management UI at http://localhost:15672 and login with configured credentials
- **SC-005**: Project passes `composer validate` without errors
- **SC-006**: All containers remain stable for at least 5 minutes of continuous operation
- **SC-007**: Developer can follow README instructions to get the application running without external assistance
- **SC-008**: Symfony environment test (`bin/console about`) displays PHP 8.3 and Symfony 7.4 versions correctly

## Assumptions

- Docker and Docker Compose are installed on the development machine
- Ports 80, 3306, 5672, 15672, and 27017 are available on the host system
- Developer has sufficient permissions to run Docker containers
- Internet connection is available for initial image downloads and Composer dependency installation
- Developers are familiar with basic Docker and Symfony commands

## Out of Scope

- Actual implementation of CQRS commands, queries, or events (this is infrastructure setup only)
- SAP integration (will be addressed in future specifications)
- User authentication or authorization (future feature)
- Domain entity implementations (future features)
- Embedding generation or semantic search (future features)
- Production deployment configuration (this setup is for local development)
- CI/CD pipeline setup
- Automated tests for the welcome page (can be added later)

## Dependencies

- External package dependencies: Symfony 7.4, Doctrine ORM, Doctrine MongoDB ODM, Symfony Messenger, Twig
- Docker images: nginx:alpine, php:8.3-fpm-alpine, mysql:8.0, mongo:7.0, rabbitmq:3-management
- No dependencies on other features (this is feature #001)

## Future Considerations

This setup prepares the infrastructure for future architectural patterns:

- **Message Bus Implementation**: Symfony Messenger is configured but not yet used. Future features will dispatch Commands (e.g., SyncMaterialCommand) and Events (e.g., MaterialSyncedEvent) through it
- **Polyglot Persistence**: Both MySQL and MongoDB are available. MySQL will be the source of truth for Materials and UserMaterials; MongoDB will store embeddings and read models
- **Async Processing**: RabbitMQ is ready to process async commands for SAP synchronization and embedding generation
- **Hexagonal Ports**: Infrastructure layer will implement interfaces (ports) defined by the Domain layer for database access and external system integration
- **SOLID Principles**: Symfony's dependency injection container enables proper dependency inversion from day one
