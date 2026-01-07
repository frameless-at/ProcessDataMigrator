<?php

namespace ProcessWire;

require_once(__DIR__ . '/classes/parsers/AbstractParser.php');
require_once(__DIR__ . '/classes/parsers/SqlParser.php');
require_once(__DIR__ . '/classes/DataAnalyzer.php');
require_once(__DIR__ . '/classes/TypeDetector.php');
require_once(__DIR__ . '/classes/MappingEngine.php');
require_once(__DIR__ . '/classes/TemplateCreator.php');
require_once(__DIR__ . '/classes/ImportProcessor.php');
require_once(__DIR__ . '/classes/ImportRollback.php');

/**
 * ProcessWire Database Importer
 *
 * Import data from database dumps (SQL, CSV, JSON, XML) into ProcessWire
 *
 * @author ProcessWire
 * @version 1.0.0
 */
class ProcessDatabaseImporter extends Process implements Module {

    /**
     * Module information
     */
    public static function getModuleInfo() {
        return [
            'title' => 'Database Importer',
            'summary' => 'Import data from database dumps into ProcessWire',
            'version' => '1.0.0',
            'author' => 'ProcessWire',
            'icon' => 'database',
            'permission' => 'database-import',
            'permissions' => [
                'database-import' => 'Import database data'
            ],
            'page' => [
                'name' => 'database-importer',
                'parent' => 'setup',
                'title' => 'Database Importer'
            ],
            'requires' => [
                'PHP>=7.4',
                'ProcessWire>=3.0.0'
            ],
        ];
    }

    /**
     * Uploaded files directory
     */
    protected $uploadsPath;

    /**
     * Session key for storing analysis data
     */
    const SESSION_KEY = 'DatabaseImporter';

    /**
     * Initialize the module
     */
    public function init() {
        parent::init();

        // Set uploads directory
        $this->uploadsPath = $this->config->paths->cache . 'DatabaseImporter/';
        if (!is_dir($this->uploadsPath)) {
            wireMkdir($this->uploadsPath, true);
        }

        // Load CSS
        $cssUrl = $this->config->urls->siteModules . 'ProcessDatabaseImporter/assets/css/database-importer.css';
        $this->config->styles->add($cssUrl);
    }

    /**
     * Main execute method
     */
    public function ___execute() {
        // Check for actions first (GET or POST)
        $action = $this->input->get('action') ?: $this->input->post('action');

        if ($action) {
            if ($action === 'clear') {
                return $this->executeClear();
            }
            if ($action === 'import') {
                $sessionData = $this->session->get(self::SESSION_KEY);

                // Store selected tables if submitted via POST
                if ($this->input->post('selected_tables')) {
                    $selectedTables = $this->input->post->array('selected_tables');
                    $sessionData['selected_tables'] = $selectedTables;
                }

                // Store selected fields if submitted via POST (independent of selected_tables!)
                $selectedFields = $this->input->post('fields');
                if ($selectedFields && is_array($selectedFields)) {
                    $sessionData['selected_fields'] = $selectedFields;
                }

                $this->session->set(self::SESSION_KEY, $sessionData);
                return $this->executeImport();
            }
            if ($action === 'rollback') {
                return $this->executeRollback();
            }
        }

        // Check if we have session data (analysis results)
        $sessionData = $this->session->get(self::SESSION_KEY);

        if ($sessionData && isset($sessionData['step'])) {
            // Continue from saved step
            switch ($sessionData['step']) {
                case 'analyze':
                    return $this->executeAnalyze();
                case 'import':
                    return $this->executeImportResult();
                default:
                    return $this->executeUpload();
            }
        }

        return $this->executeUpload();
    }

