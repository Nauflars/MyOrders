# Quickstart Guide: Material Pricing & Semantic Search System

**Feature**: 002-material-pricing-search  
**Date**: 2026-02-18  
**Purpose**: Step-by-step setup and development guide

## Prerequisites

- Docker & Docker Compose
- PHP 8.3+ (for local CLI commands)
- Composer
- Git
- MongoDB Atlas account (for vector search) OR self-hosted MongoDB with Atlas Search

---

## 1. Initial Setup

### Clone Repository & Switch Branch

```bash
git clone <repository-url>
cd MyOrders
git checkout 002-material-pricing-search
```

### Install Dependencies

```bash
composer install
```

### Configure Environment

```bash
cp .env .env.local
```

Edit `.env.local` with required values:

```env
# Database
DATABASE_URL="mysql://root:password@127.0.0.1:3306/myorders?serverVersion=8.0"

# MongoDB
MONGODB_URL="mongodb://mongodb:27017"
MONGODB_DB="myorders"

# RabbitMQ
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@rabbitmq:5672/%2f/messages

# Redis (for distributed locking)
REDIS_URL=redis://redis:6379

# SAP API
SAP_API_ENDPOINT=http://sap-server:8000/sap/bc/soap/rfc
SAP_USERNAME=your_sap_username
SAP_PASSWORD=your_sap_password

# OpenAI API (for embeddings)
OPENAI_API_KEY=sk-your-openai-api-key-here
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
```

---

## 2. Start Services

### Using Docker Compose

```bash
docker-compose up -d
```

Services started:
- MySQL (port 3306)
- MongoDB (port 27017)
- RabbitMQ (port 5672, management UI: 15672)
- Redis (port 6379)
- PHP-FPM (application)
- Nginx (port 80)

### Verify Services

```bash
docker-compose ps

# Expected output:
# myorders-mysql       Up      3306->3306
# myorders-mongodb     Up      27017->27017
# myorders-rabbitmq    Up      5672->5672, 15672->15672
# myorders-redis       Up      6379->6379
# myorders-php         Up
# myorders-nginx       Up      80->80
```

---

## 3. Database Setup

### Run Migrations

```bash
# Create database schema
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

# Expected migrations:
# - Version20260217230130 (existing schema)
# - Version20260218_AddPosnrToCustomerMaterial (NEW - adds posnr column)
# - Version20260218_CreateSyncProgress (NEW - sync progress tracking)
```

### Verify MySQL Schema

```bash
docker-compose exec mysql mysql -u root -ppassword myorders -e "DESCRIBE customer_materials;"

# Should include:
# posnr VARCHAR(6) NULL
```

```bash
docker-compose exec mysql mysql -u root -ppassword myorders -e "SHOW TABLES LIKE 'sync_progress';"

# Should return:
# sync_progress table
```

---

## 4. MongoDB Setup

### Create Collection & Indexes

```bash
# Access MongoDB shell
docker-compose exec mongodb mongosh myorders

# Create material_view collection
db.createCollection("material_view")

# Create indexes
db.material_view.createIndex(
    { "customerId": 1, "materialNumber": 1 },
    { unique: true, name: "idx_customer_material_unique" }
)

db.material_view.createIndex(
    { "customerId": 1, "salesOrg": 1 },
    { name: "idx_customer_salesorg" }
)

db.material_view.createIndex(
    { "materialNumber": 1 },
    { name: "idx_material_number" }
)

db.material_view.createIndex(
    { "customerId": 1 },
    { name: "idx_customer_id" }
)

db.material_view.createIndex(
    { "lastUpdatedAt": -1 },
    { name: "idx_last_updated" }
)

# Verify indexes
db.material_view.getIndexes()
```

### Create Vector Search Index (MongoDB Atlas)

If using MongoDB Atlas:

1. Go to Atlas UI → Database → Search
2. Click "Create Search Index"
3. Select JSON Editor
4. Paste configuration:

```json
{
  "name": "material_vector_index",
  "type": "vectorSearch",
  "definition": {
    "fields": [
      {
        "type": "vector",
        "path": "embedding",
        "numDimensions": 1536,
        "similarity": "cosine"
      },
      {
        "type": "filter",
        "path": "customerId"
      },
      {
        "type": "filter",
        "path": "salesOrg"
      }
    ]
  }
}
```

