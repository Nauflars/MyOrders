# SAP Integration - Implementation Summary

## Overview

This document describes the SAP ERP integration implementation for synchronizing customer and material data asynchronously.

## Architecture

### Components

1. **SapApiClient** (`Infrastructure/ExternalApi/SapApiClient.php`)
   - HTTP client for SAP REST API
   - Base URL: `https://erpqas.werfen.com/zsapui5_json`
   - Authentication: Basic Auth (ZWEBSERVICE / 4YVj745z)
   - Three main endpoints:
     - `ZSDO_EBU_ORDERS_ACCESS` - Customer data
     - `ZSDO_EBU_LOAD_MATERIALS` - Material list
     - `ZSDO_EBU_SHOW_MATERIAL_PRICE` - Material pricing

2. **Domain Entities**
   - `Customer` - SAP customer data (AG - Sold-to Party)
   - `Material` - Clean material master data
   - `CustomerMaterial` - Customer-specific material pricing and availability

3. **Async Commands** (CQRS pattern)
   - `SyncCustomerFromSapCommand` - Trigger customer sync
   - `SyncMaterialsFromSapCommand` - Trigger materials sync
   - `SyncMaterialPriceCommand` - Trigger price sync for one material

4. **Command Handlers**
   - `SyncCustomerFromSapHandler` - Fetches customer, updates DB, dispatches materials sync
   - `SyncMaterialsFromSapHandler` - Fetches all materials, dispatches price syncs
   - `SyncMaterialPriceHandler` - Fetches and updates customer-specific price

5. **Repositories**
   - `CustomerRepositoryInterface` / `DoctrineCustomerRepository`
   - `MaterialRepositoryInterface` / `DoctrineMaterialRepository`
   - `CustomerMaterialRepositoryInterface` / `DoctrineCustomerMaterialRepository`

## Data Flow

```
User Login (salesOrg: "101", customerId: "0000185851")
    |
    v
POST /api/sap/sync
    |
    v
SyncCustomerFromSapCommand dispatched to RabbitMQ
    |
    v
SyncCustomerFromSapHandler processes:
    - Calls SAP API: ZSDO_EBU_ORDERS_ACCESS
    - Creates/updates Customer entity
    - Saves to MySQL
    - Dispatches SyncMaterialsFromSapCommand
    |
    v
SyncMaterialsFromSapHandler processes:
    - Calls SAP API: ZSDO_EBU_LOAD_MATERIALS
    - Creates/updates Material entities (bulk)
    - For each material: dispatches SyncMaterialPriceCommand
    |
    v
SyncMaterialPriceHandler processes (parallel for each material):
    - Calls SAP API: ZSDO_EBU_SHOW_MATERIAL_PRICE
    - Creates/updates CustomerMaterial entity with price
    - Links Customer <-> Material with pricing data
```

## Database Schema

### customers table
- Primary Key: `id` (auto-increment)
- SAP Identifier: `sap_customer_id` + `sales_org` (composite unique index)
- Fields: name, address, payment terms, currency, incoterms, VAT, etc.
- Relationships: OneToMany with `customer_materials`

### materials table
- Primary Key: `id` (auto-increment)
- SAP Identifier: `sap_material_number` (unique)
- Fields: description, type, group, weight, volume, units, is_active
- Relationships: OneToMany with `customer_materials`

### customer_materials table (junction/pricing table)
- Primary Key: `id` (auto-increment)
- Foreign Keys: `customer_id`, `material_id` (composite unique)
- Fields: price, currency, weight, volume, availability, min_order_qty
- Includes: `sap_price_data` (JSON) for full SAP response storage

## Usage

### Via API (Production)

Trigger sync on user login:

```bash
curl -X POST http://localhost/api/sap/sync \
  -H "Content-Type: application/json" \
  -d '{"salesOrg": "101", "customerId": "0000185851"}'
```

Response:
```json
{
  "status": "sync_started",
  "message": "SAP synchronization has been queued",
  "salesOrg": "101",
  "customerId": "0000185851"
}
```

### Via CLI (Testing/Manual)

```bash
docker compose exec php bin/console app:sap:sync 101 0000185851
```

