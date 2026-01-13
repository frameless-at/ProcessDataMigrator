<?php

namespace ProcessWire;

/**
 * Abstract base class for data parsers
 */
abstract class AbstractParser extends WireData {

    /**
     * Check if this parser can handle the given file
     *
     * @param string $file File path
     * @return bool
     */
    abstract public function canParse($file);

    /**
     * Parse the file and return structured data
     *
     * @param string $file File path
     * @param array $options Parser options
     * @return array Parsed data structure
     */
    abstract public function parse($file, array $options = []);

    /**
     * Get metadata about the parsed file
     *
     * @return array Metadata (row count, columns, etc.)
     */
    abstract public function getMetadata();

    /**
     * Get the last error message
     *
     * @return string
     */
    public function getError() {
        return $this->error ?? '';
    }

    /**
     * Set error message
     *
     * @param string $error
     */
    protected function setError($error) {
        $this->error = $error;
    }

    /**
     * Detect base type from sample values
     * Analyzes actual data to determine if column is integer, float, boolean, or string
     *
     * @param array $values Sample values from the column
     * @return string Base type: 'integer', 'float', 'boolean', or 'string'
     */
    protected function detectBaseType($values) {
        // Filter out null/empty values
        $nonEmpty = array_filter($values, function($v) {
            return $v !== null && $v !== '';
        });

        if (empty($nonEmpty)) {
            return 'string';
        }

        $intCount = 0;
        $floatCount = 0;
        $boolCount = 0;
        $total = count($nonEmpty);

        foreach ($nonEmpty as $value) {
            // Check boolean (must be exact match)
            if ($value === '0' || $value === '1' ||
                $value === 'true' || $value === 'false' ||
                $value === true || $value === false ||
                $value === 0 || $value === 1) {
                $boolCount++;
                continue;
            }

            // Check integer
            if (is_numeric($value) && (int)$value == $value) {
                $intCount++;
                continue;
            }

            // Check float
            if (is_numeric($value) && is_float($value + 0)) {
                $floatCount++;
                continue;
            }
        }

        // Determine type based on what matched
        // Require at least 80% of values to match for confidence
        $threshold = $total * 0.8;

        if ($intCount >= $threshold) {
            return 'integer';
        }

        if ($floatCount >= $threshold || ($intCount + $floatCount) >= $threshold) {
            return 'float';
        }

        if ($boolCount >= $threshold && $total <= 2) {
            return 'boolean';
        }

        return 'string';
    }
}
