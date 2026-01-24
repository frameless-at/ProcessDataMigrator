# Konzept: External Data Mapping auf bestehende ProcessWire Felder/Templates

## 1. Übersicht

### 1.1 Problemstellung

Aktuell erstellt der ProcessDataMigrator **neue** Templates und Felder basierend auf importierten Daten. In vielen Anwendungsfällen möchte man jedoch externe Daten in **bereits bestehende** ProcessWire-Strukturen importieren:

- Import von Produktdaten in ein bestehendes Shop-Template
- Synchronisation von CRM-Daten mit bestehenden Kontakt-Seiten
- Migration von Inhalten aus anderen CMS in vorhandene Strukturen
- Regelmäßige Datenaktualisierungen aus externen Quellen

### 1.2 Ziel

Erweiterung des Moduls um einen **"Map to Existing"**-Modus, der es ermöglicht:

1. Externe Datenquellen (CSV, JSON, XML, SQL) zu analysieren
2. Die Daten auf **bestehende** ProcessWire Templates zu mappen
3. Flexible Feld-Zuordnungen (Source Column → Target Field) zu definieren
4. Neue Seiten zu erstellen ODER bestehende Seiten zu aktualisieren
5. Transformationen und Validierungen auf die Daten anzuwenden

---

## 2. Architektur-Übersicht

### 2.1 Neuer Workflow (6-Schritte-Wizard)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        MODUS-AUSWAHL (Neu)                              │
│  ┌─────────────────────┐     ┌─────────────────────────────────────┐   │
│  │  CREATE NEW         │     │  MAP TO EXISTING                    │   │
│  │  (Aktueller Modus)  │     │  (Neuer Modus)                      │   │
│  │  Erstellt neue      │     │  Mappt auf bestehende               │   │
│  │  Templates/Felder   │     │  Templates/Felder                   │   │
│  └─────────────────────┘     └─────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  SCHRITT 1: Datei-Upload                                                │
│  - Datei hochladen (CSV, JSON, XML, SQL)                                │
│  - Sample-Größe konfigurieren                                           │
└─────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  SCHRITT 2: Ziel-Template auswählen                                     │
│  - Liste aller verfügbaren Templates                                    │
│  - Template-Felder werden angezeigt                                     │
│  - Parent-Seite für neue Pages auswählen                                │
└─────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  SCHRITT 3: Feld-Mapping konfigurieren                                  │
│  ┌─────────────────┐         ┌─────────────────┐                       │
│  │ Source Columns  │  ────►  │ Target Fields   │                       │
│  │ (aus Datei)     │         │ (aus Template)  │                       │
│  ├─────────────────┤         ├─────────────────┤                       │
│  │ product_name    │  ────►  │ title           │                       │
│  │ description     │  ────►  │ body            │                       │
│  │ price_eur       │  ────►  │ price           │                       │
│  │ category_id     │  ────►  │ category (Page) │                       │
│  │ sku             │  ────►  │ sku             │                       │
│  │ [unmapped]      │         │ created_date    │                       │
│  └─────────────────┘         └─────────────────┘                       │
│                                                                         │
│  + Transformationen pro Feld konfigurierbar                            │
│  + Update-Modus: Identifikator-Feld für Matching                       │
└─────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  SCHRITT 4: Transformationen & Validierung                              │
│  - Wert-Transformationen (Trim, Lowercase, Datum-Format, etc.)         │
│  - Validierungsregeln (Required, Unique, Format-Check)                  │
│  - Default-Werte für leere Felder                                       │
│  - Lookup-Tabellen für Referenz-Felder                                  │
└─────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  SCHRITT 5: Dry-Run & Vorschau                                          │
│  - Zeigt was erstellt/aktualisiert wird                                 │
│  - Validierungs-Fehler werden angezeigt                                 │
│  - Konflikt-Erkennung bei Updates                                       │
└─────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  SCHRITT 6: Import ausführen                                            │
│  - Neue Seiten erstellen                                                │
│  - ODER bestehende Seiten aktualisieren                                 │
│  - Rollback-Daten speichern                                             │
└─────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Neue Klassen-Struktur

