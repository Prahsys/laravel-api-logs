# Laravel API Logs Package - Refactor Notes

## Major Refactor Summary (Session Date: 2025-07-10)

### Overview
This was a comprehensive refactor treating this as the "first release" with breaking changes to improve naming conventions and clarify the package's purpose.

### Key Changes Made

#### 1. Naming Convention Changes
- **IdempotentRequest** → **ApiLogItem** 
- **HasIdempotentRequests** → **HasApiLogItems**
- **IdempotencyLogMiddleware** → **ApiLogMiddleware**
- **idempotent_requests** table → **api_log_items** table
- **idempotent_request_models** table → **api_log_item_models** table

#### 2. Configuration Updates
- Added `ensure_header` boolean to correlation config
- Moved pruning configuration from `correlation` to `database` section
- Updated default TTL from 30 days to 365 days
- Updated model references in config

#### 3. Middleware Logic Enhancement
- Added logic to check `ensure_header` config
- If `ensure_header` is false and header missing, skip logging entirely
- If `ensure_header` is true (default), auto-generate UUID when header missing

#### 4. Laravel Prunable Implementation
- Added `MassPrunable` trait to ApiLogItem model
- Implemented `prunable()` method with configurable TTL
- Default retention: 365 days (configurable via `API_LOGS_TTL_HOURS`)

#### 5. Design Philosophy Clarification
- **ApiLogItem** is designed as a lightweight reference, not full data store
- Separates database retention (long-term audit trail) from log file retention (shorter-term debugging)
- Enables flexible extension for custom data storage needs
- Heavy request/response data stays in log channels with their own rotation policies

### Key Learnings

#### 1. Package Design Principles
- **Separation of concerns**: Database stores lightweight references, log channels handle heavy data
- **Flexibility over opinionation**: Users can extend models for custom storage needs
- **Compliance-ready**: Long database retention supports audit requirements
- **Performance-focused**: Lean database design prevents bloat

#### 2. Laravel Integration Patterns
- Used `MassPrunable` for automatic cleanup
- Leveraged existing log channel system for data routing
- Processor pattern integrates cleanly with Monolog
- Event-driven architecture supports async processing

#### 3. Configuration Strategy
- Environment variables for common settings (`API_LOGS_TTL_HOURS`)
- Granular configuration for advanced use cases
- Sensible defaults that work out-of-the-box
- Clear documentation of extensibility options

#### 4. Naming Evolution
- Moved away from "idempotency" terminology (confusing, implies duplicate prevention)
- Adopted "correlation" and "API logging" terminology (clearer purpose)
- "ApiLogItem" clearly indicates lightweight reference nature

### File Structure After Refactor

```
src/
├── Models/
│   ├── ApiLogItem.php (was IdempotentRequest.php)
│   └── HasApiLogItems.php (was HasIdempotentRequests.php)
├── Http/Middleware/
│   └── ApiLogMiddleware.php (was IdempotencyLogMiddleware.php)
├── ...

database/migrations/
├── create_api_log_items_table.php (was create_idempotent_requests_table.php)
└── create_api_log_item_models_table.php (was create_idempotent_request_models_table.php)
```

### ✅ Refactor Complete! (All Tests Passing)
- [x] Update all remaining class references throughout codebase
- [x] Update migration files to match new naming  
- [x] Run tests and fix any failures from refactor
- [x] Update service provider references
- [x] Update event and listener references
- [x] Update all test files

**Final Test Results:** 38 tests passing (121 assertions) - 100% success rate!

### Future Enhancements (Next Session)
- [ ] Create Guzzle/PSR middleware for outbound API calls
- [ ] Add configuration for outbound API logging  
- [ ] Document Guzzle middleware integration examples
- [ ] Consider adding more specific event types for different API call types

### Extension Examples Added
- Custom model extension with JSON columns
- Alternative storage strategies (external correlation, hybrid storage, event sourcing)
- Database vs log file retention strategy documentation

### Configuration Examples
```php
// New correlation config
'correlation' => [
    'header_name' => 'Idempotency-Key',
    'ensure_header' => true, // NEW: auto-generate or skip
],

// New database config with pruning
'database' => [
    'connection' => env('DB_CONNECTION', 'mysql'),
    'pruning' => [
        'ttl_hours' => env('API_LOGS_TTL_HOURS', 24 * 365), // 365 days default
    ],
],

// Updated model config
'models' => [
    'api_log_item' => \Prahsys\ApiLogs\Models\ApiLogItem::class,
],
```

### Key Documentation Additions
- Design philosophy section explaining lightweight approach
- Database pruning and log management section
- Extension examples for custom data storage
- Best practices for retention policies
- Performance considerations with lightweight design

### Environment Variables
```env
# NEW: Retention configuration
API_LOGS_TTL_HOURS=8760  # 365 days

# EXISTING: Basic configuration
API_LOGS_ENABLED=true
API_LOGS_RAW_CHANNEL=api_logs_raw
API_LOGS_REDACTED_CHANNEL=api_logs_redacted
```