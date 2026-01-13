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
                        // Show available fields dynamically
                        $fields = [];
                        foreach ($record->template->fields as $field) {
                            if ($field->name !== 'title') {
                                $fields[] = $field->name;
                            }
                        }
                        echo implode(', ', array_slice($fields, 0, 5));
                        if (count($fields) > 5) echo '...';
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
        // Build PHPDoc comments for all fields
        $phpDocLines = ["/**", " * Template: $templateName", " * Auto-generated by Database Importer"];
        $phpDocLines[] = " * Source Table: $tableName";
        $phpDocLines[] = " * ";
        $phpDocLines[] = " * Available Fields:";
        $phpDocLines[] = " * @property string \$title - " . ($mapping['title_field'] ?? 'Title') . " (required)";

        $fieldOutputs = [];

        foreach ($mapping['fields'] as $columnName => $fieldMapping) {
            $fieldName = $fieldMapping['target_field'];
            $fieldtype = $fieldMapping['fieldtype'];
            $sourceColumn = $fieldMapping['source_column'];

            // Determine PHP type for PHPDoc
            $phpType = $this->mapFieldtypeToPhpType($fieldtype);

            // Build PHPDoc line
            $phpDocLines[] = " * @property $phpType \${$fieldName} - {$fieldtype} (from: {$sourceColumn})";

            // Build output code for this field
            $fieldOutputs[] = $this->buildFieldOutputCode($fieldName, $fieldtype);
        }

        // Add FK relationships to PHPDoc
        if (!empty($fkRelationships)) {
            $phpDocLines[] = " * ";
            $phpDocLines[] = " * Foreign Key Relationships:";
            foreach ($fkRelationships as $columnName => $refTable) {
                $phpDocLines[] = " * - $refTable: References via '$columnName' field";
            }
        }

        $phpDocLines[] = " */";
        $phpDoc = implode("\n", $phpDocLines);

        // Build FK query examples
        $fkQueries = [];
        foreach ($fkRelationships as $columnName => $refTable) {
            // Find the ProcessWire field name (target_field) for this column
            $pwFieldName = null;
            if (isset($mapping['fields'][$columnName])) {
                $pwFieldName = $mapping['fields'][$columnName]['target_field'];
            }

            // Only generate FK query if we found the field mapping
            if ($pwFieldName) {
                $fkQueries[] = $this->buildFkQueryExample($pwFieldName, $refTable, $columnName);
            }
        }
        $fkQueriesCode = !empty($fkQueries) ? "\n" . implode("\n", $fkQueries) : '';

        // Build field outputs
        $fieldsCode = implode("\n    ", $fieldOutputs);

        return <<<PHP
<?php namespace ProcessWire;
$phpDoc

?>
<!DOCTYPE html>
<html>
<head>
    <title><?= \$page->title ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; }
        .field { margin: 15px 0; padding: 12px; background: #f9f9f9; border-left: 3px solid #0066cc; }
        .field strong { display: inline-block; min-width: 150px; color: #666; }
        .related { margin-top: 30px; padding: 20px; background: #f0f8ff; border-radius: 4px; }
        .related h2 { margin-top: 0; }
        .related ul { list-style: none; padding: 0; }
        .related li { padding: 8px; margin: 5px 0; background: white; border-radius: 4px; }
        a { color: #0066cc; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1><?= \$page->title ?></h1>

    $fieldsCode{$fkQueriesCode}

    <!-- TODO: Add your custom template code here -->
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
     * Build output code for a field
     */
    protected function buildFieldOutputCode($fieldName, $fieldtype) {
        $safeFieldName = htmlspecialchars($fieldName);

        // Special handling for certain fieldtypes
        if ($fieldtype === 'FieldtypeDatetime') {
            return "<?php if(\$page->{$fieldName}): ?>\n        <div class=\"field\"><strong>{$safeFieldName}:</strong> <?= date('Y-m-d H:i', \$page->{$fieldName}) ?></div>\n    <?php endif; ?>";
        }

        if ($fieldtype === 'FieldtypeCheckbox') {
            return "<?php if(\$page->{$fieldName}): ?>\n        <div class=\"field\"><strong>{$safeFieldName}:</strong> Yes</div>\n    <?php endif; ?>";
        }

        // Default: simple text output
        return "<?php if(\$page->{$fieldName}): ?>\n        <div class=\"field\"><strong>{$safeFieldName}:</strong> <?= \$page->{$fieldName} ?></div>\n    <?php endif; ?>";
    }

    /**
     * Build FK query example code
     *
     * @param string $pwFieldName ProcessWire field name (e.g., "orders_customer_id")
     * @param string $refTable Reference table name (e.g., "customers")
     * @param string $sourceColumn Original SQL column name for documentation (e.g., "customer_id")
     * @return string PHP code
     */
    protected function buildFkQueryExample($pwFieldName, $refTable, $sourceColumn) {
        return <<<PHP

    <!-- Related Pages: {$refTable} -->
    <?php
    // Find pages that reference this record via {$sourceColumn} field (PW field: {$pwFieldName})
    \$related{$refTable} = \$pages->find("template={$refTable}, {$pwFieldName}={\$page->{$pwFieldName}}");
    if(\$related{$refTable}->count): ?>
        <div class="related">
            <h2>{$refTable}</h2>
            <ul>
            <?php foreach(\$related{$refTable} as \$item): ?>
                <li><a href="<?= \$item->url ?>"><?= \$item->title ?></a></li>
            <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
PHP;
    }
}
