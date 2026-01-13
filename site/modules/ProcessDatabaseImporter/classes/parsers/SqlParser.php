<?php

namespace ProcessWire;

/**
 * SQL Parser for MySQL/MariaDB dumps
 * Extracts table structures and INSERT statements
 */
class SqlParser extends AbstractParser {

    protected $metadata = [];
    protected $tables = [];
    protected $logger = null;

    /**
     * Set logger instance
     *
     * @param Logger $logger
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
        if ($ext !== 'sql') {
            return false;
        }

        // Check if file contains SQL keywords
        $handle = fopen($file, 'r');
        if (!$handle) {
            return false;
        }

        $firstLines = '';
        // Read more lines to handle phpMyAdmin headers with SET statements
        for ($i = 0; $i < 50; $i++) {
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

        // MEMORY MANAGEMENT: Check file size and available memory
        $fileSize = filesize($file);
        $memoryLimit = $this->getMemoryLimit();
        $memoryUsage = memory_get_usage(true);
        $availableMemory = $memoryLimit - $memoryUsage;

        if ($this->logger) $this->logger->logInfo(sprintf(
            'Memory Check: File=%s, MemLimit=%s, Used=%s, Available=%s',
            $this->formatBytes($fileSize),
            $this->formatBytes($memoryLimit),
            $this->formatBytes($memoryUsage),
            $this->formatBytes($availableMemory)
        ));

        // Warn if file size exceeds 50% of available memory
        if ($fileSize > ($availableMemory * 0.5)) {
            wire()->warning(sprintf(
                'Large file detected (%s). This may require significant memory. Consider using a smaller sample_size.',
                $this->formatBytes($fileSize)
            ));
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

                // Skip MySQL-specific commands from phpMyAdmin dumps
                if (stripos($line, 'SET ') === 0 ||
                    stripos($line, 'START TRANSACTION') === 0 ||
                    stripos($line, 'COMMIT') === 0 ||
                    preg_match('/^\/\*!?\d*.*\*\/;?$/', $line)) {
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

                        // FALLBACK: If no primary key was found, try to guess it
                        if (!isset($this->tables[$currentTable]['primary_key'])) {
                            $this->guessPrimaryKey($currentTable);
                        }
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
                    continue;
                }

                // Parse MySQL types to basic types
                $baseType = $this->parseColumnType($columnType);

                // Extract enum/set values if present
                $enumValues = null;
                if (in_array($baseType, ['enum', 'set'])) {
                    $enumValues = $this->extractEnumValues($columnType);
                }

                // Check for NULL/NOT NULL
                $nullable = stripos($line, 'NOT NULL') === false;

                // Check for AUTO_INCREMENT
                $autoIncrement = stripos($line, 'AUTO_INCREMENT') !== false;

                // Check for inline PRIMARY KEY definition
                $isPrimaryKey = stripos($line, 'PRIMARY KEY') !== false;
                if ($isPrimaryKey && !isset($this->tables[$tableName]['primary_key'])) {
                    $this->tables[$tableName]['primary_key'] = $columnName;
                }

                // AUTO_INCREMENT fields are typically primary keys
                // Set as PK if no explicit PK has been defined yet
                if ($autoIncrement && !isset($this->tables[$tableName]['primary_key'])) {
                    $this->tables[$tableName]['primary_key'] = $columnName;
                }

                // Check for DEFAULT value
                $default = null;
                if (preg_match('/DEFAULT\s+([^\s,]+)/i', $line, $defaultMatches)) {
                    $default = trim($defaultMatches[1], "'\"");
                }

                $columnDef = [
                    'name' => $columnName,
                    'type' => $columnType,
                    'base_type' => $baseType,
                    'nullable' => $nullable,
                    'auto_increment' => $autoIncrement,
                    'default' => $default,
                ];

                // Add enum values if present
                if ($enumValues !== null) {
                    $columnDef['enum_values'] = $enumValues;
                }

                $this->tables[$tableName]['structure'][$columnName] = $columnDef;
            }
        }
    }

    /**
     * Guess primary key when none is explicitly defined
     * Uses naming conventions and field properties
     */
    protected function guessPrimaryKey($tableName) {
        if (!isset($this->tables[$tableName]['structure'])) {
            return;
        }

        $structure = $this->tables[$tableName]['structure'];
        $candidates = [];

        foreach ($structure as $columnName => $columnInfo) {
            $name = strtolower($columnName);
            $score = 0;

            // Must be integer type
            if (($columnInfo['base_type'] ?? '') !== 'integer') {
                continue;
            }

            // Strong indicators (high score)
            if ($name === 'id') {
                $score += 100;
            } elseif (preg_match('/^(.+)_id$/', $name, $matches)) {
                // table_id pattern
                $score += 80;
            } elseif (preg_match('/^id_(.+)$/', $name)) {
                // id_table pattern
                $score += 70;
            } elseif (substr($name, -2) === 'id') {
                // ends with id (e.g., comid, userid)
                $score += 50;
            }

            // Prefer NOT NULL fields
            if (!($columnInfo['nullable'] ?? true)) {
                $score += 10;
            }

            // AUTO_INCREMENT is a strong indicator (but we already handle this elsewhere)
            if ($columnInfo['auto_increment'] ?? false) {
                $score += 200;
            }

            if ($score > 0) {
                $candidates[] = [
                    'name' => $columnName,
                    'score' => $score
                ];
            }
        }

        if (!empty($candidates)) {
            // Sort by score (highest first)
            usort($candidates, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            // Use the highest scoring candidate
            $this->tables[$tableName]['primary_key'] = $candidates[0]['name'];
            if ($this->logger) $this->logger->logDebug("\n=== PRIMARY KEY GUESSING ===");
            if ($this->logger) $this->logger->logDebug("Table: $tableName");
            if ($this->logger) $this->logger->logDebug("Guessed PK: {$candidates[0]['name']} (score: {$candidates[0]['score']})");
            if ($this->logger) $this->logger->logDebug("All candidates: " . json_encode($candidates));
        } else {
            // Last resort: use first integer NOT NULL field
            foreach ($structure as $columnName => $columnInfo) {
                if (($columnInfo['base_type'] ?? '') === 'integer' && !($columnInfo['nullable'] ?? true)) {
                    $this->tables[$tableName]['primary_key'] = $columnName;
                    if ($this->logger) $this->logger->logDebug("Fallback: Using first integer NOT NULL field as PK for $tableName: $columnName");
                    return;
                }
            }

            if ($this->logger) $this->logger->logDebug("WARNING: Could not guess primary key for $tableName");
        }
    }

    /**
     * Extract enum/set values from column type definition
     * e.g., enum('active','inactive','pending') => ['active', 'inactive', 'pending']
     */
    protected function extractEnumValues($type) {
        // Match enum('value1','value2',...)
        if (preg_match('/(?:enum|set)\s*\(([^)]+)\)/i', $type, $matches)) {
            $valuesStr = $matches[1];

            // Split by comma and clean up quotes
            $values = [];
            $parts = explode(',', $valuesStr);

            foreach ($parts as $part) {
                $part = trim($part);
                // Remove surrounding quotes
                $part = trim($part, "'\"");
                if ($part !== '') {
                    $values[] = $part;
                }
            }

            return $values;
        }

        return null;
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

        // MEMORY MANAGEMENT: Periodic memory check
        static $rowCounter = 0;
        $rowCounter++;

        if ($rowCounter % 100 === 0) {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->getMemoryLimit();
            $memoryPercent = ($memoryUsage / $memoryLimit) * 100;

            if ($memoryPercent > 80) {
                wire()->warning(sprintf(
                    'High memory usage: %s of %s (%.1f%%). Consider reducing sample_size.',
                    $this->formatBytes($memoryUsage),
                    $this->formatBytes($memoryLimit),
                    $memoryPercent
                ));
            }

            // Clear statement cache to free memory
            unset($sql);
            gc_collect_cycles();
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
        $rows_processed = []; // CRITICAL: Initialize array!

        // Extract column names if specified
        // Use /s modifier to handle multiline INSERT statements
        $columns = null;
        if (preg_match('/INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?\s*\(([^)]+)\)/is', $sql, $matches)) {
            $columnStr = $matches[2]; // Column list is in second capture group
            $columns = array_map(function($col) {
                return trim($col, "` \n\r\t");
            }, explode(',', $columnStr));

            // DEBUG: Log extracted columns
            if ($this->logger) $this->logger->logInfo("Extracted columns from INSERT: " . implode(', ', $columns));
        } else {
            // Use structure columns if available
            if (isset($this->tables[$tableName]['structure'])) {
                $columns = array_keys($this->tables[$tableName]['structure']);
                if ($this->logger) $this->logger->logInfo("Using structure columns: " . implode(', ', $columns));
            } else {
                if ($this->logger) $this->logger->logInfo("WARNING: No columns found for table $tableName");
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
                    $combined = array_combine($columns, $values);
                    if ($combined === false) {
                        // Fallback to numeric keys if combine fails
                        if ($this->logger) $this->logger->logInfo("ERROR: array_combine failed for row");
                        $rows_processed[] = $values;
                    } else {
                        $rows_processed[] = $combined;
                    }
                } else {
                    // If no columns or mismatch, use numeric keys
                    if ($this->logger) $this->logger->logInfo("WARNING: Column count mismatch. Columns: " . count($columns ?: []) . ", Values: " . count($values));
                    $rows_processed[] = $values;
                }
            }

            return $rows_processed;
        }

        if ($this->logger) $this->logger->logInfo("ERROR: No VALUES clause found in INSERT statement");
        return [];
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

                // Skip characters between rows (commas, spaces, newlines)
                if ($depth === 0) {
                    continue;
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
                // Handle escape sequences properly
                switch ($char) {
                    case 'n':
                        $current .= "\n";
                        break;
                    case 'r':
                        $current .= "\r";
                        break;
                    case 't':
                        $current .= "\t";
                        break;
                    case '\\':
                    case "'":
                    case '"':
                        $current .= $char;
                        break;
                    default:
                        // Unknown escape, keep as-is
                        $current .= '\\' . $char;
                }
                $escaped = false;
                continue;
            }

            if ($char === '\\' && $inString) {
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

        // Remove quotes (quotes are already removed during parsing, this is a fallback)
        if ((substr($value, 0, 1) === "'" && substr($value, -1) === "'") ||
            (substr($value, 0, 1) === '"' && substr($value, -1) === '"')) {
            $value = substr($value, 1, -1);
        }

        // Note: Unescaping is now handled during parsing, not here

        return $value;
    }

    /**
     * Get PHP memory limit in bytes
     */
    protected function getMemoryLimit() {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit == -1) {
            // Unlimited
            return PHP_INT_MAX;
        }

        // Convert to bytes
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);

        switch ($unit) {
            case 'G':
                $value *= 1024;
                // fall through
            case 'M':
                $value *= 1024;
                // fall through
            case 'K':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Format bytes to human-readable string
     */
    protected function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
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
