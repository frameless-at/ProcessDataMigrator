<?php

namespace ProcessWire;

/**
 * Template Creator for Database Importer
 * Creates templates and fields based on mapping configuration
 */
class TemplateCreator extends WireData {

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

        if (!$template) {
            $template = $this->wire(new Template());
            $template->name = $templateName;
            $template->label = ucfirst($templateName);

            // Create fieldgroup
            $fieldgroup = $this->wire(new Fieldgroup());
            $fieldgroup->name = $templateName;
            $fieldgroup->save();

            $template->fieldgroup = $fieldgroup;
            $template->save();

            $this->message("Created template: $templateName");
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

            // Type-specific configuration
            if ($fieldtype === 'FieldtypeOptions' && isset($fieldMapping['options'])) {
                $this->configureOptionsField($field, $fieldMapping['options']);
            }

            if ($fieldtype === 'FieldtypePage' && isset($fieldMapping['reference_table'])) {
                $this->configurePageField($field, $fieldMapping['reference_table']);
            }

            $field->save();

            $this->message("Created field: $fieldName ($fieldtype)");
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

        // Add options
        $manager = $this->wire(new SelectableOptionManager());

        foreach ($options as $index => $value) {
            $option = $this->wire(new SelectableOption());
            $option->value = $index + 1;
            $option->title = $value;
            $manager->add($option);
        }

        $field->type->manager->setOptionsString($field, implode("\n", $options), false);
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

        if ($template) {
            $field->template_id = $template->id;
            $field->parent_id = 0; // Allow from any parent
            $field->inputfield = 'InputfieldSelect';
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

        // Create parent structure
        $segments = array_filter(explode('/', trim($path, '/')));
        $currentPath = '/';
        $currentParent = $this->wire('pages')->get('/');

        foreach ($segments as $segment) {
            $currentPath .= $segment . '/';
            $page = $this->wire('pages')->get($currentPath);

            if (!$page->id) {
                // Create page
                $page = $this->wire(new Page());
                $page->template = 'basic-page'; // Use basic-page or admin
                $page->parent = $currentParent;
                $page->name = $segment;
                $page->title = ucfirst($segment);
                $page->save();

                $this->message("Created parent page: $currentPath");
            }

            $currentParent = $page;
        }

        return $currentParent;
    }
}
