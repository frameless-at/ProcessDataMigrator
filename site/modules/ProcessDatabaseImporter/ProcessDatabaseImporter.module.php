<?php

namespace ProcessWire;

require_once(__DIR__ . '/classes/parsers/AbstractParser.php');
require_once(__DIR__ . '/classes/parsers/SqlParser.php');
require_once(__DIR__ . '/classes/DataAnalyzer.php');
require_once(__DIR__ . '/classes/TypeDetector.php');
require_once(__DIR__ . '/classes/MappingEngine.php');
require_once(__DIR__ . '/classes/TemplateCreator.php');
require_once(__DIR__ . '/classes/ImportProcessor.php');

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
        // Check for actions first
        if ($this->input->get('action')) {
            $action = $this->input->get('action');
            if ($action === 'clear') {
                return $this->executeClear();
            }
            if ($action === 'import') {
                return $this->executeImport();
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
                            'tables' => $result['tables']
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

        // Add buttons
        $out .= $this->buildAnalysisActions();

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
        $out = '<div class="database-importer-analysis">';

        // Summary
        $totalRows = array_sum(array_column($analysis, 'row_count'));
        $totalColumns = array_sum(array_map(function($table) {
            return count($table['columns']);
        }, $analysis));

        $out .= '<div class="uk-alert uk-alert-success">';
        $out .= '<h3>' . $this->_('Analysis Complete') . '</h3>';
        $out .= '<p>';
        $out .= sprintf(
            $this->_('%d tables found with %d total rows and %d columns'),
            count($analysis),
            $totalRows,
            $totalColumns
        );
        $out .= '</p>';
        $out .= '</div>';

        // Tables
        foreach ($analysis as $tableName => $tableAnalysis) {
            $out .= $this->buildTableAnalysis($tableName, $tableAnalysis);
        }

        $out .= '</div>';

        return $out;
    }

    /**
     * Build single table analysis view
     */
    protected function buildTableAnalysis($tableName, $analysis) {
        $out = '<div class="table-analysis uk-margin">';
        $out .= '<h3>' . $this->sanitizer->entities($tableName) . '</h3>';

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
            $out .= '<tr>';
            $out .= '<td><strong>' . $this->sanitizer->entities($column['name']) . '</strong>';

            // Add badges
            if ($column['is_likely_id']) {
                $out .= ' <span class="uk-badge">ID</span>';
            }

            // Title badge: only for the actual suggested title field
            if ($column['name'] === $analysis['suggested_title_field']) {
                $out .= ' <span class="uk-badge uk-badge-success">Title</span>';
            }

            // FK badge: only for actual foreign keys (from SQL constraints) or *_id pattern (but not is_* or has_*)
            $isForeignKey = false;
            if (isset($analysis['foreign_keys'][$column['name']])) {
                $isForeignKey = true; // Actual FK from SQL
            } else if ($column['is_likely_foreign_key']) {
                // Additional check: must end with _id but NOT start with is_ or has_
                $name = strtolower($column['name']);
                if (substr($name, -3) === '_id' &&
                    strpos($name, 'is_') !== 0 &&
                    strpos($name, 'has_') !== 0) {
                    $isForeignKey = true;
                }
            }
            if ($isForeignKey) {
                $out .= ' <span class="uk-badge uk-badge-warning">FK</span>';
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

        // Import button
        $out .= '<a href="' . $this->page->url . '?action=import" class="ui-button ui-priority-primary">';
        $out .= '<i class="fa fa-upload"></i> ' . $this->_('Start Import');
        $out .= '</a>';

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

        // Extract first table (for now - later we can add table selection)
        $allAnalysis = $sessionData['analysis'] ?? [];
        $allTables = $sessionData['tables'] ?? [];

        if (empty($allAnalysis) || empty($allTables)) {
            $this->error($this->_('No table data found in session. Please start over.'));
            $this->session->redirect($this->page->url);
        }

        // Get first table
        $tableName = array_key_first($allAnalysis);
        $analysis = $allAnalysis[$tableName] ?? [];
        $tableData = $allTables[$tableName] ?? [];

        if (empty($analysis) || empty($tableData)) {
            $this->error($this->_('Invalid table data. Please start over.'));
            $this->session->redirect($this->page->url);
        }

        try {
            // Step 1: Create automatic mapping
            $mappingEngine = $this->wire(new MappingEngine());
            $mapping = $mappingEngine->createMapping($analysis, $tableName);

            $this->message($this->_('Created field mapping'));

            // Step 2: Create template and fields
            $templateCreator = $this->wire(new TemplateCreator());
            $template = $templateCreator->createTemplate($mapping);

            $this->message($this->_('Created template: ') . $template->name);

            // Step 3: Create parent page
            $parent = $templateCreator->createParentPage($mapping['parent'], $template->name);

            $this->message($this->_('Created parent page: ') . $parent->path);

            // Step 4: Import data
            $importProcessor = $this->wire(new ImportProcessor());
            $result = $importProcessor->import($tableData['data'], $mapping, $template, $parent);

            // Store import results in session
            $sessionData['step'] = 'import';
            $sessionData['import_result'] = $result;
            $sessionData['mapping'] = $mapping;
            $sessionData['template'] = $template->name;
            $sessionData['parent'] = $parent->path;
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
        if (!$sessionData || !isset($sessionData['import_result'])) {
            $this->error($this->_('No import results found. Please start over.'));
            $this->session->redirect($this->page->url);
        }

        $result = $sessionData['import_result'];
        $mapping = $sessionData['mapping'];
        $template = $sessionData['template'];
        $parent = $sessionData['parent'];

        $out = '';

        // Success summary
        if ($result['success']) {
            $out .= '<div class="uk-alert uk-alert-success">';
            $out .= '<h3>' . $this->_('Import Completed Successfully') . '</h3>';
            $out .= '<p>' . sprintf(
                $this->_('Successfully imported %d pages using template "%s"'),
                $result['imported'],
                $template
            ) . '</p>';
            $out .= '</div>';
        } else {
            $out .= '<div class="uk-alert uk-alert-danger">';
            $out .= '<h3>' . $this->_('Import Failed') . '</h3>';
            $out .= '<p>' . $this->_('No pages were imported.') . '</p>';
            $out .= '</div>';
        }

        // Statistics
        $out .= '<div class="table-analysis">';
        $out .= '<h3>' . $this->_('Import Statistics') . '</h3>';
        $out .= '<dl class="uk-description-list">';
        $out .= '<dt>' . $this->_('Template') . '</dt>';
        $out .= '<dd><code>' . $this->sanitizer->entities($template) . '</code></dd>';
        $out .= '<dt>' . $this->_('Parent Page') . '</dt>';
        $out .= '<dd>' . $this->sanitizer->entities($parent) . '</dd>';
        $out .= '<dt>' . $this->_('Pages Created') . '</dt>';
        $out .= '<dd><strong>' . $result['imported'] . '</strong></dd>';
        $out .= '<dt>' . $this->_('Errors') . '</dt>';
        $out .= '<dd>' . count($result['errors']) . '</dd>';
        $out .= '</dl>';
        $out .= '</div>';

        // Created pages
        if (!empty($result['created_pages'])) {
            $out .= '<div class="table-analysis uk-margin">';
            $out .= '<h3>' . $this->_('Created Pages') . '</h3>';
            $out .= '<table class="uk-table uk-table-striped uk-table-small">';
            $out .= '<thead>';
            $out .= '<tr>';
            $out .= '<th>' . $this->_('ID') . '</th>';
            $out .= '<th>' . $this->_('Title') . '</th>';
            $out .= '<th>' . $this->_('Path') . '</th>';
            $out .= '<th>' . $this->_('Actions') . '</th>';
            $out .= '</tr>';
            $out .= '</thead>';
            $out .= '<tbody>';

            foreach ($result['created_pages'] as $pageInfo) {
                $out .= '<tr>';
                $out .= '<td>' . $pageInfo['id'] . '</td>';
                $out .= '<td>' . $this->sanitizer->entities($pageInfo['title']) . '</td>';
                $out .= '<td><code>' . $this->sanitizer->entities($pageInfo['path']) . '</code></td>';
                $out .= '<td>';
                $out .= '<a href="' . $this->config->urls->admin . 'page/edit/?id=' . $pageInfo['id'] . '" target="_blank">';
                $out .= '<i class="fa fa-edit"></i> ' . $this->_('Edit');
                $out .= '</a>';
                $out .= '</td>';
                $out .= '</tr>';
            }

            $out .= '</tbody>';
            $out .= '</table>';
            $out .= '</div>';
        }

        // Errors
        if (!empty($result['errors'])) {
            $out .= '<div class="table-analysis uk-margin">';
            $out .= '<h3>' . $this->_('Import Errors') . '</h3>';
            $out .= '<table class="uk-table uk-table-striped uk-table-small">';
            $out .= '<thead>';
            $out .= '<tr>';
            $out .= '<th>' . $this->_('Row') . '</th>';
            $out .= '<th>' . $this->_('Error') . '</th>';
            $out .= '</tr>';
            $out .= '</thead>';
            $out .= '<tbody>';

            foreach ($result['errors'] as $error) {
                $out .= '<tr>';
                $out .= '<td>' . $error['row'] . '</td>';
                $out .= '<td>' . $this->sanitizer->entities($error['error']) . '</td>';
                $out .= '</tr>';
            }

            $out .= '</tbody>';
            $out .= '</table>';
            $out .= '</div>';
        }

        // Actions
        $out .= '<div class="uk-margin">';
        $out .= '<a href="' . $parent . '" class="ui-button ui-priority-primary" target="_blank">';
        $out .= '<i class="fa fa-folder-open"></i> ' . $this->_('View Imported Pages');
        $out .= '</a>';
        $out .= ' &nbsp; ';
        $out .= '<a href="' . $this->page->url . '?action=clear" class="ui-button ui-priority-secondary">';
        $out .= '<i class="fa fa-arrow-left"></i> ' . $this->_('Start Over');
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