```
classes/
├── parsers/                          # (Bestehend - keine Änderungen)
│   ├── AbstractParser.php
│   ├── SqlParser.php
│   ├── CsvParser.php
│   ├── JsonParser.php
│   └── XmlParser.php
│
├── mapping/                          # (NEU - External Mapping)
│   ├── ExternalMappingEngine.php     # Kern-Mapping-Logik
│   ├── FieldMatcher.php              # Automatisches Feld-Matching
│   ├── MappingConfiguration.php      # Mapping-Config Datenklasse
│   └── MappingPreset.php             # Speicherbare Mapping-Presets
│
├── transform/                        # (NEU - Transformationen)
│   ├── TransformerInterface.php      # Interface für Transformatoren
│   ├── TransformerChain.php          # Verkettete Transformationen
│   ├── transformers/
│   │   ├── TrimTransformer.php
│   │   ├── CaseTransformer.php       # upper/lower/title case
│   │   ├── DateTransformer.php       # Datum-Format-Konvertierung
│   │   ├── NumberTransformer.php     # Zahlen-Formatierung
│   │   ├── HtmlTransformer.php       # HTML strip/encode
│   │   ├── LookupTransformer.php     # Lookup in anderen Tabellen
│   │   ├── ConcatTransformer.php     # Felder zusammenfügen
│   │   ├── SplitTransformer.php      # Feld aufteilen
│   │   ├── RegexTransformer.php      # Regex-basierte Transformation
│   │   └── CustomTransformer.php     # PHP-Expression
│   └── TransformerFactory.php        # Factory für Transformatoren
│
├── validation/                       # (NEU - Validierung)
│   ├── ValidatorInterface.php
│   ├── ValidationChain.php
│   ├── validators/
│   │   ├── RequiredValidator.php
│   │   ├── UniqueValidator.php
│   │   ├── FormatValidator.php       # Email, URL, etc.
│   │   ├── RangeValidator.php        # Min/Max für Zahlen
│   │   ├── LengthValidator.php       # String-Länge
│   │   └── CustomValidator.php       # PHP-Expression
│   └── ValidatorFactory.php
│
├── import/                           # (NEU - Erweiterte Import-Logik)
│   ├── ExternalImportProcessor.php   # Import in bestehende Templates
│   ├── UpdateStrategy.php            # Create/Update/Upsert Strategien
│   ├── ConflictResolver.php          # Konflikte bei Updates
│   └── PageMatcher.php               # Findet bestehende Seiten
│
├── DataAnalyzer.php                  # (Bestehend)
├── TypeDetector.php                  # (Bestehend)
├── MappingEngine.php                 # (Bestehend - für "Create New" Modus)
├── TemplateCreator.php               # (Bestehend - für "Create New" Modus)
├── ImportProcessor.php               # (Bestehend - für "Create New" Modus)
├── ImportRollback.php                # (Erweitert um Update-Rollback)
└── Logger.php                        # (Bestehend)
```

---

## 3. Detaillierte Komponenten-Beschreibung

### 3.1 ExternalMappingEngine

Zentrale Klasse für das Mapping auf bestehende Strukturen.

```php
<?php
namespace ProcessWire;

class ExternalMappingEngine {

    /**
     * Lädt alle verfügbaren Templates für die Auswahl
     *
     * @param array $options Filter-Optionen
     * @return array Template-Liste mit Feld-Informationen
     */
    public function getAvailableTemplates(array $options = []): array {
        $templates = [];
        foreach ($this->wire('templates') as $template) {
            // System-Templates ausschließen
            if ($template->flags & Template::flagSystem) continue;

            // Admin-Templates ausschließen
            if (strpos($template->name, 'admin') === 0) continue;

            $templates[] = [
                'id' => $template->id,
                'name' => $template->name,
                'label' => $template->getLabel() ?: $template->name,
                'fields' => $this->getTemplateFields($template),
                'pageCount' => $this->wire('pages')->count("template={$template->name}"),
                'allowedParents' => $this->getAllowedParents($template)
            ];
        }
        return $templates;
    }

    /**
     * Holt alle Felder eines Templates mit Metadaten
     *
     * @param Template $template
     * @return array Feld-Informationen
     */
    public function getTemplateFields(Template $template): array {
        $fields = [];
        foreach ($template->fields as $field) {
            $fields[] = [
                'id' => $field->id,
                'name' => $field->name,
                'label' => $field->getLabel() ?: $field->name,
                'type' => $field->type->className(),
                'typeName' => str_replace('Fieldtype', '', $field->type->className()),
                'required' => $field->required ? true : false,
                'description' => $field->description,
                'inputfield' => $field->inputfieldClass,
                'options' => $this->getFieldOptions($field)
            ];
        }
        return $fields;
    }

    /**
     * Erstellt automatische Mapping-Vorschläge
     *
     * @param array $sourceColumns Spalten aus der Quelldatei
     * @param array $targetFields Felder des Ziel-Templates
     * @return array Mapping-Vorschläge mit Confidence-Score
     */
    public function suggestMappings(array $sourceColumns, array $targetFields): array {
        $suggestions = [];
        $fieldMatcher = new FieldMatcher();

        foreach ($sourceColumns as $column) {
            $bestMatch = $fieldMatcher->findBestMatch(
                $column['name'],
                $column['analysis'],
                $targetFields
            );

            $suggestions[$column['name']] = $bestMatch;
        }

        return $suggestions;
    }

    /**
     * Validiert ein komplettes Mapping
     *
     * @param MappingConfiguration $config
     * @return array Validierungsergebnis
     */
    public function validateMapping(MappingConfiguration $config): array {
        $errors = [];
        $warnings = [];

        // Prüfe ob Title-Feld gemappt ist
        if (!$config->hasTitleMapping()) {
            $errors[] = 'Das Title-Feld muss gemappt sein.';
        }

        // Prüfe Typ-Kompatibilität
        foreach ($config->getMappings() as $mapping) {
            $compatibility = $this->checkTypeCompatibility(
                $mapping['sourceType'],
                $mapping['targetType']
            );

            if (!$compatibility['compatible']) {
                $warnings[] = sprintf(
                    'Feld "%s" → "%s": %s',
                    $mapping['source'],
                    $mapping['target'],
                    $compatibility['message']
                );
            }
        }

        // Prüfe Required-Felder
        foreach ($config->getRequiredTargetFields() as $field) {
            if (!$config->isFieldMapped($field)) {
                $errors[] = sprintf(
                    'Das Pflichtfeld "%s" ist nicht gemappt.',
                    $field
                );
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
}
```

