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

    /**
     * Create list/overview template for table parent page
     * Simple template with only title field
     *
     * @param string $templateName Template name (e.g., "customers_list")
     * @return Template
     */
    public function createListTemplate($templateName) {
        // Check if template already exists
        $template = $this->wire('templates')->get($templateName);

        if ($template && $template->id) {
            return $template;
        }

        // Create new template
        $template = $this->wire(new Template());
        $template->name = $templateName;
        $template->label = ucfirst(str_replace('_', ' ', $templateName));

        // Create fieldgroup with only title
        $fieldgroup = $this->wire(new Fieldgroup());
        $fieldgroup->name = $templateName;
        $fieldgroup->add($this->wire('fields')->get('title'));
        $fieldgroup->save();

        $template->fieldgroup = $fieldgroup;
        $template->noChildren = 0; // Allow children
        $template->save();

        $this->wire()->message("Created list template: $templateName");

        return $template;
    }

    /**
     * Create table-specific parent page under /import/
     *
     * @param string $path Parent path (e.g., "/import/customers/")
     * @param Template $template Template for this page
     * @param Page $parent Parent page (usually /import/)
     * @return Page
     */
    public function createTableParentPage($path, $template, $parent) {
        // Check if page already exists
        $page = $this->wire('pages')->get($path);

        if ($page->id) {
            // Update template if different
            if ($page->template->id !== $template->id) {
                $page->template = $template;
                $page->save();
                $this->wire()->message("Updated template for existing page: $path");
            }
            return $page;
        }

        // Extract page name from path
        $segments = array_filter(explode('/', trim($path, '/')));
        $name = end($segments);

        // Create new page
        $page = $this->wire(new Page());
        $page->template = $template;
        $page->parent = $parent;
        $page->name = $name;
        $page->title = ucfirst(str_replace(['_', '-'], ' ', $name));
        $page->save();

        $this->wire()->message("Created table parent page: $path");

        return $page;
    }

    /**
     * Generate physical template files (.php) in /site/templates/
     *
     * @param string $listTemplateName Name of list template (e.g., "customers_list")
     * @param string $detailTemplateName Name of detail template (e.g., "customers")
     * @param array $mapping Field mapping data
     * @param array $fkRelationships FK relationships [columnName => refTable]
     * @param string $tableName Original table name
     * @return array Generated file paths
     */
    public function generateTemplateFiles($listTemplateName, $detailTemplateName, $mapping, $fkRelationships = [], $tableName = '') {
        $templatesPath = $this->wire('config')->paths->templates;
        $generated = [];

        // Generate list template (Option 2 - Debug/Overview)
        $listFilePath = $templatesPath . $listTemplateName . '.php';
        $listContent = $this->buildListTemplateContent($detailTemplateName, $tableName);

        if (file_put_contents($listFilePath, $listContent)) {
            $generated[] = $listFilePath;
            $this->wire()->message("Generated list template file: $listFilePath");
        }

        // Generate detail template (Option 1 - Full field definition)
        $detailFilePath = $templatesPath . $detailTemplateName . '.php';
        $detailContent = $this->buildDetailTemplateContent($detailTemplateName, $mapping, $fkRelationships, $tableName);

        if (file_put_contents($detailFilePath, $detailContent)) {
            $generated[] = $detailFilePath;
            $this->wire()->message("Generated detail template file: $detailFilePath");
        }

        return $generated;
    }

    /**
     * Build list/overview template content (Option 2 - Debug Mode)
     *
     * @param string $detailTemplateName Detail template name for links
     * @param string $tableName Original table name
     * @return string Template PHP content
     */
    protected function buildListTemplateContent($detailTemplateName, $tableName) {
        $templateName = ucfirst(str_replace('_', ' ', $detailTemplateName));
        $listTemplateName = $tableName . '_list';

        $content = <<<'PHP'
<?php namespace ProcessWire;
/**
 * Template: {LIST_TEMPLATE_NAME}
 * Auto-generated by Database Importer
 *
 * Overview page for {TABLE_NAME} records
 * Lists all child pages of this parent
 */

// Get all children (individual records)
$records = $page->children("template={DETAIL_TEMPLATE}");
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $page->title ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; font-weight: 600; }
        tr:hover { background-color: #f9f9f9; }
        a { color: #0066cc; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .debug-info { background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 4px; }
        code { background: #e0e0e0; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
    <h1><?= $page->title ?></h1>

    <div class="debug-info">
        <strong>Template:</strong> <code><?= $page->template->name ?></code><br>
        <strong>Total Records:</strong> <?= $records->count ?><br>
        <strong>Detail Template:</strong> <code>{DETAIL_TEMPLATE}</code>
    </div>

    <?php if ($records->count): ?>
        <h2>All {TEMPLATE_NAME_DISPLAY}</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Fields</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                <tr>
                    <td><?= $record->id ?></td>
                    <td><strong><?= $record->title ?></strong></td>
                    <td>
                        <?php
                        // Show first 3 field values inline (skip title)
                        $sampleFields = [];
                        $count = 0;
                        foreach ($record->template->fields as $field) {
                            if ($field->name !== 'title' && $count < 3) {
                                $value = $record->get($field->name);
                                if ($value && !is_object($value)) {
                                    // Truncate long values
                                    $displayValue = strlen($value) > 30 ? substr($value, 0, 30) . '...' : $value;
                                    $sampleFields[] = "<em>{$field->name}:</em> {$displayValue}";
                                    $count++;
                                }
                            }
                        }
                        echo implode(' | ', $sampleFields);
                        if (count($record->template->fields) > 4) echo ' ...';
                        ?>
                    </td>
                    <td>
                        <a href="<?= $record->url ?>">View Details</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No records found.</p>
    <?php endif; ?>

    <!-- TODO: Add your custom list view code here -->
</body>
</html>
PHP;

        // Replace placeholders
        $content = str_replace('{LIST_TEMPLATE_NAME}', $listTemplateName, $content);
        $content = str_replace('{TABLE_NAME}', $tableName, $content);
        $content = str_replace('{DETAIL_TEMPLATE}', $detailTemplateName, $content);
        $content = str_replace('{TEMPLATE_NAME_DISPLAY}', $templateName, $content);

        return $content;
    }

    /**
     * Build detail template content (Option 1 - Full field definition)
     *
     * @param string $templateName Template name
     * @param array $mapping Field mapping data
     * @param array $fkRelationships FK relationships
     * @param string $tableName Original table name
     * @return string Template PHP content
     */
    protected function buildDetailTemplateContent($templateName, $mapping, $fkRelationships, $tableName) {
        // Build Quick Reference section (all fields at a glance)
        $quickRefLines = [];
        $quickRefLines[] = "// Basic Fields:";
        $quickRefLines[] = "// \$page->title                    // string  - " . ($mapping['title_field'] ?? 'Title') . " (required)";

        // Build Quick Access Variables
        $quickAccessVars = [];
        $quickAccessVars[] = "\$title = \$page->title;";

        // Build HTML field outputs (all fields displayed)
        $htmlFieldOutputs = [];

        foreach ($mapping['fields'] as $columnName => $fieldMapping) {
            $fieldName = $fieldMapping['target_field'];
            $fieldtype = $fieldMapping['fieldtype'];
            $sourceColumn = $fieldMapping['source_column'];
            $phpType = $this->mapFieldtypeToPhpType($fieldtype);

            // Quick Reference comment
            $label = $this->sanitizeLabel($sourceColumn);
            $padding = str_repeat(' ', max(0, 30 - strlen("\$page->{$fieldName}")));
            $quickRefLines[] = "// \$page->{$fieldName}{$padding}// {$phpType}     - {$label} ({$fieldtype})";

            // Quick Access variable
            $varName = $this->generateVarName($sourceColumn);
            $conversion = $this->getValueConversion($fieldName, $fieldtype);
            $quickAccessVars[] = "\${$varName} = {$conversion};";

            // HTML output
            $htmlFieldOutputs[] = $this->buildEnhancedFieldOutput($varName, $label, $fieldtype);
        }

        // Build FK relationships section
        $fkRefLines = [];
        $fkLoadCode = [];
        $fkHtmlOutputs = [];

        foreach ($fkRelationships as $columnName => $refTable) {
            $pwFieldName = null;
            if (isset($mapping['fields'][$columnName])) {
                $pwFieldName = $mapping['fields'][$columnName]['target_field'];
            }

            if ($pwFieldName) {
                $varName = $this->generateVarName($columnName);
                $fkRefLines[] = "// {$refTable}.{$columnName} → {$tableName}.{$columnName} (find {$refTable} for this {$tableName})";

                // Pre-load FK data
                $relatedVarName = 'related' . ucfirst($refTable);
                $fkLoadCode[] = "// Find all {$refTable} that belong to this {$tableName}";
                $fkLoadCode[] = "\${$relatedVarName} = \$pages->find(\"template={$refTable}, {$pwFieldName}={\${$varName}}\");";
                $fkLoadCode[] = "";

                // HTML output for related data
                $fkHtmlOutputs[] = $this->buildFkHtmlOutput($relatedVarName, $refTable);
            }
        }

        // Combine all sections
        $quickRef = implode("\n// ", $quickRefLines);
        $fkRef = !empty($fkRefLines) ? "\n//\n// Foreign Keys:\n// " . implode("\n// ", $fkRefLines) : '';
        $quickAccess = implode("\n", $quickAccessVars);
        $fkLoad = !empty($fkLoadCode) ? "\n// " . str_replace("\n", "\n// ", implode("\n", $fkLoadCode)) : '';
        $htmlFields = implode("\n    ", $htmlFieldOutputs);
        $fkHtml = !empty($fkHtmlOutputs) ? "\n    " . implode("\n    ", $fkHtmlOutputs) : '';

        return <<<PHP
<?php namespace ProcessWire;
/**
 * Template: {$templateName}
 * Source Table: {$tableName}
 */

// =============================================================================
// AVAILABLE FIELDS - Quick Reference (copy & paste these)
// =============================================================================
//
{$quickRef}{$fkRef}
//
// =============================================================================

// =============================================================================
// QUICK ACCESS VARIABLES (optional - for cleaner template code)
// =============================================================================
{$quickAccess}

// ============================================================================={$fkLoad}
// LOAD RELATED DATA (FK Relationships)
// =============================================================================

?>
<!DOCTYPE html>
<html>
<head>
    <title><?= \$title ?></title>
    <meta charset="utf-8">
</head>
<body>
    <h1><?= \$title ?></h1>

    <!-- =================================================================== -->
    <!-- ALL FIELDS DISPLAYED - Remove/customize what you don't need        -->
    <!-- =================================================================== -->

    <section class="basic-info">
        <h2>{$tableName} Information</h2>

        {$htmlFields}
    </section>

    <!-- =================================================================== -->
    <!-- RELATED DATA - FK Relationships                                    -->
    <!-- =================================================================== -->{$fkHtml}

    <!-- =================================================================== -->
    <!-- DEBUG: Show all raw field values (remove in production)            -->
    <!-- =================================================================== -->
    <?php if(\$config->debug): ?>
    <details style="margin-top: 40px; padding: 20px; background: #f5f5f5; border-radius: 4px;">
        <summary style="cursor: pointer; font-weight: bold;">🔍 Debug: All Field Values</summary>
        <pre style="margin-top: 10px; background: white; padding: 15px; overflow: auto;"><?php
            echo "Page ID: {\$page->id}\\n";
            echo "Template: {\$page->template->name}\\n\\n";
            echo "FIELDS:\\n";
            foreach(\$page->template->fields as \$field) {
                \$value = \$page->get(\$field->name);
                echo "  {\$field->name} ({\$field->type}): ";
                echo is_object(\$value) ? get_class(\$value) : var_export(\$value, true);
                echo "\\n";
            }
        ?></pre>
    </details>
    <?php endif; ?>

</body>
</html>
PHP;
    }

    /**
     * Map ProcessWire fieldtype to PHP type for PHPDoc
     */
    protected function mapFieldtypeToPhpType($fieldtype) {
        $map = [
            'FieldtypeInteger' => 'int',
            'FieldtypeFloat' => 'float',
            'FieldtypeCheckbox' => 'bool',
            'FieldtypeDatetime' => 'int',
            'FieldtypeText' => 'string',
            'FieldtypeTextarea' => 'string',
            'FieldtypeEmail' => 'string',
            'FieldtypeURL' => 'string',
            'FieldtypeOptions' => 'string',
            'FieldtypePage' => 'int',
        ];

        return $map[$fieldtype] ?? 'mixed';
    }

    /**
     * Generate clean variable name from column name
     */
    protected function generateVarName($columnName) {
        // Remove common prefixes/suffixes
        $name = preg_replace('/^(field_|tbl_|db_)/', '', $columnName);
        $name = preg_replace('/(_id|_key|_fk)$/', '', $name);

        // Convert to camelCase
        $parts = explode('_', $name);
        $camelCase = array_shift($parts);
        foreach ($parts as $part) {
            $camelCase .= ucfirst($part);
        }

        return $camelCase ?: 'value';
    }

    /**
     * Sanitize label for display
     */
    protected function sanitizeLabel($columnName) {
        $label = str_replace('_', ' ', $columnName);
        return ucwords($label);
    }

    /**
     * Get value conversion code for a field
     */
    protected function getValueConversion($fieldName, $fieldtype) {
        switch ($fieldtype) {
            case 'FieldtypeDatetime':
                return "\$page->{$fieldName} ? date('Y-m-d H:i', \$page->{$fieldName}) : ''";
            case 'FieldtypeCheckbox':
                return "\$page->{$fieldName} ? 'Yes' : 'No'";
            case 'FieldtypeTextarea':
                return "\$page->{$fieldName}";
            default:
                return "\$page->{$fieldName}";
        }
    }

    /**
     * Build enhanced HTML output for a field
     */
    protected function buildEnhancedFieldOutput($varName, $label, $fieldtype) {
        $safeLabel = htmlspecialchars($label);

        // Special handling for textareas (use nl2br)
        if ($fieldtype === 'FieldtypeTextarea') {
            return <<<HTML
<?php if(\${$varName}): ?>
        <p><strong>{$safeLabel}:</strong><br><?= nl2br(\${$varName}) ?></p>
        <?php endif; ?>
HTML;
        }

        // Email fields get mailto link
        if ($fieldtype === 'FieldtypeEmail') {
            return <<<HTML
<?php if(\${$varName}): ?>
        <p><strong>{$safeLabel}:</strong> <a href="mailto:<?= \${$varName} ?>"><?= \${$varName} ?></a></p>
        <?php endif; ?>
HTML;
        }

        // URL fields get link
        if ($fieldtype === 'FieldtypeURL') {
            return <<<HTML
<?php if(\${$varName}): ?>
        <p><strong>{$safeLabel}:</strong> <a href="<?= \${$varName} ?>" target="_blank"><?= \${$varName} ?></a></p>
        <?php endif; ?>
HTML;
        }

        // Default output
        return <<<HTML
<?php if(\${$varName}): ?>
        <p><strong>{$safeLabel}:</strong> <?= \${$varName} ?></p>
        <?php endif; ?>
HTML;
    }

    /**
     * Build HTML output for FK relationships
     */
    protected function buildFkHtmlOutput($relatedVarName, $refTable) {
        $displayName = ucfirst($refTable);

        return <<<HTML

    <?php if(\${$relatedVarName}->count): ?>
    <section class="related-{$refTable}" style="margin-top: 30px; padding: 20px; background: #f0f8ff; border-radius: 4px;">
        <h2>{$displayName} (<?= \${$relatedVarName}->count ?>)</h2>
        <ul style="list-style: none; padding: 0;">
        <?php foreach(\${$relatedVarName} as \$item): ?>
            <li style="padding: 8px; margin: 5px 0; background: white; border-radius: 4px;">
                <a href="<?= \$item->url ?>"><?= \$item->title ?></a>
            </li>
        <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
HTML;
    }
}
