/**
 * DatePicker compatibility wrapper
 *
 * This file provides backward compatibility for legacy code that calls DatePicker().
 * All date picking is now handled by the lib-datepicker web component.
 */

/**
 * Create a date picker using the lib-datepicker web component
 *
 * @param {string} name - Field name
 * @param {string} def_value - Default value in DD-MM-YYYY format
 */
function DatePicker(name, def_value) {
    if (def_value == null) def_value = "";

    // Convert DD-MM-YYYY to YYYY-MM-DD for lib-datepicker
    var isoValue = "";
    if (def_value && def_value.length === 10) {
        var parts = def_value.split("-");
        if (parts.length === 3) {
            isoValue = parts[2] + "-" + parts[1] + "-" + parts[0];
        }
    }

    // Use lib-datepicker web component
    document.write('<lib-datepicker name="' + name + '"' +
        (isoValue ? ' value="' + isoValue + '"' : '') +
        ' format="dd-mm-yyyy" locale="nl"></lib-datepicker>');
}
