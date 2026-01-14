# ProcessWire Data Migrator

Ein ProcessWire-Modul zur Migration externer Daten in die ProcessWire-Struktur.

## Features

- **Multi-Format Support**: SQL, CSV, JSON, XML
- **Auto-Erkennung**: Automatische Erkennung von Datentypen und ProcessWire-Feldtypen
- **FK-Handling**: Foreign Key Beziehungen mit automatischer Sortierung
- **Dry-Run**: Vorschau vor dem Import
- **Rollback**: Vollständiges Rückgängigmachen des Imports
- **Template-Generierung**: Automatische Erstellung von PHP-Template-Dateien

## Systemanforderungen

- ProcessWire 3.0.0+
- PHP 7.4+

## Installation

1. Modul-Verzeichnis nach `/site/modules/` hochladen
2. Setup > Modules > Refresh
3. "Data Migrator" installieren
4. Verfügbar unter **Setup > Data Migrator**

## Verwendung

### 1. Datei hochladen
- Unterstützte Formate: `.sql`, `.csv`, `.json`, `.xml`
- Sample Size und Max Rows konfigurieren

### 2. Analyse prüfen
- Tabellen und Felder auswählen
- Feldtypen bei Bedarf überschreiben
- FK-Beziehungen konfigurieren

### 3. Import
- **Dry Run**: Vorschau der Änderungen
- **Import**: Tatsächlicher Import
- **Rollback**: Bei Bedarf rückgängig machen

## Erkannte Feldtypen

| Datentyp | ProcessWire-Feldtyp |
|----------|---------------------|
| E-Mail | FieldtypeEmail |
| URL | FieldtypeURL |
| Datum | FieldtypeDatetime |
| Boolean | FieldtypeCheckbox |
| Enum/Set | FieldtypeOptions |
| Integer | FieldtypeInteger |
| Float | FieldtypeFloat |
| Text | FieldtypeText |
| Langtext | FieldtypeTextarea |

## Dateistruktur

```
ProcessDataMigrator/
├── ProcessDataMigrator.module.php
├── ProcessDataMigrator.info.json
├── classes/
│   ├── parsers/
│   │   ├── AbstractParser.php
│   │   ├── SqlParser.php
│   │   ├── CsvParser.php
│   │   ├── JsonParser.php
│   │   └── XmlParser.php
│   ├── DataAnalyzer.php
│   ├── TypeDetector.php
│   ├── MappingEngine.php
│   ├── TemplateCreator.php
│   ├── ImportProcessor.php
│   └── ImportRollback.php
└── assets/
    └── css/
        └── data-migrator.css
```

## Permission

Das Modul erstellt die Permission `data-migrate`.

## Version

**1.1.0** - Multi-Format Support (SQL, CSV, JSON, XML)
