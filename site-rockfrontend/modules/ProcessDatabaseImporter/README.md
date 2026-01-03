# ProcessWire Database Importer

Ein intelligentes ProcessWire-Modul zum Import von Datenbank-Dumps in die ProcessWire-Struktur.

## 🎯 Features (Phase 1 - Proof of Concept)

### ✅ Implementiert

- **SQL-Parser**: Import von MySQL/MariaDB SQL-Dumps
- **Auto-Erkennung**:
  - Automatische Erkennung von Datentypen
  - Intelligente Vorschläge für ProcessWire-Feldtypen
  - Erkennung von Primary Keys und Foreign Keys
  - Mustererkennung für E-Mail, URLs, Telefon, Datum, etc.
- **Datenanalyse**:
  - Statistische Auswertung der Daten
  - Erkennung von Spaltentypen und -mustern
  - Vorschläge für Title-Felder und Name-Felder
  - Automatische Template-Namen-Generierung
- **Admin-Interface**:
  - Upload-Formular für SQL-Dateien
  - Detaillierte Analyse-Ansicht
  - Übersichtliche Darstellung aller erkannten Tabellen und Felder

## 📋 Systemanforderungen

- ProcessWire 3.0.0 oder höher
- PHP 7.4 oder höher
- MySQL/MariaDB (für SQL-Import)

## 🚀 Installation

1. Laden Sie das Modul-Verzeichnis in `/site/modules/` hoch
2. Gehen Sie zu Setup > Modules > Refresh
3. Installieren Sie "Database Importer"
4. Das Modul ist nun unter Setup > Database Importer verfügbar

## 💡 Verwendung

### Schritt 1: SQL-Datei hochladen

1. Navigieren Sie zu **Setup > Database Importer**
2. Laden Sie eine SQL-Dump-Datei hoch (.sql)
3. Konfigurieren Sie optional:
   - **Sample Size**: Anzahl der Zeilen zur Analyse (Standard: 100)
   - **Maximum Rows**: Maximale Zeilenanzahl pro Tabelle (0 = alle)

### Schritt 2: Analyse betrachten

Nach dem Upload zeigt das Modul:

- **Zusammenfassung**: Anzahl Tabellen, Zeilen, Spalten
- **Pro Tabelle**:
  - Vorgeschlagener Template-Name
  - Vorgeschlagenes Title-Feld
  - Detaillierte Spalten-Analyse mit:
    - SQL-Typ
    - Erkannter Datentyp
    - Vorgeschlagener ProcessWire-Feldtyp
    - Konfidenz-Level der Erkennung
    - Beispielwerte

### Beispiel-Erkennung

Das Modul erkennt automatisch:

| Spaltenname | SQL-Typ | Erkannter Typ | ProcessWire-Feldtyp |
|-------------|---------|---------------|---------------------|
| email | varchar(255) | email | FieldtypeEmail |
| website | varchar(255) | url | FieldtypeURL |
| created_at | datetime | datetime | FieldtypeDatetime |
| is_verified | tinyint(1) | boolean | FieldtypeCheckbox |
| notes | text | text | FieldtypeTextarea |
| status | enum(...) | enum | FieldtypeOptions |

## 🔧 Technische Komponenten

### SqlParser
Parst MySQL/MariaDB SQL-Dumps und extrahiert:
- CREATE TABLE Statements (Struktur)
- INSERT Statements (Daten)
- Primary Keys
- Foreign Keys
- Spalten-Definitionen

### DataAnalyzer
Analysiert die geparsten Daten:
- Statistische Auswertung
- Nullwert-Analyse
- Eindeutigkeit-Prüfung
- Template-Vorschläge
- Title/Name-Feld-Erkennung

### TypeDetector
Intelligente Feldtyp-Erkennung:
- **Pattern-Matching**: Erkennt E-Mail, URL, Telefon, Datum, etc.
- **SQL-Typ-Mapping**: Konvertiert MySQL-Typen zu ProcessWire-Typen
- **Spaltenname-Analyse**: Nutzt Spaltennamen als Hinweise
- **Options-Erkennung**: Erkennt Felder mit begrenzten Werten

## 📊 Erkennungsmuster

