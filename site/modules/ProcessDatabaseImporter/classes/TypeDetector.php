<?php

namespace ProcessWire;

/**
 * Detects appropriate ProcessWire field types based on data samples
 */
class TypeDetector extends WireData {

    /**
     * Pattern definitions for type detection
     */
    protected $patterns = [
        // CRITICAL: Order matters! More specific patterns MUST come first
        // Date patterns BEFORE phone patterns (dates can match phone regex)
        'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/i',
        'url' => '/^https?:\/\/.+/i',
        'date_iso' => '/^\d{4}-\d{2}-\d{2}$/',
        'date_de' => '/^\d{2}\.\d{2}\.\d{4}$/',
        'date_us' => '/^\d{2}\/\d{2}\/\d{4}$/',
        'datetime_iso' => '/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/',
        'time' => '/^\d{2}:\d{2}(:\d{2})?$/',
        'phone_de' => '/^(\+49|0)[0-9\s\-\/()]{7,}$/',
        'phone_intl' => '/^\+?[0-9\s\(\)]{7,}$/',  // More restrictive: no dashes, requires + or spaces/parens
        'html' => '/<[a-z][\s\S]*>/i',
        'json' => '/^[\{\[].*[\}\]]$/s',
        'image_url' => '/\.(jpg|jpeg|png|gif|webp|svg)$/i',
        'file_url' => '/\.(pdf|doc|docx|xls|xlsx|zip|rar)$/i',
        'hex_color' => '/^#[0-9a-f]{3,6}$/i',
        'boolean' => '/^(0|1|true|false|yes|no|ja|nein)$/i',
    ];

    /**
     * Column name patterns for type hints
     */
    protected $columnNamePatterns = [
        'email' => ['email', 'e_mail', 'mail'],
        'url' => ['url', 'link', 'website'],
        'phone' => ['phone', 'tel', 'telefon', 'mobile', 'handy'],
        'date' => ['date', 'datum'],
        'datetime' => ['datetime', 'created_at', 'updated_at', 'modified_at', 'published_at'],
        'image' => ['image', 'img', 'photo', 'picture', 'foto', 'bild'],
        'file' => ['file', 'attachment', 'document', 'datei'],
        'password' => ['password', 'pass', 'pwd', 'passwort'],
        'checkbox' => ['is_', 'has_', 'active', 'enabled', 'published', 'visible'],
        'textarea' => ['description', 'body', 'content', 'text', 'beschreibung', 'inhalt'],
        'title' => ['title', 'headline', 'subject', 'titel'],
    ];

    /**
     * Fieldtype mapping for pattern matches
     */
    protected $patternFieldtypeMap = [
        'email' => 'FieldtypeEmail',
        'url' => 'FieldtypeURL',
        'phone_de' => 'FieldtypeText',
        'phone_intl' => 'FieldtypeText',
        'date_iso' => 'FieldtypeDatetime',
        'date_de' => 'FieldtypeDatetime',
        'date_us' => 'FieldtypeDatetime',
        'datetime_iso' => 'FieldtypeDatetime',
        'time' => 'FieldtypeDatetime',
        'html' => 'FieldtypeTextarea',
        'json' => 'FieldtypeTextarea',
        'image_url' => 'FieldtypeImage',
        'file_url' => 'FieldtypeFile',
        'hex_color' => 'FieldtypeText',
        'boolean' => 'FieldtypeCheckbox',
    ];

