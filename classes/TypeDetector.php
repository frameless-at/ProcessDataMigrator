<?php

namespace ProcessWire;

/**
 * Detects appropriate ProcessWire field types based on data samples
 */
class TypeDetector extends WireData {

    /**
     * Pattern definitions for type detection
     * CRITICAL: Order matters! More specific patterns MUST come first
     */
    protected $patterns = [
        // Email - very specific pattern
        'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/i',

        // URL - must start with protocol
        'url' => '/^https?:\/\/.+/i',

        // Date patterns BEFORE phone patterns (dates can match phone regex)
        // ISO format with validation (YYYY-MM-DD, month 01-12, day 01-31)
        'date_iso' => '/^(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/',
        // German format with validation (DD.MM.YYYY)
        'date_de' => '/^(0[1-9]|[12]\d|3[01])\.(0[1-9]|1[0-2])\.(\d{4})$/',
        // US format with validation (MM/DD/YYYY)
        'date_us' => '/^(0[1-9]|1[0-2])\/(0[1-9]|[12]\d|3[01])\/(\d{4})$/',
        // Datetime ISO format
        'datetime_iso' => '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])\s([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/',
        // Time only (HH:MM or HH:MM:SS)
        'time' => '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/',

        // Phone patterns - MUST contain + or country code, or start with 0
        // German phone: +49 or 0 prefix required
        'phone_de' => '/^(\+49|0049|0)[1-9][0-9\s\-\/()]{6,}$/',
        // International phone: MUST start with + followed by country code
        'phone_intl' => '/^\+[1-9][0-9]{0,2}[\s\-]?[0-9\s\-\(\)]{6,}$/',

        // HTML content
        'html' => '/<[a-z][\s\S]*>/i',

        // JSON - must be valid structure with proper brackets
        'json' => '/^(\{[^{}]*\}|\[[^\[\]]*\])$/s',

        // Image/File URLs - map to FieldtypeURL (not Image/File - those need actual uploads)
        'image_url' => '/\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i',
        'file_url' => '/\.(pdf|doc|docx|xls|xlsx|zip|rar|tar|gz)(\?.*)?$/i',

        // Hex color
        'hex_color' => '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i',

        // Boolean - exact matches only
        'boolean' => '/^(0|1|true|false|yes|no|ja|nein)$/i',
    ];

    /**
     * Fieldtype mapping for pattern matches
     * NOTE: image_url and file_url map to URL, not Image/File
     * because ProcessWire Image/File fields require actual file uploads,
     * not just URLs pointing to remote files
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
        'image_url' => 'FieldtypeURL',  // URLs to images, not actual uploads
        'file_url' => 'FieldtypeURL',   // URLs to files, not actual uploads
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

        // CRITICAL: For non-SQL parsers (CSV, JSON, XML), base_type is always 'string'
        // We MUST analyze the actual values to determine the real type
        $baseType = $columnInfo['base_type'] ?? 'string';

        // If base_type is 'string', try to detect the real type from values
        if ($baseType === 'string') {
            $detectedType = $this->detectTypeFromValues($values);
            if ($detectedType !== 'string') {
                $baseType = $detectedType;
            }
        }

        // Check for boolean/checkbox first (before options detection)
        $booleanResult = $this->detectBooleanField($columnInfo, $values);
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
        }

        // Check for options/select field - but NEVER override specific semantic types
        // Options should only apply to generic Text fields, not Email, URL, Datetime, etc.
        $specificFieldtypes = [
            'FieldtypeEmail',
            'FieldtypeURL',
            'FieldtypeDatetime',
            'FieldtypeCheckbox',
            'FieldtypeInteger',
            'FieldtypeFloat',
        ];

        $isGenericTextType = !in_array($result['fieldtype'], $specificFieldtypes);

        if ($isGenericTextType && ($result['confidence'] < 80 || $result['fieldtype'] === 'FieldtypeText')) {
            $optionsResult = $this->detectOptionsField($values);
            if ($optionsResult['is_options']) {
                return $optionsResult;
            }
        }

        // CRITICAL: For ENUM/SET fields, extract options even though fieldtype is already set
        if (in_array($baseType, ['enum', 'set']) && $result['fieldtype'] === 'FieldtypeOptions') {
            $optionsResult = $this->detectOptionsField($values);
            if ($optionsResult['is_options'] && isset($optionsResult['options'])) {
                // Merge options into result
                $result['options'] = $optionsResult['options'];
                $result['options_count'] = $optionsResult['options_count'];
            }
        }

        return $result;
    }

    /**
     * Detect base type from actual values
     * CENTRAL type detection for all parsers (CSV, JSON, XML, SQL)
     *
     * @param array $values Sample values
     * @return string Base type: 'integer', 'float', 'date', 'datetime', 'boolean', or 'string'
     */
    protected function detectTypeFromValues($values) {
        $nonEmpty = array_filter($values, function($v) {
            return $v !== null && $v !== '';
        });

        if (empty($nonEmpty)) {
            return 'string';
        }

        $intCount = 0;
        $floatCount = 0;
        $boolCount = 0;
        $dateCount = 0;
        $datetimeCount = 0;
        $total = count($nonEmpty);

        foreach ($nonEmpty as $value) {
            // Check datetime first (more specific than date)
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $value)) {
                $datetimeCount++;
                continue;
            }

