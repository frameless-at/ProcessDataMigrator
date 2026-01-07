<?php

namespace ProcessWire;

/**
 * Template Creator for Database Importer
 * Creates templates and fields based on mapping configuration
 */
class TemplateCreator extends WireData {

    /**
     * Track created fields for rollback
     */
    protected $createdFields = [];

    /**
     * Get list of created field names
     */
    public function getCreatedFields() {
        return $this->createdFields;
    }

    /**
     * Create or update template with fields from mapping
     *
     * @param array $mapping Mapping configuration
     * @return Template|null Created or existing template
     */
    public function createTemplate($mapping) {
        $templateName = $mapping['template'];

        // Get or create template
        $template = $this->wire('templates')->get($templateName);

        if (!$template || !$template->id) {
            $template = $this->wire(new Template());
            $template->name = $templateName;
            $template->label = ucfirst($templateName ?: 'Untitled');

            // Create fieldgroup
            $fieldgroup = $this->wire(new Fieldgroup());
            $fieldgroup->name = $templateName;
            $fieldgroup->save();

            $template->fieldgroup = $fieldgroup;
            $template->save();

            $this->wire()->message("Created template: $templateName");
        }

        // Add title field if not present
        if (!$template->fieldgroup->hasField('title')) {
            $template->fieldgroup->add($this->wire('fields')->get('title'));
            $template->fieldgroup->save();
        }

        // Create and add fields from mapping
        foreach ($mapping['fields'] as $fieldMapping) {
            $field = $this->createField($fieldMapping);

            if ($field && !$template->fieldgroup->hasField($field)) {
                $template->fieldgroup->add($field);
            }
        }

        $template->fieldgroup->save();

        return $template;
    }

    /**
     * Create or update a field
     *
     * @param array $fieldMapping Field mapping configuration
     * @return Field|null Created or existing field
     */
    public function createField($fieldMapping) {
        $fieldName = $fieldMapping['target_field'];
        $fieldtype = $fieldMapping['fieldtype'];

        // Get or create field
        $field = $this->wire('fields')->get($fieldName);

        if (!$field) {
            $field = $this->wire(new Field());
            $field->name = $fieldName;
            $field->type = $this->wire('modules')->get($fieldtype);
            $field->label = $fieldMapping['label'];

            // Set field properties
            if (isset($fieldMapping['required']) && $fieldMapping['required']) {
                $field->required = 1;
            }

            // CRITICAL: Options field needs inputfieldClass
            if ($fieldtype === 'FieldtypeOptions') {
                $field->set('inputfieldClass', 'InputfieldSelect');
            }

            // Save field first (required before setting options)
            $field->save();

            // Type-specific configuration AFTER initial save
            if ($fieldtype === 'FieldtypeOptions' && isset($fieldMapping['options'])) {
                $this->configureOptionsField($field, $fieldMapping['options']);
            }

            if ($fieldtype === 'FieldtypePage' && isset($fieldMapping['reference_table'])) {
                $this->configurePageField($field, $fieldMapping['reference_table']);
            }

            $this->wire()->message("Created field: $fieldName ($fieldtype)");

            // Track for rollback
            $this->createdFields[] = $fieldName;
        }

        return $field;
    }

    /**
     * Configure options field with values
     */
    protected function configureOptionsField($field, $options) {
        if (!$field->type instanceof FieldtypeOptions) {
            return;
        }

        // Get the fieldtype module
        $fieldtype = $this->wire('modules')->get('FieldtypeOptions');
        if (!$fieldtype) {
            return;
        }

        // Build options string (one option per line)
        $optionsString = implode("\n", $options);

        // Use the fieldtype's manager to set options
        $result = $fieldtype->manager->setOptionsString($field, $optionsString, true);

        if ($result['added'] > 0) {
            $this->wire()->message(sprintf(
                "Added %d options to field '%s'",
                $result['added'],
                $field->name
            ));
        }
    }

