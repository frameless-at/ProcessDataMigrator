<?php

namespace ProcessWire;

/**
 * SQL Parser for MySQL/MariaDB dumps
 * Extracts table structures and INSERT statements
 */
class SqlParser extends AbstractParser {

    protected $metadata = [];
    protected $tables = [];

    /**
     * Check if this parser can handle the given file
     */
    public function canParse($file) {
        if (!file_exists($file)) {
            return false;
        }

        // Check file extension
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            return false;
        }

        // Check if file contains SQL keywords
        $handle = fopen($file, 'r');
        if (!$handle) {
            return false;
        }

        $firstLines = '';
        for ($i = 0; $i < 10; $i++) {
            $line = fgets($handle);
            if ($line === false) break;
            $firstLines .= $line;
        }
        fclose($handle);

        return (
            stripos($firstLines, 'CREATE TABLE') !== false ||
            stripos($firstLines, 'INSERT INTO') !== false
        );
    }

    /**
     * Parse SQL dump file
     *
     * @param string $file File path
     * @param array $options Options:
     *   - table_filter: array of table names to import (empty = all)
     *   - max_rows: maximum rows per table (0 = all)
     *   - sample_size: number of rows to analyze for type detection
     * @return array Parsed data structure
     */
    public function parse($file, array $options = []) {
        if (!file_exists($file)) {
            $this->setError("File not found: $file");
            return [];
        }

        $tableFilter = $options['table_filter'] ?? [];
        $maxRows = $options['max_rows'] ?? 0;
        $sampleSize = $options['sample_size'] ?? 100;

        $this->tables = [];
        $currentTable = null;
        $currentCreateStatement = '';
        $inCreateStatement = false;
        $currentInsertStatement = '';
        $inInsertStatement = false;
        $currentInsertTable = null;

        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->setError("Cannot open file: $file");
            return [];
        }

        $lineNumber = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);

            // Skip comments and empty lines (but not inside statements)
            if (!$inCreateStatement && !$inInsertStatement) {
                if (empty($line) ||
                    substr($line, 0, 2) === '--' ||
                    substr($line, 0, 1) === '#') {
                    continue;
                }
            }

            // Handle CREATE TABLE statements
            if (stripos($line, 'CREATE TABLE') !== false) {
                $inCreateStatement = true;
                $currentCreateStatement = $line . "\n";

                // Extract table name
                if (preg_match('/CREATE TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $line, $matches)) {
                    $tableName = $matches[1];

                    // Check table filter
                    if (!empty($tableFilter) && !in_array($tableName, $tableFilter)) {
                        $currentTable = null;
                        continue;
                    }

                    $currentTable = $tableName;
                    $this->tables[$currentTable] = [
                        'name' => $currentTable,
                        'structure' => [],
                        'data' => [],
                        'row_count' => 0,
                        'primary_key' => null,
                        'foreign_keys' => [],
                    ];
                }
                continue;
            }

            // Continue collecting CREATE TABLE statement
            if ($inCreateStatement) {
                $currentCreateStatement .= $line . "\n";

                // End of CREATE TABLE
                if (substr($line, -1) === ';') {
                    $inCreateStatement = false;
                    if ($currentTable) {
                        $this->parseCreateTable($currentTable, $currentCreateStatement);
                    }
                    $currentCreateStatement = '';
                }
                continue;
            }

            // Handle INSERT statements - start
            if (stripos($line, 'INSERT INTO') !== false) {
                $inInsertStatement = true;
                $currentInsertStatement = $line . "\n";

                // Extract table name
                if (preg_match('/INSERT INTO\s+`?([a-zA-Z0-9_]+)`?/i', $line, $matches)) {
                    $currentInsertTable = $matches[1];
                }

                // Check if statement ends on same line
                if (substr($line, -1) === ';') {
                    $inInsertStatement = false;
                    $this->processInsertStatement($currentInsertStatement, $currentInsertTable, $maxRows, $sampleSize);
                    $currentInsertStatement = '';
                    $currentInsertTable = null;
                }
                continue;
            }

            // Continue collecting INSERT statement
            if ($inInsertStatement) {
                $currentInsertStatement .= $line . "\n";

                // End of INSERT
                if (substr($line, -1) === ';') {
                    $inInsertStatement = false;
                    $this->processInsertStatement($currentInsertStatement, $currentInsertTable, $maxRows, $sampleSize);
                    $currentInsertStatement = '';
                    $currentInsertTable = null;
                }
                continue;
            }
        }

        fclose($handle);

        // Build metadata
        $this->metadata = [
            'file' => basename($file),
            'size' => filesize($file),
            'tables' => count($this->tables),
            'total_rows' => array_sum(array_column($this->tables, 'row_count')),
            'parsed_at' => date('Y-m-d H:i:s'),
        ];

        return $this->tables;
    }

    /**
     * Parse CREATE TABLE statement to extract structure
     */
    protected function parseCreateTable($tableName, $sql) {
        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip CREATE TABLE line and closing parenthesis
            if (stripos($line, 'CREATE TABLE') !== false ||
                $line === ');' ||
                empty($line)) {
                continue;
            }

            // Remove trailing comma
            $line = rtrim($line, ',');

            // Parse column definition
            // Match column name and type (including enum/set with values)
            if (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+([a-z]+(?:\([^)]+\))?(?:\s+unsigned)?)/i', $line, $matches)) {
                $columnName = $matches[1];
                $columnType = strtolower(trim($matches[2]));

                // Skip special keywords
                if (in_array(strtoupper($columnName), ['PRIMARY', 'KEY', 'UNIQUE', 'INDEX', 'CONSTRAINT', 'FOREIGN'])) {
                    // Extract primary key
                    if (stripos($line, 'PRIMARY KEY') !== false) {
                        if (preg_match('/PRIMARY KEY\s+\(`?([a-zA-Z0-9_]+)`?\)/i', $line, $pkMatches)) {
                            $this->tables[$tableName]['primary_key'] = $pkMatches[1];
                        }
                    }

                    // Extract foreign keys
                    if (stripos($line, 'FOREIGN KEY') !== false) {
                        if (preg_match('/FOREIGN KEY\s+\(`?([a-zA-Z0-9_]+)`?\)\s+REFERENCES\s+`?([a-zA-Z0-9_]+)`?\s+\(`?([a-zA-Z0-9_]+)`?\)/i', $line, $fkMatches)) {
                            $this->tables[$tableName]['foreign_keys'][$fkMatches[1]] = [
                                'table' => $fkMatches[2],
                                'column' => $fkMatches[3],
                            ];
                        }
                    }
                    continue;
                }

                // Parse MySQL types to basic types
                $baseType = $this->parseColumnType($columnType);

                // Check for NULL/NOT NULL
                $nullable = stripos($line, 'NOT NULL') === false;

                // Check for AUTO_INCREMENT
                $autoIncrement = stripos($line, 'AUTO_INCREMENT') !== false;

                // Check for DEFAULT value
                $default = null;
                if (preg_match('/DEFAULT\s+([^\s,]+)/i', $line, $defaultMatches)) {
                    $default = trim($defaultMatches[1], "'\"");
                }

                $this->tables[$tableName]['structure'][$columnName] = [
                    'name' => $columnName,
                    'type' => $columnType,
                    'base_type' => $baseType,
                    'nullable' => $nullable,
                    'auto_increment' => $autoIncrement,
                    'default' => $default,
                ];
            }
        }
    }

    /**
     * Parse MySQL column type to basic type
     */
    protected function parseColumnType($type) {
        $type = strtolower($type);

        // Extract base type (remove size/precision)
        if (preg_match('/^([a-z]+)/', $type, $matches)) {
            $baseType = $matches[1];

            // Map to basic types
            $typeMap = [
                'int' => 'integer',
                'tinyint' => 'integer',
                'smallint' => 'integer',
                'mediumint' => 'integer',
                'bigint' => 'integer',
                'decimal' => 'decimal',
                'float' => 'float',
                'double' => 'float',
                'varchar' => 'string',
                'char' => 'string',
                'text' => 'text',
                'tinytext' => 'text',
                'mediumtext' => 'text',
                'longtext' => 'text',
                'date' => 'date',
                'datetime' => 'datetime',
                'timestamp' => 'datetime',
                'time' => 'time',
                'year' => 'integer',
                'enum' => 'enum',
                'set' => 'set',
                'blob' => 'binary',
                'json' => 'json',
            ];

            return $typeMap[$baseType] ?? 'string';
        }

        return 'string';
    }

    /**
     * Process complete INSERT statement (potentially multiline)
     */
    protected function processInsertStatement($sql, $tableName, $maxRows, $sampleSize) {
        // Check if we're tracking this table
        if (!isset($this->tables[$tableName])) {
            return;
        }

        // CRITICAL: Check SAMPLE SIZE limit, not max_rows
        // max_rows only limits IMPORT, not ANALYSIS
        // We need enough data for accurate type detection
        if ($sampleSize > 0 && $this->tables[$tableName]['row_count'] >= $sampleSize) {
            return;
        }

        // Parse INSERT statement
        $rows = $this->parseInsertStatement($sql, $tableName);

        foreach ($rows as $row) {
            // Store rows up to sample_size for analysis
            if ($this->tables[$tableName]['row_count'] < $sampleSize) {
                $this->tables[$tableName]['data'][] = $row;
            }
            $this->tables[$tableName]['row_count']++;

            // Stop when we have enough data for analysis
            if ($sampleSize > 0 && $this->tables[$tableName]['row_count'] >= $sampleSize) {
                break;
            }
        }
    }

    /**
     * Parse INSERT statement to extract rows
     */
    protected function parseInsertStatement($sql, $tableName) {
        $rows = [];

        // Extract column names if specified
        $columns = null;
        if (preg_match('/INSERT INTO\s+`?[a-zA-Z0-9_]+`?\s+\(([^)]+)\)/i', $sql, $matches)) {
            $columnStr = $matches[1];
            $columns = array_map(function($col) {
                return trim($col, '` ');
            }, explode(',', $columnStr));
        } else {
            // Use structure columns if available
            if (isset($this->tables[$tableName]['structure'])) {
                $columns = array_keys($this->tables[$tableName]['structure']);
            }
        }

        // Extract VALUES
        if (preg_match('/VALUES\s+(.+);?$/is', $sql, $matches)) {
            $valuesStr = rtrim($matches[1], ';');

            // Parse rows character by character to handle parentheses in strings correctly
            $rows = $this->extractRows($valuesStr);

            foreach ($rows as $rowStr) {
                $values = $this->parseRowValues($rowStr);

                if ($columns && count($columns) === count($values)) {
                    $rows_processed[] = array_combine($columns, $values);
                } else {
                    // If no columns or mismatch, use numeric keys
                    $rows_processed[] = $values;
                }
            }

            return $rows_processed ?? [];
        }

        return $rows;
    }

    /**
     * Extract individual row strings from VALUES clause
     * Handles parentheses inside quoted strings correctly
     */
    protected function extractRows($valuesStr) {
        $rows = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = null;
        $escaped = false;

        $len = strlen($valuesStr);
        for ($i = 0; $i < $len; $i++) {
            $char = $valuesStr[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                $current .= $char;
                continue;
            }

            // Handle string delimiters
            if (($char === "'" || $char === '"') && !$inString) {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                continue;
            }

            if ($char === $stringChar && $inString) {
                $inString = false;
                $stringChar = null;
                $current .= $char;
                continue;
            }

            // Only count parentheses outside of strings
            if (!$inString) {
                if ($char === '(') {
                    $depth++;
                    if ($depth === 1) {
                        // Start of a new row, don't include the opening paren
                        continue;
                    }
                } elseif ($char === ')') {
                    $depth--;
                    if ($depth === 0) {
                        // End of this row
                        $rows[] = $current;
                        $current = '';
                        continue;
                    }
                }
            }

            $current .= $char;
        }

        return $rows;
    }

    /**
     * Parse row values from INSERT statement
     */
    protected function parseRowValues($str) {
        $values = [];
        $current = '';
        $inString = false;
        $stringChar = null;
        $escaped = false;

        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $char = $str[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if (($char === "'" || $char === '"') && !$inString) {
                $inString = true;
                $stringChar = $char;
                continue;
            }

            if ($char === $stringChar && $inString) {
                $inString = false;
                $stringChar = null;
                continue;
            }

            if ($char === ',' && !$inString) {
                $values[] = $this->normalizeValue(trim($current));
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Add last value
        if (trim($current) !== '') {
            $values[] = $this->normalizeValue(trim($current));
        }

        return $values;
    }

    /**
     * Normalize parsed value
     */
    protected function normalizeValue($value) {
        // NULL
        if (strtoupper($value) === 'NULL') {
            return null;
        }

        // Remove quotes
        if ((substr($value, 0, 1) === "'" && substr($value, -1) === "'") ||
            (substr($value, 0, 1) === '"' && substr($value, -1) === '"')) {
            $value = substr($value, 1, -1);
        }

        // Unescape
        $value = str_replace(['\\\'', '\\"', '\\\\'], ["'", '"', '\\'], $value);

        return $value;
    }

    /**
     * Get metadata about parsed data
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
