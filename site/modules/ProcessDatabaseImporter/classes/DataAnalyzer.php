<?php

namespace ProcessWire;

/**
 * Analyzes parsed data and provides insights about structure and types
 */
class DataAnalyzer extends WireData {

    protected $typeDetector;

    public function __construct() {
        parent::__construct();
        $this->typeDetector = new TypeDetector();
    }

    /**
     * Analyze table data and structure
     *
     * @param array $tableData Table data from parser
     * @param array $options Analysis options
     * @return array Analysis results
     */
    public function analyze($tableData, array $options = []) {
        $sampleSize = $options['sample_size'] ?? 100;

        $analysis = [
            'table_name' => $tableData['name'] ?? 'unknown',
            'row_count' => $tableData['row_count'] ?? count($tableData['data'] ?? []),
            'sample_count' => min($sampleSize, count($tableData['data'] ?? [])),
            'columns' => [],
            'primary_key' => $tableData['primary_key'] ?? null,
            'foreign_keys' => $tableData['foreign_keys'] ?? [],
            'suggested_template' => null,
            'suggested_parent' => '/',
        ];

        // Analyze each column
        $structure = $tableData['structure'] ?? [];
        $data = array_slice($tableData['data'] ?? [], 0, $sampleSize);

        foreach ($structure as $columnName => $columnInfo) {
            $columnAnalysis = $this->analyzeColumn(
                $columnName,
                $columnInfo,
                $data
            );

            $analysis['columns'][$columnName] = $columnAnalysis;
        }

        // Generate suggestions
        $analysis['suggested_template'] = $this->suggestTemplate($analysis);
        $analysis['suggested_title_field'] = $this->suggestTitleField($analysis);
        $analysis['suggested_name_field'] = $this->suggestNameField($analysis);

        return $analysis;
    }

    /**
     * Analyze a single column
     */
    protected function analyzeColumn($columnName, $columnInfo, $data) {
        // Extract sample values for this column
        $values = [];
        foreach ($data as $row) {
            if (isset($row[$columnName])) {
                $values[] = $row[$columnName];
            }
        }

        // Basic statistics
        $nonNullValues = array_filter($values, function($v) {
            return $v !== null && $v !== '';
        });

        $uniqueValues = array_unique($nonNullValues);

        $analysis = [
            'name' => $columnName,
            'sql_type' => $columnInfo['type'] ?? 'unknown',
            'base_type' => $columnInfo['base_type'] ?? 'string',
            'nullable' => $columnInfo['nullable'] ?? true,
            'auto_increment' => $columnInfo['auto_increment'] ?? false,
            'default' => $columnInfo['default'] ?? null,

            // Statistics
            'sample_count' => count($values),
            'null_count' => count($values) - count($nonNullValues),
            'unique_count' => count($uniqueValues),
            'null_percentage' => count($values) > 0
                ? round((count($values) - count($nonNullValues)) / count($values) * 100, 2)
                : 0,

            // Sample values
            'sample_values' => array_slice($nonNullValues, 0, 5),
        ];

        // String-specific analysis
        if (in_array($analysis['base_type'], ['string', 'text'])) {
            $lengths = array_map('strlen', $nonNullValues);
            $analysis['min_length'] = !empty($lengths) ? min($lengths) : 0;
            $analysis['max_length'] = !empty($lengths) ? max($lengths) : 0;
            $analysis['avg_length'] = !empty($lengths)
                ? round(array_sum($lengths) / count($lengths), 2)
                : 0;
        }

        // Numeric-specific analysis
        if (in_array($analysis['base_type'], ['integer', 'float', 'decimal'])) {
            $numericValues = array_filter($nonNullValues, 'is_numeric');
            if (!empty($numericValues)) {
                $analysis['min_value'] = min($numericValues);
                $analysis['max_value'] = max($numericValues);
                $analysis['avg_value'] = round(array_sum($numericValues) / count($numericValues), 2);
            }
        }

        // Detect ProcessWire field type
        $detection = $this->typeDetector->detect($nonNullValues, $columnName, $columnInfo);
        $analysis['detected_type'] = $detection['type'];
        $analysis['detection_confidence'] = $detection['confidence'];
        $analysis['suggested_fieldtype'] = $detection['fieldtype'];
        $analysis['patterns'] = $detection['patterns'] ?? [];

        // Special flags
        $analysis['is_likely_id'] = $this->isLikelyId($columnName, $columnInfo);
        $analysis['is_likely_foreign_key'] = $this->isLikelyForeignKey($columnName, $columnInfo);
        $analysis['is_likely_title'] = $this->isLikelyTitle($columnName, $analysis);
        $analysis['is_likely_name'] = $this->isLikelyName($columnName, $analysis);

        return $analysis;
    }

    /**
     * Check if column is likely an ID field
     */
    protected function isLikelyId($columnName, $columnInfo) {
        return (
            strtolower($columnName) === 'id' ||
            $columnInfo['auto_increment'] ?? false
        );
    }

