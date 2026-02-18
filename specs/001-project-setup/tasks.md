---
description: "Implementation tasks for initial project setup with Docker, Symfony 7.4, and DDD architecture"
---

# Tasks: Initial Project Setup and Technology Stack

**Input**: Design documents from `/specs/001-project-setup/`  
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md

**Tests**: Tests are NOT included for this infrastructure setup feature per spec "Out of Scope"

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Repository root**: All files at top level (docker-compose.yml, composer.json, etc.)
- **Docker configs**: `docker/nginx/`, `docker/php/`
- **Symfony**: `src/`, `config/`, `public/`, `bin/`
- DDD layers: `src/Domain/`, `src/Application/`, `src/Infrastructure/`, `src/UI/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Initialize repository and basic project structure

- [x] T001 Create `.gitignore` file with Symfony/Docker patterns at repository root
- [x] T002 Create `README.md` with project overview and quick start reference at repository root
- [x] T003 [P] Create `docker/` directory structure with `nginx/` and `php/` subdirectories

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core Docker and Symfony infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T004 Create `docker-compose.yml` with all five services (nginx, php, mysql, mongodb, rabbitmq) at repository root
- [x] T005 [P] Create `docker/php/Dockerfile` with PHP 8.3-FPM base image (simplified: only pdo_mysql, opcache for US1)
- [x] T006 [P] Create `docker/nginx/default.conf` with Symfony front controller configuration
- [x] T007 Create `.env.example` with documented environment variables for all services at repository root
- [ ] T008 [P] Configure persistent volumes in `docker-compose.yml` for MySQL, MongoDB, and RabbitMQ data
- [ ] T009 [P] Configure health checks in `docker-compose.yml` for all five services
- [ ] T010 Configure custom Docker network in `docker-compose.yml` for inter-container communication
- [ ] T011 Start containers with `docker compose up -d --build` and verify all services reach "running" state
- [x] T012 Initialize Symfony 7.4 project - Created composer.json, Kernel, and core structure manually
- [~] T013 Install Symfony bundles: `composer require webapp` - Structure created, needs `composer install`
- [ ] T014 [P] Install Doctrine ORM: `composer require symfony/orm-pack` inside PHP container
- [ ] T015 [P] Install Doctrine MongoDB ODM: `composer require doctrine/mongodb-odm-bundle` inside PHP container
- [ ] T016 [P] Install Symfony Messenger: `composer require symfony/messenger` inside PHP container
- [~] T017 [P] Install Twig - Added to composer.json, needs `composer install`
- [ ] T018 [P] Install PHPUnit: `composer require --dev phpunit/phpunit` inside PHP container

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Welcome Page Accessible (Priority: P1) 🎯 MVP

**Goal**: Deliver a working welcome page that validates the complete web stack (Nginx → PHP-FPM → Symfony → Twig)

**Independent Test**: Navigate to http://localhost in a browser and see "Welcome to MyOrders" rendered via Twig template

### Implementation for User Story 1

- [x] T019 [US1] Create `WelcomeController.php` in `src/UI/Controller/` with index action mapped to root route
- [x] T020 [US1] Create `templates/welcome/index.html.twig` with "Welcome to MyOrders" heading and beautiful responsive design
- [x] T021 [US1] Configure root route (`/`) in `config/routes.yaml` to map to WelcomeController::index
- [x] T022 [US1] Add Twig configuration in `config/packages/twig.yaml` with template paths
- [ ] T023 [US1] Verify welcome page renders by accessing http://localhost in browser
- [ ] T024 [US1] Verify page source shows Twig-generated HTML (not static HTML)
- [ ] T025 [US1] Test undefined route (e.g., http://localhost/nonexistent) to verify Symfony error handling

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently (welcome page accessible)

---

## Phase 4: User Story 2 - All Infrastructure Services Running (Priority: P2)

**Goal**: Ensure all backing services (MySQL, MongoDB, RabbitMQ) are operational and accessible for future CQRS/async features

**Independent Test**: Execute `docker compose ps` and verify all 5 services show "running (healthy)" status. Access RabbitMQ management UI at http://localhost:15672

### Implementation for User Story 2

- [x] T026 [P] [US2] Create `config/packages/doctrine.yaml` with MySQL connection configuration
- [x] T027 [P] [US2] Create `config/packages/doctrine_mongodb.yaml` with MongoDB connection configuration
- [x] T028 [P] [US2] Create `config/packages/messenger.yaml` with RabbitMQ AMQP transport configuration
- [x] T029 [US2] Configure MySQL database connection in `.env`: `DATABASE_URL=mysql://user:pass@mysql:3306/myorders_db`
- [x] T030 [US2] Configure MongoDB connection in `.env`: `MONGODB_URL=mongodb://user:pass@mongodb:27017`
- [x] T031 [US2] Configure RabbitMQ transport in `.env`: `MESSENGER_TRANSPORT_DSN=amqp://guest:guest@rabbitmq:5672/%2f`
- [ ] T032 [US2] Create MySQL database: Run `bin/console doctrine:database:create` inside PHP container
- [ ] T033 [US2] Verify MySQL connectivity: Run `bin/console dbal:run-sql "SELECT 1"` inside PHP container
- [ ] T034 [US2] Verify MongoDB connectivity: Run PHP script to test MongoDB\Client connection inside PHP container
- [ ] T035 [US2] Verify RabbitMQ management UI accessible at http://localhost:15672 with guest/guest credentials
- [ ] T036 [US2] Verify RabbitMQ AMQP port accessible: Test connection from PHP container to rabbitmq:5672
- [ ] T037 [US2] Run `docker compose ps` and confirm all five services show "healthy" status
- [ ] T038 [US2] Monitor containers for 5 minutes to verify stability (no restarts or crashes)

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently (welcome page + all services operational)

