<?php

namespace ProcessWire;

/**
 * CSV Parser
 * Parses CSV files and extracts tabular data
 */
class CsvParser extends AbstractParser {

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
        if (!in_array($ext, ['csv', 'txt'])) {
            return false;
        }

        // Try to detect CSV structure
        $handle = fopen($file, 'r');
        if (!$handle) {
            return false;
        }

        $firstLine = fgets($handle);
        fclose($handle);

        // Check if line contains delimiters (comma, semicolon, tab)
        return (
            strpos($firstLine, ',') !== false ||
            strpos($firstLine, ';') !== false ||
            strpos($firstLine, "\t") !== false
        );
    }

    /**
     * Parse CSV file
     *
     * @param string $file File path
     * @param array $options Options:
     *   - delimiter: CSV delimiter (auto-detect if not set)
     *   - enclosure: Field enclosure character (default: ")
     *   - has_header: First row contains headers (default: true)
     *   - table_name: Name for the virtual table (default: filename)
     *   - sample_size: number of rows to analyze (default: 100)
     * @return array Parsed data structure
     */
    public function parse($file, array $options = []) {
        if (!file_exists($file)) {
            $this->setError("File not found: $file");
            return [];
        }

        $delimiter = $options['delimiter'] ?? null;
        $enclosure = $options['enclosure'] ?? '"';
        $hasHeader = $options['has_header'] ?? true;
        $tableName = $options['table_name'] ?? pathinfo($file, PATHINFO_FILENAME);
        $sampleSize = $options['sample_size'] ?? 100;

        // Auto-detect delimiter if not specified
        if ($delimiter === null) {
            $delimiter = $this->detectDelimiter($file);
        }

        if ($this->logger) $this->logger->logInfo("CSV Parser: Using delimiter '$delimiter'");

        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->setError("Cannot open file: $file");
            return [];
        }

        $headers = [];
        $data = [];
        $rowCount = 0;
        $lineNumber = 0;

        while (($row = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false) {
            $lineNumber++;

            // Skip empty rows
            if (empty($row) || (count($row) === 1 && $row[0] === null)) {
                continue;
            }

            // First row: headers
            if ($rowCount === 0) {
                if ($hasHeader) {
                    $headers = array_map('trim', $row);
                } else {
                    // Generate column names: col_0, col_1, col_2, ...
                    $headers = array_map(function($i) {
                        return 'col_' . $i;
                    }, range(0, count($row) - 1));

                    // Process this row as data
                    $combinedRow = array_combine($headers, $row);
                    if ($combinedRow !== false) {
                        $data[] = $combinedRow;
                    }
                }
                $rowCount++;
                continue;
            }

            // Data rows
            if (count($row) === count($headers)) {
                $combinedRow = array_combine($headers, $row);
                if ($combinedRow !== false) {
                    $data[] = $combinedRow;
                }
            }

            $rowCount++;

            // Stop at sample size
            if ($sampleSize > 0 && count($data) >= $sampleSize) {
                // Continue counting total rows
                while (fgets($handle) !== false) {
                    $rowCount++;
                }
                break;
            }
        }

        fclose($handle);

        // Build table structure from data
        $structure = [];
        foreach ($headers as $columnName) {
            $structure[$columnName] = [
                'name' => $columnName,
                'type' => 'string',
                'base_type' => 'string',
                'nullable' => true,
                'auto_increment' => false,
                'default' => null,
            ];
        }

        // Guess primary key (look for 'id' column)
        $primaryKey = null;
        foreach ($headers as $header) {
            if (strtolower($header) === 'id' || preg_match('/_id$/i', $header)) {
                $primaryKey = $header;
                break;
            }
        }

        // Store as single table
        $this->tables[$tableName] = [
            'name' => $tableName,
            'structure' => $structure,
            'data' => $data,
            'row_count' => $rowCount - ($hasHeader ? 1 : 0), // Don't count header row
            'primary_key' => $primaryKey,
        ];

        // Build metadata
        $this->metadata = [
            'file' => basename($file),
            'size' => filesize($file),
            'tables' => 1,
            'total_rows' => $rowCount - ($hasHeader ? 1 : 0),
            'parsed_at' => date('Y-m-d H:i:s'),
            'delimiter' => $delimiter,
            'has_header' => $hasHeader,
        ];

        return $this->tables;
    }

    /**
     * Auto-detect CSV delimiter
     */
    protected function detectDelimiter($file) {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return ',';
        }

        // Read first few lines
        $sample = '';
        for ($i = 0; $i < 10; $i++) {
            $line = fgets($handle);
            if ($line === false) break;
            $sample .= $line;
        }
        fclose($handle);

        // Count occurrences of common delimiters
        $delimiters = [
            ',' => substr_count($sample, ','),
            ';' => substr_count($sample, ';'),
            "\t" => substr_count($sample, "\t"),
            '|' => substr_count($sample, '|'),
        ];

        // Return most common delimiter
        arsort($delimiters);
        $detected = key($delimiters);

        if ($this->logger) $this->logger->logDebug("Delimiter detection: " . json_encode($delimiters) . " -> using '$detected'");

        return $detected;
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
