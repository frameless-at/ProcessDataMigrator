# ProcessWire Forum Post Draft

---

**Title:** Introducing Data Migrator – Import SQL/CSV/JSON/XML into ProcessWire

**Category:** Modules/Plugins

---

Hey everyone,

At [frameless](https://frameless.at) we frequently work with clients who already have a website but aren't happy with it and want us to rebuild it from scratch. Whenever possible, we use ProcessWire for new web projects – no surprise there, given the flexibility and clean API we all love.

For smaller sites, migrating content is usually straightforward – a bit of copy/paste and you're done. But for larger projects with hundreds or thousands of records across multiple database tables, this quickly becomes tedious and error-prone.

Over the years, we've written various import scripts and parsers to handle these migrations. We finally decided to clean them up and package everything into a proper module that we'd like to share with the community.

---

## Introducing: Data Migrator

**Data Migrator** is a Process module that imports external data (SQL dumps, CSV, JSON, XML) directly into ProcessWire's page structure – including automatic creation of templates, fields, and even PHP template files.

### Key Features

- **Multi-format support** – Import from `.sql`, `.csv`, `.json`, and `.xml` files
- **Automatic type detection** – Recognizes emails, URLs, dates, booleans, integers, etc. and maps them to appropriate ProcessWire fieldtypes
- **SQL schema parsing** – Extracts column types from `CREATE TABLE` statements for better field mapping
- **Foreign Key handling** – Detects FK relationships and sorts tables by dependency order
- **Dry Run mode** – Preview exactly what will be created before committing anything
- **Full Rollback** – Undo an entire migration with one click (removes all created pages, templates, and fields)
- **Template file generation** – Automatically creates ready-to-use `.php` template files in `/site/templates/`

### How it works

1. Upload your data file (SQL dump, CSV, JSON, or XML)
2. Review the analysis – the module shows detected tables, columns, suggested fieldtypes, and sample values
3. Fine-tune if needed – override fieldtypes via dropdown, configure FK relationships
4. Run a Dry Run to preview all changes
5. Execute the migration – templates, fields, parent pages, and data pages are created automatically
6. If something's wrong – hit Rollback to cleanly undo everything

### Screenshots

*(Screenshots coming soon)*

### Requirements

- ProcessWire 3.0.0+
- PHP 7.4+

### Links

- **GitHub:** [github.com/frameless-at/ProcessDataMigrator](https://github.com/frameless-at/ProcessDataMigrator)
- **Modules Directory:** *(submission pending)*

---

We've been using this internally for a while now and it has saved us a lot of time on migration projects. We hope it's useful for others facing similar challenges.

Feedback, bug reports, and feature requests are welcome – either here in this thread or on GitHub.

Cheers,
Mike