### 3.2 FieldMatcher

Intelligentes automatisches Feld-Matching.

```php
<?php
namespace ProcessWire;

class FieldMatcher {

    /**
     * Matching-Strategien mit Gewichtung
     */
    protected array $strategies = [
        'exactName' => 100,        // Exakter Name-Match
        'normalizedName' => 90,    // Normalisierter Name (lowercase, ohne Sonderzeichen)
        'labelMatch' => 80,        // Label-Übereinstimmung
        'synonymMatch' => 70,      // Bekannte Synonyme (name↔title, desc↔body)
        'typeMatch' => 50,         // Typ-basiertes Matching
        'patternMatch' => 40,      // Pattern-basiert (email→FieldtypeEmail)
    ];

    /**
     * Bekannte Feld-Synonyme
     */
    protected array $synonyms = [
        'title' => ['name', 'headline', 'titel', 'bezeichnung', 'subject', 'betreff'],
        'body' => ['description', 'content', 'text', 'beschreibung', 'inhalt', 'desc'],
        'email' => ['mail', 'e-mail', 'email_address', 'emailaddress'],
        'phone' => ['telefon', 'tel', 'telephone', 'mobile', 'handy'],
        'image' => ['photo', 'picture', 'bild', 'foto', 'img'],
        'date' => ['datum', 'created', 'modified', 'published', 'erstellt'],
        'price' => ['preis', 'cost', 'amount', 'betrag', 'kosten'],
        'category' => ['kategorie', 'cat', 'type', 'typ', 'gruppe', 'group'],
        'status' => ['state', 'zustand', 'active', 'aktiv', 'enabled'],
    ];

    /**
     * Findet die beste Feld-Übereinstimmung
     *
     * @param string $sourceName Quell-Spaltenname
     * @param array $sourceAnalysis Analyse-Daten der Spalte
     * @param array $targetFields Verfügbare Ziel-Felder
     * @return array Match-Ergebnis mit Confidence
     */
    public function findBestMatch(
        string $sourceName,
        array $sourceAnalysis,
        array $targetFields
    ): array {
        $matches = [];

        foreach ($targetFields as $field) {
            $score = $this->calculateMatchScore(
                $sourceName,
                $sourceAnalysis,
                $field
            );

            if ($score > 0) {
                $matches[] = [
                    'field' => $field['name'],
                    'score' => $score,
                    'reason' => $this->getMatchReason($sourceName, $field, $score)
                ];
            }
        }

        // Sortiere nach Score absteigend
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        if (empty($matches)) {
            return [
                'field' => null,
                'score' => 0,
                'reason' => 'Keine passende Übereinstimmung gefunden',
                'alternatives' => []
            ];
        }

        return [
            'field' => $matches[0]['field'],
            'score' => $matches[0]['score'],
            'reason' => $matches[0]['reason'],
            'alternatives' => array_slice($matches, 1, 3) // Top 3 Alternativen
        ];
    }

    /**
     * Berechnet Match-Score zwischen Quelle und Ziel
     */
    protected function calculateMatchScore(
        string $sourceName,
        array $sourceAnalysis,
        array $targetField
    ): int {
        $score = 0;
        $sourceNormalized = $this->normalize($sourceName);
        $targetNormalized = $this->normalize($targetField['name']);

        // 1. Exakter Name-Match
        if ($sourceName === $targetField['name']) {
            return $this->strategies['exactName'];
        }

        // 2. Normalisierter Name-Match
        if ($sourceNormalized === $targetNormalized) {
            return $this->strategies['normalizedName'];
        }

        // 3. Label-Match
        $targetLabelNorm = $this->normalize($targetField['label'] ?? '');
        if ($sourceNormalized === $targetLabelNorm) {
            return $this->strategies['labelMatch'];
        }

        // 4. Synonym-Match
        foreach ($this->synonyms as $canonical => $synonymList) {
            $allTerms = array_merge([$canonical], $synonymList);
            $sourceInList = in_array($sourceNormalized, $allTerms);
            $targetInList = in_array($targetNormalized, $allTerms);

            if ($sourceInList && $targetInList) {
                return $this->strategies['synonymMatch'];
            }
        }

        // 5. Typ-basiertes Matching
        $sourceType = $sourceAnalysis['suggested_type'] ?? null;
        $targetType = $targetField['typeName'] ?? null;

        if ($sourceType && $targetType && $this->typesMatch($sourceType, $targetType)) {
            $score = max($score, $this->strategies['typeMatch']);
        }

        // 6. Teilstring-Match (mit Penalty)
        if (str_contains($sourceNormalized, $targetNormalized) ||
            str_contains($targetNormalized, $sourceNormalized)) {
            $score = max($score, 30);
        }

        return $score;
    }

    /**
     * Normalisiert einen Feldnamen für Vergleiche
     */
    protected function normalize(string $name): string {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]/', '', $name);
        return $name;
    }
}
```

