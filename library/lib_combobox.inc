<?php
/**
 * Legacy combobox functions - wrapper for FormControls class
 *
 * This file provides backward compatibility for code using WriteCombo().
 * New code should use App\Library\FormControls::combo() directly.
 */

// Include-guard: dit bestand weigert zichzelf een tweede keer uit te voeren.
// require_once dekt het gewone geval al — die lost het pad eerst op — maar niet een
// kale require/include, en niet een pad dat via een junction anders uitkomt. De naam
// is uniek per bestand, zodat de guard niet van de aanroeper afhangt.
if (defined('INC_LIB_COMBOBOX')) { return; }
define('INC_LIB_COMBOBOX', 1);

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
