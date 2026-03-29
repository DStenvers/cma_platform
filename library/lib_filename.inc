<?php

/**

* Lib Filenamesanitize

*

*/

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
