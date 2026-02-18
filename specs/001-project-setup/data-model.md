# Data Model: Initial Project Setup and Technology Stack

**Feature**: 001-project-setup  
**Date**: 2026-02-17  
**Status**: Not Applicable (Infrastructure Setup)

## Overview

This feature (001-project-setup) is an **infrastructure and technology stack setup**. It does not introduce domain entities or data models.

## Rationale

The purpose of this feature is to:
1. Establish Docker container orchestration
2. Install and configure Symfony 7.4 with PHP 8.3
3. Configure database connections (MySQL, MongoDB)
4. Set up Symfony Messenger with RabbitMQ
5. Create DDD/Hexagonal directory structure
6. Render a welcome page

**No domain entities, database schemas, or data persistence logic are implemented in this phase.**

## Future Data Models

Future features will introduce domain entities following the project constitution:

### Planned Entities (Future Features)

**Material** (Domain Entity):
- Represents products/materials synced from SAP
- Will reside in `src/Domain/Material/`
- Persisted to MySQL (source of truth) via Doctrine ORM
- Embeddings stored in MongoDB for semantic search

**UserMaterial** (Domain Entity):
- Represents user access relationships to materials
- Will reside in `src/Domain/User/` or `src/Domain/Access/`
- Persisted to MySQL (source of truth) via Doctrine ORM
- Used for access control filtering

**Embedding** (Infrastructure Document):
- Vector embeddings for semantic search
- Will reside in `src/Infrastructure/Persistence/MongoDB/Document/`
- Stored in MongoDB as read model
- Not a domain entity (infrastructure optimization)

## Database Schema

### MySQL (Current State: Empty)

No tables or schema defined yet. Migration system initialized but no migrations exist.

**Next Feature**: When Material entity is introduced, Doctrine migrations will create the schema.

### MongoDB (Current State: Empty)

No collections or documents defined yet. MongoDB database created but empty.

**Next Feature**: When embeddings are introduced, MongoDB collections will be created programmatically.

---

## Action Items for Implementation Phase

When implementing this feature (tasks.md), **skip all data model tasks**:
- ❌ No entity classes to create
- ❌ No migrations to write
- ❌ No repository interfaces to define
- ❌ No MongoDB documents to create

**Focus on**:
- ✅ Docker Compose configuration
- ✅ Symfony installation and configuration
- ✅ Service connection configuration (MySQL, MongoDB, RabbitMQ)
- ✅ Directory structure creation (Domain, Application, Infrastructure, UI)
- ✅ Welcome controller and Twig template

---

**Conclusion**: This file serves as documentation that data modeling is intentionally deferred to future feature specifications that introduce actual domain entities.

**See Also**:
- [spec.md](spec.md) - Feature specification
- [plan.md](plan.md) - Implementation plan
- [research.md](research.md) - Technology research
- [quickstart.md](quickstart.md) - Getting started guide
