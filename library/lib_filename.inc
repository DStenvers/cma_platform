<?php

/**

* Lib Filenamesanitize

*

*/

// Include-guard: dit bestand weigert zichzelf een tweede keer uit te voeren.
// require_once dekt het gewone geval al — die lost het pad eerst op — maar niet een
// kale require/include, en niet een pad dat via een junction anders uitkomt. De naam
// is uniek per bestand, zodat de guard niet van de aanroeper afhangt.
if (defined('INC_LIB_FILENAME')) { return; }
define('INC_LIB_FILENAME', 1);

function Lib_FilenameSanitize($strInput)
{
    $strInput = $strInput ?? '';
    $strTemp = '';

    $pos = null;

    $cOneChar = '';

    $strInput = str_ireplace(' ', '_', $strInput);

    $strInput = str_ireplace('�', 'e', $strInput);

    $strInput = str_ireplace('�', 'e', $strInput);

    $strInput = str_ireplace('�', 'e', $strInput);

    $strInput = str_ireplace('�', 'e', $strInput);

    $strInput = str_ireplace('�', 'i', $strInput);

    $strInput = str_ireplace('�', 'o', $strInput);

    $strInput = str_ireplace('�', 'O', $strInput);

    $strInput = str_ireplace('?', '-', $strInput);

    $strInput = str_ireplace('&', '-', $strInput);

    $strInput = str_ireplace('#', '-', $strInput);

    for ($pos = 1; $pos <= strlen($strInput); $pos++) {

        $cOneChar = substr($strInput, $pos - 1, 1);

        if (stripos('ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890.-_()[]', strtoupper($cOneChar)) !== false) {

            $strTemp = $strTemp . $cOneChar;

        }

    }

    return $strTemp;

}

?>
