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
        'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/i',
        'url' => '/^https?:\/\/.+/i',
        'phone_de' => '/^(\+49|0)[0-9\s\-\/()]{7,}$/',
        'phone_intl' => '/^\+?[0-9\s\-\/()]{7,}$/',
        'date_iso' => '/^\d{4}-\d{2}-\d{2}$/',
        'date_de' => '/^\d{2}\.\d{2}\.\d{4}$/',
        'date_us' => '/^\d{2}\/\d{2}\/\d{4}$/',
        'datetime_iso' => '/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/',
        'time' => '/^\d{2}:\d{2}(:\d{2})?$/',
        'html' => '/<[a-z][\s\S]*>/i',
        'json' => '/^[\{\[].*[\}\]]$/s',
        'image_url' => '/\.(jpg|jpeg|png|gif|webp|svg)$/i',
        'file_url' => '/\.(pdf|doc|docx|xls|xlsx|zip|rar)$/i',
        'hex_color' => '/^#[0-9a-f]{3,6}$/i',
        'boolean' => '/^(0|1|true|false|yes|no|ja|nein)$/i',
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

        // Start with SQL type-based detection
        $result = $this->detectFromSqlType($baseType, $columnInfo);

        // Refine with pattern matching for string types
        if (in_array($baseType, ['string', 'text'])) {
            $patternResult = $this->detectFromPatterns($values);
            if ($patternResult['confidence'] > $result['confidence']) {
                $result = $patternResult;
            }
        }

        // Refine with column name hints
        $nameResult = $this->detectFromColumnName($columnName);
        if ($nameResult['confidence'] > 0) {
            // Combine confidences
            $result['confidence'] = min(100, $result['confidence'] + $nameResult['confidence'] * 0.3);
            if ($nameResult['fieldtype'] !== 'FieldtypeText') {
                $result['fieldtype'] = $nameResult['fieldtype'];
                $result['type'] = $nameResult['type'];
            }
        }

        // Check for options/select field
        $optionsResult = $this->detectOptionsField($values);
        if ($optionsResult['is_options']) {
            return $optionsResult;
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
            foreach ($sample as $value) {
                if (preg_match($pattern, $value)) {
                    $matches++;
                }
            }

            $percentage = ($matches / count($sample)) * 100;

            if ($percentage >= 70) {
                $patternMatches[$patternName] = $percentage;
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

        $fieldtypeMap = [
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

        return [
            'type' => $bestPattern,
            'confidence' => round($confidence),
            'fieldtype' => $fieldtypeMap[$bestPattern] ?? 'FieldtypeText',
            'patterns' => array_keys($patternMatches)
        ];
    }

    /**
     * Detect type from column name
     */
    protected function detectFromColumnName($columnName) {
        $name = strtolower($columnName);

        $namePatterns = [
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

        foreach ($namePatterns as $type => $patterns) {
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
    protected function detectOptionsField($values) {
        $unique = array_unique($values);
        $total = count($values);
        $uniqueCount = count($unique);

        // If less than 50 unique values and each value appears multiple times
        if ($uniqueCount > 0 && $uniqueCount <= 50 && $total / $uniqueCount >= 3) {
            return [
                'is_options' => true,
                'type' => 'options',
                'confidence' => 85,
                'fieldtype' => 'FieldtypeOptions',
                'patterns' => ['options'],
                'options' => array_values($unique),
                'options_count' => $uniqueCount
            ];
        }

        return ['is_options' => false];
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