    /**
     * Detect appropriate ProcessWire field type
     *
     * @param array $values Sample values
     * @param string $columnName Column name for context
     * @param array $columnInfo SQL column info
     * @return array Detection result with type, confidence, and fieldtype
     */
    public function detect($values, $columnName = '', $columnInfo = []) {
        $values = array_filter($values, function($v) {
            return $v !== null && $v !== '';
        });

        if (empty($values)) {
            return [
                'type' => 'unknown',
                'confidence' => 0,
                'fieldtype' => 'FieldtypeText',
                'patterns' => []
            ];
        }

        $baseType = $columnInfo['base_type'] ?? 'string';

        // Check for boolean/checkbox first (before options detection)
        $booleanResult = $this->detectBooleanField($columnName, $columnInfo, $values);
        if ($booleanResult['is_boolean']) {
            return $booleanResult;
        }

        // Start with SQL type-based detection - this is the most reliable
        $result = $this->detectFromSqlType($baseType, $columnInfo);

        // CRITICAL: SQL type detection has priority!
        // Only refine if SQL type detection has LOW confidence (<70%)
        // This prevents "int" fields from being misdetected as "FieldtypeImage"
        // just because the column name contains "image"

        if ($result['confidence'] >= 70) {
            // SQL type is reliable - only add pattern/name hints, don't override
            // For string types, check patterns but don't override high-confidence SQL types
            if (in_array($baseType, ['string', 'text'])) {
                $patternResult = $this->detectFromPatterns($values);
                if ($patternResult['confidence'] > $result['confidence']) {
                    $result = $patternResult;
                }
            }
        } else {
            // SQL type is uncertain - use patterns and names to refine

            // Refine with pattern matching
            if (in_array($baseType, ['string', 'text'])) {
                $patternResult = $this->detectFromPatterns($values);
                if ($patternResult['confidence'] > $result['confidence']) {
                    $result = $patternResult;
                }
            }

            // Refine with column name hints ONLY if result is still uncertain
            if ($result['confidence'] < 80) {
                $nameResult = $this->detectFromColumnName($columnName);
                if ($nameResult['confidence'] > 0 && $nameResult['fieldtype'] !== 'FieldtypeText') {
                    // Only apply if name result is more confident
                    if ($nameResult['confidence'] > $result['confidence']) {
                        $result['fieldtype'] = $nameResult['fieldtype'];
                        $result['type'] = $nameResult['type'];
                        $result['confidence'] = $nameResult['confidence'];
                    }
                }
            }
        }

        // Check for options/select field - but only if not already detected as email, URL, etc.
        // Options should not override specific types with high confidence
        if ($result['confidence'] < 80 || $result['fieldtype'] === 'FieldtypeText') {
            $optionsResult = $this->detectOptionsField($values, $columnName);
            if ($optionsResult['is_options']) {
                return $optionsResult;
            }
        }

        // CRITICAL: For ENUM/SET fields, extract options even though fieldtype is already set
        if (in_array($baseType, ['enum', 'set']) && $result['fieldtype'] === 'FieldtypeOptions') {
            $optionsResult = $this->detectOptionsField($values, $columnName);
            if ($optionsResult['is_options'] && isset($optionsResult['options'])) {
                // Merge options into result
                $result['options'] = $optionsResult['options'];
                $result['options_count'] = $optionsResult['options_count'];
            }
        }

        return $result;
    }

    /**
     * Detect type from SQL column type
     */
    protected function detectFromSqlType($baseType, $columnInfo) {
        $typeMap = [
            'integer' => ['FieldtypeInteger', 90],
            'float' => ['FieldtypeFloat', 90],
            'decimal' => ['FieldtypeFloat', 90],
            'date' => ['FieldtypeDatetime', 95],
            'datetime' => ['FieldtypeDatetime', 95],
            'time' => ['FieldtypeDatetime', 80],
            'text' => ['FieldtypeTextarea', 85],
            'json' => ['FieldtypeTextarea', 70],
            'enum' => ['FieldtypeOptions', 85],
            'set' => ['FieldtypeOptions', 85],
        ];

        if (isset($typeMap[$baseType])) {
            return [
                'type' => $baseType,
                'confidence' => $typeMap[$baseType][1],
                'fieldtype' => $typeMap[$baseType][0],
                'patterns' => []
            ];
        }

        // Default to text for strings
        return [
            'type' => 'string',
            'confidence' => 60,
            'fieldtype' => 'FieldtypeText',
            'patterns' => []
        ];
    }