---

## Phase 5: User Story 3 - Symfony Project Structure Initialized (Priority: P3)

**Goal**: Establish DDD/Hexagonal architecture directory structure with proper namespace configuration

**Independent Test**: Review `src/` directory and verify presence of Domain, Application, Infrastructure, UI subdirectories with correct PSR-4 autoloading

### Implementation for User Story 3

- [x] T039 [P] [US3] Create `src/Domain/` directory for business logic layer
- [x] T040 [P] [US3] Create `src/Application/` directory - Created with Command and CommandHandler examples
- [x] T041 [P] [US3] Create `src/Infrastructure/` directory for external system adapters
- [x] T042 [P] [US3] Create `src/UI/` directory for interface layer (WelcomeController already created)
- [x] T043 [P] [US3] Create `src/Infrastructure/Persistence/Doctrine/` - Created with Order entity example
- [x] T044 [P] [US3] Create `src/Infrastructure/Persistence/MongoDB/` - Created with OrderView document example
- [ ] T045 [P] [US3] Create `.gitkeep` file in `src/Domain/` to preserve empty directory
- [ ] T046 [P] [US3] Create `.gitkeep` file in `src/Application/` to preserve empty directory
- [ ] T047 [P] [US3] Create `.gitkeep` file in `src/Infrastructure/` to preserve empty directory
- [x] T048 [US3] Configure PSR-4 autoloading in `composer.json` for all four architectural layers
- [x] T049 [US3] Update `composer.json` to require PHP 8.3: `"php": "^8.3"`
- [x] T050 [US3] Update `composer.json` to require Symfony 7.4: `"symfony/framework-bundle": "^7.4"`
- [ ] T051 [US3] Run `composer dump-autoload` to regenerate autoload files
- [ ] T052 [US3] Verify PSR-4 namespaces by checking `vendor/composer/autoload_psr4.php` contains Domain, Application, Infrastructure, UI mappings
- [ ] T053 [US3] Run `composer validate` and confirm no errors or warnings

**Checkpoint**: All user stories should now be independently functional (welcome page + services + DDD structure)

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Documentation, validation, and final cleanup

- [ ] T054 [P] Update `README.md` with complete Docker stack startup instructions
- [ ] T055 [P] Document all environment variables in `.env.example` with descriptions and examples
- [ ] T056 [P] Add troubleshooting section to `README.md` referencing `quickstart.md`
- [ ] T057 Run `bin/console about` and verify PHP 8.3 + Symfony 7.4 versions display correctly
- [ ] T058 Run `composer validate` and verify no errors
- [ ] T059 Access http://localhost and verify welcome page loads within 2 seconds
- [ ] T060 Verify all containers remain stable for 5+ minutes without restarts
- [ ] T061 Copy `.env.example` to `.env` and document this step in `README.md`
- [ ] T062 [P] Add `var/` directory to `.gitignore` to exclude cache and logs
- [ ] T063 [P] Add `vendor/` directory to `.gitignore` to exclude Composer dependencies
- [ ] T064 Create git commit with message: "feat: initial project setup with Docker, Symfony 7.4, DDD structure"

---

## Dependencies & Execution Order

### Critical Path (Sequential)

1. **Phase 1: Setup** (T001-T003) → Creates basic repository structure
2. **Phase 2: Foundational** (T004-T018) → **BLOCKS ALL USER STORIES** - Must complete before any story work
3. After Phase 2 completes, the following can proceed **in parallel**:
   - **Phase 3: US1** (T019-T025) - Welcome page
   - **Phase 4: US2** (T026-T038) - Infrastructure services
   - **Phase 5: US3** (T039-T053) - DDD structure
4. **Phase 6: Polish** (T054-T064) → Final cleanup and documentation

### Foundational Blockers

These Phase 2 tasks MUST complete before ANY user story work:
- T004: docker-compose.yml (required by all containers)
- T005: PHP Dockerfile (required by PHP container)
- T011: Container startup (required by all subsequent tasks)
- T012: Symfony initialization (required by all Symfony tasks)
- T013-T018: Bundle installation (required by configuration tasks)

### Parallel Opportunities

**Within Phase 2 (after T004 completes):**
- T005, T006 (Docker configs) can run in parallel
- T007, T008, T009, T010 (docker-compose.yml sections) can run in parallel
- T014, T015, T016, T017, T018 (Composer installs) can run in parallel