    /**
     * Configure page reference field
     */
    protected function configurePageField($field, $referenceTable) {
        if (!$field->type instanceof FieldtypePage) {
            return;
        }

        // Try to find matching template
        $templateName = $this->sanitizeTemplateName($referenceTable);
        $template = $this->wire('templates')->get($templateName);

        if (!$template) {
            $this->wire()->warning("Template '{$templateName}' not found for Page Reference field '{$field->name}'");
            return;
        }

        // Find parent page where referenced pages are stored
        // Try to find pages with this template
        $parentId = 0;
        $referencedPages = $this->wire('pages')->find("template={$template->name}, limit=1");
        if ($referencedPages->count() > 0) {
            $parentId = $referencedPages->first()->parent_id;
        }

        // If no pages found, try to find a parent page named like the table
        if (!$parentId) {
            $parentPath = "/imports/" . $this->sanitizeTemplateName($referenceTable) . "/";
            $parentPage = $this->wire('pages')->get($parentPath);
            if ($parentPage->id) {
                $parentId = $parentPage->id;
            }
        }

        // Configure the field as single-page reference
        $field->derefAsPage = 1; // Single page (not PageArray)
        $field->template_id = $template->id;
        $field->parent_id = $parentId; // 0 = any parent if not found
        $field->labelFieldName = 'title'; // Use title field for display
        $field->inputfield = 'InputfieldSelect'; // Select dropdown
        $field->findPagesSelector = "template={$template->name}"; // Selector to find selectable pages

        // Save field with updated configuration
        $field->save();

        if ($parentId) {
            $this->wire()->message("Page Reference field '{$field->name}' configured: template={$template->name}, parent={$parentId}");
        } else {
            $this->wire()->warning("Page Reference field '{$field->name}' configured without specific parent (will show all pages with template={$template->name})");
        }
    }

    /**
     * Sanitize table name to template name
     */
    protected function sanitizeTemplateName($tableName) {
        $name = strtolower($tableName);
        $name = preg_replace('/^(tbl_|wp_|db_)/', '', $name);

        // Convert to singular if plural
        if (substr($name, -1) === 's') {
            $name = substr($name, 0, -1);
        }

        $name = str_replace('_', '-', $name);

        return $name;
    }

    /**
     * Create parent page for imported pages
     *
     * @param string $path Parent path
     * @param string $templateName Template name for children
     * @return Page|null Parent page
     */
    public function createParentPage($path, $templateName) {
        // Check if parent already exists
        $parent = $this->wire('pages')->get($path);

        if ($parent->id) {
            return $parent;
        }

        // Get or create import-container template
        $containerTemplate = $this->getOrCreateContainerTemplate();

        // Create parent structure
        $segments = array_filter(explode('/', trim($path, '/')));
        $currentPath = '/';
        $currentParent = $this->wire('pages')->get('/');

        foreach ($segments as $segment) {
            $currentPath .= $segment . '/';
            $page = $this->wire('pages')->get($currentPath);

            if (!$page->id) {
                $page = $this->wire(new Page());
                $page->template = $containerTemplate;
                $page->parent = $currentParent;
                $page->name = $segment;
                $page->title = ucfirst($segment);
                $page->save();

                $this->wire()->message("Created parent page: $currentPath");
            }

            $currentParent = $page;
        }

        return $currentParent;
    }

    /**
     * Get or create import-container template
     *
     * @return Template
     */
    protected function getOrCreateContainerTemplate() {
        $templateName = 'import-container';
        $template = $this->wire('templates')->get($templateName);

        if ($template && $template->id) {
            return $template;
        }

        // Create template
        $template = $this->wire(new Template());
        $template->name = $templateName;
        $template->label = 'Import Container';

        // Create fieldgroup with only title
        $fieldgroup = $this->wire(new Fieldgroup());
        $fieldgroup->name = $templateName;
        $fieldgroup->add($this->wire('fields')->get('title'));
        $fieldgroup->save();

        $template->fieldgroup = $fieldgroup;
        $template->noChildren = 0; // Allow children
        $template->noParents = 1; // Don't show in page add menu
        $template->save();

        $this->wire()->message("Created template: $templateName");

        return $template;
    }
}
