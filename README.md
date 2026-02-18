# MyOrders

A Symfony-based web application for managing SAP material orders with DDD/Hexagonal architecture, CQRS pattern, and polyglot persistence.

## Overview

MyOrders provides a modern, scalable platform for managing materials synchronized from SAP systems. The application follows Domain-Driven Design principles with a clear separation of concerns across Domain, Application, Infrastructure, and UI layers.

## Technology Stack

- **Backend**: PHP 8.3 + Symfony 7.4
- **Web Server**: Nginx (Alpine)
- **Databases**: 
  - MySQL 8.0 (source of truth)
  - MongoDB 7.0 (read models, embeddings)
- **Message Broker**: RabbitMQ 3 (with management UI)
- **ORM/ODM**: Doctrine ORM + Doctrine MongoDB ODM
- **Messaging**: Symfony Messenger with AMQP transport
- **Templates**: Twig 3.x
- **Testing**: PHPUnit 10.x
- **Container**: Docker + Docker Compose

## Architecture

```
src/
├── Domain/          # Business logic (framework-independent)
├── Application/     # Use cases, CQRS messages, handlers
├── Infrastructure/  # Database adapters, external integrations
└── UI/              # Controllers, templates, CLI commands
```

## Quick Start

### Prerequisites

- Docker 20.10+ and Docker Compose 2.0+
- 4GB RAM minimum (8GB recommended)
- Available ports: 80, 3306, 5672, 15672, 27017

### Setup

```bash
# Clone the repository
git clone <repository-url>
cd MyOrders

# Copy environment configuration
cp .env.example .env

# Start all services
docker compose up -d --build

# Install dependencies
docker compose exec php composer install

# Create database
docker compose exec php bin/console doctrine:database:create

# Access the application
open http://localhost
```

### Verify Installation

```bash
# Check all containers are running
docker compose ps

# Check Symfony environment
docker compose exec php bin/console about

# Access RabbitMQ Management UI
open http://localhost:15672
# (Username: guest, Password: guest)
```

## Development

### Common Commands

```bash
# Container management
docker compose up -d              # Start services
docker compose down               # Stop services
docker compose logs -f php        # View PHP logs
docker compose restart php        # Restart PHP container

# Symfony console
docker compose exec php bin/console [command]
docker compose exec php bin/console cache:clear
docker compose exec php bin/console debug:router

# Database
docker compose exec php bin/console doctrine:database:create
docker compose exec php bin/console doctrine:migrations:migrate

# Composer
docker compose exec php composer install
docker compose exec php composer require [package]

# Testing
docker compose exec php bin/phpunit
```

### Project Structure

For detailed setup instructions, troubleshooting, and development guidelines, see:
- [specs/001-project-setup/quickstart.md](specs/001-project-setup/quickstart.md) - Comprehensive setup guide
- [specs/001-project-setup/plan.md](specs/001-project-setup/plan.md) - Implementation plan
- [.specify/memory/constitution.md](.specify/memory/constitution.md) - Project constitution

## Constitutional Principles

This project follows strict architectural principles:

1. **DDD + Hexagonal Architecture** - Clear layer separation
2. **CQRS** - Separate command, query, and event buses
3. **Asynchronous Processing** - RabbitMQ for async operations
4. **Polyglot Persistence** - MySQL (source of truth) + MongoDB (read models)
5. **SOLID Principles** - Dependency injection and proper abstraction
6. **Eventual Consistency** - Optimized for scalability

See [.specify/memory/constitution.md](.specify/memory/constitution.md) for complete principles.

## Contributing

1. Follow DDD/Hexagonal architecture patterns
2. Keep Domain layer framework-independent
3. Use Symfony Messenger for Commands, Queries, and Events
4. Write tests before implementation (TDD when applicable)
5. Ensure all containers pass health checks
6. Run `composer validate` before committing

## Services

- **Application**: http://localhost
- **RabbitMQ Management**: http://localhost:15672
- **MySQL**: localhost:3306
- **MongoDB**: localhost:27017

## License

[LICENSE TYPE] - See LICENSE file for details

## Support

For issues and questions:
- Check [specs/001-project-setup/quickstart.md](specs/001-project-setup/quickstart.md) troubleshooting section
- Review project documentation in `specs/` directory
- Open an issue in the repository

---

**Status**: Initial Setup (Feature 001-project-setup)  
**Last Updated**: 2026-02-17