### String-Patterns
- **E-Mail**: `/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/`
- **URL**: `/^https?:\/\/.+/`
- **Telefon (DE)**: `/^(\+49|0)[0-9\s\-\/()]{7,}$/`
- **HTML**: `/<[a-z][\s\S]*>/`
- **JSON**: `/^[\{\[].*[\}\]]$/`

### Datum-Formate
- ISO: `2024-01-03`
- Deutsch: `03.01.2024`
- US: `01/03/2024`
- DateTime: `2024-01-03 14:30:00`

### Spaltenname-Hinweise
- `*_id` → Foreign Key / FieldtypePage
- `is_*`, `has_*` → FieldtypeCheckbox
- `*email*` → FieldtypeEmail
- `*url*`, `*link*` → FieldtypeURL
- `description`, `body`, `content` → FieldtypeTextarea

## 🧪 Test-Daten

Eine Beispiel-SQL-Datei finden Sie unter:
```
/site/modules/ProcessDatabaseImporter/test-data/sample-customers.sql
```

Diese enthält:
- **customers**: 10 Testdatensätze mit verschiedenen Feldtypen
- **products**: 5 Produkte
- **orders**: 8 Bestellungen mit Foreign Keys zu customers

## 🎯 Roadmap

### Phase 1 (✅ Implementiert)
- [x] SQL-Parser
- [x] Basis-Typ-Erkennung
- [x] Admin-UI für Upload
- [x] Analyse-Ansicht

### Phase 2 (Geplant)
- [ ] CSV-Parser
- [ ] JSON-Parser
- [ ] XML-Parser
- [ ] Mapping-Interface (Drag & Drop)
- [ ] Import-Vorschau
- [ ] Tatsächliche Daten-Import-Funktionalität

### Phase 3 (Geplant)
- [ ] Template/Field-Auto-Generierung
- [ ] Gespeicherte Mapping-Konfigurationen
- [ ] Beziehungs-Handling (Foreign Keys)
- [ ] Batch-Processing
- [ ] Error-Recovery & Rollback

### Phase 4 (Zukunft)
- [ ] Excel-Import
- [ ] ML-basierte Erkennung
- [ ] Asynchrone Verarbeitung
- [ ] Migration-Wizards (WordPress, Drupal, etc.)
- [ ] Scheduled Imports

## 📝 Konfiguration

### Modul-Einstellungen

Aktuell keine zusätzlichen Einstellungen erforderlich.

### Permissions

Das Modul erstellt automatisch die Permission:
- **database-import**: Erlaubt den Import von Datenbank-Daten

Weisen Sie diese Permission den entsprechenden Rollen zu.

## 🔒 Sicherheit

- Nur .sql Dateien werden akzeptiert
- Hochgeladene Dateien werden im Cache-Verzeichnis gespeichert
- Session-basierte Daten-Speicherung
- Sanitizing aller Ausgaben

## 🐛 Bekannte Limitierungen (Phase 1)

- Import funktioniert noch nicht - nur Analyse
- Nur SQL-Format wird unterstützt
- Keine Mapping-Konfiguration möglich
- Keine Template/Field-Erstellung
- Keine Beziehungs-Auflösung

## 👨‍💻 Entwicklung

### Dateistruktur
```
ProcessDatabaseImporter/
├── ProcessDatabaseImporter.module.php    # Haupt-Modul
├── ProcessDatabaseImporter.info.json     # Modul-Info
├── README.md                              # Diese Datei
├── classes/
│   ├── parsers/
│   │   ├── AbstractParser.php            # Parser-Basisklasse
│   │   └── SqlParser.php                 # SQL-Parser
│   ├── DataAnalyzer.php                  # Daten-Analyse
│   └── TypeDetector.php                  # Typ-Erkennung
├── assets/
│   └── css/
│       └── database-importer.css         # Styling
└── test-data/
    └── sample-customers.sql              # Test-Daten
```

### Eigene Parser entwickeln

```php
class CustomParser extends AbstractParser {
    public function canParse($file) {
        // Prüfen ob Parser zuständig ist
    }

    public function parse($file, array $options = []) {
        // Datei parsen und Daten zurückgeben
    }

    public function getMetadata() {
        // Metadaten zurückgeben
    }
}
```

## 📄 Lizenz

ProcessWire Module

## 🙏 Credits

Entwickelt für ProcessWire CMS

---

**Version**: 1.0.0 (Phase 1 - Proof of Concept)
**Status**: Beta - Nur Analyse-Funktionalität
