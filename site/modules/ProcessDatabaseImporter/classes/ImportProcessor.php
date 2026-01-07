<?php

namespace ProcessWire;

/**
 * Import Processor for Database Importer
 * Creates pages from imported data
 */
class ImportProcessor extends WireData {

    protected $imported = 0;
    protected $errors = [];
    protected $createdPages = [];
    protected $fkMappings = [];
    protected $currentTable = '';

    /**
     * Set FK mappings for this import
     */
    public function setFkMappings($fkMappings) {
        $this->fkMappings = $fkMappings;
    }

    /**
     * Set current table name
     */
    public function setCurrentTable($tableName) {
        $this->currentTable = $tableName;
    }

    /**
     * Import data and create pages
     *
     * @param array $data Table data from parser
     * @param array $mapping Mapping configuration
     * @param Template $template Target template
     * @param Page $parent Parent page
     * @return array Import result
     */
    public function import($data, $mapping, $template, $parent) {
        $this->imported = 0;
        $this->errors = [];
        $this->createdPages = [];

        $titleField = $mapping['title_field'];

        foreach ($data as $index => $row) {
            try {
                $page = $this->createPage($row, $mapping, $template, $parent, $titleField);

                if ($page && $page->id) {
                    $this->imported++;
                    $this->createdPages[] = [
                        'id' => $page->id,
                        'path' => $page->path,
                        'title' => $page->title,
                    ];
                }
            } catch (\Exception $e) {
                $this->errors[] = [
                    'row' => $index + 1,
                    'error' => $e->getMessage(),
                    'data' => $row,
                ];
            }
        }

        return [
            'success' => $this->imported > 0,
            'imported' => $this->imported,
            'errors' => $this->errors,
            'created_pages' => $this->createdPages,
        ];
    }

    /**
     * Create a single page from row data
     *
     * @param array $row Data row
     * @param array $mapping Mapping configuration
     * @param Template $template Target template
     * @param Page $parent Parent page
     * @param string $titleField Title field name
     * @return Page|null Created page
     */
    protected function createPage($row, $mapping, $template, $parent, $titleField) {
        // Get title value
        $title = isset($row[$titleField]) ? $row[$titleField] : 'Untitled';

        if (empty($title)) {
            $title = 'Untitled ' . uniqid();
        }

        // Create page
        $page = $this->wire(new Page());
        $page->template = $template;
        $page->parent = $parent;
        $page->title = $title;

        // Generate name from title
        $page->name = $this->wire('sanitizer')->pageName($title, Sanitizer::translate);

        // Make name unique
        $baseName = $page->name;
        $n = 1;
        while ($this->wire('pages')->get("parent=$parent, name=$page->name, id!=$page")->id) {
            $page->name = $baseName . '-' . $n;
            $n++;
        }

        // DEBUG: Show what we're trying to import
        $this->wire()->log->save('db-importer', "Processing row with title: $title");
        $this->wire()->log->save('db-importer', "Row data columns: " . implode(', ', array_keys($row)));
        $this->wire()->log->save('db-importer', "Mapping fields: " . implode(', ', array_keys($mapping['fields'])));

        // Store original SQL ID if template has _sql_original_id field
        if ($template->fieldgroup->hasField('_sql_original_id') && isset($row['id'])) {
            $page->set('_sql_original_id', (int) $row['id']);
            $this->wire()->log->save('db-importer', "  SET: _sql_original_id = " . $row['id']);
        }

        // Set field values from mapping
        foreach ($mapping['fields'] as $sourceColumn => $fieldMapping) {
            $targetField = $fieldMapping['target_field'];
            $fieldtype = $fieldMapping['fieldtype'];

            $this->wire()->log->save('db-importer', "Checking field: $sourceColumn -> $targetField ($fieldtype)");

            // Skip if field doesn't exist in template
            if (!$template->fieldgroup->hasField($targetField)) {
                $this->wire()->log->save('db-importer', "  SKIP: Field $targetField not in template");
                continue;
            }

            // Skip image/file fields - these need special handling with actual files
            if (in_array($fieldtype, ['FieldtypeImage', 'FieldtypeFile'])) {
                $this->wire()->log->save('db-importer', "  SKIP: Field $targetField is image/file type");
                continue;
            }

            // Skip if no data
            if (!isset($row[$sourceColumn])) {
                $this->wire()->log->save('db-importer', "  SKIP: No data for source column $sourceColumn");
                continue;
            }

            $value = $row[$sourceColumn];
            $this->wire()->log->save('db-importer', "  Value from DB: " . var_export($value, true));

            // Convert value based on fieldtype
            $value = $this->convertValue($value, $fieldMapping);
            $this->wire()->log->save('db-importer', "  Converted value: " . var_export($value, true));

            // Set field value
            $page->set($targetField, $value);
            $this->wire()->log->save('db-importer', "  SET: $targetField = " . var_export($value, true));
        }

        // Save page
        $saved = $page->save();

        // Check if save was successful
        if (!$saved || !$page->id) {
            // Collect error messages from page
            $errorMessages = [];
            foreach ($page->getErrors() as $error) {
                $errorMessages[] = $error;
            }

            $errorMsg = !empty($errorMessages)
                ? implode('; ', $errorMessages)
                : 'Page save failed for unknown reason';

            throw new \Exception($errorMsg);
        }

        return $page;
    }

