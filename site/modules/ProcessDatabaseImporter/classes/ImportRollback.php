<?php

namespace ProcessWire;

/**
 * Import Rollback Manager
 * Tracks and reverses database imports
 */
class ImportRollback extends WireData {

    /**
     * Rollback an import by deleting all created items
     *
     * @param array $rollbackData Data from import
     * @return array Result with counts
     */
    public function rollback($rollbackData) {
        $result = [
            'pages_deleted' => 0,
            'templates_deleted' => 0,
            'fields_deleted' => 0,
            'errors' => []
        ];

        // Delete pages first (must be before templates)
        if (isset($rollbackData['created_pages'])) {
            foreach ($rollbackData['created_pages'] as $pageInfo) {
                try {
                    $page = $this->wire('pages')->get($pageInfo['id']);
                    if ($page->id) {
                        $this->wire('pages')->delete($page, true); // recursive
                        $result['pages_deleted']++;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to delete page {$pageInfo['id']}: " . $e->getMessage();
                }
            }
        }

        // Delete parent page and container hierarchy
        if (isset($rollbackData['parent_page'])) {
            try {
                $parent = $this->wire('pages')->get($rollbackData['parent_page']);

                if ($parent && $parent->id) {
                    // CRITICAL: Use actual count, not cached numChildren()
                    $actualChildCount = $this->wire('pages')->count("parent={$parent->id}");

                    // Delete parent if empty
                    if ($actualChildCount == 0) {
                        $this->wire('pages')->delete($parent, true);
                        $result['pages_deleted']++;

                        // Walk up and delete empty container parents
                        $currentParent = $parent->parent;
                        while ($currentParent && $currentParent->id && $currentParent->id != 1) {
                            if ($currentParent->template->name === 'import-container') {
                                // CRITICAL: Use actual count, not cached
                                $containerChildCount = $this->wire('pages')->count("parent={$currentParent->id}");

                                if ($containerChildCount == 0) {
                                    $nextParent = $currentParent->parent;
                                    $this->wire('pages')->delete($currentParent, true);
                                    $result['pages_deleted']++;
                                    $currentParent = $nextParent;
                                } else {
                                    break;
                                }
                            } else {
                                break;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Failed to delete parent page: " . $e->getMessage();
            }
        }

        // Delete templates (both detail and list templates)
        // CRITICAL: Delete list_template first, then detail template
        if (isset($rollbackData['list_template'])) {
            try {
                $listTemplate = $this->wire('templates')->get($rollbackData['list_template']);
                if ($listTemplate && $listTemplate->id) {
                    $templateName = $listTemplate->name;

                    // Remove all fields from fieldgroup first
                    foreach ($listTemplate->fieldgroup as $field) {
                        if ($field->name !== 'title') { // Keep title
                            $listTemplate->fieldgroup->remove($field);
                        }
                    }
                    $listTemplate->fieldgroup->save();

                    // Delete template and fieldgroup
                    $fieldgroup = $listTemplate->fieldgroup;
                    $this->wire('templates')->delete($listTemplate);
                    $this->wire('fieldgroups')->delete($fieldgroup);
                    $result['templates_deleted']++;

                    // Delete physical template file
                    $templateFile = $this->wire('config')->paths->templates . $templateName . '.php';
                    if (file_exists($templateFile)) {
                        @unlink($templateFile);
                    }
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Failed to delete list template: " . $e->getMessage();
            }
        }

        // Delete detail template (must be before fields)
        if (isset($rollbackData['template'])) {
            try {
                $template = $this->wire('templates')->get($rollbackData['template']);
                if ($template && $template->id) {
                    $templateName = $template->name;

                    // Remove all fields from fieldgroup first
                    foreach ($template->fieldgroup as $field) {
                        if ($field->name !== 'title') { // Keep title
                            $template->fieldgroup->remove($field);
                        }
                    }
                    $template->fieldgroup->save();

                    // Delete template and fieldgroup
                    $fieldgroup = $template->fieldgroup;
                    $this->wire('templates')->delete($template);
                    $this->wire('fieldgroups')->delete($fieldgroup);
                    $result['templates_deleted']++;

                    // Delete physical template file
                    $templateFile = $this->wire('config')->paths->templates . $templateName . '.php';
                    if (file_exists($templateFile)) {
                        @unlink($templateFile);
                    }
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Failed to delete detail template: " . $e->getMessage();
            }
        }

        // Delete fields
        if (isset($rollbackData['created_fields'])) {
            foreach ($rollbackData['created_fields'] as $fieldName) {
                try {
                    $field = $this->wire('fields')->get($fieldName);
                    if ($field && $field->id) {
                        // Check if field is used by other templates
                        $numTemplates = $this->wire('templates')->find("fieldgroup.fields=$field")->count();
                        if ($numTemplates == 0) {
                            $this->wire('fields')->delete($field);
                            $result['fields_deleted']++;
                        }
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to delete field {$fieldName}: " . $e->getMessage();
                }
            }
        }

        // Delete import-container template if not used by any pages
        try {
            $containerTemplate = $this->wire('templates')->get('import-container');
            if ($containerTemplate && $containerTemplate->id) {
                $numPages = $this->wire('pages')->count("template=$containerTemplate");
                if ($numPages == 0) {
                    $fieldgroup = $containerTemplate->fieldgroup;
                    $this->wire('templates')->delete($containerTemplate);
                    $this->wire('fieldgroups')->delete($fieldgroup);
                    $result['templates_deleted']++;
                }
            }
        } catch (\Exception $e) {
            $result['errors'][] = "Failed to delete import-container template: " . $e->getMessage();
        }

        return $result;
    }
}
