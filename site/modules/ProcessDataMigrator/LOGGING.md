# Configurable Logging System

## Overview

The Database Importer includes a flexible logging system that allows you to control how much information is written to log files. This helps keep logs manageable while still providing detailed debugging information when needed.

## Log Levels

### ERROR (Level 1)
**Production recommended**
Only logs critical errors that prevent import from completing.

**Use when:**
- Production environment
- You only want to know about failures
- Log file size is critical

**Example output:**
```
[ERROR] FK ERROR: Table 'orders' not found in globalIdMapping
[ERROR] Failed to save page: Title field is required
```

### WARNING (Level 2)
**Balanced**
Logs errors and potential problems that might need attention.

**Use when:**
- Staging environment
- You want to catch issues before they become critical
- Troubleshooting data quality issues

**Example output:**
```
[ERROR] FK ERROR: Table 'orders' not found in globalIdMapping
[WARNING] FK NOT FOUND: SQL ID 123 not in customers mapping
[WARNING] Column count mismatch. Columns: 5, Values: 4
```

### INFO (Level 3) - DEFAULT
**Recommended for most users**
Logs errors, warnings, and important events like import start, memory checks.

**Use when:**
- Development environment
- Regular usage
- You want overview of what's happening without excessive detail

**Example output:**
```
[INFO] === IMPORT START ===
[INFO] Memory Check: File=45.2MB, MemLimit=256MB, Used=38MB, Available=218MB
[ERROR] FK ERROR: Table 'orders' not found in globalIdMapping
[WARNING] FK NOT FOUND: SQL ID 123 not in customers mapping
[INFO] Extracted columns from INSERT: id, name, email, phone
```

### DEBUG (Level 4)
**Troubleshooting**
Logs everything including detailed field processing, FK resolution steps, value conversions.

**Use when:**
- Debugging import issues
- Understanding exactly what's happening
- Developing/testing the module

**Example output:**
```
[INFO] === IMPORT START ===
[DEBUG] FK Mappings: {"orders":{"customer_id":"customers"}}
[DEBUG] Global ID Mapping tables: customers, products
[DEBUG]   - customers: 150 entries
[DEBUG]   - products: 45 entries
[DEBUG] Processing row with title: John Doe
[DEBUG] Row data columns: id, name, email, customer_id
[DEBUG] Mapping fields: name, email, customer
[DEBUG] Checking field: customer_id -> customer (FieldtypePage)
[DEBUG]     FK CHECK: customer_id=123 maps to table 'customers'
[DEBUG]     Available tables in globalIdMapping: customers, products
[DEBUG]     FK RESOLVED: customer_id=123 → customers Page #456
[DEBUG]   Converted value: 456
[DEBUG]   SET: customer = 456
[DEBUG] Checking field: name -> name (FieldtypeText)
[DEBUG]   Value from DB: 'John Doe'
[DEBUG]   Converted value: 'John Doe'
[DEBUG]   SET: name = 'John Doe'
```

## Configuration

### Via Module Settings (Recommended)

1. Go to **Setup → Modules → Database Importer**
2. Click **Configure**
3. Select desired **Log Level**:
   - ○ ERROR - Only critical errors
   - ○ WARNING - Errors and warnings
   - ◉ **INFO - Errors, warnings, and important info (recommended)**
   - ○ DEBUG - Everything including detailed debug info
4. Click **Save**

Changes apply immediately to all future imports.

### Log File Location

All logs are written to:
```
/site/assets/logs/db-importer.log
```

## Performance Impact

### Log File Size Comparison
For a typical import of 1000 pages:

| Level   | Log Size | Lines | Performance |
|---------|----------|-------|-------------|
| ERROR   | ~5 KB    | ~10   | No impact   |
| WARNING | ~15 KB   | ~50   | No impact   |
| INFO    | ~50 KB   | ~200  | Minimal     |
| DEBUG   | ~5 MB    | ~15K  | Slight      |

### Recommendations by Use Case

| Use Case              | Recommended Level | Why                                    |
|-----------------------|-------------------|----------------------------------------|
| Production            | ERROR or WARNING  | Minimal logs, only problems recorded   |
| Staging/Testing       | INFO              | Balance of visibility and size         |
| Development           | INFO or DEBUG     | Full visibility for troubleshooting    |
| Debugging FK issues   | DEBUG             | See detailed FK resolution steps       |
| Debugging field types | DEBUG             | See value conversions and type checks  |
| Large imports (10K+)  | INFO or WARNING   | Prevent huge log files                 |

## Log Format

All log entries follow this format:
```
YYYY-MM-DD HH:MM:SS [LEVEL] Message
```

Example:
```
2026-01-07 22:30:45 [INFO] === IMPORT START ===
2026-01-07 22:30:45 [DEBUG] FK Mappings: {"orders":{"customer_id":"customers"}}
2026-01-07 22:30:46 [ERROR] FK ERROR: Table 'orders' not found in globalIdMapping
```

## Programmatic Usage

If you need to use the logger in custom code:

```php
// Get configured logger instance
$logger = $this->modules->get('ProcessDatabaseImporter')->getLogger();

// Log messages at different levels
$logger->error('Critical error occurred');
$logger->warning('Potential problem detected');
$logger->info('Import started');
$logger->debug('Detailed processing info');

// Set custom log level
$logger->setLevel(Logger::DEBUG);

// Change log file name
$logger->setLogName('my-custom-import');
```

### Logger Constants

```php
Logger::ERROR   // 1
Logger::WARNING // 2
Logger::INFO    // 3
Logger::DEBUG   // 4
```

## Troubleshooting

### Logs not appearing

**Check level:** Ensure log level is high enough for the messages you expect.
```php
// If logger is set to ERROR, info() calls won't log anything
$logger->setLevel(Logger::ERROR);
$logger->info('This will NOT appear'); // Skipped
$logger->error('This WILL appear');    // Logged
```

### Log file too large

**Solution 1:** Lower log level in module settings
- Change from DEBUG → INFO (reduces ~99%)
- Change from INFO → WARNING (reduces ~70%)

**Solution 2:** Clear old logs
```bash
# In ProcessWire admin:
Setup → Logs → db-importer → Delete
```

**Solution 3:** Rotate logs automatically
```php
// Add to config.php
$config->logRotate = [
    'db-importer' => 7 // Keep 7 days of logs
];
```

### Missing debug info

**Problem:** DEBUG logs not showing even though level is DEBUG

**Solution:** Verify logger is actually passed to classes:
```php
// In executeImport():
$importProcessor = $this->wire(new ImportProcessor());
$importProcessor->setLogger($this->getLogger()); // REQUIRED!
```

## Version History

**v1.1.0** (2026-01-07):
- ✅ Added Logger class with 4 levels
- ✅ Module configuration for log level
- ✅ Updated ImportProcessor and SqlParser
- ✅ Default level: INFO

**v1.0.0** (2025-12-15):
- Basic logging with wire()->log->save()
- No level control
- All debug info always logged

---

**Last Updated:** 2026-01-07
**Version:** 1.1.0
**Default Level:** INFO (recommended)
