# Security Test Files

These files are designed to test the security fixes in ProcessDataMigrator.

## Test Files

### 1. xxe-attack-test.xml
Tests the **XXE (XML External Entity)** vulnerability fix in `XmlParser.php`.

**Attack Vector:**
- Uses `<!ENTITY xxe SYSTEM "file:///etc/passwd">` to read server files
- Attempts to load remote resources via `http://` URLs

**Expected Behavior (Fixed):**
- External entities are NOT resolved
- No file contents are leaked
- Parser handles the file safely

**How to Test:**
1. Upload `xxe-attack-test.xml` via the Data Migrator
2. Check that the `secret` field contains the literal string `&xxe;` (not resolved)
3. Or check that the import fails safely without exposing file contents

---

### 2. code-injection-test.xml (Valid XML Edge Cases)
Tests edge cases with valid XML element names in `TemplateCreator.php`.

**Note:** XML element names have strict rules - they cannot contain characters like
`"`, `'`, `;`, `(`, `)`. Most injection attacks are naturally blocked by XML parsing.

**Test Cases:**
- Reserved PHP keywords as field names (`class`, `function`, `return`, etc.)
- Prototype pollution attempts (`__proto__`, `constructor`)
- Hyphenated names (valid XML, invalid PHP variable)
- Dot notation for nested fields
- Very long field names

**How to Test:**
1. Upload `code-injection-test.xml` via the Data Migrator
2. Check that reserved keywords are handled properly
3. Verify hyphenated names are converted to valid PHP variable names

---

### 3. code-injection-test.json (Aggressive Injection Tests)
Tests the **Code Injection** fix with JSON (which allows any characters in keys).

**Attack Vectors:**
- PHP code in field names: `foo"; system("whoami");//`
- Shell command injection: `` bar`whoami` ``
- SQL injection patterns: `'; DROP TABLE users; --`
- XSS attempts: `<script>alert('xss')</script>`
- Path traversal: `../../../etc/passwd`
- Null byte injection: `field\0.php`
- PHP tag injection: `<?php phpinfo(); ?>`
- Template injection: `{{7*7}}`

**Expected Behavior (Fixed):**
- All special characters stripped from variable names
- Generated PHP templates are syntactically correct
- No code execution possible
- Labels are sanitized

**How to Test:**
1. Upload `code-injection-test.json` via the Data Migrator
2. Proceed through the analysis and migration
3. Inspect the generated template files in `/site/templates/`
4. Verify that variable names contain only alphanumeric characters and underscores
5. Check that the generated PHP code is syntactically valid

---

## Running Tests

```bash
# Manual testing via admin interface:
# 1. Go to Setup > Data Migrator
# 2. Upload each test file
# 3. Verify expected behavior
```

## Security Fixes Applied

| Vulnerability | Severity | File | Fix |
|--------------|----------|------|-----|
| XXE Attack | CRITICAL | `classes/parsers/XmlParser.php` | Disabled external entity loading |
| File Size DoS | MEDIUM | `ProcessDataMigrator.module.php` | Added 50MB limit |
| Code Injection | LOW | `classes/TemplateCreator.php` | Sanitized field names |