5. Click "Create"

**Note**: If using self-hosted MongoDB, install Atlas Search or vector search will be unavailable (keyword search still works).

---

## 5. Start Workers

Workers consume async messages from RabbitMQ.

### Start High Priority Queue Worker

```bash
docker-compose exec php bin/console messenger:consume async_priority_high -vv

# Processes: SyncMaterialPriceCommand, AcquireSyncLockCommand, SyncUserMaterialsCommand
```

### Start Normal Priority Queue Worker

```bash
docker-compose exec php bin/console messenger:consume async_priority_normal -vv

# Processes: GenerateEmbeddingCommand
```

### Start Low Priority Queue Worker (Events)

```bash
docker-compose exec php bin/console messenger:consume async_priority_low -vv

# Processes: MaterialSyncedEvent, PriceFetchedEvent
```

**Production Tip**: Use Supervisor to manage workers (see `docker/supervisor/supervisor.conf`).

### Verify Workers

```bash
# Check RabbitMQ management UI
open http://localhost:15672
# Login: guest / guest
# Navigate to Queues tab
# Should see: priority_high, priority_normal, priority_low queues
```

---

## 6. Test Material Sync

### Trigger Manual Sync

```bash
# CLI command to sync materials for customer
docker-compose exec php bin/console app:sync-materials 185 0000210839

# Parameters:
# - 185: Sales organization
# - 0000210839: Customer ID

# Expected output:
# [OK] Starting material sync for customer 0000210839, sales org 185
# [INFO] Acquiring sync lock...
# [INFO] Lock acquired
# [INFO] Fetching materials from SAP...
# [INFO] Found 1,250 materials
# [INFO] Processing materials...
# [INFO] Processed 100/1250 materials (8%)
# [INFO] Processed 200/1250 materials (16%)
# ...
# [INFO] Sync completed in 45 seconds
# [OK] Materials synced successfully
```

### Verify Data in MySQL

```bash
docker-compose exec mysql mysql -u root -ppassword myorders -e "
SELECT material_number, posnr, price_amount, price_currency 
FROM customer_materials 
WHERE customer_id = '0000210839' 
LIMIT 5;
"

# Should show materials with POSNR values
```

### Verify Data in MongoDB

```bash
docker-compose exec mongodb mongosh myorders --eval "
db.material_view.find({ customerId: '0000210839' }).limit(5).pretty()
"

# Should show MaterialView documents with POSNR, prices
```

### Check Sync Progress

```bash
docker-compose exec mysql mysql -u root -ppassword myorders -e "
SELECT * FROM sync_progress 
WHERE customer_id = '0000210839' AND sales_org = '185';
"

# Should show completed sync with total_materials = processed_materials
```

---

## 7. Test Catalog Page

### Access Catalog

```bash
# In browser or curl
curl http://localhost/catalog/185/0000210839

# Or open: http://localhost/catalog/185/0000210839
```

### Expected Output (HTML)

- List of materials with prices
- Progress bar (if sync in progress)
- Search box
- Pagination controls

### Test Search

```bash
# Keyword search
curl "http://localhost/catalog/185/0000210839?search=HEMOSIL"

# Should return filtered list matching "HEMOSIL" in material number or description
```

---

## 8. Test Semantic Search

### Generate Embeddings

```bash
# CLI command to regenerate all embeddings (one-time operation)
docker-compose exec php bin/console app:regenerate-embeddings 0000210839 185

# Expected output:
# [INFO] Fetching materials for customer 0000210839, sales org 185
# [INFO] Found 1,250 materials
# [INFO] Generating embeddings...
# [INFO] Generated 100/1250 embeddings (8%)
# [INFO] Generated 200/1250 embeddings (16%)
# ...
# [OK] Embeddings generated successfully
```

### Test Semantic Search

```bash
# API endpoint for semantic search
curl -X POST http://localhost/api/search/semantic \
  -H "Content-Type: application/json" \
  -d '{
    "customerId": "0000210839",
    "salesOrg": "185",
    "query": "blood coagulation test kits",
    "limit": 10
  }'

# Expected response:
# [
#   {
#     "materialNumber": "00020006800",
#     "description": "HEMOSIL QC Normal Level 2",
#     "priceAmount": 125.50,
#     "priceCurrency": "EUR",
#     "relevanceScore": 0.87
#   },
#   ...
# ]
```