### 3.3 MappingConfiguration

Datenklasse für die Mapping-Konfiguration.

```php
<?php
namespace ProcessWire;

class MappingConfiguration {

    protected string $targetTemplate;
    protected int $parentPageId;
    protected array $fieldMappings = [];
    protected array $transformations = [];
    protected array $validations = [];
    protected string $updateMode = 'create'; // create|update|upsert
    protected ?string $matchField = null;    // Feld für Page-Matching bei Updates
    protected array $defaultValues = [];

    /**
     * Fügt ein Feld-Mapping hinzu
     *
     * @param string $sourceColumn Quell-Spalte
     * @param string $targetField Ziel-Feld
     * @param array $options Zusätzliche Optionen
     */
    public function addMapping(
        string $sourceColumn,
        string $targetField,
        array $options = []
    ): self {
        $this->fieldMappings[$sourceColumn] = [
            'source' => $sourceColumn,
            'target' => $targetField,
            'sourceType' => $options['sourceType'] ?? null,
            'targetType' => $options['targetType'] ?? null,
            'transformations' => $options['transformations'] ?? [],
            'validations' => $options['validations'] ?? [],
            'defaultValue' => $options['defaultValue'] ?? null,
            'skipIfEmpty' => $options['skipIfEmpty'] ?? false,
        ];
        return $this;
    }

    /**
     * Setzt den Update-Modus
     *
     * @param string $mode create|update|upsert
     * @param string|null $matchField Feld für Matching (bei update/upsert)
     */
    public function setUpdateMode(string $mode, ?string $matchField = null): self {
        if (!in_array($mode, ['create', 'update', 'upsert'])) {
            throw new \InvalidArgumentException("Invalid update mode: $mode");
        }

        if ($mode !== 'create' && !$matchField) {
            throw new \InvalidArgumentException("Match field required for $mode mode");
        }

        $this->updateMode = $mode;
        $this->matchField = $matchField;
        return $this;
    }

    /**
     * Exportiert die Konfiguration als Array (für Session/Preset)
     */
    public function toArray(): array {
        return [
            'targetTemplate' => $this->targetTemplate,
            'parentPageId' => $this->parentPageId,
            'fieldMappings' => $this->fieldMappings,
            'updateMode' => $this->updateMode,
            'matchField' => $this->matchField,
            'defaultValues' => $this->defaultValues,
        ];
    }

    /**
     * Erstellt Konfiguration aus Array
     */
    public static function fromArray(array $data): self {
        $config = new self();
        $config->targetTemplate = $data['targetTemplate'];
        $config->parentPageId = $data['parentPageId'];
        $config->fieldMappings = $data['fieldMappings'];
        $config->updateMode = $data['updateMode'] ?? 'create';
        $config->matchField = $data['matchField'] ?? null;
        $config->defaultValues = $data['defaultValues'] ?? [];
        return $config;
    }
}
```

### 3.4 Transformer-System

Flexibles System für Wert-Transformationen.

