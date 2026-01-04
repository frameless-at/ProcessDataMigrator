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

                // Delete parent if empty
                if ($parent->id && $parent->numChildren() == 0) {
                    $this->wire('pages')->delete($parent, true);
                    $result['pages_deleted']++;

                    // Walk up and delete empty container parents
                    $currentParent = $parent->parent;
                    while ($currentParent->id && $currentParent->id != 1 && $currentParent->template->name === 'import-container') {
                        if ($currentParent->numChildren() == 0) {
                            $nextParent = $currentParent->parent;
                            $this->wire('pages')->delete($currentParent, true);
                            $result['pages_deleted']++;
                            $currentParent = $nextParent;
                        } else {
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Failed to delete parent page: " . $e->getMessage();
            }
        }

        // Delete template (must be before fields)
        if (isset($rollbackData['template'])) {
            try {
                $template = $this->wire('templates')->get($rollbackData['template']);
                if ($template && $template->id) {
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
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Failed to delete template: " . $e->getMessage();
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
                // Count pages using this template (exclude trash)
                $numPages = $this->wire('pages')->count("template=$containerTemplate, include=all");

                if ($numPages == 0) {
                    $fieldgroup = $containerTemplate->fieldgroup;
                    $this->wire('templates')->delete($containerTemplate);
                    $this->wire('fieldgroups')->delete($fieldgroup);
                    $result['templates_deleted']++;
                    $result['errors'][] = "DEBUG: Successfully deleted import-container template";
                } else {
                    $result['errors'][] = "DEBUG: import-container still used by {$numPages} pages - not deleting";

                    // DEBUG: Show which pages are still using it
                    $pages = $this->wire('pages')->find("template=$containerTemplate, include=all, limit=5");
                    $pagePaths = [];
                    foreach ($pages as $p) {
                        $pagePaths[] = $p->path . " (ID: {$p->id})";
                    }
                    if (!empty($pagePaths)) {
                        $result['errors'][] = "DEBUG: Pages still using import-container: " . implode(', ', $pagePaths);
                    }
                }
            } else {
                $result['errors'][] = "DEBUG: import-container template not found";
            }
        } catch (\Exception $e) {
            $result['errors'][] = "Failed to delete import-container template: " . $e->getMessage();
        }

        return $result;
    }
}
