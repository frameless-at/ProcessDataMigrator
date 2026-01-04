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

        // DEBUG: Show what we're trying to delete
        if (isset($rollbackData['created_pages'])) {
            $result['errors'][] = "DEBUG: Found " . count($rollbackData['created_pages']) . " pages to delete";
        }

        // Delete pages first (must be before templates)
        if (isset($rollbackData['created_pages'])) {
            foreach ($rollbackData['created_pages'] as $pageInfo) {
                try {
                    $pageId = (int)$pageInfo['id'];
                    $page = $this->wire('pages')->get($pageId);

                    $result['errors'][] = "DEBUG: Trying to delete page ID {$pageId}, found: " . ($page && $page->id ? "YES (ID: {$page->id}, path: {$page->path})" : "NO");

                    if ($page && $page->id) {
                        // Force delete (bypassing trash)
                        $this->wire('pages')->delete($page, true); // recursive = true
                        $result['pages_deleted']++;
                        $result['errors'][] = "DEBUG: Successfully deleted page {$pageId}";
                    } else {
                        $result['errors'][] = "Page {$pageId} not found or already deleted";
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to delete page {$pageInfo['id']}: " . $e->getMessage();
                }
            }
        }

        // Delete parent page and container hierarchy
        if (isset($rollbackData['parent_page'])) {
            try {
                // IMPORTANT: Get fresh instance after deleting child pages
                $parent = $this->wire('pages')->get($rollbackData['parent_page']);

                if ($parent && $parent->id) {
                    // Force refresh to get current child count
                    $parent->uncache();
                    $actualChildCount = $this->wire('pages')->count("parent={$parent->id}");

                    $result['errors'][] = "DEBUG: Parent page path: {$rollbackData['parent_page']}, found: YES (ID: {$parent->id}, cached children: {$parent->numChildren()}, actual children: {$actualChildCount})";

                    // Delete parent if empty
                    if ($actualChildCount == 0) {
                        $this->wire('pages')->delete($parent, true);
                        $result['pages_deleted']++;
                        $result['errors'][] = "DEBUG: Deleted parent page {$parent->id}";

                        // Walk up and delete empty container parents
                        $currentParent = $parent->parent;
                        while ($currentParent && $currentParent->id && $currentParent->id != 1 && $currentParent->template->name === 'import-container') {
                            $currentParent->uncache();
                            $containerChildCount = $this->wire('pages')->count("parent={$currentParent->id}");
                            $result['errors'][] = "DEBUG: Checking container parent {$currentParent->path} (children: {$containerChildCount})";

                            if ($containerChildCount == 0) {
                                $nextParent = $currentParent->parent;
                                $this->wire('pages')->delete($currentParent, true);
                                $result['pages_deleted']++;
                                $result['errors'][] = "DEBUG: Deleted container parent {$currentParent->id}";
                                $currentParent = $nextParent;
                            } else {
                                $result['errors'][] = "DEBUG: Container parent still has children, stopping";
                                break;
                            }
                        }
                    } else {
                        $result['errors'][] = "DEBUG: Parent page not empty (has {$actualChildCount} children), not deleting";
                    }
                } else {
                    $result['errors'][] = "DEBUG: Parent page not found: {$rollbackData['parent_page']}";
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
                    // Check if template is still in use
                    $numPages = $this->wire('pages')->count("template=$template, include=all");
                    if ($numPages > 0) {
                        $result['errors'][] = "Cannot delete template '{$template->name}': still used by {$numPages} pages (check if pages were deleted correctly)";
                    } else {
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
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Failed to delete template: " . $e->getMessage();
            }
        }

        // Delete fields
        if (isset($rollbackData['created_fields'])) {
            $result['errors'][] = "DEBUG: Found " . count($rollbackData['created_fields']) . " fields to delete: " . implode(', ', $rollbackData['created_fields']);

            foreach ($rollbackData['created_fields'] as $fieldName) {
                try {
                    $field = $this->wire('fields')->get($fieldName);

                    if ($field && $field->id) {
                        // Check if field is used by other templates
                        $numTemplates = $this->wire('templates')->find("fieldgroup.fields=$field")->count();
                        $result['errors'][] = "DEBUG: Field '{$fieldName}' found, used by {$numTemplates} templates";

                        if ($numTemplates == 0) {
                            $this->wire('fields')->delete($field);
                            $result['fields_deleted']++;
                            $result['errors'][] = "DEBUG: Successfully deleted field '{$fieldName}'";
                        } else {
                            $result['errors'][] = "DEBUG: Field '{$fieldName}' still in use, not deleting";
                        }
                    } else {
                        $result['errors'][] = "DEBUG: Field '{$fieldName}' not found";
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to delete field {$fieldName}: " . $e->getMessage();
                }
            }
        } else {
            $result['errors'][] = "DEBUG: No fields to delete (created_fields not set in rollback data)";
        }

        // Delete import-container template if not used by any pages
        try {
            $containerTemplate = $this->wire('templates')->get('import-container');
            if ($containerTemplate && $containerTemplate->id) {
                $numPages = $this->wire('pages')->count("template=$containerTemplate");
                $result['errors'][] = "DEBUG: import-container template found, used by {$numPages} pages";

                if ($numPages == 0) {
                    $fieldgroup = $containerTemplate->fieldgroup;
                    $this->wire('templates')->delete($containerTemplate);
                    $this->wire('fieldgroups')->delete($fieldgroup);
                    $result['templates_deleted']++;
                    $result['errors'][] = "DEBUG: Successfully deleted import-container template";
                } else {
                    $result['errors'][] = "DEBUG: import-container still in use, not deleting";
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
