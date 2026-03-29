<?php

// Generate a relatively safe password (or other random name)

// to prevent problems with confusing the 0 with the O and the I with the 1, the digits start with 2

/**

* Lib Passwordgenerate

*

*/

function lib_PasswordGenerate($nLength)
{

    global $Rnd;

    $cTemp = '';

    $t = null;

    $x = null;

    for ($t = 1; $t <= $nLength; $t++) {

        // randomize selection between digit or character

        mt_srand();

        $x = round(2 * Rnd, 0);

        // Randomize

        if ($x == 1) {

            $cTemp .= chr(64 + floor(26 * Rnd + 1));

        } else {

            $cTemp .= chr(49 + floor(8 * Rnd + 1));

        }

    }

    return str_replace('I', 'X', str_replace('O', 'P', $cTemp));

}

// Compatibility function

/**

* Lib Generatepassword

*

*/

function lib_GeneratePassword($nLength)
{

    return lib_PasswordGenerate($nLength);

}

?>
