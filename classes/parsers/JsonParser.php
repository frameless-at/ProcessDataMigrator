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
    protected $childTables = []; // Track child tables (arrays within objects)

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
        $maxRows = $options['max_rows'] ?? 0; // 0 = unlimited

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
        // FIX: Pass maxRows for data limiting (sampleSize is only for analysis)
        $this->extractTables($data, $tableName, $maxRows);

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
     * @param int $maxRows Maximum rows to store (0 = unlimited)
     */
    protected function extractTables($data, $defaultTableName, $maxRows) {
        // Case 1: Array of objects [{...}, {...}]
        if ($this->isArrayOfObjects($data)) {
            $this->createTableFromArray($defaultTableName, $data, $maxRows);
            return;
        }

        // Case 2: Object with arrays {"users": [{...}], "orders": [{...}]}
        if (is_array($data) && !$this->isSequentialArray($data)) {
            foreach ($data as $key => $value) {
                if ($this->isArrayOfObjects($value)) {
                    $this->createTableFromArray($key, $value, $maxRows);
                } elseif (is_array($value) && $this->isSequentialArray($value)) {
                    // Simple array - convert to objects with single column
                    $this->createTableFromSimpleArray($key, $value, $maxRows);
                }
            }
            return;
        }

        // Case 3: Single object {...}
        if (is_array($data) && !$this->isSequentialArray($data)) {
            $this->createTableFromArray($defaultTableName, [$data], $maxRows);
            return;
        }

        if ($this->logger) $this->logger->logWarning("Could not determine JSON structure");
    }

    /**
     * Create table from array of objects
     * Also extracts nested arrays as separate child tables with FK relations
     * @param int $maxRows Maximum rows to store (0 = unlimited)
     */
    protected function createTableFromArray($tableName, $array, $maxRows) {
        if (empty($array)) {
            return;
        }

        // Detect primary key field from data
        $pkField = 'id';
        $first = reset($array);
        if (is_array($first)) {
            if (isset($first['id'])) {
                $pkField = 'id';
            } else {
                // Find first field ending with _id
                foreach (array_keys($first) as $key) {
                    if (preg_match('/_id$/i', $key)) {
                        $pkField = $key;
                        break;
                    }
                }
            }
        }

        // Flatten nested objects and collect all unique keys
        // Also detect and extract child arrays
        $allKeys = [];
        $flattenedData = [];
        $childArrays = []; // Arrays found in objects

        foreach ($array as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            // Get the parent ID for FK relations
            $parentId = $item[$pkField] ?? $index;

            // Flatten and extract child arrays
            $flattened = $this->flattenObjectAndExtractArrays($item, $parentId, $tableName, $childArrays);
            $flattenedData[] = $flattened;

            foreach (array_keys($flattened) as $key) {
                if (!in_array($key, $allKeys)) {
                    $allKeys[] = $key;
                }
            }

            // FIX: Stop at max_rows (0 = unlimited), NOT sample_size
            // sample_size limiting is done in DataAnalyzer
            if ($maxRows > 0 && count($flattenedData) >= $maxRows) {
                break;
            }
        }

        // Create child tables from extracted arrays
        foreach ($childArrays as $childName => $childData) {
            $this->createChildTable($tableName, $childName, $childData, $maxRows);
        }

        // Build structure
        $structure = [];
        foreach ($allKeys as $columnName) {
            $structure[$columnName] = [
                'name' => $columnName,
                'type' => 'string',
                'base_type' => 'string',
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
     * @param int $maxRows Maximum rows to store (0 = unlimited)
     */
    protected function createTableFromSimpleArray($tableName, $array, $maxRows) {
        $data = [];
        $count = 0;

        foreach ($array as $index => $value) {
            $data[] = [
                'id' => $index,
                'value' => is_scalar($value) ? $value : json_encode($value),
            ];

            $count++;
            // FIX: Stop at max_rows (0 = unlimited)
            if ($maxRows > 0 && $count >= $maxRows) {
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
     * Flatten nested object AND extract arrays as separate tables
     *
     * @param array $object Object to flatten
     * @param mixed $parentId Parent ID for FK relations
     * @param string $parentTable Parent table name
     * @param array &$childArrays Reference to collect child arrays
     * @param string $prefix Prefix for nested keys
     * @return array Flattened object (without arrays)
     */
    protected function flattenObjectAndExtractArrays($object, $parentId, $parentTable, &$childArrays, $prefix = '') {
        $result = [];

        foreach ($object as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;

            // CRITICAL: Detect arrays of objects - extract as child table
            if (is_array($value) && $this->isArrayOfObjects($value)) {
                // This is an array of objects - extract to separate table
                $childTableName = $key;

                if (!isset($childArrays[$childTableName])) {
                    $childArrays[$childTableName] = [];
                }

                // Add parent_id to each child item
                foreach ($value as $childItem) {
                    if (is_array($childItem)) {
                        $childItem[$parentTable . '_id'] = $parentId;
                        $childArrays[$childTableName][] = $childItem;
                    }
                }

                // DON'T add to result - this field becomes a separate table
                continue;
            }

            // Nested object (not array of objects) - flatten recursively
            if (is_array($value) && !$this->isSequentialArray($value)) {
                $result = array_merge($result, $this->flattenObjectAndExtractArrays(
                    $value,
                    $parentId,
                    $parentTable,
                    $childArrays,
                    $newKey
                ));
            } else {
                // Scalar value or simple array
                if (is_array($value)) {
                    // Simple sequential array (not objects) - keep as JSON for now
                    $result[$newKey] = json_encode($value);
                } elseif (is_bool($value)) {
                    $result[$newKey] = $value ? 'true' : 'false';
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
     * Flatten nested object to dot notation (legacy method, kept for compatibility)
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
     * Create child table from extracted array
     *
     * @param string $parentTable Parent table name
     * @param string $childName Child array name (e.g., "items")
     * @param array $childData Array of child objects with parent_id
     * @param int $maxRows Maximum rows to store (0 = unlimited)
     */
    protected function createChildTable($parentTable, $childName, $childData, $maxRows) {
        if (empty($childData)) {
            return;
        }

        // Generate child table name: parent_childname (e.g., "order_items")
        $childTableName = $parentTable . '_' . $childName;

        // Flatten child objects
        $allKeys = [];
        $flattenedData = [];

        foreach ($childData as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            // Add auto-increment ID if not present
            if (!isset($item['id'])) {
                $item['id'] = $index + 1;
            }

            // Flatten nested objects in child items
            $childArraysIgnored = []; // We don't support 3+ levels
            $flattened = $this->flattenObjectAndExtractArrays(
                $item,
                null,
                $childTableName,
                $childArraysIgnored
            );

            $flattenedData[] = $flattened;

            foreach (array_keys($flattened) as $key) {
                if (!in_array($key, $allKeys)) {
                    $allKeys[] = $key;
                }
            }

            // FIX: Stop at max_rows (0 = unlimited)
            if ($maxRows > 0 && count($flattenedData) >= $maxRows) {
                break;
            }
        }

        // Build structure
        $structure = [];
        foreach ($allKeys as $columnName) {
            $structure[$columnName] = [
                'name' => $columnName,
                'type' => 'string',
                'base_type' => 'string',
                'nullable' => true,
                'auto_increment' => false,
                'default' => null,
            ];
        }

        // Find primary key
        $primaryKey = null;
        if (in_array('id', $allKeys)) {
            $primaryKey = 'id';
        }

        $this->tables[$childTableName] = [
            'name' => $childTableName,
            'structure' => $structure,
            'data' => $flattenedData,
            'row_count' => count($childData),
            'primary_key' => $primaryKey,
            'parent_table' => $parentTable, // Mark this as a child table
            'fk_field' => $parentTable . '_id', // FK field name
        ];

        // Track this as a child table
        $this->childTables[$childTableName] = [
            'parent' => $parentTable,
            'fk_field' => $parentTable . '_id',
        ];
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

    /**
     * Get child tables info (for FK detection)
     *
     * @return array Array of child table info: ['child_table' => ['parent' => 'parent_table', 'fk_field' => 'parent_id']]
     */
    public function getChildTables() {
        return $this->childTables;
    }
}
