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
}
