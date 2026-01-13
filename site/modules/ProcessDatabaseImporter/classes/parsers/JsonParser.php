<?php

namespace ProcessWire;

/**
 * JSON Parser
 * Parses JSON files and extracts tabular data
 * Supports multiple formats:
 * - Array of objects: [{...}, {...}]
 * - Object with arrays: {"users": [{...}], "orders": [{...}]}
 * - Single object: {...}
 */
class JsonParser extends AbstractParser {

    protected $metadata = [];
    protected $tables = [];
    protected $logger = null;

    /**
     * Set logger instance
     */
    public function setLogger($logger) {
        $this->logger = $logger;
    }

    /**
     * Check if this parser can handle the given file
     */
    public function canParse($file) {
        if (!file_exists($file)) {
            return false;
        }

        // Check file extension
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            return false;
        }

        // Try to decode JSON
        $content = file_get_contents($file);
        if ($content === false) {
            return false;
        }

        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Parse JSON file
     *
     * @param string $file File path
     * @param array $options Options:
     *   - table_name: Name for the virtual table (default: filename)
     *   - sample_size: number of rows to analyze (default: 100)
     * @return array Parsed data structure
     */
    public function parse($file, array $options = []) {
        if (!file_exists($file)) {
            $this->setError("File not found: $file");
            return [];
        }

        $tableName = $options['table_name'] ?? pathinfo($file, PATHINFO_FILENAME);
        $sampleSize = $options['sample_size'] ?? 100;

        $content = file_get_contents($file);
        if ($content === false) {
            $this->setError("Cannot read file: $file");
            return [];
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->setError("JSON decode error: " . json_last_error_msg());
            return [];
        }

        // Determine JSON structure and extract tables
        $this->extractTables($data, $tableName, $sampleSize);

        // Build metadata
        $totalRows = 0;
        foreach ($this->tables as $table) {
            $totalRows += $table['row_count'];
        }

        $this->metadata = [
            'file' => basename($file),
            'size' => filesize($file),
            'tables' => count($this->tables),
            'total_rows' => $totalRows,
            'parsed_at' => date('Y-m-d H:i:s'),
        ];

        return $this->tables;
    }

    /**
     * Extract tables from JSON data
     */
    protected function extractTables($data, $defaultTableName, $sampleSize) {
        // Case 1: Array of objects [{...}, {...}]
        if ($this->isArrayOfObjects($data)) {
            $this->createTableFromArray($defaultTableName, $data, $sampleSize);
            return;
        }

        // Case 2: Object with arrays {"users": [{...}], "orders": [{...}]}
        if (is_array($data) && !$this->isSequentialArray($data)) {
            foreach ($data as $key => $value) {
                if ($this->isArrayOfObjects($value)) {
                    $this->createTableFromArray($key, $value, $sampleSize);
                } elseif (is_array($value) && $this->isSequentialArray($value)) {
                    // Simple array - convert to objects with single column
                    $this->createTableFromSimpleArray($key, $value, $sampleSize);
                }
            }
            return;
        }

        // Case 3: Single object {...}
        if (is_array($data) && !$this->isSequentialArray($data)) {
            $this->createTableFromArray($defaultTableName, [$data], $sampleSize);
            return;
        }

        if ($this->logger) $this->logger->logWarning("Could not determine JSON structure");
    }

    /**
     * Create table from array of objects
     */
    protected function createTableFromArray($tableName, $array, $sampleSize) {
        if (empty($array)) {
            return;
        }

        // Flatten nested objects and collect all unique keys
        $allKeys = [];
        $flattenedData = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                continue;
            }

            $flattened = $this->flattenObject($item);
            $flattenedData[] = $flattened;

            foreach (array_keys($flattened) as $key) {
                if (!in_array($key, $allKeys)) {
                    $allKeys[] = $key;
                }
            }

            // Stop at sample size for structure detection
            if (count($flattenedData) >= $sampleSize) {
                break;
            }
        }

        // Build structure
        // Analyze each column to detect base type
        $structure = [];
        foreach ($allKeys as $columnName) {
            // Collect values for this column from flattened data
            $columnValues = array_column($flattenedData, $columnName);

            // Detect base type from actual data
            $baseType = $this->detectBaseType($columnValues);

            $structure[$columnName] = [
                'name' => $columnName,
                'type' => $baseType,
                'base_type' => $baseType,
                'nullable' => true,
                'auto_increment' => false,
                'default' => null,
            ];
        }

        // Guess primary key
        $primaryKey = null;
        foreach ($allKeys as $key) {
            if (strtolower($key) === 'id' || preg_match('/_id$/i', $key)) {
                $primaryKey = $key;
                break;
            }
        }

        $this->tables[$tableName] = [
            'name' => $tableName,
            'structure' => $structure,
            'data' => $flattenedData,
            'row_count' => count($array),
            'primary_key' => $primaryKey,
        ];
    }

    /**
     * Create table from simple array
     */
    protected function createTableFromSimpleArray($tableName, $array, $sampleSize) {
        $data = [];
        $count = 0;

        foreach ($array as $index => $value) {
            $data[] = [
                'id' => $index,
                'value' => is_scalar($value) ? $value : json_encode($value),
            ];

            $count++;
            if ($count >= $sampleSize) {
                break;
            }
        }

        $structure = [
            'id' => [
                'name' => 'id',
                'type' => 'integer',
                'base_type' => 'integer',
                'nullable' => false,
                'auto_increment' => false,
                'default' => null,
            ],
            'value' => [
                'name' => 'value',
                'type' => 'string',
                'base_type' => 'string',
                'nullable' => true,
                'auto_increment' => false,
                'default' => null,
            ],
        ];

        $this->tables[$tableName] = [
            'name' => $tableName,
            'structure' => $structure,
            'data' => $data,
            'row_count' => count($array),
            'primary_key' => 'id',
        ];
    }

    /**
     * Flatten nested object to dot notation
     * Example: {user: {name: "John"}} => {user.name: "John"}
     */
    protected function flattenObject($object, $prefix = '') {
        $result = [];

        foreach ($object as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;

            if (is_array($value) && !$this->isSequentialArray($value)) {
                // Nested object - flatten recursively
                $result = array_merge($result, $this->flattenObject($value, $newKey));
            } else {
                // Scalar value or array - store as string
                if (is_array($value)) {
                    $result[$newKey] = json_encode($value);
                } elseif (is_bool($value)) {
                    $result[$newKey] = $value ? '1' : '0';
                } elseif ($value === null) {
                    $result[$newKey] = null;
                } else {
                    $result[$newKey] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Check if array is sequential (0, 1, 2, ...)
     */
    protected function isSequentialArray($array) {
        if (!is_array($array) || empty($array)) {
            return false;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Check if data is array of objects
     */
    protected function isArrayOfObjects($data) {
        if (!is_array($data) || empty($data)) {
            return false;
        }

        if (!$this->isSequentialArray($data)) {
            return false;
        }

        // Check if first item is object (associative array)
        $first = reset($data);
        return is_array($first) && !$this->isSequentialArray($first);
    }

    /**
     * Get metadata
     */
    public function getMetadata() {
        return $this->metadata;
    }

    /**
     * Get parsed tables
     */
    public function getTables() {
        return $this->tables;
    }

    /**
     * Get specific table data
     */
    public function getTable($tableName) {
        return $this->tables[$tableName] ?? null;
    }
}
