<?php

// Include-guard: dit bestand weigert zichzelf een tweede keer uit te voeren.
// require_once dekt het gewone geval al — die lost het pad eerst op — maar niet een
// kale require/include, en niet een pad dat via een junction anders uitkomt. De naam
// is uniek per bestand, zodat de guard niet van de aanroeper afhangt.
if (defined('INC_LIB_TEXTBUTTONS')) { return; }
define('INC_LIB_TEXTBUTTONS', 1);

define("BUTTONS_ALIGN_LEFT", true);
define("BUTTONS_ALIGN_RIGHT", false);
$static_blnAlignLeft = null;

/**

* Buttonsinit

*

*/

function ButtonsInit($blnAlignLeft)
{

    global $static_blnAlignLeft;

    $static_blnAlignLeft = $blnAlignLeft;

    echo '<script type=text/javaScript>TBInit(';

    if ($static_blnAlignLeft == BUTTONS_ALIGN_LEFT) {

        echo 'true';

    } else {

        echo 'false';

    }

    echo ');';

}

/**

* Buttonswrite

*

*/

function ButtonsWrite($strContent)
{

    echo "TB('" . $strContent . "');";

}

/**

* Buttonsend

*

*/

function ButtonsEnd()
{

    global $static_blnAlignLeft;

    echo 'TBEnd(';

    if ($static_blnAlignLeft == BUTTONS_ALIGN_LEFT) {

        echo 'true';

    } else {

        echo 'false';

    }

    echo ');</script>';

}

?>