```php
<?php
namespace ProcessWire;

interface TransformerInterface {
    /**
     * Transformiert einen Wert
     *
     * @param mixed $value Eingabe-Wert
     * @param array $options Transformer-Optionen
     * @param array $context Kontext (andere Felder, Row-Daten)
     * @return mixed Transformierter Wert
     */
    public function transform($value, array $options = [], array $context = []);

    /**
     * Gibt den Namen des Transformers zurück
     */
    public function getName(): string;

    /**
     * Gibt verfügbare Optionen zurück
     */
    public function getOptions(): array;
}

/**
 * Verkettete Transformationen
 */
class TransformerChain {
    protected array $transformers = [];

    public function add(TransformerInterface $transformer, array $options = []): self {
        $this->transformers[] = [
            'transformer' => $transformer,
            'options' => $options
        ];
        return $this;
    }

    public function transform($value, array $context = []) {
        foreach ($this->transformers as $item) {
            $value = $item['transformer']->transform(
                $value,
                $item['options'],
                $context
            );
        }
        return $value;
    }
}

/**
 * Beispiel: Datum-Transformer
 */
class DateTransformer implements TransformerInterface {

    public function transform($value, array $options = [], array $context = []) {
        if (empty($value)) {
            return $options['default'] ?? null;
        }

        $inputFormat = $options['inputFormat'] ?? null;
        $outputFormat = $options['outputFormat'] ?? 'Y-m-d H:i:s';

        // Versuche Datum zu parsen
        if ($inputFormat) {
            $date = \DateTime::createFromFormat($inputFormat, $value);
        } else {
            // Automatische Erkennung
            $date = new \DateTime($value);
        }

        if (!$date) {
            return $options['onError'] === 'null' ? null : $value;
        }

        // Für ProcessWire Datetime-Felder: Unix Timestamp
        if ($options['asTimestamp'] ?? false) {
            return $date->getTimestamp();
        }

        return $date->format($outputFormat);
    }

    public function getName(): string {
        return 'date';
    }

    public function getOptions(): array {
        return [
            'inputFormat' => [
                'type' => 'text',
                'label' => 'Eingabe-Format',
                'description' => 'PHP date() Format, z.B. d.m.Y',
                'default' => null
            ],
            'outputFormat' => [
                'type' => 'text',
                'label' => 'Ausgabe-Format',
                'default' => 'Y-m-d H:i:s'
            ],
            'asTimestamp' => [
                'type' => 'checkbox',
                'label' => 'Als Unix-Timestamp',
                'default' => true
            ]
        ];
    }
}

/**
 * Lookup-Transformer für Referenz-Felder
 */
class LookupTransformer implements TransformerInterface {

    public function transform($value, array $options = [], array $context = []) {
        if (empty($value)) {
            return null;
        }

        $lookupTemplate = $options['template'];
        $lookupField = $options['matchField'];
        $returnField = $options['returnField'] ?? 'id';

        // Suche Page mit passendem Wert
        $page = wire('pages')->findOne(
            "template=$lookupTemplate, $lookupField=$value"
        );

        if (!$page || !$page->id) {
            // Fallback-Strategie
            switch ($options['onNotFound'] ?? 'null') {
                case 'create':
                    return $this->createLookupPage($value, $options);
                case 'error':
                    throw new \RuntimeException("Lookup failed for value: $value");
                case 'null':
                default:
                    return null;
            }
        }

        return $returnField === 'id' ? $page->id : $page->get($returnField);
    }

    public function getName(): string {
        return 'lookup';
    }

    public function getOptions(): array {
        return [
            'template' => [
                'type' => 'select',
                'label' => 'Lookup-Template',
                'required' => true
            ],
            'matchField' => [
                'type' => 'select',
                'label' => 'Match-Feld',
                'required' => true
            ],
            'returnField' => [
                'type' => 'select',
                'label' => 'Rückgabe-Feld',
                'default' => 'id'
            ],
            'onNotFound' => [
                'type' => 'select',
                'label' => 'Bei keinem Treffer',
                'options' => ['null', 'create', 'error'],
                'default' => 'null'
            ]
        ];
    }
}
```

### 3.5 ExternalImportProcessor

Erweiterte Import-Logik für bestehende Templates.

