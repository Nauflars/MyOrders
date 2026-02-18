# MyOrders - Material Pricing & Semantic Search System
# Makefile for development and deployment commands

.PHONY: help start stop restart build logs test sync-materials regenerate-embeddings migrate cache-clear worker-start worker-stop

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
NC := \033[0m # No Color

help: ## Show this help message
	@echo "$(BLUE)MyOrders - Material Pricing & Semantic Search System$(NC)"
	@echo ""
	@echo "$(GREEN)Available commands:$(NC)"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  $(YELLOW)%-25s$(NC) %s\n", $$1, $$2}' $(MAKEFILE_LIST)

#------------------------------------------------------------------------------
# Docker & Environment
#------------------------------------------------------------------------------

start: ## Start all Docker services
	@echo "$(BLUE)Starting Docker services...$(NC)"
	docker-compose up -d
	@echo "$(GREEN)Services started successfully!$(NC)"

stop: ## Stop all Docker services
	@echo "$(BLUE)Stopping Docker services...$(NC)"
	docker-compose down
	@echo "$(GREEN)Services stopped successfully!$(NC)"

restart: ## Restart all Docker services
	@echo "$(BLUE)Restarting Docker services...$(NC)"
	docker-compose restart
	@echo "$(GREEN)Services restarted successfully!$(NC)"

build: ## Build Docker images
	@echo "$(BLUE)Building Docker images...$(NC)"
	docker-compose build
	@echo "$(GREEN)Images built successfully!$(NC)"

logs: ## Show logs from all services
	docker-compose logs -f

#------------------------------------------------------------------------------
# Database & Migrations
#------------------------------------------------------------------------------

migrate: ## Run database migrations
	@echo "$(BLUE)Running database migrations...$(NC)"
	docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction
	@echo "$(GREEN)Migrations completed successfully!$(NC)"

migrate-status: ## Show migration status
	docker-compose exec php bin/console doctrine:migrations:status

migrate-rollback: ## Rollback last migration
	docker-compose exec php bin/console doctrine:migrations:migrate prev --no-interaction

cache-clear: ## Clear application cache
	@echo "$(BLUE)Clearing cache...$(NC)"
	docker-compose exec php bin/console cache:clear
	@echo "$(GREEN)Cache cleared successfully!$(NC)"

#------------------------------------------------------------------------------
# Testing
#------------------------------------------------------------------------------

test: ## Run all tests
	@echo "$(BLUE)Running test suite...$(NC)"
	docker-compose exec php bin/phpunit
	@echo "$(GREEN)Tests completed!$(NC)"

test-unit: ## Run unit tests only
	@echo "$(BLUE)Running unit tests...$(NC)"
	docker-compose exec php bin/phpunit tests/Unit

test-integration: ## Run integration tests only
	@echo "$(BLUE)Running integration tests...$(NC)"
	docker-compose exec php bin/phpunit tests/Integration

test-functional: ## Run functional tests only
	@echo "$(BLUE)Running functional tests...$(NC)"
	docker-compose exec php bin/phpunit tests/Functional

test-e2e: ## Run E2E tests only
	@echo "$(BLUE)Running E2E tests...$(NC)"
	docker-compose exec php bin/phpunit tests/E2E

test-coverage: ## Run tests with coverage report
	@echo "$(BLUE)Running tests with coverage...$(NC)"
	docker-compose exec php bin/phpunit --coverage-html var/coverage
	@echo "$(GREEN)Coverage report generated in var/coverage/$(NC)"

#------------------------------------------------------------------------------
# Material Sync Operations
#------------------------------------------------------------------------------

sync-materials: ## Sync materials for a customer/sales org (usage: make sync-materials CUSTOMER=185 SALES_ORG=0000210839)
	@echo "$(BLUE)Syncing materials for customer $(CUSTOMER) and sales org $(SALES_ORG)...$(NC)"
	docker-compose exec php bin/console app:sync-materials $(CUSTOMER) $(SALES_ORG)
	@echo "$(GREEN)Sync initiated successfully!$(NC)"

sync-progress: ## Check sync progress (usage: make sync-progress CUSTOMER=185 SALES_ORG=0000210839)
	@echo "$(BLUE)Checking sync progress...$(NC)"
	docker-compose exec php bin/console app:sync-progress $(CUSTOMER) $(SALES_ORG)

#------------------------------------------------------------------------------
# Semantic Search & Embeddings
#------------------------------------------------------------------------------

regenerate-embeddings: ## Regenerate embeddings for all materials
	@echo "$(BLUE)Regenerating embeddings for all materials...$(NC)"
	docker-compose exec php bin/console app:regenerate-embeddings
	@echo "$(GREEN)Embeddings regeneration initiated!$(NC)"

regenerate-embeddings-customer: ## Regenerate embeddings for specific customer (usage: make regenerate-embeddings-customer CUSTOMER=185)
	@echo "$(BLUE)Regenerating embeddings for customer $(CUSTOMER)...$(NC)"
	docker-compose exec php bin/console app:regenerate-embeddings --customer=$(CUSTOMER)
	@echo "$(GREEN)Embeddings regeneration initiated!$(NC)"

#------------------------------------------------------------------------------
# Messenger Workers
#------------------------------------------------------------------------------

worker-start: ## Start all messenger workers
	@echo "$(BLUE)Starting messenger workers...$(NC)"
	docker-compose exec -d php bin/console messenger:consume async_priority_high --limit=100 --time-limit=3600 --memory-limit=512M
	docker-compose exec -d php bin/console messenger:consume async_priority_normal --limit=100 --time-limit=3600 --memory-limit=512M
	docker-compose exec -d php bin/console messenger:consume async_priority_low --limit=100 --time-limit=3600 --memory-limit=512M
	@echo "$(GREEN)Workers started successfully!$(NC)"

worker-stop: ## Stop all messenger workers
	@echo "$(BLUE)Stopping messenger workers...$(NC)"
	docker-compose exec php bin/console messenger:stop-workers
	@echo "$(GREEN)Workers stopped successfully!$(NC)"

worker-failed: ## Show failed messages
	docker-compose exec php bin/console messenger:failed:show

worker-retry: ## Retry failed messages
	docker-compose exec php bin/console messenger:failed:retry

#------------------------------------------------------------------------------
# MongoDB Operations
#------------------------------------------------------------------------------

mongo-shell: ## Open MongoDB shell
	docker-compose exec mongodb mongosh -u myorders_user -p myorders_password --authenticationDatabase admin myorders

mongo-indexes: ## Create MongoDB indexes
	@echo "$(BLUE)Creating MongoDB indexes...$(NC)"
	docker-compose exec php bin/console app:mongodb-indexes
	@echo "$(GREEN)Indexes created successfully!$(NC)"

mongo-rebuild: ## Rebuild MongoDB read model from MySQL
	@echo "$(BLUE)Rebuilding MongoDB read model...$(NC)"
	docker-compose exec php bin/console app:rebuild-mongo-read-model
	@echo "$(GREEN)Rebuild initiated!$(NC)"

#------------------------------------------------------------------------------
# Development Tools
#------------------------------------------------------------------------------

shell: ## Open PHP container shell
	docker-compose exec php bash

composer-install: ## Install PHP dependencies
	@echo "$(BLUE)Installing PHP dependencies...$(NC)"
	docker-compose exec php composer install
	@echo "$(GREEN)Dependencies installed successfully!$(NC)"

composer-update: ## Update PHP dependencies
	@echo "$(BLUE)Updating PHP dependencies...$(NC)"
	docker-compose exec php composer update
	@echo "$(GREEN)Dependencies updated successfully!$(NC)"

phpstan: ## Run PHPStan static analysis (if configured)
	docker-compose exec php vendor/bin/phpstan analyze src tests

php-cs-fixer: ## Run PHP CS Fixer (if configured)
	docker-compose exec php vendor/bin/php-cs-fixer fix src tests --dry-run --diff

#------------------------------------------------------------------------------
# Quick Diagnostics
#------------------------------------------------------------------------------

status: ## Check service status
	@echo "$(BLUE)Service Status:$(NC)"
	@docker-compose ps

health: ## Check application health
	@echo "$(BLUE)Checking application health...$(NC)"
	@echo "$(YELLOW)MySQL:$(NC)"
	@docker-compose exec mysql mysqladmin ping -h localhost || echo "MySQL is DOWN"
	@echo "$(YELLOW)MongoDB:$(NC)"
	@docker-compose exec mongodb mongosh --eval "db.adminCommand('ping')" --quiet || echo "MongoDB is DOWN"
	@echo "$(YELLOW)RabbitMQ:$(NC)"
	@curl -s -u guest:guest http://localhost:15672/api/healthchecks/node | grep -q "ok" && echo "RabbitMQ is UP" || echo "RabbitMQ is DOWN"
	@echo "$(YELLOW)Redis:$(NC)"
	@docker-compose exec redis redis-cli ping || echo "Redis is DOWN"

clean: ## Clean cache, logs, and temporary files
	@echo "$(BLUE)Cleaning temporary files...$(NC)"
	rm -rf var/cache/* var/log/* var/share/dev/*
	@echo "$(GREEN)Cleanup completed!$(NC)"

#------------------------------------------------------------------------------
# Default target
#------------------------------------------------------------------------------

.DEFAULT_GOAL := help
