/**
 * Smart Checkbox Logic for Data Migrator
 * Handles table and field checkbox interactions
 */

document.addEventListener('DOMContentLoaded', function() {

    /**
     * Get all table checkboxes
     */
    function getTableCheckboxes() {
        return document.querySelectorAll('input[name="selected_tables[]"]');
    }

    /**
     * Get field checkboxes for a specific table
     */
    function getFieldCheckboxes(tableName) {
        return document.querySelectorAll('input[name="fields[' + tableName + '][]"]:not([type="hidden"])');
    }

    /**
     * Update table checkbox state based on field checkboxes
     */
    function updateTableCheckbox(tableName) {
        const tableCheckbox = document.querySelector('input[name="selected_tables[]"][value="' + tableName + '"]');
        const fieldCheckboxes = getFieldCheckboxes(tableName);

        if (!tableCheckbox) return;

        // Count checked field checkboxes (excluding disabled ones like title field)
        let checkedCount = 0;
        fieldCheckboxes.forEach(function(checkbox) {
            if (checkbox.checked && !checkbox.disabled) {
                checkedCount++;
            }
        });

        // If any non-disabled field is checked, table should be checked
        tableCheckbox.checked = checkedCount > 0;
    }

    /**
     * Handle table checkbox change
     */
    function onTableCheckboxChange(event) {
        const tableCheckbox = event.target;
        const tableName = tableCheckbox.value;
        const isChecked = tableCheckbox.checked;
        const fieldCheckboxes = getFieldCheckboxes(tableName);

        // Update all field checkboxes (except disabled ones like title field)
        fieldCheckboxes.forEach(function(checkbox) {
            if (!checkbox.disabled) {
                checkbox.checked = isChecked;
            }
        });
    }

    /**
     * Handle field checkbox change
     */
    function onFieldCheckboxChange(event) {
        const fieldCheckbox = event.target;

        // Extract table name from name attribute: fields[tableName][]
        const match = fieldCheckbox.name.match(/fields\[([^\]]+)\]\[\]/);
        if (!match) return;

        const tableName = match[1];

        // Update table checkbox based on field selections
        updateTableCheckbox(tableName);
    }

    /**
     * Initialize checkbox event listeners
     */
    function init() {
        // Add event listeners to table checkboxes
        const tableCheckboxes = getTableCheckboxes();
        tableCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', onTableCheckboxChange);
        });

        // Add event listeners to all field checkboxes
        const allFieldCheckboxes = document.querySelectorAll('input[name^="fields["]:not([type="hidden"])');
        allFieldCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', onFieldCheckboxChange);
        });

        console.log('Data Migrator: Smart checkbox logic initialized');
        console.log('- Table checkboxes: ' + tableCheckboxes.length);
        console.log('- Field checkboxes: ' + allFieldCheckboxes.length);
    }

    // Initialize
    init();
});