    /**
     * Step 1: Upload file
     */
    protected function executeUpload() {
        $this->headline('Database Import - Upload');

        // Build form first
        $form = $this->buildUploadForm();

        // Handle file upload via $_FILES
        if ($this->input->post('submit_upload') && isset($_FILES['sql_file'])) {
            // Process form to get other field values
            $form->processInput($this->input->post);

            $file = $_FILES['sql_file'];

            // Validate upload
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->error($this->_('File upload failed'));
            } else if ($file['size'] === 0) {
                $this->error($this->_('File is empty'));
            } else if (!preg_match('/\.sql$/i', $file['name'])) {
                $this->error($this->_('Only .sql files are allowed'));
            } else {
                // Get form field values
                $sampleSize = (int) $form->get('sample_size')->value;
                $maxRows = (int) $form->get('max_rows')->value;

                // Move to temp location
                $tempFile = $this->uploadsPath . uniqid('import_') . '.sql';
                if (move_uploaded_file($file['tmp_name'], $tempFile)) {
                    // Process the file
                    $result = $this->processUpload($tempFile, $sampleSize, $maxRows);

                    if ($result['success']) {
                        // Store analysis in session
                        $this->session->set(self::SESSION_KEY, [
                            'step' => 'analyze',
                            'file' => $result['file'],
                            'temp_file' => $tempFile,
                            'analysis' => $result['analysis'],
                            'tables' => $result['tables'],
                            'max_rows' => $maxRows  // Store for import limiting
                        ]);

                        // Redirect to analysis
                        $this->session->redirect($this->page->url);
                    } else {
                        $this->error($result['error']);
                        // Clean up temp file
                        if (file_exists($tempFile)) {
                            unlink($tempFile);
                        }
                    }
                } else {
                    $this->error($this->_('Failed to save uploaded file'));
                }
            }
        }

