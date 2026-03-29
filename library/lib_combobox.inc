<?php
/**
 * Legacy combobox functions - wrapper for FormControls class
 *
 * This file provides backward compatibility for code using WriteCombo().
 * New code should use App\Library\FormControls::combo() directly.
 */

use App\Library\FormControls;

// Prevent redeclaration if already loaded
if (!function_exists('WriteCombo')) {
    /**
     * Write a combobox (legacy wrapper)
     *
     * @deprecated Use FormControls::combo() instead
     */
    function WriteCombo($sName, $oConn, $sSql, $sDisplayField, $sIDField, $sCurrentValue, $intHeight, $blnRequired)
    {
        FormControls::combo(
            $sName,
            $oConn,
            $sSql,
            $sDisplayField,
            $sIDField,
            $sCurrentValue,
            [
                'height' => $intHeight,
                'required' => (bool)$blnRequired,
                'id' => $sName,
            ],
            true // echo output
        );
    }
}
