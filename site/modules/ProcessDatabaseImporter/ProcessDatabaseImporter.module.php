<?php

namespace ProcessWire;

require_once(__DIR__ . '/classes/parsers/AbstractParser.php');
require_once(__DIR__ . '/classes/parsers/SqlParser.php');
require_once(__DIR__ . '/classes/DataAnalyzer.php');
require_once(__DIR__ . '/classes/TypeDetector.php');

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
        }

        // Check if we have session data (analysis results)
        $sessionData = $this->session->get(self::SESSION_KEY);

        if ($sessionData && isset($sessionData['step'])) {
            // Continue from saved step
            switch ($sessionData['step']) {
                case 'analyze':
                    return $this->executeAnalyze();
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

        // Handle file upload via $_FILES
        if ($this->input->post('submit_upload') && isset($_FILES['sql_file'])) {
            $file = $_FILES['sql_file'];

            // Validate upload
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->error($this->_('File upload failed'));
            } else if ($file['size'] === 0) {
                $this->error($this->_('File is empty'));
            } else if (!preg_match('/\.sql$/i', $file['name'])) {
                $this->error($this->_('Only .sql files are allowed'));
            } else {
                // Move to temp location
                $tempFile = $this->uploadsPath . uniqid('import_') . '.sql';
                if (move_uploaded_file($file['tmp_name'], $tempFile)) {
                    // Process the file
                    $result = $this->processUpload($tempFile);

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

        return $this->buildUploadForm();
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

        return $form->render();
    }

    /**
     * Process uploaded file
     */
    protected function processUpload($filePath) {
        // $filePath is now directly the file path string

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
            'sample_size' => (int) $this->input->post('sample_size', 100),
            'max_rows' => (int) $this->input->post('max_rows', 0),
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
            if ($column['is_likely_title']) {
                $out .= ' <span class="uk-badge uk-badge-success">Title</span>';
            }
            if ($column['is_likely_foreign_key']) {
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

        // Clear session button
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