    /**
     * Check if column is likely a foreign key
     */
    protected function isLikelyForeignKey($columnName, $columnInfo) {
        $name = strtolower($columnName);
        return (
            substr($name, -3) === '_id' ||
            substr($name, 0, 3) === 'id_' ||
            in_array($columnInfo['base_type'] ?? '', ['integer'])
        ) && $name !== 'id';
    }

    /**
     * Check if column is likely a title field
     */
    protected function isLikelyTitle($columnName, $analysis) {
        $name = strtolower($columnName);
        $titleNames = ['title', 'name', 'headline', 'subject', 'titel'];

        if (in_array($name, $titleNames)) {
            return true;
        }

        // Check if it's a non-null string with reasonable length
        if (in_array($analysis['base_type'], ['string', 'text'])) {
            if ($analysis['null_percentage'] < 10 &&
                ($analysis['avg_length'] ?? 0) > 5 &&
                ($analysis['avg_length'] ?? 0) < 100) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if column is likely a name/slug field
     */
    protected function isLikelyName($columnName, $analysis) {
        $name = strtolower($columnName);
        $namePatterns = ['slug', 'url_slug', 'url', 'permalink', 'path'];

        return in_array($name, $namePatterns);
    }

    /**
     * Suggest a template name based on table analysis
     */
    protected function suggestTemplate($analysis) {
        $tableName = $analysis['table_name'];

        // Clean up table name
        $template = strtolower($tableName);

        // Remove common prefixes
        $template = preg_replace('/^(tbl_|wp_|db_)/', '', $template);

        // Convert to singular if plural
        if (substr($template, -1) === 's') {
            $template = substr($template, 0, -1);
        }

        // Replace underscores with hyphens
        $template = str_replace('_', '-', $template);

        return $template;
    }

    /**
     * Suggest which field should be used for page title
     */
    protected function suggestTitleField($analysis) {
        $candidates = [];

        foreach ($analysis['columns'] as $column) {
            if ($column['is_likely_title']) {
                $candidates[] = [
                    'name' => $column['name'],
                    'score' => $this->scoreTitleCandidate($column)
                ];
            }
        }

        if (empty($candidates)) {
            // Fallback to first string column
            foreach ($analysis['columns'] as $column) {
                if (in_array($column['base_type'], ['string', 'text'])) {
                    return $column['name'];
                }
            }
            return null;
        }

        // Sort by score
        usort($candidates, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $candidates[0]['name'];
    }

    /**
     * Score a title field candidate
     */
    protected function scoreTitleCandidate($column) {
        $score = 0;

        // Name matches
        $name = strtolower($column['name']);
        if ($name === 'title') $score += 10;
        elseif ($name === 'name') $score += 8;
        elseif ($name === 'headline') $score += 7;

        // Low null percentage
        $score += (100 - $column['null_percentage']) / 10;

        // Reasonable length
        $avgLen = $column['avg_length'] ?? 0;
        if ($avgLen > 10 && $avgLen < 80) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Suggest which field should be used for page name
     */
    protected function suggestNameField($analysis) {
        foreach ($analysis['columns'] as $column) {
            if ($column['is_likely_name']) {
                return $column['name'];
            }
        }

        // Fallback: auto-generate from title
        return null; // null means auto-generate
    }

    /**
     * Analyze relationships between tables
     */
    public function analyzeRelationships($tables) {
        $relationships = [];

        foreach ($tables as $tableName => $tableData) {
            $foreignKeys = $tableData['foreign_keys'] ?? [];

            foreach ($foreignKeys as $column => $fk) {
                $relationships[] = [
                    'source_table' => $tableName,
                    'source_column' => $column,
                    'target_table' => $fk['table'],
                    'target_column' => $fk['column'],
                    'type' => 'foreign_key',
                    'cardinality' => 'many_to_one',
                ];
            }

            // Detect implicit foreign keys (columns ending with _id)
            if (isset($tableData['structure'])) {
                foreach ($tableData['structure'] as $columnName => $columnInfo) {
                    if (substr($columnName, -3) === '_id' && $columnName !== 'id') {
                        // Try to guess the referenced table
                        $referencedTable = substr($columnName, 0, -3);

                        // Check if that table exists (add 's' for plural)
                        $possibleTables = [
                            $referencedTable,
                            $referencedTable . 's',
                            $referencedTable . 'es',
                        ];

                        foreach ($possibleTables as $targetTable) {
                            if (isset($tables[$targetTable])) {
                                $relationships[] = [
                                    'source_table' => $tableName,
                                    'source_column' => $columnName,
                                    'target_table' => $targetTable,
                                    'target_column' => 'id',
                                    'type' => 'implicit_foreign_key',
                                    'cardinality' => 'many_to_one',
                                ];
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $relationships;
    }
}
