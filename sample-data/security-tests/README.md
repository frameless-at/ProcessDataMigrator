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

### 2. code-injection-test.xml
Tests the **Code Injection** fix in `TemplateCreator.php`.

**Attack Vectors:**
- PHP code in field names: `foo"; system("whoami");//`
- Shell commands: `bar\`whoami\``
- SQL injection patterns: `id; DROP TABLE users;--`
- XSS in labels: `<script>alert(1)</script>`
- Path traversal: `../../etc/passwd`
- Null byte injection: `field%00.php`

**Expected Behavior (Fixed):**
- All special characters stripped from variable names
- Generated PHP templates are syntactically correct
- No code execution possible
- Labels are sanitized

**How to Test:**
1. Upload `code-injection-test.xml` via the Data Migrator
2. Proceed through the analysis and migration
3. Inspect the generated template files in `/site/templates/`
4. Verify that variable names are sanitized (e.g., `$fooSystemWhoami` becomes `$foosystemwhoami` or similar safe name)
5. Check that the generated PHP code is syntactically valid

---

## Running Tests

```bash
# Manual testing via admin interface:
# 1. Go to Setup > Data Migrator
# 2. Upload each test file
# 3. Verify expected behavior

# Automated testing (if PHPUnit is available):
# cd /path/to/processwire
# vendor/bin/phpunit tests/security/
```

## Security Fixes Applied

| Vulnerability | Severity | File | Fix |
|--------------|----------|------|-----|
| XXE Attack | CRITICAL | `classes/parsers/XmlParser.php` | Disabled external entity loading |
| File Size DoS | MEDIUM | `ProcessDataMigrator.module.php` | Added 50MB limit |
| Code Injection | LOW | `classes/TemplateCreator.php` | Sanitized field names |
