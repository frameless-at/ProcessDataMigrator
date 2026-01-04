<?php

namespace ProcessWire;

/**
 * Mapping Engine for Database Importer
 * Creates automatic field mappings based on analysis results
 */
class MappingEngine extends WireData {

    /**
     * Create automatic mapping from analysis results
     *
     * @param array $analysis Table analysis from DataAnalyzer
     * @param string $tableName Original table name
     * @return array Mapping configuration
     */
    public function createMapping($analysis, $tableName) {
        // Validate required analysis fields
        if (!isset($analysis['suggested_template']) || !isset($analysis['suggested_title_field']) || !isset($analysis['columns'])) {
            throw new \Exception('Invalid analysis data structure. Missing required fields.');
        }

        $mapping = [
            'table_name' => $tableName,
            'template' => $analysis['suggested_template'],
            'parent' => '/imports/' . $analysis['suggested_template'] . 's/',
            'title_field' => $analysis['suggested_title_field'],
            'name_field' => null, // Auto-generate from title
            'fields' => [],
            'skip_fields' => ['id'], // Skip ID field (will be auto-generated)
        ];

        // Map each column to a ProcessWire field
        foreach ($analysis['columns'] as $column) {
            $columnName = $column['name'];

            // Skip ID field
            if ($column['is_likely_id']) {
                continue;
            }

            // Create field mapping
            $fieldMapping = [
                'source_column' => $columnName,
                'target_field' => $this->sanitizeFieldName($columnName, $mapping['template']),
                'fieldtype' => $column['suggested_fieldtype'],
                'label' => $this->generateLabel($columnName),
                'required' => !$column['nullable'],
                'confidence' => $column['detection_confidence'],
            ];

            // Add type-specific options
            if ($column['suggested_fieldtype'] === 'FieldtypeOptions' && isset($column['options'])) {
                $fieldMapping['options'] = $column['options'];
            }

            // Foreign key handling
            if (isset($analysis['foreign_keys'][$columnName])) {
                $fk = $analysis['foreign_keys'][$columnName];
                $fieldMapping['is_foreign_key'] = true;
                $fieldMapping['reference_table'] = $fk['table'];
                $fieldMapping['reference_column'] = $fk['column'];
                $fieldMapping['fieldtype'] = 'FieldtypePage';
            }

            $mapping['fields'][$columnName] = $fieldMapping;
        }

        return $mapping;
    }

    /**
     * Sanitize column name to valid ProcessWire field name
     * IMPORTANT: Fields are prefixed with template name to ensure uniqueness across tables
     */
    protected function sanitizeFieldName($name, $templateName) {
        // Convert to lowercase
        $name = strtolower($name);

        // Replace invalid characters with underscore
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);

        // Remove consecutive underscores
        $name = preg_replace('/_+/', '_', $name);

        // Trim underscores
        $name = trim($name, '_');

        // Check for ProcessWire reserved field names
        $reserved = [
            'id', 'name', 'parent', 'parent_id', 'parents', 'template', 'template_id',
            'templates', 'children', 'child', 'created', 'modified', 'createdUser',
            'modifiedUser', 'sort', 'sortfield', 'numChildren', 'num_children',
            'url', 'path', 'httpUrl', 'httpurl', 'status', 'references', 'rootParent',
            'description', // Page description meta field
        ];

        // If field name is reserved, prefix with "field_"
        if (in_array($name, $reserved)) {
            $name = 'field_' . $name;
        }

        // Ensure it doesn't start with a number
        if (preg_match('/^\d/', $name)) {
            $name = 'field_' . $name;
        }

        // CRITICAL: Prefix with template name to make fields unique per table
        // This prevents conflicts when multiple tables have same column names
        // e.g., orders.status and customers.status become orders_status and customers_status
        $name = $templateName . '_' . $name;

        return $name;
    }

    /**
     * Generate human-readable label from column name
     */
    protected function generateLabel($name) {
        // Replace underscores with spaces
        $label = str_replace('_', ' ', $name);

        // Uppercase first letter of each word
        $label = ucwords($label);

        return $label;
    }

    /**
     * Validate mapping configuration
     *
     * @param array $mapping Mapping configuration
     * @return array Validation result
     */
    public function validateMapping($mapping) {
        $errors = [];
        $warnings = [];

        // Check template name
        if (empty($mapping['template'])) {
            $errors[] = 'Template name is required';
        }

        // Check parent path
        if (empty($mapping['parent'])) {
            $errors[] = 'Parent path is required';
        }

        // Check title field
        if (empty($mapping['title_field'])) {
            $warnings[] = 'No title field specified - will use first text field';
        }

        // Check field mappings
        if (empty($mapping['fields'])) {
            $errors[] = 'No field mappings defined';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get mapping summary for display
     *
     * @param array $mapping Mapping configuration
     * @return array Summary information
     */
    public function getMappingSummary($mapping) {
        $fieldCount = count($mapping['fields']);
        $requiredFields = 0;
        $optionalFields = 0;
        $foreignKeys = 0;

        foreach ($mapping['fields'] as $field) {
            if ($field['required']) {
                $requiredFields++;
            } else {
                $optionalFields++;
            }

            if (isset($field['is_foreign_key']) && $field['is_foreign_key']) {
                $foreignKeys++;
            }
        }

        return [
            'template' => $mapping['template'],
            'parent' => $mapping['parent'],
            'title_field' => $mapping['title_field'],
            'field_count' => $fieldCount,
            'required_fields' => $requiredFields,
            'optional_fields' => $optionalFields,
            'foreign_keys' => $foreignKeys,
        ];
    }
}
