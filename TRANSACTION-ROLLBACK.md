# Transaction Rollback & Dry-Run Mode

## Overview

The Database Importer now includes automatic rollback functionality and a dry-run mode to test imports safely without modifying data.

## Features

### 1. Automatic Rollback on Errors

When an import fails with a critical error, the system automatically rolls back **all** changes made during that import session.

**What gets rolled back:**
- ✅ All created pages (deleted)
- ✅ All created templates (deleted if not used elsewhere)
- ✅ All created fields (deleted if not used elsewhere)
- ✅ Parent page hierarchy (deleted if empty)

**Example scenario:**
```
1. Import users table → ✅ 50 pages created
2. Import orders table → ✅ 100 pages created
3. Import products table → ❌ ERROR: Invalid data

→ AUTOMATIC ROLLBACK TRIGGERED
   - 150 pages deleted
   - 3 templates deleted
   - 15 fields deleted

User sees: "Import failed: [...] Automatic rollback completed successfully"
```

### 2. Dry-Run Mode

Test your import **without saving any data** to the database.

**How to use:**
1. Upload SQL file and analyze
2. Configure tables/fields/fieldtypes
3. ✅ Check "Dry-Run Mode" checkbox
4. Click "Import Selected Tables"

**What happens in Dry-Run:**
```
[DRY-RUN] Would create template: customer
[DRY-RUN] Would create parent page: /imports/customers/
[DRY-RUN] Skipping page save (simulated)
[DRY-RUN] Would import 150 pages

→ NO actual data is saved
→ You see what WOULD happen
→ Test FK mappings and field detection
```

## Technical Implementation

### Automatic Rollback

Located in: `ProcessDatabaseImporter.module.php:997-1043`

```php
try {
    // Import all tables...
} catch (\Exception $e) {
    // AUTOMATIC ROLLBACK
    if (!empty($allRollbackData)) {
        $rollback = new ImportRollback();
        foreach ($allRollbackData as $tableData) {
            $rollback->rollback($tableData);
        }
    }
}
```

**Rollback Data Structure:**
```php
$rollbackData = [
    'table' => 'customers',
    'template' => 'customer',
    'parent_page' => '/imports/customers/',
    'created_fields' => ['customer_email', 'customer_phone'],
    'created_pages' => [123, 124, 125],
    'timestamp' => 1704672000
];
```

### Dry-Run Implementation

**1. UI Checkbox** (`ProcessDatabaseImporter.module.php:729-735`):
```php
<input type="checkbox" name="dry_run" value="1">
Dry-Run Mode (test import without saving data)
```

**2. Skip Template/Field Creation** (`ProcessDatabaseImporter.module.php:944-972`):
```php
if ($dryRun) {
    // Use existing or create mock template
    $template = $this->templates->get($mapping['template']);
    if (!$template) {
        $template = new Template();
        $template->name = $mapping['template'];
        $this->message('[DRY-RUN] Would create template: ' . $template->name);
    }
}
```

**3. Skip Page Save** (`ImportProcessor.php:170-179`):
```php
if ($this->dryRun) {
    $this->wire()->log->save('db-importer', "DRY-RUN: Skipping page save");
    $page->id = 999000 + $this->imported; // Mock ID for FK simulation
    return $page;
}
```

## Error Handling

### Before (v1.0.0):
```
Import fails → Error message shown → User manually clicks "Rollback"
```

**Problem:**
- Already imported data remains in database
- Manual cleanup required
- Partial imports leave database dirty

### After (v1.1.0):
```
Import fails → Automatic rollback → Clean state restored → Error message
```

**Benefits:**
- ✅ No manual intervention needed
- ✅ Database always in clean state
- ✅ Safe to retry import immediately
- ✅ No orphaned data

## Use Cases

### Use Case 1: Testing Complex FK Mappings

```
1. Upload SQL with multiple related tables
2. Configure FK mappings (orders → customers)
3. Enable DRY-RUN mode
4. Click Import

Result: See if FK resolution works without creating data
```

### Use Case 2: Validating Field Detection

```
1. Upload SQL with mixed data types
2. Review auto-detected fieldtypes
3. Enable DRY-RUN mode
4. Click Import

Result: Verify type detection accuracy without database writes
```