            // Check date (ISO format)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $dateCount++;
                continue;
            }

            // Check boolean (must be exact match)
            if ($value === '0' || $value === '1' ||
                $value === 'true' || $value === 'false' ||
                $value === true || $value === false ||
                $value === 0 || $value === 1) {
                $boolCount++;
                continue;
            }

            // Check numeric types
            if (is_numeric($value)) {
                // Check integer
                if ((int)$value == $value) {
                    $intCount++;
                    continue;
                }
                // Check float
                if (is_float($value + 0)) {
                    $floatCount++;
                    continue;
                }
            }
        }

        // Determine type based on what matched
        // Require at least 80% of values to match for confidence
        $threshold = $total * 0.8;

        if ($datetimeCount >= $threshold) {
            return 'datetime';
        }

        if ($dateCount >= $threshold) {
            return 'date';
        }

        if ($intCount >= $threshold) {
            return 'integer';
        }

        if ($floatCount >= $threshold || ($intCount + $floatCount) >= $threshold) {
            return 'float';
        }

        if ($boolCount >= $threshold && count(array_unique($nonEmpty)) <= 2) {
            return 'boolean';
        }

        return 'string';
    }

    /**
     * Detect type from SQL column type
     */
    protected function detectFromSqlType($baseType, $columnInfo) {
        $typeMap = [
            // Integer types
            'integer' => ['FieldtypeInteger', 90],
            'int' => ['FieldtypeInteger', 90],
            'bigint' => ['FieldtypeInteger', 90],
            'smallint' => ['FieldtypeInteger', 90],
            'mediumint' => ['FieldtypeInteger', 90],
            'tinyint' => ['FieldtypeInteger', 85],  // Lower confidence - could be boolean

            // Float/Decimal types
            'float' => ['FieldtypeFloat', 90],
            'double' => ['FieldtypeFloat', 90],
            'real' => ['FieldtypeFloat', 90],
            'decimal' => ['FieldtypeFloat', 90],
            'numeric' => ['FieldtypeFloat', 90],

            // Date/Time types
            'date' => ['FieldtypeDatetime', 95],
            'datetime' => ['FieldtypeDatetime', 95],
            'timestamp' => ['FieldtypeDatetime', 95],
            'time' => ['FieldtypeDatetime', 80],
            'year' => ['FieldtypeInteger', 85],

            // Text types
            'text' => ['FieldtypeTextarea', 85],
            'longtext' => ['FieldtypeTextarea', 90],
            'mediumtext' => ['FieldtypeTextarea', 85],
            'tinytext' => ['FieldtypeText', 80],

            // Binary types (store as text, user must handle)
            'blob' => ['FieldtypeTextarea', 60],
            'longblob' => ['FieldtypeTextarea', 60],
            'mediumblob' => ['FieldtypeTextarea', 60],
            'tinyblob' => ['FieldtypeTextarea', 60],
            'binary' => ['FieldtypeText', 60],
            'varbinary' => ['FieldtypeText', 60],

            // JSON type
            'json' => ['FieldtypeTextarea', 70],

            // Enum/Set types
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
     * Detect if this should be an options/select field
     * Pure data-based detection using uniqueness ratio and value patterns
     */
    protected function detectOptionsField($values) {
        $unique = array_unique($values);
        $total = count($values);
        $uniqueCount = count($unique);

        if ($uniqueCount === 0) {
            return ['is_options' => false];
        }

        // CRITICAL: Check if values are boolean BEFORE treating as options
        // Values like "true"/"false" or "0"/"1" should be FieldtypeCheckbox, NOT Options
        if ($uniqueCount <= 2) {
            $normalizedUnique = array_map(function($v) {
                return strtolower(trim($v));
            }, $unique);

            $booleanValues = ['0', '1', 'true', 'false', 'yes', 'no', 'ja', 'nein'];
            $allAreBooleanLike = true;

            foreach ($normalizedUnique as $val) {
                if (!in_array($val, $booleanValues)) {
                    $allAreBooleanLike = false;
                    break;
                }
            }

            // If all values are boolean-like, this should be a checkbox, not options
            if ($allAreBooleanLike) {
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
     * Pure data-based detection using SQL type and actual boolean values
     */
    protected function detectBooleanField($columnInfo, $values) {
        $sqlType = $columnInfo['type'] ?? '';

        // Check for tinyint(1) - MySQL standard for boolean
        $isTinyInt = stripos($sqlType, 'tinyint(1)') !== false;

        // Check values - must be EXACTLY 2 unique values AND they must be boolean-like
        $unique = array_unique($values);
        $uniqueCount = count($unique);

        // CRITICAL: Check if unique values are ACTUALLY boolean values
        // "1, 2" is NOT boolean, but "0, 1" IS boolean!
        $areBooleanValues = false;
        if ($uniqueCount <= 2 && $uniqueCount > 0) {
            $normalizedUnique = array_map(function($v) {
                return strtolower(trim((string)$v));
            }, $unique);

            // VALID boolean value sets
            $validBooleanSets = [
                ['0', '1'],           // 0/1
                ['true', 'false'],    // true/false
                ['yes', 'no'],        // yes/no
                ['ja', 'nein'],       // German
                ['0'],                // Only zeros
                ['1'],                // Only ones
                ['true'],             // Only true
                ['false'],            // Only false
            ];

            foreach ($validBooleanSets as $validSet) {
                if (count(array_diff($normalizedUnique, $validSet)) === 0) {
                    $areBooleanValues = true;
                    break;
                }
            }
        }

        // If values are NOT boolean-like (e.g., "1, 2" or "5, 10"), this is NOT a boolean field
        if ($uniqueCount <= 2 && !$areBooleanValues) {
            return ['is_boolean' => false];
        }

        // Strong indicator: tinyint(1) with boolean values - highest confidence
        if ($isTinyInt && $areBooleanValues) {
            return [
                'is_boolean' => true,
                'type' => 'boolean',
                'confidence' => 98,
                'fieldtype' => 'FieldtypeCheckbox',
                'patterns' => ['tinyint(1)', 'boolean_values']
            ];
        }

        // Values are literally boolean strings (true/false, yes/no, etc.)
        // This catches "true"/"false" from JSON/XML, regardless of field name
        if ($areBooleanValues) {
            return [
                'is_boolean' => true,
                'type' => 'boolean',
                'confidence' => 95,
                'fieldtype' => 'FieldtypeCheckbox',
                'patterns' => ['boolean_values']
            ];
        }

        // tinyint(1) alone (without confirming boolean values) - medium confidence
        // This handles SQL imports where tinyint(1) is the standard boolean type
        if ($isTinyInt && $uniqueCount <= 2) {
            return [
                'is_boolean' => true,
                'type' => 'boolean',
                'confidence' => 80,
                'fieldtype' => 'FieldtypeCheckbox',
                'patterns' => ['tinyint(1)']
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