```php
<?php
namespace ProcessWire;

class ExternalImportProcessor {

    protected MappingConfiguration $config;
    protected TransformerFactory $transformerFactory;
    protected ValidatorFactory $validatorFactory;
    protected Logger $logger;
    protected array $stats = [];
    protected array $errors = [];
    protected array $rollbackData = [];

    /**
     * Führt den Import aus
     *
     * @param array $data Geparste Daten
     * @param MappingConfiguration $config Mapping-Konfiguration
     * @return array Import-Ergebnis
     */
    public function import(array $data, MappingConfiguration $config): array {
        $this->config = $config;
        $this->stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        $this->errors = [];
        $this->rollbackData = [
            'created_pages' => [],
            'updated_pages' => [], // Speichert Original-Werte
            'mode' => $config->getUpdateMode()
        ];

        $template = $this->wire('templates')->get($config->getTargetTemplate());
        $parent = $this->wire('pages')->get($config->getParentPageId());

        if (!$template || !$parent->id) {
            throw new \RuntimeException('Invalid template or parent page');
        }

        foreach ($data as $rowIndex => $row) {
            try {
                $this->processRow($row, $rowIndex, $template, $parent);
            } catch (\Exception $e) {
                $this->errors[] = [
                    'row' => $rowIndex,
                    'message' => $e->getMessage()
                ];
                $this->stats['errors']++;
                $this->logger->error("Row $rowIndex: " . $e->getMessage());
            }
        }

        return [
            'success' => $this->stats['errors'] === 0,
            'stats' => $this->stats,
            'errors' => $this->errors,
            'rollbackData' => $this->rollbackData
        ];
    }

    /**
     * Verarbeitet eine einzelne Zeile
     */
    protected function processRow(
        array $row,
        int $rowIndex,
        Template $template,
        Page $parent
    ): void {
        // 1. Werte transformieren
        $transformedValues = $this->transformRow($row);

        // 2. Validierung
        $validationResult = $this->validateRow($transformedValues, $rowIndex);
        if (!$validationResult['valid']) {
            throw new \RuntimeException(
                'Validation failed: ' . implode(', ', $validationResult['errors'])
            );
        }

        // 3. Page finden oder erstellen
        $page = $this->findOrCreatePage($transformedValues, $template, $parent);

        // 4. Werte setzen
        $isNew = $page->isNew();

        if (!$isNew && $this->config->getUpdateMode() === 'create') {
            $this->stats['skipped']++;
            return;
        }

        // Bei Update: Original-Werte speichern für Rollback
        if (!$isNew) {
            $this->rollbackData['updated_pages'][$page->id] =
                $this->captureOriginalValues($page);
        }

        // Werte auf Page setzen
        foreach ($this->config->getMappings() as $mapping) {
            $targetField = $mapping['target'];
            $value = $transformedValues[$mapping['source']] ?? null;

            if ($value === null && ($mapping['skipIfEmpty'] ?? false)) {
                continue;
            }

            $page->set($targetField, $value);
        }

        // Page speichern
        $page->save();

        if ($isNew) {
            $this->rollbackData['created_pages'][] = $page->id;
            $this->stats['created']++;
        } else {
            $this->stats['updated']++;
        }
    }

    /**
     * Findet bestehende Page oder erstellt neue
     */
    protected function findOrCreatePage(
        array $values,
        Template $template,
        Page $parent
    ): Page {
        $mode = $this->config->getUpdateMode();
        $matchField = $this->config->getMatchField();

        // Bei update/upsert: Versuche bestehende Page zu finden
        if ($mode !== 'create' && $matchField) {
            $matchValue = $values[$matchField] ?? null;

            if ($matchValue !== null) {
                $existing = $this->wire('pages')->findOne(
                    "template={$template->name}, " .
                    "parent={$parent->id}, " .
                    "{$matchField}={$matchValue}"
                );

                if ($existing && $existing->id) {
                    return $existing;
                }
            }

            // Bei reinem Update-Modus: Fehler wenn nicht gefunden
            if ($mode === 'update') {
                throw new \RuntimeException(
                    "No existing page found for $matchField=$matchValue"
                );
            }
        }

        // Neue Page erstellen
        $page = $this->wire('pages')->newPage();
        $page->template = $template;
        $page->parent = $parent;

        // Title setzen
        $titleMapping = $this->config->getTitleMapping();
        if ($titleMapping) {
            $page->title = $values[$titleMapping['source']] ?? 'Untitled';
        }

        return $page;
    }

    /**
     * Transformiert alle Werte einer Zeile
     */
    protected function transformRow(array $row): array {
        $transformed = [];

        foreach ($this->config->getMappings() as $mapping) {
            $sourceColumn = $mapping['source'];
            $value = $row[$sourceColumn] ?? null;

            // Transformationen anwenden
            if (!empty($mapping['transformations'])) {
                $chain = $this->buildTransformerChain($mapping['transformations']);
                $value = $chain->transform($value, $row);
            }

            // Default-Wert wenn leer
            if ($value === null || $value === '') {
                $value = $mapping['defaultValue'] ?? null;
            }

            $transformed[$sourceColumn] = $value;
        }

        return $transformed;
    }
}
```

---

## 4. UI/UX Konzept

### 4.1 Template-Auswahl (Schritt 2)

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ZIEL-TEMPLATE AUSWÄHLEN                                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ 🔍 Template suchen...                                           │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ ○ product (Produkt)                              247 Seiten     │   │
│  │   Felder: title, body, price, sku, category, images             │   │
│  ├─────────────────────────────────────────────────────────────────┤   │
│  │ ○ news (Neuigkeiten)                              52 Seiten     │   │
│  │   Felder: title, body, date, author, tags                       │   │
│  ├─────────────────────────────────────────────────────────────────┤   │
│  │ ● contact (Kontakt)                              128 Seiten     │   │
│  │   Felder: title, email, phone, company, address, notes          │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  PARENT-SEITE                                                           │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ /kontakte/ (Kontakte-Übersicht)                              ▼  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  UPDATE-MODUS                                                           │
│  ○ Nur neue Seiten erstellen (Create)                                  │
│  ○ Nur bestehende aktualisieren (Update)                               │
│  ● Erstellen oder aktualisieren (Upsert)                               │
│                                                                         │
│  MATCH-FELD (für Update/Upsert)                                        │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ email (E-Mail-Adresse)                                       ▼  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│  ℹ️ Seiten werden anhand dieses Feldes identifiziert                    │
│                                                                         │
│                                            [ Zurück ]  [ Weiter → ]    │
└─────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Feld-Mapping (Schritt 3)