**Across User Stories (after Phase 2 completes):**
- All of US1 (T019-T025) is independent
- All of US2 (T026-T038) is independent  
- All of US3 (T039-T053) is independent
- These three stories can be implemented by different developers simultaneously

**Within Phase 6:**
- T054, T055, T056, T062, T063 (documentation) can run in parallel

---

## Parallel Example: After Phase 2 Completion

```
Developer A: Implements US1 (Welcome Page)
├─ T019: Create WelcomeController
├─ T020: Create Twig template
├─ T021: Configure routes
└─ T022-T025: Testing

Developer B: Implements US2 (Infrastructure Services)
├─ T026-T028: Create config files
├─ T029-T031: Configure .env
└─ T032-T038: Verification

Developer C: Implements US3 (DDD Structure)  
├─ T039-T044: Create directories
├─ T045-T047: Add .gitkeep files
└─ T048-T053: Configure autoloading

All three developers work independently without conflicts.
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001-T003)
2. Complete Phase 2: Foundational (T004-T018) - **CRITICAL: Blocks all stories**
3. Complete Phase 3: User Story 1 (T019-T025)
4. **STOP and VALIDATE**: Test welcome page at http://localhost
5. Deploy/demo if ready (working web application)

### Incremental Delivery

1. Complete Setup + Foundational (T001-T018) → Foundation ready
2. Add User Story 1 (T019-T025) → Test independently → Deploy/Demo (MVP: working web app!)
3. Add User Story 2 (T026-T038) → Test independently → Deploy/Demo (MVP + operational infrastructure)
4. Add User Story 3 (T039-T053) → Test independently → Deploy/Demo (MVP + infrastructure + proper architecture)
5. Complete Polish (T054-T064) → Final production-ready state
6. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together (T001-T018)
2. Once Foundational is done:
   - **Developer A**: User Story 1 (T019-T025) - Welcome page
   - **Developer B**: User Story 2 (T026-T038) - Infrastructure services
   - **Developer C**: User Story 3 (T039-T053) - DDD structure
3. Stories complete and integrate independently
4. Team completes Polish together (T054-T064)

---

## Validation Checklist

After completing all tasks, verify:

- [ ] ✅ All 5 Docker containers running and healthy (`docker compose ps`)
- [ ] ✅ Welcome page accessible at http://localhost with "Welcome to MyOrders"
- [ ] ✅ MySQL database created and accessible (`bin/console dbal:run-sql "SELECT 1"`)
- [ ] ✅ MongoDB connection successful (test script runs without errors)
- [ ] ✅ RabbitMQ management UI accessible at http://localhost:15672
- [ ] ✅ Symfony version shows 7.4.x (`bin/console about`)
- [ ] ✅ PHP version shows 8.3.x (`bin/console about`)
- [ ] ✅ DDD directories exist: Domain, Application, Infrastructure, UI
- [ ] ✅ PSR-4 autoloading configured for all architectural layers
- [ ] ✅ Composer validation passes (`composer validate`)
- [ ] ✅ All containers stable for 5+ minutes
- [ ] ✅ README.md contains clear setup instructions
- [ ] ✅ All success criteria from spec.md are met (SC-001 through SC-008)

---

## Notes

### Critical Success Factors

- **Docker First**: Complete all Docker infrastructure (T004-T011) before Symfony installation
- **Container Health**: Wait for health checks to pass before proceeding with application configuration
- **Parallel Safety**: Tasks marked [P] can run in parallel only if they modify different files
- **User Story Independence**: Each user story should be testable independently after completion

### Known Risks

- **Port Conflicts**: Ports 80, 3306, 5672, 15672, 27017 must be available (documented in quickstart.md)
- **MongoDB Lag**: First connection may take 10-15 seconds (health checks account for this)
- **RabbitMQ Startup**: Management UI takes ~20 seconds to become available
- **Volume Permissions**: `var/` directory may need 777 permissions on some systems

### Constitutional Alignment

This task plan enforces:
- ✅ **Principle I**: DDD/Hexagonal architecture (Phase 5 creates Domain, Application, Infrastructure, UI layers)
- ✅ **Principle II**: CQRS readiness (Phase 4 configures Symfony Messenger)
- ✅ **Principle III**: Async processing (Phase 4 configures RabbitMQ)
- ✅ **Principle V**: Polyglot persistence (Phase 4 configures MySQL + MongoDB)
- ✅ **Principle VIII**: SOLID principles (Symfony DI container)
- ✅ **Principle XII**: Operational resilience (Phase 2 health checks)

---

**Total Tasks**: 64  
**Estimated Time**: 6-8 hours (experienced developer), 12-15 hours (first-time setup)  
**Parallel Opportunities**: 18 tasks marked [P]  
**Critical Path Length**: ~35 sequential tasks (if parallelization is maximized)

**Status**: ✅ Ready for implementation  
**Next Step**: Begin with Phase 1 (T001-T003) to initialize repository structure
