<?php

// creates keywords from a given text: strips text and puts all other items within comma's

/**

* Lib Keyword

*

*/

function lib_Keyword($strBaseText)
{

    $strTemp = ' ' . (is_null(str_replace('?', '', $strBaseText)) ? "" : strtolower(str_replace('?', '', $strBaseText))) . ' ';

    // voorzetsels

    $strTemp = str_replace(' de ', ' ', $strTemp);

    $strTemp = str_replace(' het ', ' ', $strTemp);

    $strTemp = str_replace(' een ', ' ', $strTemp);

    // vragende voornaamwoorden

    $strTemp = str_replace(' wat ', ' ', $strTemp);

    $strTemp = str_replace(' wie ', ' ', $strTemp);

    $strTemp = str_replace(' wanneer ', ' ', $strTemp);

    $strTemp = str_replace(' waarom ', ' ', $strTemp);

    $strTemp = str_replace(' waar ', ' ', $strTemp);

    $strTemp = str_replace(' hoe ', ' ', $strTemp);

    // persoonlijke voornaamwoorden

    $strTemp = str_replace(' ik ', ' ', $strTemp);

    $strTemp = str_replace(' je ', ' ', $strTemp);

    $strTemp = str_replace(' jij ', ' ', $strTemp);

    $strTemp = str_replace(' wij ', ' ', $strTemp);

    // voegwoorden

    $strTemp = str_replace(' en ', ' ', $strTemp);

    $strTemp = str_replace(' met ', ' ', $strTemp);

    // eenvoudige werkwoorden

    $strTemp = str_replace(' ga ', ' ', $strTemp);

    $strTemp = str_replace(' doen ', ' ', $strTemp);

    $strTemp = str_replace(' heb ', ' ', $strTemp);

    $strTemp = str_replace(' heeft ', ' ', $strTemp);

    $strTemp = str_replace(' had ', ' ', $strTemp);

    $strTemp = str_replace(' ben ', ' ', $strTemp);

    $strTemp = str_replace(' is ', ' ', $strTemp);

    $strTemp = str_replace(' was ', ' ', $strTemp);

    $strTemp = str_replace(' weet ', ' ', $strTemp);

    // bezittelijk

    $strTemp = str_replace(' onze ', ' ', $strTemp);

    $strTemp = str_replace(' mijn ', ' ', $strTemp);

    $strTemp = str_replace(' zijn ', ' ', $strTemp);

    // andere

    $strTemp = str_replace(' geen ', ' ', $strTemp);

    $strTemp = str_replace(' voor ', ' ', $strTemp);

    $strTemp = str_replace(' na ', ' ', $strTemp);

    $strTemp = str_replace(' van ', ' ', $strTemp);

    $strTemp = str_replace(' aan ', ' ', $strTemp);

    $strTemp = str_replace(' op ', ' ', $strTemp);

    $strTemp = str_replace(' in ', ' ', $strTemp);

    $strTemp = str_replace(' te ', ' ', $strTemp);

    $strTemp = str_replace(' niet ', ' ', $strTemp);

    $strTemp = str_replace(' naar ', ' ', $strTemp);

    $strTemp = str_replace(' weer ', ' ', $strTemp);

    $strTemp = str_replace(' nu ', ' ', $strTemp);

    $strTemp = str_replace('  ', ',', $strTemp);

    $strTemp = str_replace(' ', ',', trim($strTemp));

    $strTemp = str_replace(',,', ',', trim($strTemp));

    return $strTemp;

}

?>