```
┌─────────────────────────────────────────────────────────────────────────┐
│  FELD-MAPPING KONFIGURIEREN                                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Automatisches Mapping: 4 von 6 Feldern (67%)  [ Auto-Map ausführen ]  │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ QUELLE (CSV)              →      ZIEL (contact Template)        │   │
│  ├─────────────────────────────────────────────────────────────────┤   │
│  │                                                                  │   │
│  │ ┌─────────────────┐   →   ┌─────────────────┐  ┌──────┐        │   │
│  │ │ full_name       │       │ title        ▼  │  │ ⚙️   │        │   │
│  │ └─────────────────┘       └─────────────────┘  └──────┘        │   │
│  │ 🟢 100% Match (exact)     Text → Text                           │   │
│  │                                                                  │   │
│  │ ┌─────────────────┐   →   ┌─────────────────┐  ┌──────┐        │   │
│  │ │ email_address   │       │ email        ▼  │  │ ⚙️   │        │   │
│  │ └─────────────────┘       └─────────────────┘  └──────┘        │   │
│  │ 🟢 90% Match (synonym)    Email → Email                         │   │
│  │                                                                  │   │
│  │ ┌─────────────────┐   →   ┌─────────────────┐  ┌──────┐        │   │
│  │ │ telefon         │       │ phone        ▼  │  │ ⚙️   │        │   │
│  │ └─────────────────┘       └─────────────────┘  └──────┘        │   │
│  │ 🟢 70% Match (synonym)    Text → Text                           │   │
│  │                                                                  │   │
│  │ ┌─────────────────┐   →   ┌─────────────────┐  ┌──────┐        │   │
│  │ │ firma           │       │ company      ▼  │  │ ⚙️   │        │   │
│  │ └─────────────────┘       └─────────────────┘  └──────┘        │   │
│  │ 🟡 50% Match (type)       Text → Text                           │   │
│  │                                                                  │   │
│  │ ┌─────────────────┐   →   ┌─────────────────┐  ┌──────┐        │   │
│  │ │ category_id     │       │ -- nicht mappen │  │ ⚙️   │        │   │
│  │ └─────────────────┘       └─────────────────┘  └──────┘        │   │
│  │ 🔴 Kein Match             Integer → ?                           │   │
│  │                                                                  │   │
│  │ ┌─────────────────┐   →   ┌─────────────────┐  ┌──────┐        │   │
│  │ │ bemerkungen     │       │ notes        ▼  │  │ ⚙️   │        │   │
│  │ └─────────────────┘       └─────────────────┘  └──────┘        │   │
│  │ 🟢 70% Match (synonym)    Text → Textarea                       │   │
│  │                                                                  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  NICHT GEMAPPTE ZIEL-FELDER:                                           │
│  ⚠️ address (Adresse) - Pflichtfeld!                                    │
│                                                                         │
│                                            [ Zurück ]  [ Weiter → ]    │
└─────────────────────────────────────────────────────────────────────────┘
```

### 4.3 Transformations-Dialog (⚙️ Button)

```
┌─────────────────────────────────────────────────────────────────────────┐
│  TRANSFORMATIONEN FÜR: email_address → email                     [ X ] │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  AKTIVE TRANSFORMATIONEN (in Reihenfolge)                              │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ 1. ≡ Trim (Leerzeichen entfernen)                         [ 🗑️ ] │   │
│  │ 2. ≡ Lowercase (Kleinschreibung)                          [ 🗑️ ] │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  TRANSFORMATION HINZUFÜGEN                                              │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ Transformation auswählen...                                  ▼  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│  • Trim - Leerzeichen entfernen                                        │
│  • Lowercase - In Kleinbuchstaben                                      │
│  • Uppercase - In Großbuchstaben                                       │
│  • Date - Datum formatieren                                            │
│  • Number - Zahl formatieren                                           │
│  • Lookup - Wert nachschlagen                                          │
│  • Replace - Suchen & Ersetzen                                         │
│  • Regex - Regulärer Ausdruck                                          │
│  • Concat - Felder zusammenfügen                                       │
│  • Custom - PHP-Ausdruck                                               │
│                                                                         │
│  VALIDIERUNG                                                            │
│  ☑️ Pflichtfeld (darf nicht leer sein)                                  │
│  ☑️ E-Mail-Format prüfen                                                │
│  ☐ Eindeutig (keine Duplikate)                                         │
│                                                                         │
│  DEFAULT-WERT (wenn leer)                                              │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                                                                  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  VORSCHAU (erste 5 Werte)                                              │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ Original              →  Transformiert                          │   │
│  │ " John@Example.COM "  →  "john@example.com"                     │   │
│  │ "jane@test.de"        →  "jane@test.de"                         │   │
│  │ "  BOB@FIRMA.DE  "    →  "bob@firma.de"                         │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│                                       [ Abbrechen ]  [ Übernehmen ]    │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 5. Mapping-Presets

### 5.1 Speichern und Laden von Mapping-Konfigurationen

```php
<?php
namespace ProcessWire;

class MappingPreset {

    /**
     * Speichert ein Mapping-Preset
     *
     * @param string $name Preset-Name
     * @param MappingConfiguration $config Konfiguration
     * @param array $metadata Zusätzliche Metadaten
     */
    public function save(
        string $name,
        MappingConfiguration $config,
        array $metadata = []
    ): int {
        // Speichere in ProcessWire-Modul-Konfiguration oder eigene Tabelle
        $presets = $this->wire('modules')->getConfig(
            'ProcessDataMigrator',
            'mapping_presets'
        ) ?: [];

        $preset = [
            'name' => $name,
            'description' => $metadata['description'] ?? '',
            'sourceFormat' => $metadata['sourceFormat'] ?? '',
            'targetTemplate' => $config->getTargetTemplate(),
            'config' => $config->toArray(),
            'created' => time(),
            'modified' => time()
        ];

        $id = count($presets) + 1;
        $presets[$id] = $preset;

        $this->wire('modules')->saveConfig(
            'ProcessDataMigrator',
            'mapping_presets',
            $presets
        );

        return $id;
    }