        return $form->render();
    }

    /**
     * Step 2: Analyze and show results
     */
    protected function executeAnalyze() {
        $this->headline('Database Import - Analyze');

        $sessionData = $this->session->get(self::SESSION_KEY);
        $analysis = $sessionData['analysis'] ?? [];

        if (empty($analysis)) {
            $this->session->redirect($this->page->url);
            return;
        }

        $out = $this->buildAnalysisView($analysis);

        return $out;
    }

    /**
     * Build upload form
     */
    protected function buildUploadForm() {
        $form = $this->modules->get('InputfieldForm');
        $form->attr('method', 'post');
        $form->attr('action', $this->page->url);
        $form->attr('enctype', 'multipart/form-data');

        // File upload - using Markup instead of InputfieldFile
        $f = $this->modules->get('InputfieldMarkup');
        $f->label = $this->_('Upload Database File');
        $f->description = $this->_('Upload a SQL dump file to analyze and import');

        $fileInput = '<div class="InputfieldContent">';
        $fileInput .= '<input type="file" name="sql_file" accept=".sql" required>';
        $fileInput .= '<p class="description">' . $this->_('Only .sql files are accepted') . '</p>';
        $fileInput .= '</div>';

        $f->value = $fileInput;
        $form->add($f);

        // Options fieldset
        $fieldset = $this->modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Import Options');
        $fieldset->collapsed = Inputfield::collapsedYes;

        // Sample size
        $f = $this->modules->get('InputfieldInteger');
        $f->name = 'sample_size';
        $f->label = $this->_('Sample Size');
        $f->description = $this->_('Number of rows to analyze per table');
        $f->value = 100;
        $f->min = 10;
        $f->max = 1000;
        $fieldset->add($f);

        // Max rows
        $f = $this->modules->get('InputfieldInteger');
        $f->name = 'max_rows';
        $f->label = $this->_('Maximum Rows');
        $f->description = $this->_('Maximum number of rows to import per table (0 = all)');
        $f->value = 0;
        $f->min = 0;
        $fieldset->add($f);

        $form->add($fieldset);

        // Submit
        $f = $this->modules->get('InputfieldSubmit');
        $f->name = 'submit_upload';
        $f->value = $this->_('Analyze File');
        $f->icon = 'search';
        $form->add($f);

        return $form;
    }

    /**
     * Process uploaded file
     */
    protected function processUpload($filePath, $sampleSize = 100, $maxRows = 0) {
        // Initialize parser
        $parser = new SqlParser();

        if (!$parser->canParse($filePath)) {
            return [
                'success' => false,
                'error' => 'File format not supported or invalid SQL file'
            ];
        }

        // Parse file
        $options = [
            'sample_size' => $sampleSize,
            'max_rows' => $maxRows,
        ];

        try {
            $tables = $parser->parse($filePath, $options);

            if (empty($tables)) {
                return [
                    'success' => false,
                    'error' => 'No tables found in SQL file'
                ];
            }

            // Analyze each table
            $analyzer = new DataAnalyzer();
            $analysis = [];

            foreach ($tables as $tableName => $tableData) {
                $analysis[$tableName] = $analyzer->analyze($tableData, $options);
            }

            return [
                'success' => true,
                'file' => basename($filePath),
                'tables' => $tables,
                'analysis' => $analysis,
                'metadata' => $parser->getMetadata()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error parsing file: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build analysis view
     */
    protected function buildAnalysisView($analysis) {
        $out = '<form method="post" action="' . $this->page->url . '">';
        $out .= '<div class="database-importer-analysis">';

        // Summary
        $totalRows = array_sum(array_column($analysis, 'row_count'));
        $totalColumns = array_sum(array_map(function($table) {
            return count($table['columns']);
        }, $analysis));

        $out .= '<div class="uk-alert uk-alert-success">';
        $out .= '<h3>' . $this->_('Analysis Complete') . '</h3>';
        $out .= '<p>';
        $out .= sprintf(
            $this->_('%d tables found - Select tables to import below'),
            count($analysis)
        );
        $out .= '<br>';
        $out .= sprintf(
            $this->_('%d total rows and %d columns'),
            $totalRows,
            $totalColumns
        );
        $out .= '</p>';
        $out .= '</div>';

        // Tables
        foreach ($analysis as $tableName => $tableAnalysis) {
            $out .= $this->buildTableAnalysis($tableName, $tableAnalysis);
        }

        // Add action buttons inside the form
        $out .= $this->buildAnalysisActions();

        $out .= '</div>';
        $out .= '</form>';

        return $out;
    }

    /**
     * Build single table analysis view
     */
    protected function buildTableAnalysis($tableName, $analysis) {
        $out = '<div class="table-analysis uk-margin" style="border: 2px solid #ddd; padding: 15px; border-radius: 4px;">';

        // Checkbox for table selection
        $out .= '<div style="margin-bottom: 10px;">';
        $out .= '<label style="font-size: 16px; font-weight: bold;">';
        $out .= '<input type="checkbox" name="selected_tables[]" value="' . $this->sanitizer->entities($tableName) . '" checked> ';
        $out .= $this->sanitizer->entities($tableName);
        $out .= '</label>';
        $out .= '</div>';

        $out .= '<dl class="uk-description-list">';
        $out .= '<dt>' . $this->_('Rows') . ':</dt>';
        $out .= '<dd>' . number_format($analysis['row_count']) . '</dd>';

        $out .= '<dt>' . $this->_('Columns') . ':</dt>';
        $out .= '<dd>' . count($analysis['columns']) . '</dd>';

        if ($analysis['suggested_template']) {
            $out .= '<dt>' . $this->_('Suggested Template') . ':</dt>';
            $out .= '<dd><code>' . $this->sanitizer->entities($analysis['suggested_template']) . '</code></dd>';
        }

        if ($analysis['suggested_title_field']) {
            $out .= '<dt>' . $this->_('Suggested Title Field') . ':</dt>';
            $out .= '<dd><code>' . $this->sanitizer->entities($analysis['suggested_title_field']) . '</code></dd>';
        }

        $out .= '</dl>';

        // Columns table
        $out .= '<table class="uk-table uk-table-striped uk-table-small">';
        $out .= '<thead>';
        $out .= '<tr>';
        $out .= '<th style="width: 30px;">' . $this->_('Import') . '</th>';
        $out .= '<th>' . $this->_('Column') . '</th>';
        $out .= '<th>' . $this->_('SQL Type') . '</th>';
        $out .= '<th>' . $this->_('Detected Type') . '</th>';
        $out .= '<th>' . $this->_('Suggested Fieldtype') . '</th>';
        $out .= '<th>' . $this->_('Confidence') . '</th>';
        $out .= '<th>' . $this->_('Sample Values') . '</th>';
        $out .= '</tr>';
        $out .= '</thead>';
        $out .= '<tbody>';

        foreach ($analysis['columns'] as $column) {
            $columnName = $column['name'];
            $isIdField = $column['is_likely_id'];
            $isTitleField = ($columnName === $analysis['suggested_title_field']);

            // Checkbox: checked by default, except for ID fields
            $checked = !$isIdField ? ' checked' : '';
            // Title field is required, so make it disabled but checked
            $disabled = $isTitleField ? ' disabled checked' : '';

            $out .= '<tr>';
            $out .= '<td>';
            // Use consistent format like table checkboxes: name="fields[table][]" value="fieldname"
            $out .= '<input type="checkbox" name="fields[' . $tableName . '][]" value="' . $columnName . '"' . $checked . $disabled . '>';
            // If disabled (title field), add hidden input to ensure it's included in POST
            if ($disabled) {
                $out .= '<input type="hidden" name="fields[' . $tableName . '][]" value="' . $columnName . '">';
            }
            $out .= '</td>';
            $out .= '<td><strong>' . $this->sanitizer->entities($columnName) . '</strong>';

            // Add badges
            if ($column['is_likely_id']) {
                $out .= ' <span class="uk-badge">ID</span>';
            }

            // Title badge: only for the actual suggested title field
            if ($column['name'] === $analysis['suggested_title_field']) {
                $out .= ' <span class="uk-badge uk-badge-success">Title</span>';
            }

            $out .= '</td>';
            $out .= '<td><code>' . $this->sanitizer->entities($column['sql_type']) . '</code></td>';
            $out .= '<td>' . $this->sanitizer->entities($column['detected_type']) . '</td>';
            $out .= '<td><code>' . $this->sanitizer->entities($column['suggested_fieldtype']) . '</code></td>';

            // Confidence with color
            $confidence = $column['detection_confidence'];
            $color = $confidence >= 80 ? 'success' : ($confidence >= 60 ? 'warning' : 'danger');
            $out .= '<td><span class="uk-text-' . $color . '">' . $confidence . '%</span></td>';

            // Sample values
            $samples = array_slice($column['sample_values'], 0, 3);
            $samplesHtml = array_map(function($v) {
                $v = $this->sanitizer->entities((string) $v);
                return strlen($v) > 30 ? substr($v, 0, 30) . '...' : $v;
            }, $samples);
            $out .= '<td><small>' . implode(', ', $samplesHtml) . '</small></td>';

            $out .= '</tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';

        $out .= '</div>';

        return $out;
    }

    /**
     * Build analysis action buttons
     */
    protected function buildAnalysisActions() {
        $out = '<div class="uk-margin">';

        // Import button (submits form with selected tables)
        $out .= '<button type="submit" name="action" value="import" class="ui-button ui-priority-primary">';
        $out .= '<i class="fa fa-upload"></i> ' . $this->_('Import Selected Tables');
        $out .= '</button>';

        $out .= ' &nbsp; ';

        // Clear session button
        $out .= '<a href="' . $this->page->url . '?action=clear" class="ui-button ui-priority-secondary">';
        $out .= '<i class="fa fa-arrow-left"></i> ' . $this->_('Start Over');
        $out .= '</a>';

        $out .= '</div>';

        return $out;
    }

    /**
     * Execute import process
     */
    protected function executeImport() {
        $this->headline('Database Import - Processing');

        // Get session data
        $sessionData = $this->session->get(self::SESSION_KEY);
        if (!$sessionData || !isset($sessionData['analysis'])) {
            $this->error($this->_('No analysis data found. Please start over.'));
            $this->session->redirect($this->page->url);
        }

        // Get all tables data
        $allAnalysis = $sessionData['analysis'] ?? [];
        $allTables = $sessionData['tables'] ?? [];

        if (empty($allAnalysis) || empty($allTables)) {
            $this->error($this->_('No table data found in session. Please start over.'));
            $this->session->redirect($this->page->url);
        }

        // Get selected tables (or all if none selected)
        $selectedTables = $sessionData['selected_tables'] ?? array_keys($allAnalysis);

        if (empty($selectedTables)) {
            $this->error($this->_('No tables selected for import.'));
            $this->session->redirect($this->page->url);
        }

        // Import each selected table
        $allRollbackData = [];
        $totalImported = 0;
        $maxRows = $sessionData['max_rows'] ?? 0;

        try {
            foreach ($selectedTables as $tableName) {
                $analysis = $allAnalysis[$tableName] ?? null;
                $tableData = $allTables[$tableName] ?? null;

                if (!$analysis || !$tableData) {
                    $this->error($this->_("Table '{$tableName}' not found in analysis"));
                    continue;
                }

                $this->message($this->_("Importing table: {$tableName}"));

                // Step 1: Create automatic mapping
                $mappingEngine = $this->wire(new MappingEngine());
                $mapping = $mappingEngine->createMapping($analysis, $tableName);

                // Filter mapping to only include selected fields (if field selection was used)
                if (isset($sessionData['selected_fields'])) {
                    $selectedFields = $sessionData['selected_fields'][$tableName] ?? [];
                    $filteredFields = [];
                    foreach ($mapping['fields'] as $columnName => $fieldMapping) {
                        // Include field if it's in selected array OR if it's the title field (always required)
                        if (in_array($columnName, $selectedFields) || $columnName === $mapping['title_field']) {
                            $filteredFields[$columnName] = $fieldMapping;
                        }
                    }
                    $mapping['fields'] = $filteredFields;
                    $fieldCount = count($filteredFields);
                    $this->message($this->_("Filtered to $fieldCount selected field(s) for table {$tableName}"));
                }

                // Step 2: Create template and fields
                $templateCreator = $this->wire(new TemplateCreator());
                $template = $templateCreator->createTemplate($mapping);

                $this->message($this->_('Created template: ') . $template->name);

                // Step 3: Create parent page
                $parent = $templateCreator->createParentPage($mapping['parent'], $template->name);

                $this->message($this->_('Created parent page: ') . $parent->path);

                // Step 4: Import data
                // CRITICAL: Limit data to max_rows for import (analysis used full sample_size)
                $importData = $tableData['data'];
                if ($maxRows > 0 && count($importData) > $maxRows) {
                    $importData = array_slice($importData, 0, $maxRows);
                    $this->message($this->_("Import limited to {$maxRows} rows for table {$tableName}"));
                }

                $importProcessor = $this->wire(new ImportProcessor());
                $result = $importProcessor->import($importData, $mapping, $template, $parent);

                $totalImported += $result['imported'];

                // Collect rollback data for this table
                $allRollbackData[] = [
                    'table' => $tableName,
                    'template' => $template->name,
                    'parent_page' => $parent->path,
                    'created_fields' => $templateCreator->getCreatedFields(),
                    'created_pages' => $result['created_pages'],
                    'errors' => $result['errors'],
                    'timestamp' => time()
                ];

                // Show import summary
                $this->message($this->_("Imported {$result['imported']} pages for table {$tableName}"));

                // Show errors if any
                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $this->error($this->_("Row {$error['row']}: {$error['error']}"));
                    }
                }
            }

            // Store import results in session
            $sessionData['step'] = 'import';
            $sessionData['total_imported'] = $totalImported;
            $sessionData['rollback_data'] = $allRollbackData;
            $this->session->set(self::SESSION_KEY, $sessionData);

            // Redirect to results
            $this->session->redirect($this->page->url);

        } catch (\Exception $e) {
            $this->error($this->_('Import failed: ') . $e->getMessage());
            return $this->executeAnalyze();
        }
    }

    /**
     * Show import results
     */
    protected function executeImportResult() {
        $this->headline('Database Import - Results');

        // Get session data
        $sessionData = $this->session->get(self::SESSION_KEY);
        if (!$sessionData || !isset($sessionData['rollback_data'])) {
            $this->error($this->_('No import results found. Please start over.'));
            $this->session->redirect($this->page->url);
        }

        $rollbackData = $sessionData['rollback_data'];
        $totalImported = $sessionData['total_imported'] ?? 0;

        $out = '';

        // Success summary
        $out .= '<div class="uk-alert uk-alert-success">';
        $out .= '<h3>' . $this->_('Import Completed Successfully') . '</h3>';
        $out .= '<p>' . sprintf(
            $this->_('Successfully imported %d pages from %d tables'),
            $totalImported,
            count($rollbackData)
        ) . '</p>';
        $out .= '</div>';

        // Statistics for each table
        foreach ($rollbackData as $tableData) {
            $hasErrors = !empty($tableData['errors']);
            $borderColor = $hasErrors ? '#f0ad4e' : '#ddd';

            $out .= '<div class="table-analysis uk-margin" style="border: 2px solid ' . $borderColor . '; padding: 15px; border-radius: 4px;">';
            $out .= '<h3>' . $this->sanitizer->entities($tableData['table']) . '</h3>';
            $out .= '<dl class="uk-description-list">';
            $out .= '<dt>' . $this->_('Template') . '</dt>';
            $out .= '<dd><code>' . $this->sanitizer->entities($tableData['template']) . '</code></dd>';
            $out .= '<dt>' . $this->_('Parent Page') . '</dt>';
            $out .= '<dd>' . $this->sanitizer->entities($tableData['parent_page']) . '</dd>';
            $out .= '<dt>' . $this->_('Pages Created') . '</dt>';
            $out .= '<dd><strong>' . count($tableData['created_pages']) . '</strong></dd>';
            $out .= '<dt>' . $this->_('Fields Created') . '</dt>';
            $out .= '<dd>' . count($tableData['created_fields']) . '</dd>';
            $out .= '<dt>' . $this->_('Errors') . '</dt>';
            $out .= '<dd>' . ($hasErrors ? '<strong style="color: #f0ad4e;">' . count($tableData['errors']) . '</strong>' : '0') . '</dd>';
            $out .= '</dl>';

            // Show errors if any
            if ($hasErrors) {
                $out .= '<div class="uk-alert uk-alert-warning" style="margin-top: 10px;">';
                $out .= '<h4>' . $this->_('Import Errors') . '</h4>';
                $out .= '<ul>';
                foreach ($tableData['errors'] as $error) {
                    $out .= '<li><strong>Row ' . $error['row'] . ':</strong> ' . $this->sanitizer->entities($error['error']) . '</li>';
                }
                $out .= '</ul>';
                $out .= '</div>';
            }

            $out .= '</div>';
        }

        // Actions
        $out .= '<div class="uk-margin">';

        // Rollback button (if rollback data available)
        if (isset($sessionData['rollback_data'])) {
            $out .= '<a href="' . $this->page->url . '?action=rollback" class="ui-button ui-priority-warning" ';
            $out .= 'onclick="return confirm(\'' . $this->_('Delete all imported data? This cannot be undone!') . '\')">';
            $out .= '<i class="fa fa-trash"></i> ' . $this->_('Rollback Import');
            $out .= '</a>';
            $out .= ' &nbsp; ';
        }

        $out .= '<a href="' . $this->page->url . '?action=clear" class="ui-button ui-priority-secondary">';
        $out .= '<i class="fa fa-arrow-left"></i> ' . $this->_('Start Over');
        $out .= '</a>';
        $out .= '</div>';

        return $out;
    }

    /**
     * Rollback import - delete all created items
     */
    protected function executeRollback() {
        $this->headline('Database Import - Rollback');

        // Get session data
        $sessionData = $this->session->get(self::SESSION_KEY);
        if (!$sessionData || !isset($sessionData['rollback_data'])) {
            $this->error($this->_('No rollback data found.'));
            $this->session->redirect($this->page->url);
        }

        $rollbackData = $sessionData['rollback_data'];

        // Handle both single rollback (old format) and multiple (new format)
        if (!isset($rollbackData[0])) {
            // Old format: single rollback data
            $rollbackData = [$rollbackData];
        }

        // Execute rollback for all tables
        $rollback = $this->wire(new ImportRollback());
        $combinedResult = [
            'pages_deleted' => 0,
            'templates_deleted' => 0,
            'fields_deleted' => 0,
            'errors' => []
        ];

        foreach ($rollbackData as $tableRollback) {
            $result = $rollback->rollback($tableRollback);
            $combinedResult['pages_deleted'] += $result['pages_deleted'];
            $combinedResult['templates_deleted'] += $result['templates_deleted'];
            $combinedResult['fields_deleted'] += $result['fields_deleted'];
            $combinedResult['errors'] = array_merge($combinedResult['errors'], $result['errors']);
        }

        $result = $combinedResult;

        // Build result page
        $out = '';

        if (empty($result['errors'])) {
            $out .= '<div class="uk-alert uk-alert-success">';
            $out .= '<h3>' . $this->_('Rollback Completed Successfully') . '</h3>';
            $out .= '<p>' . $this->_('All imported data has been deleted.') . '</p>';
            $out .= '</div>';
        } else {
            $out .= '<div class="uk-alert uk-alert-warning">';
            $out .= '<h3>' . $this->_('Rollback Completed with Errors') . '</h3>';
            $out .= '<p>' . $this->_('Some items could not be deleted.') . '</p>';
            $out .= '</div>';
        }

        // Statistics
        $out .= '<div class="table-analysis">';
        $out .= '<h3>' . $this->_('Rollback Statistics') . '</h3>';
        $out .= '<dl class="uk-description-list">';
        $out .= '<dt>' . $this->_('Pages Deleted') . '</dt>';
        $out .= '<dd>' . $result['pages_deleted'] . '</dd>';
        $out .= '<dt>' . $this->_('Templates Deleted') . '</dt>';
        $out .= '<dd>' . $result['templates_deleted'] . '</dd>';
        $out .= '<dt>' . $this->_('Fields Deleted') . '</dt>';
        $out .= '<dd>' . $result['fields_deleted'] . '</dd>';
        $out .= '<dt>' . $this->_('Errors') . '</dt>';
        $out .= '<dd>' . count($result['errors']) . '</dd>';
        $out .= '</dl>';
        $out .= '</div>';

        // Errors
        if (!empty($result['errors'])) {
            $out .= '<div class="table-analysis uk-margin">';
            $out .= '<h3>' . $this->_('Errors') . '</h3>';
            $out .= '<ul>';
            foreach ($result['errors'] as $error) {
                $out .= '<li>' . $this->sanitizer->entities($error) . '</li>';
            }
            $out .= '</ul>';
            $out .= '</div>';
        }

        // Clear session after rollback
        $this->session->remove(self::SESSION_KEY);

        // Action
        $out .= '<div class="uk-margin">';
        $out .= '<a href="' . $this->page->url . '" class="ui-button ui-priority-primary">';
        $out .= '<i class="fa fa-upload"></i> ' . $this->_('Start New Import');
        $out .= '</a>';
        $out .= '</div>';

        return $out;
    }

    /**
     * Clear session data and return to upload
     */
    protected function executeClear() {
        // Clean up temp file if exists
        $sessionData = $this->session->get(self::SESSION_KEY);
        if (isset($sessionData['temp_file']) && file_exists($sessionData['temp_file'])) {
            unlink($sessionData['temp_file']);
        }

        $this->session->remove(self::SESSION_KEY);
        $this->message($this->_('Session cleared'));
        $this->session->redirect($this->page->url);
    }

    /**
     * Install the module
     */
    public function ___install() {
        // Create uploads directory
        $uploadsPath = $this->config->paths->cache . 'DatabaseImporter/';
        if (!is_dir($uploadsPath)) {
            wireMkdir($uploadsPath, true);
        }

        // Create permission
        $permission = $this->permissions->get('database-import');
        if (!$permission->id) {
            $permission = $this->permissions->add('database-import');
            $permission->title = 'Database Import';
            $permission->save();
            $this->message("Created permission: database-import");
        }

        // Create admin page
        $page = $this->pages->get('template=admin, name=database-importer');
        if (!$page->id) {
            // Get setup page as parent
            $parent = $this->pages->get($this->config->adminRootPageID)->child('name=setup');
            if (!$parent->id) {
                throw new WireException("Setup page not found");
            }

            $page = new Page();
            $page->template = 'admin';
            $page->parent = $parent;
            $page->name = 'database-importer';
            $page->title = 'Database Importer';
            $page->process = $this;
            $page->save();

            $this->message("Created page: {$page->path}");
        }
    }

    /**
     * Uninstall the module
     */
    public function ___uninstall() {
        // Remove uploads directory
        $uploadsPath = $this->config->paths->cache . 'DatabaseImporter/';
        if (is_dir($uploadsPath)) {
            wireRmdir($uploadsPath, true);
        }

        // Remove admin page
        $page = $this->pages->get('template=admin, name=database-importer');
        if ($page->id) {
            $this->pages->delete($page, true);
            $this->message("Removed page: database-importer");
        }

        // Note: We don't remove the permission as it might be in use
    }
}
