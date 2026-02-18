# Research: Initial Project Setup and Technology Stack

**Feature**: 001-project-setup  
**Phase**: 0 - Research and Technology Decisions  
**Date**: 2026-02-17

## Overview

This document captures research findings and technology decisions for the foundational MyOrders infrastructure setup. All technical choices align with the project constitution's requirements for DDD/Hexagonal architecture, CQRS readiness, and polyglot persistence.

---

## Research Areas

### 1. Docker Development Environment for PHP 8.3 + Symfony 7.4

**Research Question**: What is the optimal Docker setup for Symfony development with multiple backing services?

**Decision**: Multi-container architecture using Docker Compose with Alpine Linux base images

**Rationale**:
- **Separation of Concerns**: Each service (nginx, php-fpm, mysql, mongodb, rabbitmq) runs in its own container, enabling independent scaling and updates
- **Alpine Linux**: Minimal image size (~5MB base) reduces download time and attack surface
- **PHP-FPM vs Apache**: FPM provides better process management and resource isolation, industry standard for production Symfony deployments
- **Official Images**: Using `php:8.3-fpm-alpine`, `nginx:alpine`, `mysql:8.0`, `mongo:7.0`, `rabbitmq:3-management` ensures security patches and community support

**Alternatives Considered**:
- **All-in-one container**: Rejected because it violates single responsibility and makes debugging harder
- **LAMP stack image**: Rejected because it's inflexible and doesn't support MongoDB/RabbitMQ
- **Ubuntu base images**: Rejected due to larger size (>100MB vs ~5MB Alpine)

**Implementation Notes**:
- Custom Dockerfile for PHP to add required extensions (pdo_mysql, mongodb, amqp)
- Persistent volumes for MySQL, MongoDB, RabbitMQ data directories to survive container restarts
- Custom network bridge for inter-container communication with DNS resolution

**References**:
- Symfony Docker Best Practices: https://symfony.com/doc/current/setup/docker.html
- PHP-FPM Configuration: https://www.php.net/manual/en/install.fpm.configuration.php

---

### 2. Symfony 7.4 + PHP 8.3 Compatibility and Configuration

**Research Question**: How to properly configure Symfony 7.4 with PHP 8.3 for DDD/Hexagonal architecture?

**Decision**: Symfony 7.4 with custom PSR-4 namespace structure for architectural layers

**Rationale**:
- **PHP 8.3 Support**: Symfony 7.4 (released late 2024) fully supports PHP 8.3 with optimizations for typed properties and enums
- **Symfony Flex**: Automatic recipe execution simplifies bundle configuration
- **Minimal Bundles**: Install only FrameworkBundle, TwigBundle, DoctrineBundle, MongoDBBundle, MessengerBundle to avoid bloat
- **Custom Namespaces**: Configure PSR-4 autoloading for `App\Domain\`, `App\Application\`, `App\Infrastructure\`, `App\UI\` to enforce architectural boundaries

**Alternatives Considered**:
- **Symfony 6.4 LTS**: Rejected in favor of latest stable 7.4 which has better performance and DX improvements
- **Symfony 8.0**: Not yet stable, premature for production foundations
- **Framework-less PHP**: Rejected because Symfony's DI container and Messenger component are constitutional requirements

**Implementation Notes**:
- `composer.json` requires: `"php": "^8.3"`, `"symfony/framework-bundle": "^7.4"`
- PSR-4 autoload configuration maps each layer to its namespace
- Disable Symfony's default `App\Entity` and `App\Repository` namespaces to prevent ORM/domain coupling

**Configuration Files**:
- `config/packages/framework.yaml`: Enable property accessors, validation
- `config/services.yaml`: Autowiring enabled for all layers except Domain (explicit wiring for domain services)

---

### 3. Doctrine ORM (MySQL) + MongoDB ODM Integration

**Research Question**: How to configure dual persistence (relational + document) in Symfony?

**Decision**: Doctrine ORM for MySQL, Doctrine MongoDB ODM for MongoDB, separate repository namespaces

**Rationale**:
- **Polyglot Persistence**: Constitutional requirement (Principle V) mandates MySQL as source of truth, MongoDB for read models
- **Doctrine ORM**: Industry-standard PHP ORM with excellent Symfony integration, handles migrations
- **Doctrine MongoDB ODM**: Official MongoDB object mapper, supports embedding and flexible schemas
- **Repository Pattern**: Each persistence mechanism has its own repository namespace to avoid confusion

**Alternatives Considered**:
- **Eloquent ORM**: Rejected because Laravel-specific, poor Symfony integration
- **Raw PDO/MongoDB PHP Driver**: Rejected because too low-level, violates DRY principle
- **Single persistence layer**: Rejected because violates constitutional polyglot persistence requirement

**Implementation Notes**:
- Install `doctrine/orm`, `doctrine/doctrine-bundle`, `doctrine/doctrine-migrations-bundle` for MySQL
- Install `doctrine/mongodb-odm`, `doctrine/mongodb-odm-bundle` for MongoDB
- MySQL entities in `src/Domain/` (pure domain objects)
- ORM mappings in `src/Infrastructure/Persistence/Doctrine/Mapping/`
- MongoDB documents in `src/Infrastructure/Persistence/MongoDB/Document/`
- Separate connection names: `default` (MySQL), `mongodb` (MongoDB)

**Configuration Files**:
- `config/packages/doctrine.yaml`: MySQL connection, entity paths, migrations
- `config/packages/doctrine_mongodb.yaml`: MongoDB connection, document paths

---

### 4. Symfony Messenger + RabbitMQ Configuration

**Research Question**: How to configure Symfony Messenger for CQRS with RabbitMQ transport?

**Decision**: Symfony Messenger with multiple buses (command, query, event) and RabbitMQ AMQP transport

**Rationale**:
- **Constitutional Requirement**: Principle II mandates separate Command, Query, and Event buses
- **Symfony Messenger**: Native Symfony component with excellent DX, supports multiple transports
- **RabbitMQ AMQP**: Industry-standard message broker, guarantees message delivery, supports priority queues
- **Async by Default**: Commands for SAP sync and Events for embeddings route to RabbitMQ; Queries remain synchronous

**Alternatives Considered**:
- **Redis transport**: Rejected because less reliable than RabbitMQ for critical business operations
- **Doctrine transport (database)**: Rejected because adds load to source-of-truth database
- **AWS SQS/SNS**: Rejected for local development complexity, cloud dependency
- **Single bus**: Rejected because violates CQRS separation requirement

**Implementation Notes**:
- Install `symfony/messenger`, `symfony/amqp-messenger` (RabbitMQ adapter)
- Define three buses: `command.bus`, `query.bus`, `event.bus`
- Command bus routing: Async commands → `rabbitmq` transport, sync commands → `sync` transport
- Query bus: Always `sync` transport (constitutional requirement)
- Event bus: Always `async` transport via `rabbitmq`
- RabbitMQ connection: `amqp://guest:guest@rabbitmq:5672/%2f`