    /**
     * Convert value based on fieldtype
     *
     * @param mixed $value Raw value
     * @param array $fieldMapping Field mapping configuration
     * @return mixed Converted value
     */
    protected function convertValue($value, $fieldMapping) {
        if ($value === null) {
            return null;
        }

        $fieldtype = $fieldMapping['fieldtype'];

        switch ($fieldtype) {
            case 'FieldtypeInteger':
                return (int) $value;

            case 'FieldtypeFloat':
                return (float) $value;

            case 'FieldtypeCheckbox':
                // Convert to boolean
                return in_array($value, [1, '1', 'true', 'yes', true], true) ? 1 : 0;

            case 'FieldtypeDatetime':
                // Convert to Unix timestamp
                if (is_numeric($value)) {
                    return (int) $value;
                }
                $timestamp = strtotime($value);
                // If strtotime fails, return null instead of false
                return $timestamp !== false ? $timestamp : null;

            case 'FieldtypeOptions':
                // For options, return the value as-is (will be matched by title)
                return $value;

            case 'FieldtypePage':
                // Handle FK mapping if configured
                if (isset($fieldMapping['fk_config'])) {
                    return $this->resolveForeignKey($value, $fieldMapping['fk_config']);
                }
                // For page references without FK config, return null
                return null;

            case 'FieldtypeText':
            case 'FieldtypeTextarea':
            case 'FieldtypeEmail':
            case 'FieldtypeURL':
            default:
                return $value;
        }
    }

    /**
     * Resolve a foreign key value to a Page reference
     *
     * @param mixed $fkValue The foreign key value (original SQL ID)
     * @param array $fkConfig FK configuration (ref_table, ref_column)
     * @return int|null Referenced page ID or null if not found
     */
    protected function resolveForeignKey($fkValue, $fkConfig) {
        if (empty($fkValue)) {
            return null;
        }

        $refTable = $fkConfig['ref_table'];
        $refColumn = $fkConfig['ref_column'] ?? 'id';

        $this->wire()->log->save('db-importer', "    FK LOOKUP: Searching for {$refTable} where {$refColumn} = {$fkValue}");

        // Find the page with matching _sql_original_id
        // The template name should match the table name
        $templateName = $this->wire('sanitizer')->pageName($refTable, Sanitizer::translate);

        $selector = "template={$templateName}, _sql_original_id=" . (int)$fkValue;
        $referencedPage = $this->wire('pages')->get($selector);

        if ($referencedPage && $referencedPage->id) {
            $this->wire()->log->save('db-importer', "    FK FOUND: Page #{$referencedPage->id} ({$referencedPage->title})");
            // Return Page ID for single-page reference fields (derefAsPage=1)
            return $referencedPage->id;
        } else {
            $this->wire()->log->save('db-importer', "    FK NOT FOUND: No page found with selector: {$selector}");
            return null;
        }
    }

    /**
     * Get import statistics
     *
     * @return array Statistics
     */
    public function getStats() {
        return [
            'imported' => $this->imported,
            'errors' => count($this->errors),
            'success_rate' => $this->imported > 0
                ? round(($this->imported / ($this->imported + count($this->errors))) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get created pages
     *
     * @return array Created pages info
     */
    public function getCreatedPages() {
        return $this->createdPages;
    }

    /**
     * Get errors
     *
     * @return array Errors
     */
    public function getErrors() {
        return $this->errors;
    }
}