    /**
     * Detect type from value patterns
     */
    protected function detectFromPatterns($values) {
        $sampleSize = min(100, count($values));
        $sample = array_slice($values, 0, $sampleSize);

        $patternMatches = [];

        foreach ($this->patterns as $patternName => $pattern) {
            $matches = 0;
            $nonNullCount = 0;

            foreach ($sample as $value) {
                if ($value !== null && $value !== '') {
                    $nonNullCount++;
                    if (preg_match($pattern, $value)) {
                        $matches++;
                    }
                }
            }

            // Calculate percentage based on non-null values only
            if ($nonNullCount > 0) {
                $percentage = ($matches / $nonNullCount) * 100;

                // Lower threshold to 60% to handle fields with NULL values
                if ($percentage >= 60) {
                    $patternMatches[$patternName] = $percentage;
                }
            }
        }

        if (empty($patternMatches)) {
            // Check if it contains HTML
            $hasHtml = false;
            foreach ($sample as $value) {
                if (strlen($value) > 100 && strip_tags($value) !== $value) {
                    $hasHtml = true;
                    break;
                }
            }

            if ($hasHtml) {
                return [
                    'type' => 'html',
                    'confidence' => 80,
                    'fieldtype' => 'FieldtypeTextarea',
                    'patterns' => ['html']
                ];
            }

            // Check average length for text vs textarea
            $avgLength = array_sum(array_map('strlen', $sample)) / count($sample);
            if ($avgLength > 255) {
                return [
                    'type' => 'text',
                    'confidence' => 75,
                    'fieldtype' => 'FieldtypeTextarea',
                    'patterns' => []
                ];
            }

            return [
                'type' => 'string',
                'confidence' => 60,
                'fieldtype' => 'FieldtypeText',
                'patterns' => []
            ];
        }

        // Get best matching pattern
        arsort($patternMatches);
        $bestPattern = array_key_first($patternMatches);
        $confidence = $patternMatches[$bestPattern];

        return [
            'type' => $bestPattern,
            'confidence' => round($confidence),
            'fieldtype' => $this->patternFieldtypeMap[$bestPattern] ?? 'FieldtypeText',
            'patterns' => array_keys($patternMatches)
        ];
    }