**Configuration Files**:
- `config/packages/messenger.yaml`: Bus definitions, transport configuration, routing rules

---

### 5. DDD/Hexagonal Architecture in Symfony

**Research Question**: How to enforce architectural boundaries in Symfony's bundle-centric structure?

**Decision**: Custom namespace organization with explicit PSR-4 configuration, avoiding Symfony's default App conventions

**Rationale**:
- **Domain Independence**: Domain layer must not import Symfony, Doctrine, or any framework code (Constitutional Principle I)
- **Ports and Adapters**: Infrastructure layer implements interfaces defined in Domain layer
- **Explicit Boundaries**: PSR-4 namespaces make layer violations visible in import statements
- **Framework in Infrastructure**: Symfony controllers, services, and configs reside in Infrastructure/UI layers

**Alternatives Considered**:
- **Symfony's default App namespace**: Rejected because encourages tight coupling to framework
- **Separate Composer packages**: Rejected as premature for initial setup, adds complexity
- **Module-by-feature**: Rejected because cross-cutting concerns (persistence, messaging) are infrastructure-level

**Implementation Notes**:
- `src/Domain/`: Pure PHP domain entities, value objects, domain services, repository interfaces (ports)
- `src/Application/`: Command/Query/Event messages, handlers, application services
- `src/Infrastructure/`: Doctrine repositories (adapter implementations), Symfony configs, external integrations
- `src/UI/`: Controllers, CLI commands, view models, templates
- Domain layer has NO dependencies in `composer.json` (only PHP core)
- Application layer depends on Domain only
- Infrastructure and UI depend on Application and Domain

**Enforcement**:
- PHPStan/Psalm architecture rules (future task)
- Code reviews verify import statements
- Dependency diagrams in documentation

---

## Technology Stack Summary

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| Language | PHP | 8.3 | Constitutional requirement, latest stable |
| Framework | Symfony | 7.4 | Constitutional requirement, DI + Messenger |
| Web Server | Nginx | Alpine | HTTP termination, static file serving |
| App Server | PHP-FPM | 8.3-Alpine | Symfony application runtime |
| Relational DB | MySQL | 8.0 | Source of truth (Materials, UserMaterials) |
| Document DB | MongoDB | 7.0 | Read models, embeddings |
| Message Broker | RabbitMQ | 3-management | Async command/event processing |
| ORM | Doctrine ORM | Latest | MySQL persistence layer |
| ODM | Doctrine MongoDB ODM | Latest | MongoDB persistence layer |
| Messaging | Symfony Messenger | 7.4 | CQRS buses, RabbitMQ transport |
| Templates | Twig | 3.x | View rendering |
| Testing | PHPUnit | 10.x | Unit, integration, functional tests |
| Container | Docker Compose | 2.x | Development environment orchestration |

---

## Open Questions

None. All technical decisions finalized for initial setup.

---

## Next Steps (Phase 1)

1. **Quickstart Guide**: Document step-by-step instructions for developers to start the stack
2. **Environment Configuration**: Define all `.env` variables with documentation
3. **Service Health Checks**: Configure Docker health checks for each container
4. **Networking**: Define custom Docker network with service discovery

---

**Research Completed**: 2026-02-17  
**Reviewed By**: N/A (initial setup)  
**Status**: ✅ All decisions finalized, ready for Phase 1 design