    /**
     * Lädt ein Mapping-Preset
     */
    public function load(int $id): ?MappingConfiguration {
        $presets = $this->wire('modules')->getConfig(
            'ProcessDataMigrator',
            'mapping_presets'
        ) ?: [];

        if (!isset($presets[$id])) {
            return null;
        }

        return MappingConfiguration::fromArray($presets[$id]['config']);
    }

    /**
     * Listet alle verfügbaren Presets
     */
    public function listAll(): array {
        return $this->wire('modules')->getConfig(
            'ProcessDataMigrator',
            'mapping_presets'
        ) ?: [];
    }
}
```

---

## 6. Rollback-Erweiterung

### 6.1 Erweiterung für Update-Rollback

```php
<?php
namespace ProcessWire;

class ExtendedRollback {

    /**
     * Führt Rollback für External-Import durch
     */
    public function rollback(array $rollbackData): array {
        $stats = [
            'deleted' => 0,
            'restored' => 0,
            'errors' => 0
        ];

        // 1. Neu erstellte Seiten löschen
        foreach ($rollbackData['created_pages'] ?? [] as $pageId) {
            try {
                $page = $this->wire('pages')->get($pageId);
                if ($page->id) {
                    $this->wire('pages')->delete($page, true);
                    $stats['deleted']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
            }
        }

        // 2. Aktualisierte Seiten auf Originalwerte zurücksetzen
        foreach ($rollbackData['updated_pages'] ?? [] as $pageId => $originalValues) {
            try {
                $page = $this->wire('pages')->get($pageId);
                if ($page->id) {
                    foreach ($originalValues as $field => $value) {
                        $page->set($field, $value);
                    }
                    $page->save();
                    $stats['restored']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
            }
        }

        return $stats;
    }
}
```

---

## 7. Implementierungsplan

### Phase 1: Grundstruktur (Kern-Funktionalität)

1. **ExternalMappingEngine** - Template/Feld-Auswahl
2. **MappingConfiguration** - Datenstruktur für Mappings
3. **FieldMatcher** - Automatisches Feld-Matching
4. **ExternalImportProcessor** - Basis-Import (Create-Modus)

### Phase 2: Transformationen

1. **TransformerInterface** und **TransformerChain**
2. Basis-Transformer: Trim, Case, Date, Number
3. **LookupTransformer** für Referenz-Felder
4. UI für Transformer-Konfiguration

### Phase 3: Update-Funktionalität

1. **PageMatcher** - Bestehende Seiten finden
2. Update/Upsert-Modi im ImportProcessor
3. Konflikt-Erkennung und -Behandlung
4. Erweiterte Rollback-Funktionalität

### Phase 4: Validierung & Presets

1. **ValidatorInterface** und Validatoren
2. **MappingPreset** - Speichern/Laden
3. Validierungs-Fehler-Anzeige im UI

### Phase 5: UI-Integration

1. Modus-Auswahl im Wizard
2. Template-Auswahl-Seite
3. Drag-and-Drop Feld-Mapping
4. Transformations-Dialog
5. Erweiterte Dry-Run Vorschau

---

## 8. Technische Überlegungen

### 8.1 Kompatibilität

- Vollständig rückwärtskompatibel (bestehender Modus bleibt unverändert)
- Neue Funktionen additiv hinzugefügt
- Gleiche Session-Architektur nutzen

### 8.2 Performance

- Batch-Processing für große Datenmengen
- Lazy-Loading für Template/Feld-Listen
- Caching für wiederholte Lookups

### 8.3 Sicherheit

- Alle Eingaben durch ProcessWire Sanitizer
- Keine direkte SQL-Ausführung
- Permission-Check für Ziel-Templates

### 8.4 Erweiterbarkeit

- Plugin-System für Custom-Transformer
- Hook-Integration für Pre/Post-Import Events
- API für programmatischen Zugriff

---

## 9. Fazit

Dieses Konzept erweitert den ProcessDataMigrator um eine mächtige Mapping-Funktionalität für bestehende ProcessWire-Strukturen. Die modulare Architektur ermöglicht:

- Flexibles Feld-Mapping mit automatischen Vorschlägen
- Konfigurierbare Transformationen und Validierungen
- Unterstützung für Create, Update und Upsert-Szenarien
- Wiederverwendbare Mapping-Presets
- Vollständige Rollback-Fähigkeit

Die phasenweise Implementierung erlaubt einen iterativen Ansatz, bei dem jede Phase eigenständigen Mehrwert liefert.
