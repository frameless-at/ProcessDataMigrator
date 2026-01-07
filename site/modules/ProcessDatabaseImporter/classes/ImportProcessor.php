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
    protected $globalIdMapping = [];
    protected $localIdMapping = [];

    /**
     * Import data and create pages
     *
     * @param array $data Table data from parser
     * @param array $mapping Mapping configuration
     * @param Template $template Target template
     * @param Page $parent Parent page
     * @param array $fkMappings FK mappings for this table [column => refTable]
     * @param array $globalIdMapping Global ID mapping from previous tables [table => [sql_id => pw_id]]
     * @return array Import result
     */
    public function import($data, $mapping, $template, $parent, $fkMappings = [], $globalIdMapping = []) {
        $this->imported = 0;
        $this->errors = [];
        $this->createdPages = [];
        $this->fkMappings = $fkMappings;
        $this->globalIdMapping = $globalIdMapping;
        $this->localIdMapping = [];

        $titleField = $mapping['title_field'];

        // DEBUG: Log FK mappings and global ID mapping
        $this->wire()->log->save('db-importer', "=== IMPORT START ===");
        $this->wire()->log->save('db-importer', "FK Mappings: " . json_encode($fkMappings));
        $this->wire()->log->save('db-importer', "Global ID Mapping tables: " . implode(', ', array_keys($globalIdMapping)));
        foreach ($globalIdMapping as $table => $idMap) {
            $this->wire()->log->save('db-importer', "  - {$table}: " . count($idMap) . " entries");
        }

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

                    // Store SQL ID → PW Page ID mapping
                    if (isset($row['id'])) {
                        $this->localIdMapping[(int)$row['id']] = $page->id;
                    }
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
            'id_mapping' => $this->localIdMapping,
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

        // Set field values from mapping
        foreach ($mapping['fields'] as $fieldName => $fieldMapping) {
            $targetField = $fieldMapping['target_field'];
            $fieldtype = $fieldMapping['fieldtype'];
            $sourceColumn = $fieldMapping['source_column']; // CRITICAL: Use actual SQL column name!

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

            // Convert value based on fieldtype (pass sourceColumn for FK resolution)
            $value = $this->convertValue($value, $fieldMapping, $sourceColumn);
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
     * @param string $sourceColumn Source column name (for FK resolution)
     * @return mixed Converted value
     */
    protected function convertValue($value, $fieldMapping, $sourceColumn) {
        if ($value === null) {
            return null;
        }

        $fieldtype = $fieldMapping['fieldtype'];

        // Check if this column has an FK mapping
        if (isset($this->fkMappings[$sourceColumn])) {
            $refTable = $this->fkMappings[$sourceColumn];
            $sqlId = (int) $value;

            $this->wire()->log->save('db-importer', "    FK CHECK: {$sourceColumn}={$sqlId} maps to table '{$refTable}'");
            $this->wire()->log->save('db-importer', "    Available tables in globalIdMapping: " . implode(', ', array_keys($this->globalIdMapping)));

            // Look up PW Page ID in global ID mapping
            if (isset($this->globalIdMapping[$refTable])) {
                if (isset($this->globalIdMapping[$refTable][$sqlId])) {
                    $pwPageId = $this->globalIdMapping[$refTable][$sqlId];
                    $this->wire()->log->save('db-importer', "    FK RESOLVED: {$sourceColumn}={$sqlId} → {$refTable} Page #{$pwPageId}");
                    return $pwPageId;
                } else {
                    $this->wire()->log->save('db-importer', "    FK NOT FOUND: SQL ID {$sqlId} not in {$refTable} mapping (has: " . implode(', ', array_keys($this->globalIdMapping[$refTable])) . ")");
                    return null;
                }
            } else {
                $this->wire()->log->save('db-importer', "    FK ERROR: Table '{$refTable}' not found in globalIdMapping! Was it imported first?");
                return null;
            }
        }

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
                // Page references without FK mapping remain empty
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