    /**
     * Detect type from column name
     */
    protected function detectFromColumnName($columnName) {
        $name = strtolower($columnName);

        foreach ($this->columnNamePatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($name, $pattern) !== false) {
                    $fieldtypeMap = [
                        'email' => 'FieldtypeEmail',
                        'url' => 'FieldtypeURL',
                        'phone' => 'FieldtypeText',
                        'date' => 'FieldtypeDatetime',
                        'datetime' => 'FieldtypeDatetime',
                        'image' => 'FieldtypeImage',
                        'file' => 'FieldtypeFile',
                        'password' => 'FieldtypePassword',
                        'checkbox' => 'FieldtypeCheckbox',
                        'textarea' => 'FieldtypeTextarea',
                        'title' => 'FieldtypePageTitle',
                    ];

                    return [
                        'type' => $type,
                        'confidence' => 70,
                        'fieldtype' => $fieldtypeMap[$type] ?? 'FieldtypeText',
                        'patterns' => [$pattern]
                    ];
                }
            }
        }

        return [
            'type' => 'unknown',
            'confidence' => 0,
            'fieldtype' => 'FieldtypeText',
            'patterns' => []
        ];
    }

    /**
     * Detect if this should be an options/select field
     */
    protected function detectOptionsField($values, $columnName = '') {
        $unique = array_unique($values);
        $total = count($values);
        $uniqueCount = count($unique);

        if ($uniqueCount === 0) {
            return ['is_options' => false];
        }

        // CRITICAL: Certain column names should NEVER be options fields
        // Even if they have few unique values in the sample
        $neverOptionsPatterns = [
            'name', 'title', 'headline', 'subject',
            'description', 'desc', 'text', 'content', 'body',
            'product', 'article', 'item',
            'first_name', 'last_name', 'full_name',
            'company', 'organization',
            'street', 'address', 'city', 'town',
            'note', 'comment', 'remark'
        ];

        $colName = strtolower($columnName);
        foreach ($neverOptionsPatterns as $pattern) {
            if (strpos($colName, $pattern) !== false) {
                return ['is_options' => false];
            }
        }

        // Calculate uniqueness ratio to handle different sample sizes consistently
        $uniqueRatio = $uniqueCount / $total;

        // CRITICAL: Use RATIO instead of absolute count
        // This ensures 5 rows and 100 rows are analyzed consistently

        // Very low uniqueness (<=20%) with limited options = Options field
        // Example: 100 rows, 10 unique (10%) = Options
        // Example: 5 rows, 1 unique (20%) = Options
        if ($uniqueRatio <= 0.20 && $uniqueCount <= 10) {
            return [
                'is_options' => true,
                'type' => 'options',
                'confidence' => 95,
                'fieldtype' => 'FieldtypeOptions',
                'patterns' => ['very_low_uniqueness'],
                'options' => array_values($unique),
                'options_count' => $uniqueCount
            ];
        }

        // Low uniqueness (<=30%) with very few unique values
        // Example: 10 rows, 3 unique (30%) = Options
        if ($uniqueRatio <= 0.30 && $uniqueCount <= 5) {
            return [
                'is_options' => true,
                'type' => 'options',
                'confidence' => 90,
                'fieldtype' => 'FieldtypeOptions',
                'patterns' => ['low_uniqueness'],
                'options' => array_values($unique),
                'options_count' => $uniqueCount
            ];
        }

        // Absolute fallback: Only with enough data AND very few unique values
        // Requires at least 20 rows to be confident
        if ($total >= 20 && $uniqueCount <= 3) {
            return [
                'is_options' => true,
                'type' => 'options',
                'confidence' => 85,
                'fieldtype' => 'FieldtypeOptions',
                'patterns' => ['very_low_cardinality'],
                'options' => array_values($unique),
                'options_count' => $uniqueCount
            ];
        }

        return ['is_options' => false];
    }

    /**
     * Detect if this is a boolean/checkbox field
     */
    protected function detectBooleanField($columnName, $columnInfo, $values) {
        $sqlType = $columnInfo['type'] ?? '';
        $name = strtolower($columnName);

        // Check for tinyint(1) - MySQL standard for boolean
        $isTinyInt = stripos($sqlType, 'tinyint(1)') !== false;

        // Check for boolean-like column names
        $booleanPrefixes = ['is_', 'has_', 'can_', 'should_', 'will_', 'does_'];
        $booleanSuffixes = ['_active', '_enabled', '_visible', '_published', '_verified', '_confirmed'];
        $booleanNames = ['active', 'enabled', 'visible', 'published', 'verified', 'confirmed', 'status'];

        $hasBooleanName = false;
        foreach ($booleanPrefixes as $prefix) {
            if (strpos($name, $prefix) === 0) {
                $hasBooleanName = true;
                break;
            }
        }
        if (!$hasBooleanName) {
            foreach ($booleanSuffixes as $suffix) {
                if (substr($name, -strlen($suffix)) === $suffix) {
                    $hasBooleanName = true;
                    break;
                }
            }
        }
        if (!$hasBooleanName && in_array($name, $booleanNames)) {
            $hasBooleanName = true;
        }

        // Check values - should only be 0/1 or true/false
        $unique = array_unique($values);
        $uniqueCount = count($unique);
        $isBinaryValues = $uniqueCount <= 2;

        // Strong indicator: tinyint(1) + boolean name
        if ($isTinyInt && $hasBooleanName) {
            return [
                'is_boolean' => true,
                'type' => 'boolean',
                'confidence' => 95,
                'fieldtype' => 'FieldtypeCheckbox',
                'patterns' => ['tinyint(1)', 'boolean_name']
            ];
        }

        // Medium indicator: tinyint(1) with binary values
        if ($isTinyInt && $isBinaryValues) {
            return [
                'is_boolean' => true,
                'type' => 'boolean',
                'confidence' => 90,
                'fieldtype' => 'FieldtypeCheckbox',
                'patterns' => ['tinyint(1)', 'binary_values']
            ];
        }

        // Weak indicator: boolean name with binary values
        if ($hasBooleanName && $isBinaryValues) {
            return [
                'is_boolean' => true,
                'type' => 'boolean',
                'confidence' => 85,
                'fieldtype' => 'FieldtypeCheckbox',
                'patterns' => ['boolean_name', 'binary_values']
            ];
        }

        return ['is_boolean' => false];
    }

    /**
     * Get suggestions for improving field detection
     */
    public function getSuggestions($detectionResult) {
        $suggestions = [];

        if ($detectionResult['confidence'] < 80) {
            $suggestions[] = 'Niedrige Konfidenz - Manuelle Überprüfung empfohlen';
        }

        if ($detectionResult['fieldtype'] === 'FieldtypeTextarea') {
            $suggestions[] = 'Überlegen Sie, ob TinyMCE/CKEditor für HTML-Inhalte aktiviert werden soll';
        }

        if ($detectionResult['type'] === 'datetime') {
            $suggestions[] = 'Stellen Sie sicher, dass das Datumsformat korrekt konfiguriert ist';
        }

        if (isset($detectionResult['is_options']) && $detectionResult['is_options']) {
            $suggestions[] = sprintf(
                'Gefunden: %d eindeutige Werte. Als Options-Feld konfigurieren?',
                $detectionResult['options_count']
            );
        }

        return $suggestions;
    }
}