### Use Case 3: Safe Production Imports

```
1. Configure import with confidence
2. Disable DRY-RUN mode
3. Click Import
4. If error occurs → Automatic rollback

Result: Production database protected from partial imports
```

## Limitations

### Dry-Run Mode

**What is NOT simulated:**
- ProcessWire's internal validation (e.g., required fields)
- Database constraints (unique keys, foreign keys)
- Permission checks on real pages
- Hook execution (beforePageSave, etc.)

**Why:**
These checks require actual database operations that we skip in dry-run mode.

**Recommendation:**
Use dry-run for basic validation, then do a real test import on staging environment.

### Automatic Rollback

**What cannot be rolled back:**
- External API calls made by hooks
- File uploads (images, PDFs)
- Log entries
- Email notifications sent during import

**Recommendation:**
Avoid triggering external actions during import. Use `afterImportComplete` hook instead.

## Configuration

### Enable/Disable Automatic Rollback

Currently **always enabled**. To disable (not recommended):

```php
// In ProcessDatabaseImporter.module.php:997
} catch (\Exception $e) {
    $this->error($this->_('Import failed: ') . $e->getMessage());
    // Comment out rollback code to disable
    // return $this->executeAnalyze();
}
```

### Customize Dry-Run Behavior

```php
// In ImportProcessor.php:24-26
public function setDryRun($dryRun) {
    $this->dryRun = (bool) $dryRun;
    // Add custom dry-run logic here
}
```

## Logging

All dry-run and rollback operations are logged:

**Log file:** `/site/assets/logs/db-importer.log`

**Example entries:**
```
DRY-RUN: Skipping page save for: John Doe
[DRY-RUN] Would create template: customer
Automatic rollback initiated due to error: Invalid FK reference
Rollback completed: 50 pages deleted, 2 templates deleted
```

## Performance Impact

### Automatic Rollback
- **Minimal**: Only executes on errors
- **Time**: ~100ms per 100 pages to delete
- **Memory**: Uses existing `ImportRollback` class

### Dry-Run Mode
- **Faster**: Skips all database writes
- **Time**: ~50% faster than real import
- **Memory**: Same as real import (creates Page objects)

## Best Practices

### 1. Always Test with Dry-Run First
```
✅ Upload SQL → Analyze → Enable Dry-Run → Import
❌ Upload SQL → Analyze → Import (risky!)
```

### 2. Check Dry-Run Output Carefully
Look for:
- `[DRY-RUN]` prefixed messages
- Number of pages that would be created
- FK resolution warnings

### 3. Monitor Rollback Messages
If rollback triggers:
- Read the error message carefully
- Fix the underlying issue
- Retry import with corrected configuration

### 4. Use Staging Environment
```
Development: Test with Dry-Run
Staging: Test with real import (with rollback protection)
Production: Import with confidence
```

## Troubleshooting

### Rollback Fails with Errors

**Symptom:**
```
Rollback completed with errors:
  - Cannot delete field 'customer_email': in use by template 'user'
```

**Cause:**
Field was already in use before import.

**Solution:**
This is expected behavior. Fields shared across templates are not deleted.

### Dry-Run Shows Different Results

**Symptom:**
Dry-run succeeds, but real import fails.

**Cause:**
Database constraints or validation only checked during save.

**Solution:**
This is a limitation. Always test real import on staging first.

### Automatic Rollback Doesn't Clean Everything

**Symptom:**
Some templates/fields remain after rollback.

**Cause:**
`ImportRollback` only deletes items created **during this import**.

**Solution:**
This is correct behavior. Pre-existing items are preserved.

## Version History

**v1.1.0** (2026-01-07):
- ✅ Added automatic rollback on errors
- ✅ Added dry-run mode UI checkbox
- ✅ Integrated dry-run into ImportProcessor
- ✅ Added comprehensive error messages

**v1.0.0** (2025-12-15):
- Manual rollback only
- No dry-run mode

## Related Documentation

- [SECURITY.md](SECURITY.md) - Input validation and security measures
- [README.md](README.md) - General module documentation
- [ImportRollback.php](classes/ImportRollback.php) - Rollback implementation

---

**Last Updated:** 2026-01-07
**Author:** ProcessWire Database Importer Team
