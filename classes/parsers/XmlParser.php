<?php

namespace ProcessWire;

/**
 * XML Parser
 * Parses XML files and extracts tabular data
 * Supports multiple formats:
 * - Flat structure: <items><item><field>value</field></item></items>
 * - Nested elements: automatically flattened to dot notation
 * - Attributes: extracted as fields
 */
class XmlParser extends AbstractParser {

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
        if ($ext !== 'xml') {
            return false;
        }

        // Try to load XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file);
        libxml_clear_errors();

        return $xml !== false;
    }

    /**
     * Parse XML file
     *
     * @param string $file File path
     * @param array $options Options:
     *   - table_name: Name for the virtual table (default: root element name)
     *   - sample_size: number of rows to analyze (default: 100)
     *   - record_xpath: XPath to record elements (default: auto-detect)
     * @return array Parsed data structure
     */
    public function parse($file, array $options = []) {
        if (!file_exists($file)) {
            $this->setError("File not found: $file");
            return [];
        }

        $tableName = $options['table_name'] ?? null;
        $sampleSize = $options['sample_size'] ?? 100;
        $recordXpath = $options['record_xpath'] ?? null;

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $errorMsg = !empty($errors) ? $errors[0]->message : 'Unknown XML error';
            $this->setError("XML parsing error: " . trim($errorMsg));
            libxml_clear_errors();
            return [];
        }

        // Determine table name from root element if not specified
        if (!$tableName) {
            $tableName = $xml->getName();
        }

        // Auto-detect record elements if XPath not provided
        if (!$recordXpath) {
            $recordXpath = $this->detectRecordXpath($xml);
        }

        if ($this->logger) {
            $this->logger->logInfo("XML Parser: Using XPath '$recordXpath' for records");
        }

        // Extract records using XPath
        $records = $xml->xpath($recordXpath);

        if ($records === false || empty($records)) {
            $this->setError("No records found using XPath: $recordXpath");
            return [];
        }

        // Convert XML elements to arrays
        $data = [];
        $allKeys = [];

        foreach ($records as $index => $record) {
            if ($index >= $sampleSize) {
                break;
            }

            $row = $this->xmlToArray($record);
            $data[] = $row;

            // Collect all keys
            foreach (array_keys($row) as $key) {
                if (!in_array($key, $allKeys)) {
                    $allKeys[] = $key;
                }
            }
        }

        // Count total records
        $totalRecords = count($records);

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
            'data' => $data,
            'row_count' => $totalRecords,
            'primary_key' => $primaryKey,
        ];

        // Build metadata
        $this->metadata = [
            'file' => basename($file),
            'size' => filesize($file),
            'tables' => 1,
            'total_rows' => $totalRecords,
            'parsed_at' => date('Y-m-d H:i:s'),
            'record_xpath' => $recordXpath,
        ];

        return $this->tables;
    }

    /**
     * Auto-detect XPath for record elements
     * Looks for repeating child elements
     */
    protected function detectRecordXpath($xml) {
        $rootName = $xml->getName();

        // Check if root has multiple children with same name
        $childNames = [];
        foreach ($xml->children() as $child) {
            $childName = $child->getName();
            $childNames[] = $childName;
        }

        // Find most common child element
        $counts = array_count_values($childNames);
        arsort($counts);
        $mostCommon = key($counts);

        if ($counts[$mostCommon] > 1) {
            // Multiple children with same name found
            return "//{$rootName}/{$mostCommon}";
        }

        // Fallback: use direct children
        return "//{$rootName}/*";
    }

    /**
     * Convert SimpleXMLElement to array with flattening
     * Handles nested elements and attributes
     */
    protected function xmlToArray($xml, $prefix = '') {
        $result = [];

        // Process attributes
        foreach ($xml->attributes() as $attrName => $attrValue) {
            $key = $prefix ? $prefix . '.' . $attrName : $attrName;
            $result[$key] = (string) $attrValue;
        }

        // Process child elements
        foreach ($xml->children() as $childName => $child) {
            $key = $prefix ? $prefix . '.' . $childName : $childName;

            // Check if child has children or attributes
            if ($child->count() > 0 || count($child->attributes()) > 0) {
                // Nested element - flatten recursively
                $nested = $this->xmlToArray($child, $key);
                $result = array_merge($result, $nested);
            } else {
                // Simple value
                $value = (string) $child;
                $result[$key] = $value;
            }
        }

        // If no children and no attributes, get text content
        if (empty($result)) {
            $text = trim((string) $xml);
            if ($text !== '') {
                $key = $prefix ?: 'value';
                $result[$key] = $text;
            }
        }

        return $result;
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