### Test SAP API Directly

```bash
docker compose exec php php test-sap-api.php
```

This creates response files:
- `sap-customer-response.json`
- `sap-materials-response.json`
- `sap-price-response.json`

## Async Processing

### Start Messenger Workers

```bash
# Start worker to consume messages
docker compose exec php bin/console messenger:consume async -vv

# Or with multiple workers
docker compose exec php bin/console messenger:consume async -vv --limit=10 &
docker compose exec php bin/console messenger:consume async -vv --limit=10 &
```

### Monitor Messenger

```bash
# Check failed messages
docker compose exec php bin/console messenger:failed:show

# Retry failed messages
docker compose exec php bin/console messenger:failed:retry
```

## Configuration Files

### Messenger Routing (`config/packages/messenger.yaml`)
```yaml
routing:
    'App\Application\Command\SyncCustomerFromSapCommand': async
    'App\Application\Command\SyncMaterialsFromSapCommand': async
    'App\Application\Command\SyncMaterialPriceCommand': async
```

### HTTP Client (`config/packages/http_client.yaml`)
```yaml
framework:
    http_client:
        scoped_clients:
            sap.client:
                base_uri: 'https://erpqas.werfen.com/zsapui5_json'
                timeout: 30
                verify_peer: false  # For QAS environment
```

### Services (`config/services_sap.yaml`)
Repository bindings and SapApiClient configuration.

## Database Migrations

Generate migration for new entities:

```bash
docker compose exec php bin/console doctrine:migrations:diff
```

Execute migrations:

```bash
docker compose exec php bin/console doctrine:migrations:migrate
```

## Logging

All sync operations are logged with context:
- `app.INFO`: Sync start/completion
- `app.DEBUG`: Individual operations (create/update entities)
- `app.ERROR`: Failures with stack traces

View logs:
```bash
docker compose logs -f php
```

## Error Handling

- **Customer sync fails**: Entire sync stops (critical error)
- **Materials sync fails**: Entire sync stops
- **Individual price sync fails**: Logged but doesn't block other prices (graceful degradation)

Retry strategy (configured in messenger.yaml):
- Max retries: 3
- Multiplier: 2 (exponential backoff)
- Failed messages go to `failed` transport

## Performance Considerations

For a customer with 100 materials:
- 1 customer API call
- 1 materials API call (returns all materials)
- 100 price API calls (parallelized via async queue)

Estimated time:
- Sync dispatch: < 100ms
- Customer + Materials: ~2-5 seconds
- Prices (100 materials): ~30-60 seconds (depends on worker count)

To improve performance:
- Increase messenger workers
- Batch price API calls if SAP supports it
- Cache material master data (change less frequently)

## Testing

### Test Script
```bash
docker compose exec php php test-sap-api.php
```

### Unit Tests (TODO)
- Test entity updates from SAP data
- Test repository methods
- Mock SapApiClient for handler tests

### Integration Tests (TODO)
- Test full sync flow with test data
- Test error handling and retries
- Test concurrent syncs for different customers

## Next Steps

1. ✅ Implement core sync functionality
2. ⏳ Run database migrations
3. ⏳ Test with real SAP data
4. ⏳ Add unit tests
5. ⏳ Add integration tests
6. ⏳ Implement sync status tracking
7. ⏳ Add metrics and monitoring
8. ⏳ Optimize batch operations
9. ⏳ Add caching layer for materials
10. ⏳ Implement incremental sync (delta updates)

## Troubleshooting

### Sync not starting
- Check RabbitMQ is running: `docker compose ps rabbitmq`
- Check messenger workers: `docker compose exec php bin/console messenger:stats`

### SAP API errors
- Verify credentials in SapApiClient
- Check network connectivity to erpqas.werfen.com
- Review SAP logs (if accessible)

### Database errors
- Check migrations ran: `bin/console doctrine:migrations:status`
- Verify MySQL is running: `docker compose ps mysql`
- Check entity relationships and cascade settings

### Performance issues
- Increase messenger workers
- Check RabbitMQ memory/disk usage
- Monitor MySQL slow queries
- Consider SAP API rate limits