---

## 9. Monitoring & Debugging

### Check Worker Status

```bash
# View running workers
docker-compose exec php bin/console messenger:stats

# Expected output:
# Queue: async_priority_high - 2 messages waiting
# Queue: async_priority_normal - 15 messages waiting
# Queue: async_priority_low - 50 messages waiting
```

### View Logs

```bash
# Application logs
docker-compose logs -f php

# Nginx access logs
docker-compose logs -f nginx

# MySQL logs
docker-compose logs -f mysql

# MongoDB logs
docker-compose logs -f mongodb

# RabbitMQ logs
docker-compose logs -f rabbitmq
```

### Check Failed Messages

```bash
# View failed messages
docker-compose exec php bin/console messenger:failed:show

# Retry failed message
docker-compose exec php bin/console messenger:failed:retry <message-id>
```

### Monitor RabbitMQ

```bash
# Management UI
open http://localhost:15672

# CLI stats
docker-compose exec rabbitmq rabbitmqctl list_queues name messages_ready messages_unacknowledged
```

### Monitor Redis Locks

```bash
# Access Redis CLI
docker-compose exec redis redis-cli

# Check active locks
KEYS sync_lock_*

# Check lock TTL
TTL sync_lock_185_0000210839

# Manually clear lock (emergency only)
DEL sync_lock_185_0000210839
```

---

## 10. Running Tests

### Unit Tests

```bash
# Run all unit tests
docker-compose exec php bin/phpunit tests/Unit/

# Run specific test class
docker-compose exec php bin/phpunit tests/Unit/Domain/ValueObject/PosnrTest.php

# Expected output:
# PHPUnit 11.0
# ..................................................... 49 / 49 (100%)
# Time: 2.5 seconds, Memory: 18.00 MB
# OK (49 tests, 120 assertions)
```

### Integration Tests

```bash
# Run integration tests (requires real database/MongoDB/Redis)
docker-compose exec php bin/phpunit tests/Integration/

# Expected output:
# PHPUnit 11.0
# ........................... 27 / 27 (100%)
# Time: 15 seconds, Memory: 22.00 MB
# OK (27 tests, 75 assertions)
```

### Functional Tests

```bash
# Run functional tests (HTTP endpoints)
docker-compose exec php bin/phpunit tests/Functional/

# Expected output:
# PHPUnit 11.0
# ............... 15 / 15 (100%)
# Time: 8 seconds, Memory: 20.00 MB
# OK (15 tests, 45 assertions)
```

### E2E Tests

```bash
# Run end-to-end tests (full workflows)
docker-compose exec php bin/phpunit tests/E2E/

# Expected output:
# PHPUnit 11.0
# ........ 8 / 8 (100%)
# Time: 45 seconds, Memory: 25.00 MB
# OK (8 tests, 32 assertions)
```

### Run All Tests

```bash
docker-compose exec php bin/phpunit

# Or with coverage
docker-compose exec php bin/phpunit --coverage-html coverage/
```

---

## 11. Makefile Commands

### Create Makefile

Create `Makefile` in project root:

