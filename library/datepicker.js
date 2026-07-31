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

    // <lib-datepicker> wants YYYY-MM-DD. What arrives here is dd-mm-jjjj from a legacy
    // page or, on a round trip through a form post, the jjjj-mm-dd the component itself
    // submitted. Reversing the parts blindly turned that second case back into dd-mm-jjjj
    // and it only kept working because the component happens to read both. Let the shared
    // reader decide instead — and an unreadable value stays empty rather than becoming
    // some other date.
    var isoValue = (typeof lib_datum_normaliseer === "function")
        ? lib_datum_normaliseer(def_value)
        : "";

    // Use lib-datepicker web component
    document.write('<lib-datepicker name="' + name + '"' +
        (isoValue ? ' value="' + isoValue + '"' : '') +
        ' format="dd-mm-yyyy" locale="nl"></lib-datepicker>');
}
