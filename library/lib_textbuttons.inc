<?php

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