```makefile
.PHONY: help install start stop test sync-materials clear-cache db-migrate regenerate-embeddings

help:
	@echo "Available commands:"
	@echo "  make install             - Install dependencies"
	@echo "  make start               - Start Docker services"
	@echo "  make stop                - Stop Docker services"
	@echo "  make test                - Run all tests"
	@echo "  make sync-materials      - Sync materials (requires SALES_ORG and CUSTOMER_ID)"
	@echo "  make clear-cache         - Clear application cache"
	@echo "  make db-migrate          - Run database migrations"
	@echo "  make regenerate-embeddings - Regenerate OpenAI embeddings"

install:
	composer install

start:
	docker-compose up -d
	@echo "Services started. Access: http://localhost"

stop:
	docker-compose down

test:
	docker-compose exec php bin/phpunit

test-unit:
	docker-compose exec php bin/phpunit tests/Unit/

test-integration:
	docker-compose exec php bin/phpunit tests/Integration/

test-functional:
	docker-compose exec php bin/phpunit tests/Functional/

test-e2e:
	docker-compose exec php bin/phpunit tests/E2E/

sync-materials:
	@if [ -z "$(SALES_ORG)" ] || [ -z "$(CUSTOMER_ID)" ]; then \
		echo "Usage: make sync-materials SALES_ORG=185 CUSTOMER_ID=0000210839"; \
		exit 1; \
	fi
	docker-compose exec php bin/console app:sync-materials $(SALES_ORG) $(CUSTOMER_ID)

clear-cache:
	docker-compose exec php bin/console cache:clear
	docker-compose exec php bin/console cache:warmup

db-migrate:
	docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction

regenerate-embeddings:
	@if [ -z "$(SALES_ORG)" ] || [ -z "$(CUSTOMER_ID)" ]; then \
		echo "Usage: make regenerate-embeddings SALES_ORG=185 CUSTOMER_ID=0000210839"; \
		exit 1; \
	fi
	docker-compose exec php bin/console app:regenerate-embeddings $(CUSTOMER_ID) $(SALES_ORG)

logs:
	docker-compose logs -f php

workers:
	docker-compose exec php bin/console messenger:stats
```

### Usage

```bash
# View available commands
make help

# Start services
make start

# Run all tests
make test

# Sync materials
make sync-materials SALES_ORG=185 CUSTOMER_ID=0000210839

# Regenerate embeddings
make regenerate-embeddings SALES_ORG=185 CUSTOMER_ID=0000210839

# View logs
make logs
```

---

## 12. Troubleshooting

### Issue: Sync Fails with "Lock Held"

**Symptom**: `Sync already in progress, skipping`

**Cause**: Previous sync crashed without releasing lock

**Fix**:
```bash
# Clear lock manually
docker-compose exec redis redis-cli DEL sync_lock_185_0000210839

# Or CLI command
docker-compose exec php bin/console app:clear-sync-lock 185 0000210839
```

---

### Issue: No Prices Returned from SAP

**Symptom**: Materials synced but `priceAmount` is NULL

**Cause**: POSNR not provided to SAP price call

**Debug**:
```bash
# Check if POSNR stored
docker-compose exec mysql mysql -u root -ppassword myorders -e "
SELECT material_number, posnr FROM customer_materials LIMIT 5;
"

# Should show POSNR values (e.g., "000010", "000020")
```

**Fix**: Verify SapApiClient passes POSNR in getMaterialPrice() call

---

### Issue: Semantic Search Returns No Results

**Symptom**: Keyword search works, semantic search returns empty array

**Cause**: Embeddings not generated or vector index missing

**Debug**:
```bash
# Check if embeddings exist
docker-compose exec mongodb mongosh myorders --eval "
db.material_view.find({ embedding: { \$exists: true } }).count()
"

# Should be > 0
```

**Fix**: Run `make regenerate-embeddings` and verify Atlas vector index exists

---

### Issue: Workers Not Processing Messages

**Symptom**: Messages in queue but not processed

**Debug**:
```bash
# Check worker processes
docker-compose exec php ps aux | grep messenger

# Check RabbitMQ queues
docker-compose exec rabbitmq rabbitmqctl list_queues
```

**Fix**: Restart workers or verify supervisor configuration running

---

## 13. Next Steps

### Production Deployment

- Configure environment variables in production
- Set up Supervisor for worker management
- Configure MongoDB Atlas cluster for vector search
- Set up monitoring (Prometheus, Grafana)
- Configure log aggregation (ELK stack)
- Set up alerts for failed syncs, worker crashes

### Feature Enhancements

- Scheduled syncs (cron job)
- Batch price updates
- Price history tracking
- Material image uploads
- Export catalog to PDF/Excel

---

## Summary

You should now have:
- ✅ All services running (MySQL, MongoDB, RabbitMQ, Redis)
- ✅ Database schema created with POSNR support
- ✅ MongoDB indexes and vector search configured
- ✅ Workers consuming messages
- ✅ Material sync tested
- ✅ Catalog displaying materials with prices
- ✅ Semantic search functional
- ✅ Tests passing

**Need help?** Check logs, RabbitMQ management UI, or run diagnostic commands above.
