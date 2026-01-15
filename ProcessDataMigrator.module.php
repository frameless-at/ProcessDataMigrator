<?php

namespace ProcessWire;

require_once(__DIR__ . '/classes/parsers/AbstractParser.php');
require_once(__DIR__ . '/classes/parsers/SqlParser.php');
require_once(__DIR__ . '/classes/parsers/CsvParser.php');
require_once(__DIR__ . '/classes/parsers/JsonParser.php');
require_once(__DIR__ . '/classes/parsers/XmlParser.php');
require_once(__DIR__ . '/classes/DataAnalyzer.php');
require_once(__DIR__ . '/classes/TypeDetector.php');
require_once(__DIR__ . '/classes/MappingEngine.php');
require_once(__DIR__ . '/classes/TemplateCreator.php');
require_once(__DIR__ . '/classes/ImportProcessor.php');
require_once(__DIR__ . '/classes/ImportRollback.php');
require_once(__DIR__ . '/classes/Logger.php');

/**
 * ProcessWire Data Migrator
 *
 * Migrate external data (SQL, CSV, JSON, XML) into ProcessWire
 *
 * @author frameless Media
 * @version 1.1.1
 */
class ProcessDataMigrator extends Process implements Module, ConfigurableModule {

    /**
     * Module information
     */
    public static function getModuleInfo() {
        return [
            'title' => 'Data Migrator',
            'summary' => 'Migrate external data (SQL, CSV, JSON, XML) into ProcessWire',
            'version' => '1.1.1',
            'author' => 'ProcessWire',
            'icon' => 'exchange',
            'permission' => 'data-migrate',
            'permissions' => [
                'data-migrate' => 'Migrate external data into ProcessWire'
            ],
            'page' => [
                'name' => 'data-migrator',
                'parent' => 'setup',
                'title' => 'Data Migrator'
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
    const SESSION_KEY = 'DataMigrator';

    /**
     * Maximum file upload size in bytes (50 MB)
     * SECURITY: Prevents resource exhaustion attacks via large file uploads
     */
    const MAX_FILE_SIZE = 52428800;

    /**
     * Initialize the module
     */
    public function init() {
        parent::init();

        // Set uploads directory
        $this->uploadsPath = $this->config->paths->cache . 'DataMigrator/';
        if (!is_dir($this->uploadsPath)) {
            wireMkdir($this->uploadsPath, true);
        }

        // Load CSS
        $cssUrl = $this->config->urls->siteModules . 'ProcessDataMigrator/assets/css/data-migrator.css';
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
            if ($action === 'dry_run' || $action === 'import') {
                $sessionData = $this->session->get(self::SESSION_KEY);

                // Store selected tables if submitted via POST
                if ($this->input->post('selected_tables')) {
                    $selectedTables = $this->input->post->array('selected_tables');
                    $sessionData['selected_tables'] = $selectedTables;
                }

                // Store selected fields if submitted via POST (independent of selected_tables!)
                // CRITICAL: Use $_POST directly - WireInput filters nested arrays!
                $selectedFields = isset($_POST['fields']) ? $_POST['fields'] : null;
                if ($selectedFields && is_array($selectedFields)) {
                    $sessionData['selected_fields'] = $selectedFields;
                }

                // Store fieldtype overrides if submitted via POST
                // CRITICAL: Use $_POST directly - WireInput filters nested arrays!
                $fieldtypeOverrides = isset($_POST['fieldtypes']) ? $_POST['fieldtypes'] : null;
                if ($fieldtypeOverrides && is_array($fieldtypeOverrides)) {
                    $sessionData['fieldtype_overrides'] = $fieldtypeOverrides;
                }

                // Store FK table mappings
                $fkTables = isset($_POST['fk_table']) ? $_POST['fk_table'] : null;
                if ($fkTables && is_array($fkTables)) {
                    $fkMappings = [];
                    foreach ($fkTables as $tableName => $columns) {
                        foreach ($columns as $columnName => $refTable) {
                            if (!empty($refTable)) {
                                $fkMappings[$tableName][$columnName] = $refTable;
                            }
                        }
                    }
                    if (!empty($fkMappings)) {
                        $sessionData['fk_mappings'] = $fkMappings;
                    }
                }

                $this->session->set(self::SESSION_KEY, $sessionData);

                // Route to dry run or actual import
                if ($action === 'dry_run') {
                    return $this->executeDryRun();
                } else {
                    return $this->executeImport();
                }
            }
            if ($action === 'confirm_import') {
                // Execute import with existing session data (after dry run confirmation)
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
        $this->headline('Data Migration - Upload');

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
            } else if ($file['size'] > self::MAX_FILE_SIZE) {
                // SECURITY: Prevent resource exhaustion attacks via large file uploads
                $maxMb = round(self::MAX_FILE_SIZE / 1048576);
                $this->error(sprintf($this->_('File size exceeds maximum allowed size of %d MB'), $maxMb));
            } else if (!preg_match('/\.(sql|csv|json|xml)$/i', $file['name'])) {
                $this->error($this->_('Only .sql, .csv, .json, and .xml files are allowed'));
            } else {
                // Get form field values
                $sampleSize = (int) $form->get('sample_size')->value;
                $maxRows = (int) $form->get('max_rows')->value;

                // Move to temp location - preserve original extension
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $tempFile = $this->uploadsPath . uniqid('import_') . '.' . $extension;
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
        $this->headline('Data Migration - Analyze');

        $sessionData = $this->session->get(self::SESSION_KEY);
        $analysis = $sessionData['analysis'] ?? [];

        if (empty($analysis)) {
            $this->session->redirect($this->page->url);
            return;
        }

        // Pass session data to preserve user selections after errors/back from dry run
        $out = $this->buildAnalysisView($analysis, $sessionData);

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
        $f->label = $this->_('Upload Data File');
        $f->description = $this->_('Upload a data file to analyze and migrate');

        $fileInput = '<div class="InputfieldContent">';
        $fileInput .= '<input type="file" name="sql_file" accept=".sql,.csv,.json,.xml" required>';
        $fileInput .= '<p class="description">' . $this->_('Supported formats: SQL, CSV, JSON, XML') . '</p>';
        $fileInput .= '</div>';

        $f->value = $fileInput;
        $form->add($f);

        // Options fieldset
        $fieldset = $this->modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Migration Options');
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
        $f->description = $this->_('Maximum number of rows to migrate per table (0 = all)');
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
        // Detect and initialize appropriate parser
        $parser = $this->detectParser($filePath);

        if (!$parser) {
            return [
                'success' => false,
                'error' => 'File format not supported. Supported formats: SQL, CSV, JSON, XML'
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
                    'error' => 'No data found in file'
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
     * Detect and return appropriate parser for file
     *
     * @param string $filePath Path to file
     * @return AbstractParser|null Parser instance or null if no suitable parser found
     */
    protected function detectParser($filePath) {
        // List of available parsers in priority order
        $parsers = [
            new CsvParser(),
            new JsonParser(),
            new XmlParser(),
            new SqlParser(),
        ];

        // Try each parser
        foreach ($parsers as $parser) {
            if ($parser->canParse($filePath)) {
                $parserName = get_class($parser);
                $parserName = str_replace('ProcessWire\\', '', $parserName);
                $this->message($this->_("Using parser: {$parserName}"));
                return $parser;
            }
        }

        return null;
    }

    /**
     * Build analysis view
     */
    protected function buildAnalysisView($analysis, $sessionData = []) {
        $allTableNames = array_keys($analysis);

        $out = '<form method="post" action="' . $this->page->url . '">';
        $out .= '<div class="data-migrator-analysis">';
        // Summary
        $totalRows = array_sum(array_column($analysis, 'row_count'));
        $totalColumns = array_sum(array_map(function($table) {
            return count($table['columns']);
        }, $analysis));

        $out .= '<div class="uk-alert uk-alert-success">';
        $out .= '<h3>' . $this->_('Analysis Complete') . '</h3>';
        $out .= '<p>';
        $out .= sprintf(
            $this->_('%d tables found - Select tables to migrate below'),
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
            $out .= $this->buildTableAnalysis($tableName, $tableAnalysis, $allTableNames, $sessionData);
        }

        // Add action buttons inside the form
        $out .= $this->buildAnalysisActions();

        $out .= '</div>';
        $out .= '</form>';

        return $out;
    }

    /**
     * Get available fieldtypes for selection
     */
    protected function getAvailableFieldtypes() {
        return [
            'FieldtypeText' => 'Text',
            'FieldtypeTextarea' => 'Textarea',
            'FieldtypeInteger' => 'Integer',
            'FieldtypeFloat' => 'Float',
            'FieldtypeCheckbox' => 'Checkbox',
            'FieldtypeEmail' => 'Email',
            'FieldtypeURL' => 'URL',
            'FieldtypeDatetime' => 'Datetime',
            'FieldtypeOptions' => 'Options/Select',
            'FieldtypePage' => 'Page Reference *',
            'FieldtypeImage' => 'Image **',
            'FieldtypeFile' => 'File **',
            'FieldtypePassword' => 'Password',
        ];
    }

    /**
     * Build fieldtype selector dropdown
     */
    protected function buildFieldtypeSelector($tableName, $columnName, $suggested, $sessionData = []) {
        $fieldtypes = $this->getAvailableFieldtypes();

        // Check if there's a fieldtype override from session data
        $selectedFieldtype = $sessionData['fieldtype_overrides'][$tableName][$columnName] ?? $suggested;

        // CRITICAL: Don't escape the square brackets in the name attribute!
        // entities() would convert [ to &#91; which breaks POST array structure
        $safeName = 'fieldtypes[' . $this->sanitizer->name($tableName) . '][' . $this->sanitizer->name($columnName) . ']';

        $out = '<select name="' . $safeName . '" class="uk-select" style="font-size: 12px; padding: 2px 4px;">';

        foreach ($fieldtypes as $type => $label) {
            $selected = ($type === $selectedFieldtype) ? ' selected' : '';
            $out .= '<option value="' . $this->sanitizer->entities($type) . '"' . $selected . '>' . $this->sanitizer->entities($label) . '</option>';
        }

        $out .= '</select>';

        return $out;
    }

    /**
     * Build single table analysis view
     */
    protected function buildTableAnalysis($tableName, $analysis, $allTableNames = [], $sessionData = []) {
        $out = '<div class="table-analysis uk-margin" style="border: 2px solid #ddd; padding: 15px; border-radius: 4px;">';

        // Checkbox for table selection - restore from session if available
        $isTableSelected = true; // Default: checked
        if (isset($sessionData['selected_tables'])) {
            // Session data exists, use it
            $isTableSelected = in_array($tableName, $sessionData['selected_tables']);
        }

        $out .= '<div style="margin-bottom: 10px;">';
        $out .= '<label style="font-size: 16px; font-weight: bold;">';
        $out .= '<input type="checkbox" name="selected_tables[]" value="' . $this->sanitizer->entities($tableName) . '"' . ($isTableSelected ? ' checked' : '') . '> ';
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
        $out .= '<th style="width: 30px;">' . $this->_('Migrate') . '</th>';
        $out .= '<th>' . $this->_('Column') . '</th>';
        $out .= '<th>' . $this->_('Detected Type') . '</th>';
        $out .= '<th style="min-width: 250px;">' . $this->_('Suggested Fieldtype') . '</th>';
        $out .= '<th>' . $this->_('Confidence') . '</th>';
        $out .= '<th>' . $this->_('Sample Values') . '</th>';
        $out .= '</tr>';
        $out .= '</thead>';
        $out .= '<tbody>';

        foreach ($analysis['columns'] as $column) {
            $columnName = $column['name'];
            $isIdField = $column['is_likely_id'];
            $isTitleField = ($columnName === $analysis['suggested_title_field']);

            // Show FK dropdown for all integer fields (except main ID field)
            // User decides which fields are actually foreign keys
            $isPotentialFk = (
                in_array($column['detected_type'], ['integer', 'int']) &&
                !$isIdField // Not the main ID field
            );

            // Checkbox: restore from session if available, otherwise default (checked except for ID fields)
            $isFieldSelected = !$isIdField; // Default: checked if not ID field
            if (isset($sessionData['selected_fields'][$tableName])) {
                // Session data exists, use it
                $isFieldSelected = in_array($columnName, $sessionData['selected_fields'][$tableName]);
            }

            $checked = $isFieldSelected ? ' checked' : '';
            // Title field is required, so make it disabled but checked
            $disabled = $isTitleField ? ' disabled checked' : '';

            $out .= '<tr>';
            $out .= '<td>';
            $out .= '<input type="checkbox" name="fields[' . $tableName . '][]" value="' . $columnName . '"' . $checked . $disabled . '>';
            if ($disabled) {
                $out .= '<input type="hidden" name="fields[' . $tableName . '][]" value="' . $columnName . '">';
            }
            $out .= '</td>';
            $out .= '<td><strong>' . $this->sanitizer->entities($columnName) . '</strong>';

            if ($column['is_likely_id']) {
                $out .= ' <span class="uk-badge">ID</span>';
            }

            if ($column['name'] === $analysis['suggested_title_field']) {
                $out .= ' <span class="uk-badge uk-badge-success">Title</span>';
            }

            $out .= '</td>';
            $out .= '<td>' . $this->sanitizer->entities($column['detected_type']) . '</td>';

            // Fieldtype + FK combined in one cell
            $out .= '<td style="white-space: nowrap;">';
            $out .= '<div style="display: flex; align-items: center; gap: 8px;">';
            $out .= $this->buildFieldtypeSelector($tableName, $columnName, $column['suggested_fieldtype'], $sessionData);

            // Add FK dropdown inline for potential FK fields (integer fields, not ID)
            if ($isPotentialFk) {
                // Check if there's an FK mapping from session data
                $selectedFkTable = $sessionData['fk_mappings'][$tableName][$columnName] ?? '';

                $out .= '<span style="color: #666; font-size: 11px;">FK:</span>';
                $out .= '<select name="fk_table[' . $this->sanitizer->name($tableName) . '][' . $this->sanitizer->name($columnName) . ']" ';
                $out .= 'class="uk-select" style="font-size: 12px; padding: 2px 6px; width: auto; min-width: 100px;">';
                $out .= '<option value="">--</option>';
                foreach ($allTableNames as $tbl) {
                    $selected = ($tbl === $selectedFkTable) ? ' selected' : '';
                    $out .= '<option value="' . $this->sanitizer->entities($tbl) . '"' . $selected . '>' . $this->sanitizer->entities($tbl) . '</option>';
                }
                $out .= '</select>';
            }
            $out .= '</div>';
            $out .= '</td>';

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

        // Dry Run button (recommended)
        $out .= '<button type="submit" name="action" value="dry_run" class="ui-button ui-priority-primary">';
        $out .= '<i class="fa fa-eye"></i> ' . $this->_('Preview Migration (Dry Run)');
        $out .= '</button>';

        $out .= ' &nbsp; ';

        // Direct import button (skip preview)
        $out .= '<button type="submit" name="action" value="import" class="ui-button ui-priority-secondary">';
        $out .= '<i class="fa fa-upload"></i> ' . $this->_('Migrate Now (Skip Preview)');
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
     * Build dry run confirmation screen
     */
    protected function buildDryRunConfirmation($dryRunResult) {
        $out = '<div class="uk-container">';

        // Success alert
        $out .= '<div class="uk-alert uk-alert-success" style="margin: 20px 0;">';
        $out .= '<h3 style="margin-top: 0;"><i class="fa fa-check-circle"></i> ' . $this->_('Dry Run Complete - Preview Results') . '</h3>';
        $out .= '<p>' . $this->_('The following changes will be made when you execute the migration:') . '</p>';
        $out .= '</div>';

        // Summary boxes
        $out .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">';

        // Templates count
        $templateCount = count($dryRunResult['templates']);
        $out .= '<div style="background: #f8f9fa; border-left: 4px solid #3B82F6; padding: 15px;">';
        $out .= '<div style="font-size: 24px; font-weight: bold; color: #3B82F6;">' . $templateCount . '</div>';
        $out .= '<div style="color: #666;">' . $this->_('Templates') . '</div>';
        $out .= '</div>';

        // Fields count
        $fieldsCount = count($dryRunResult['fields']);
        $out .= '<div style="background: #f8f9fa; border-left: 4px solid #10B981; padding: 15px;">';
        $out .= '<div style="font-size: 24px; font-weight: bold; color: #10B981;">' . $fieldsCount . '</div>';
        $out .= '<div style="color: #666;">' . $this->_('Fields') . '</div>';
        $out .= '</div>';

        // Pages count
        $pagesCount = $dryRunResult['pages_count'];
        $out .= '<div style="background: #f8f9fa; border-left: 4px solid #F59E0B; padding: 15px;">';
        $out .= '<div style="font-size: 24px; font-weight: bold; color: #F59E0B;">' . number_format($pagesCount) . '</div>';
        $out .= '<div style="color: #666;">' . $this->_('Pages') . '</div>';
        $out .= '</div>';

        // FK relationships count
        $fkCount = count($dryRunResult['fk_relationships']);
        $out .= '<div style="background: #f8f9fa; border-left: 4px solid #8B5CF6; padding: 15px;">';
        $out .= '<div style="font-size: 24px; font-weight: bold; color: #8B5CF6;">' . $fkCount . '</div>';
        $out .= '<div style="color: #666;">' . $this->_('FK Relationships') . '</div>';
        $out .= '</div>';

        $out .= '</div>';

        // Details sections
        $out .= '<div style="margin: 30px 0;">';

        // Templates list
        if (!empty($dryRunResult['templates'])) {
            $out .= '<h4>' . $this->_('Templates to be created:') . '</h4>';
            $out .= '<ul>';
            foreach ($dryRunResult['templates'] as $template) {
                $out .= '<li><code>' . $this->sanitizer->entities($template) . '</code></li>';
            }
            $out .= '</ul>';
        }

        // Pages breakdown per table
        if (!empty($dryRunResult['tables_breakdown'])) {
            $out .= '<h4>' . $this->_('Pages per table:') . '</h4>';
            $out .= '<ul>';
            foreach ($dryRunResult['tables_breakdown'] as $table => $count) {
                $out .= '<li><strong>' . $this->sanitizer->entities($table) . '</strong>: ' . number_format($count) . ' ' . $this->_('pages') . '</li>';
            }
            $out .= '</ul>';
        }

        // FK relationships
        if (!empty($dryRunResult['fk_relationships'])) {
            $out .= '<h4>' . $this->_('Foreign Key Relationships:') . '</h4>';
            $out .= '<ul>';
            foreach ($dryRunResult['fk_relationships'] as $relationship) {
                $out .= '<li>' . $this->sanitizer->entities($relationship) . '</li>';
            }
            $out .= '</ul>';
        }

        $out .= '</div>';

        // Action buttons
        $out .= '<form method="post" action="' . $this->page->url . '" style="margin: 30px 0;">';
        $out .= '<input type="hidden" name="action" value="confirm_import">';

        $out .= '<button type="submit" class="ui-button ui-priority-primary" style="font-size: 16px; padding: 10px 20px;">';
        $out .= '<i class="fa fa-check"></i> ' . $this->_('Execute Migration Now');
        $out .= '</button>';

        $out .= ' &nbsp; ';

        $out .= '<a href="' . $this->page->url . '" class="ui-button ui-priority-secondary">';
        $out .= '<i class="fa fa-arrow-left"></i> ' . $this->_('Back to Edit Configuration');
        $out .= '</a>';

        $out .= '</form>';

        $out .= '</div>';

        return $out;
    }

    /**
     * Detect circular dependencies in dependency graph
     * Returns array of nodes in cycle, or null if no cycle found
     */
    protected function detectCycle($dependencies) {
        $visited = [];
        $recursionStack = [];

        foreach (array_keys($dependencies) as $node) {
            if (!isset($visited[$node])) {
                $cycle = $this->detectCycleDFS($node, $dependencies, $visited, $recursionStack, []);
                if ($cycle !== null) {
                    return $cycle;
                }
            }
        }

        return null;
    }

    /**
     * DFS helper for cycle detection
     */
    protected function detectCycleDFS($node, $dependencies, &$visited, &$recursionStack, $path) {
        $visited[$node] = true;
        $recursionStack[$node] = true;
        $path[] = $node;

        if (isset($dependencies[$node])) {
            foreach ($dependencies[$node] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $cycle = $this->detectCycleDFS($neighbor, $dependencies, $visited, $recursionStack, $path);
                    if ($cycle !== null) {
                        return $cycle;
                    }
                } elseif (isset($recursionStack[$neighbor])) {
                    // Found a cycle - extract it from path
                    $cycleStart = array_search($neighbor, $path);
                    return array_slice($path, $cycleStart);
                }
            }
        }

        unset($recursionStack[$node]);
        return null;
    }

    /**
     * Sort tables by FK dependencies using topological sort
     * Throws exception if circular dependencies are detected
     */
    protected function sortTablesByDependencies($tables, $fkMappings) {
        if (empty($fkMappings)) {
            return $tables;
        }

        $dependencies = [];
        $sorted = [];
        $visited = [];

        // Build dependency graph
        foreach ($tables as $table) {
            $dependencies[$table] = [];
            if (isset($fkMappings[$table])) {
                foreach ($fkMappings[$table] as $column => $refTable) {
                    if (in_array($refTable, $tables) && $refTable !== $table) {
                        $dependencies[$table][] = $refTable;
                    }
                }
            }
        }

        // CYCLE DETECTION: Check for circular dependencies before sorting
        $cycle = $this->detectCycle($dependencies);
        if ($cycle !== null) {
            $cycleStr = implode(' → ', $cycle) . ' → ' . $cycle[0];
            throw new \Exception(sprintf(
                $this->_('Circular Foreign Key dependency detected: %s. Please remove one of the FK mappings to break the cycle.'),
                $cycleStr
            ));
        }

        // Topological sort using DFS
        $visit = function($table) use (&$visit, &$visited, &$sorted, $dependencies) {
            if (isset($visited[$table])) {
                return;
            }
            $visited[$table] = true;

            foreach ($dependencies[$table] as $dep) {
                $visit($dep);
            }

            // Append (not prepend) to get correct dependency order
            $sorted[] = $table;
        };

        foreach ($tables as $table) {
            $visit($table);
        }

        return $sorted;
    }

    /**
     * Execute dry run (preview without actual import)
     */
    protected function executeDryRun() {
        $this->headline('Data Migration - Dry Run Preview');

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

        // Get selected tables
        $selectedTables = $sessionData['selected_tables'] ?? array_keys($allAnalysis);

        if (empty($selectedTables)) {
            $this->error($this->_('No tables selected for migration.'));
            $this->session->redirect($this->page->url);
        }

        // Get FK mappings
        $fkMappings = $sessionData['fk_mappings'] ?? [];

        // Sort tables by dependencies
        if (!empty($fkMappings)) {
            try {
                $sortedTables = $this->sortTablesByDependencies($selectedTables, $fkMappings);
                $selectedTables = $sortedTables;
            } catch (\Exception $e) {
                // Circular FK dependency detected - show error and return to analysis view
                $this->error($e->getMessage());
                $this->error($this->_('Please uncheck one of the FK dropdowns in the cycle to break the circular dependency, then try again.'));
                return $this->executeAnalyze();
            }
        }

        // Dry run analysis
        $dryRunResult = [
            'templates' => [],
            'fields' => [],
            'pages_count' => 0,
            'tables_breakdown' => [],
            'fk_relationships' => []
        ];

        $maxRows = $sessionData['max_rows'] ?? 0;

        foreach ($selectedTables as $tableName) {
            $analysis = $allAnalysis[$tableName] ?? null;
            $tableData = $allTables[$tableName] ?? null;

            if (!$analysis || !$tableData) {
                continue;
            }

            // Create mapping to see what would be created
            $mappingEngine = $this->wire(new MappingEngine());
            $mapping = $mappingEngine->createMapping($analysis, $tableName);

            // Apply field filtering
            if (isset($sessionData['selected_fields'])) {
                $selectedFields = $sessionData['selected_fields'][$tableName] ?? [];
                $filteredFields = [];
                foreach ($mapping['fields'] as $columnName => $fieldMapping) {
                    if (in_array($columnName, $selectedFields) || $columnName === $mapping['title_field']) {
                        $filteredFields[$columnName] = $fieldMapping;
                    }
                }
                $mapping['fields'] = $filteredFields;
            }

            // Apply fieldtype overrides
            if (isset($sessionData['fieldtype_overrides'][$tableName])) {
                $overrides = $sessionData['fieldtype_overrides'][$tableName];
                foreach ($mapping['fields'] as $fieldName => $fieldMapping) {
                    $sourceColumn = $fieldMapping['source_column'];
                    if (isset($overrides[$sourceColumn])) {
                        $mapping['fields'][$fieldName]['fieldtype'] = $overrides[$sourceColumn];
                    }
                }
            }

            // Count what would be created
            $dryRunResult['templates'][] = $mapping['template'];

            foreach ($mapping['fields'] as $fieldMapping) {
                $fieldName = $fieldMapping['target_field'];
                if (!in_array($fieldName, $dryRunResult['fields'])) {
                    $dryRunResult['fields'][] = $fieldName;
                }
            }

            // Count pages that would be imported
            $importData = $tableData['data'];
            if ($maxRows > 0 && count($importData) > $maxRows) {
                $importData = array_slice($importData, 0, $maxRows);
            }
            $pageCount = count($importData);
            $dryRunResult['pages_count'] += $pageCount;
            $dryRunResult['tables_breakdown'][$tableName] = $pageCount;

            // Count FK relationships
            if (isset($fkMappings[$tableName])) {
                foreach ($fkMappings[$tableName] as $column => $refTable) {
                    $dryRunResult['fk_relationships'][] = "{$tableName}.{$column} → {$refTable}";
                }
            }
        }

        // Save dry run result to session
        $sessionData['dry_run_result'] = $dryRunResult;
        $sessionData['dry_run_completed'] = true;
        $this->session->set(self::SESSION_KEY, $sessionData);

        // Show confirmation screen
        return $this->buildDryRunConfirmation($dryRunResult);
    }

    /**
     * Execute import process
     */
    protected function executeImport() {
        $this->headline('Data Migration - Processing');

        // Get session data
        $sessionData = $this->session->get(self::SESSION_KEY);
        if (!$sessionData || !isset($sessionData['analysis'])) {
            $this->error($this->_('No analysis data found. Please start over.'));
            $this->session->redirect($this->page->url);
        }

        // Clear dry run flags (we're doing actual import now)
        unset($sessionData['dry_run_result']);
        unset($sessionData['dry_run_completed']);
        $this->session->set(self::SESSION_KEY, $sessionData);

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
            $this->error($this->_('No tables selected for migration.'));
            $this->session->redirect($this->page->url);
        }

        $this->message($this->_("Selected tables for migration: " . implode(', ', $selectedTables)));

        // Get FK mappings
        $fkMappings = $sessionData['fk_mappings'] ?? [];

        if (!empty($fkMappings)) {
            $this->message($this->_("FK Mappings configured:"));
            foreach ($fkMappings as $table => $columns) {
                foreach ($columns as $col => $refTable) {
                    $this->message($this->_("  - {$table}.{$col} → {$refTable}"));
                }
            }
        }

        // Sort tables by dependencies (referenced tables must be imported first)
        if (!empty($fkMappings)) {
            try {
                $sortedTables = $this->sortTablesByDependencies($selectedTables, $fkMappings);
                $this->message($this->_('Tables sorted by dependencies: ' . implode(' → ', $sortedTables)));
                $selectedTables = $sortedTables;
            } catch (\Exception $e) {
                // Circular FK dependency detected - show error and return to analysis view
                // IMPORTANT: Keep session data so user selections are preserved
                $this->error($e->getMessage());
                $this->error($this->_('Please uncheck one of the FK dropdowns in the cycle to break the circular dependency, then try migrating again.'));

                // Return analysis view with preserved session data
                return $this->executeAnalyze();
            }
        }

        // Import each selected table
        $allRollbackData = [];
        $totalImported = 0;
        $maxRows = $sessionData['max_rows'] ?? 0;
        $idMapping = []; // Maps: table => [sql_id => pw_page_id]

        try {
            foreach ($selectedTables as $tableName) {
                $analysis = $allAnalysis[$tableName] ?? null;
                $tableData = $allTables[$tableName] ?? null;

                if (!$analysis || !$tableData) {
                    $this->error($this->_("Table '{$tableName}' not found in analysis"));
                    continue;
                }

                $this->message($this->_("Migrating table: {$tableName}"));

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

                // Apply fieldtype overrides (if user changed them in UI)
                if (isset($sessionData['fieldtype_overrides'][$tableName])) {
                    $overrides = $sessionData['fieldtype_overrides'][$tableName];
                    $overrideCount = 0;
                    foreach ($mapping['fields'] as $fieldName => $fieldMapping) {
                        // CRITICAL: Use source_column, not the prefixed field name!
                        $sourceColumn = $fieldMapping['source_column'];

                        if (isset($overrides[$sourceColumn]) && $overrides[$sourceColumn] !== $fieldMapping['fieldtype']) {
                            $oldType = $fieldMapping['fieldtype'];
                            $newType = $overrides[$sourceColumn];
                            $mapping['fields'][$fieldName]['fieldtype'] = $newType;
                            $overrideCount++;
                            $this->message($this->_("Override: {$sourceColumn} changed from {$oldType} to {$newType}"));

                            // CRITICAL: If changed to FieldtypeOptions, copy options from analysis
                            if ($newType === 'FieldtypeOptions') {
                                $columnData = $analysis['columns'][$sourceColumn] ?? null;
                                if ($columnData && isset($columnData['options'])) {
                                    $mapping['fields'][$fieldName]['options'] = $columnData['options'];
                                    $optionCount = count($columnData['options']);
                                    $this->message($this->_("  → Added {$optionCount} option(s): " . implode(', ', array_slice($columnData['options'], 0, 5)) . (count($columnData['options']) > 5 ? '...' : '')));
                                } else {
                                    // No options available - extract unique values from data
                                    $values = [];
                                    foreach ($tableData['data'] as $row) {
                                        if (isset($row[$sourceColumn]) && $row[$sourceColumn] !== null && $row[$sourceColumn] !== '') {
                                            $values[] = $row[$sourceColumn];
                                        }
                                    }
                                    $uniqueValues = array_values(array_unique($values));
                                    if (!empty($uniqueValues)) {
                                        $mapping['fields'][$fieldName]['options'] = $uniqueValues;
                                        $uniqueCount = count($uniqueValues);
                                        $this->message($this->_("  → Extracted {$uniqueCount} unique values as options"));
                                    } else {
                                        $this->warning($this->_("  → No options available for {$sourceColumn} - field will be created without selectable options"));
                                    }
                                }
                            }
                        }
                    }
                    if ($overrideCount > 0) {
                        $this->message($this->_("Applied {$overrideCount} fieldtype override(s) for table {$tableName}"));
                    }
                }

                // Step 2: Create TWO templates: list template and detail template
                $templateCreator = $this->wire(new TemplateCreator());

                // Create detail template (singular) for individual records
                $detailTemplate = $templateCreator->createTemplate($mapping);
                $this->message($this->_('Created detail template: ') . $detailTemplate->name);

                // Create list template (plural) for parent/overview page
                $listTemplateName = $tableName . '_list'; // e.g., "customers_list"
                $listTemplate = $templateCreator->createListTemplate($listTemplateName);
                $this->message($this->_('Created list template: ') . $listTemplate->name);

                // Step 3: Create parent structure
                // First, ensure /migration/ container exists
                $migrationContainer = $templateCreator->createParentPage('/migration/', null);

                // Then, create table-specific parent page under /migration/
                // e.g., /migration/customers/ with template "customers_list"
                $tableParentPath = '/migration/' . $tableName . '/';
                $tableParent = $templateCreator->createTableParentPage($tableParentPath, $listTemplate, $migrationContainer);

                $this->message($this->_('Created table parent page: ') . $tableParent->path);

                // Step 4: Import data
                // CRITICAL: Limit data to max_rows for import (analysis used full sample_size)
                $importData = $tableData['data'];
                if ($maxRows > 0 && count($importData) > $maxRows) {
                    $importData = array_slice($importData, 0, $maxRows);
                    $this->message($this->_("Migration limited to {$maxRows} rows for table {$tableName}"));
                }

                $importProcessor = $this->wire(new ImportProcessor());

                // Pass FK mappings and ID mapping for this table
                // IMPORTANT: Use detailTemplate for records, tableParent as parent
                $tableFkMappings = isset($fkMappings[$tableName]) ? $fkMappings[$tableName] : [];
                $result = $importProcessor->import($importData, $mapping, $detailTemplate, $tableParent, $tableFkMappings, $idMapping);

                // Update ID mapping with newly created pages
                if (isset($result['id_mapping'])) {
                    $idMapping[$tableName] = $result['id_mapping'];
                    $this->message($this->_("  → Stored ID mapping for {$tableName}: " . count($result['id_mapping']) . " entries"));
                }

                $totalImported += $result['imported'];

                // Collect rollback data for this table
                $allRollbackData[] = [
                    'table' => $tableName,
                    'template' => $detailTemplate->name,
                    'list_template' => $listTemplate->name,
                    'parent_page' => $tableParent->path,
                    'created_fields' => $templateCreator->getCreatedFields(),
                    'created_pages' => $result['created_pages'],
                    'errors' => $result['errors'],
                    'timestamp' => time()
                ];

                // Show import summary
                $this->message($this->_("Migrated {$result['imported']} pages for table {$tableName}"));

                // Show errors if any
                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $this->error($this->_("Row {$error['row']}: {$error['error']}"));
                    }
                }

                // Step 5: Generate frontend template files (.php)
                $this->message($this->_("Generating frontend template files for {$tableName}..."));
                $generatedFiles = $templateCreator->generateTemplateFiles(
                    $listTemplate->name,
                    $detailTemplate->name,
                    $mapping,
                    $tableFkMappings,
                    $tableName
                );

                foreach ($generatedFiles as $filePath) {
                    $this->message($this->_("  ✓ Generated: " . basename($filePath)));
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
            $this->error($this->_('Migration failed: ') . $e->getMessage());
            return $this->executeAnalyze();
        }
    }

    /**
     * Show import results
     */
    protected function executeImportResult() {
        $this->headline('Data Migration - Results');

        // Get session data
        $sessionData = $this->session->get(self::SESSION_KEY);
        if (!$sessionData || !isset($sessionData['rollback_data'])) {
            $this->error($this->_('No migration results found. Please start over.'));
            $this->session->redirect($this->page->url);
        }

        $rollbackData = $sessionData['rollback_data'];
        $totalImported = $sessionData['total_imported'] ?? 0;

        $out = '';

        // Success summary
        $out .= '<div class="uk-alert uk-alert-success">';
        $out .= '<h3>' . $this->_('Migration Completed Successfully') . '</h3>';
        $out .= '<p>' . sprintf(
            $this->_('Successfully migrated %d pages from %d tables'),
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
                $out .= '<h4>' . $this->_('Migration Errors') . '</h4>';
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
            $out .= 'onclick="return confirm(\'' . $this->_('Delete all migrated data? This cannot be undone!') . '\')">';
            $out .= '<i class="fa fa-trash"></i> ' . $this->_('Rollback Migration');
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
        $this->headline('Data Migration - Rollback');

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
            $out .= '<p>' . $this->_('All migrated data has been deleted.') . '</p>';
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
        $out .= '<i class="fa fa-upload"></i> ' . $this->_('Start New Migration');
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
     * Module configuration
     */
    public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
        // Log Level setting
        $f = $this->modules->get('InputfieldRadios');
        $f->name = 'log_level';
        $f->label = $this->_('Log Level');
        $f->description = $this->_('Control how much information is written to the log file');
        $f->notes = $this->_('Lower levels produce smaller log files. Higher levels help with debugging.');
        $f->addOption(Logger::ERROR, $this->_('ERROR - Only critical errors'));
        $f->addOption(Logger::WARNING, $this->_('WARNING - Errors and warnings'));
        $f->addOption(Logger::INFO, $this->_('INFO - Errors, warnings, and important info (recommended)'));
        $f->addOption(Logger::DEBUG, $this->_('DEBUG - Everything including detailed debug info'));
        $f->value = $this->log_level ?: Logger::INFO;
        $f->columnWidth = 100;
        $inputfields->add($f);

        return $inputfields;
    }

    /**
     * Get logger instance with configured level
     *
     * @return Logger
     */
    protected function getLogger() {
        $logger = $this->wire(new Logger());
        $logger->setLevel($this->log_level ?: Logger::INFO);
        $logger->setLogName('data-migrator');
        return $logger;
    }

    /**
     * Install the module
     */
    public function ___install() {
        // Create uploads directory
        $uploadsPath = $this->config->paths->cache . 'DataMigrator/';
        if (!is_dir($uploadsPath)) {
            wireMkdir($uploadsPath, true);
        }

        // Create permission
        $permission = $this->permissions->get('data-migrate');
        if (!$permission->id) {
            $permission = $this->permissions->add('data-migrate');
            $permission->title = 'Data Migration';
            $permission->save();
            $this->message("Created permission: data-migrate");
        }

        // Create admin page
        $page = $this->pages->get('template=admin, name=data-migrator');
        if (!$page->id) {
            // Get setup page as parent
            $parent = $this->pages->get($this->config->adminRootPageID)->child('name=setup');
            if (!$parent->id) {
                throw new WireException("Setup page not found");
            }

            $page = new Page();
            $page->template = 'admin';
            $page->parent = $parent;
            $page->name = 'data-migrator';
            $page->title = 'Data Migrator';
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
        $uploadsPath = $this->config->paths->cache . 'DataMigrator/';
        if (is_dir($uploadsPath)) {
            wireRmdir($uploadsPath, true);
        }

        // Remove admin page
        $page = $this->pages->get('template=admin, name=data-migrator');
        if ($page->id) {
            $this->pages->delete($page, true);
            $this->message("Removed page: data-migrator");
        }

        // Note: We don't remove the permission as it might be in use
    }
}
