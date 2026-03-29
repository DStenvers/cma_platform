<?php
use App\Library\Application;

define("EDITMODE_EDIT", 1);
define("EDITMODE_ADD", 2);
define("EDITMODE_DELETE", 3);
// Determines the edit mode,
// Based upon 2 parameters, this function determines the edit-state
/**
* Editmodedetermine
*
*/
function EditModeDetermine($strIDParam, $strDeleteParam)
{
    if ((is_null($strDeleteParam) ? "" : strtolower($strDeleteParam)) == 'yes') {
        return EDITMODE_DELETE;
    } elseif (STRIDPARAM != '') {
        return EDITMODE_EDIT;
    }

    return EDITMODE_ADD;
}
// Determines the edit mode,
// returns a description of the edit mode for titles
/**
* Editmodetext
*
*/
function EditModeText($intEditMode)
{
    switch ($intEditMode) {
    case EDITMODE_EDIT:
        return 'wijzigen';
        break;
    case EDITMODE_ADD:
        return 'toevoegen';
        break;
    case EDITMODE_DELETE:
        return 'verwijderen';
        break;
    }
}
// Determines the edit mode,
// returns a description of the edit mode for the Save button
/**
* Editmodebuttontext
*
*/
function EditModeButtonText($intEditMode)
{
    switch ($intEditMode) {
    case EDITMODE_EDIT:
    case EDITMODE_ADD:
        return 'Bewaar';
        break;
    case EDITMODE_DELETE:
        return 'Verwijder!';
        break;
    }
}
// Creates the text for the Save button
// TODO: returns a string for the button  Use this one
/**
* Editmodesavebutton
*
*/
function EditModeSaveButton($intEditMode)
{
    switch ($intEditMode) {
    case EDITMODE_EDIT:
    case EDITMODE_ADD:
        if (lib_isIE4orUp()) {
                return '<BUTTON type=submit name=Bewaar><table cellpadding=0 cellspacing=0><tr><td><IMG alt=Bewaar border=0 height=16 width=16 src=' . Application::get('base_path', '') . 'library/images/formbtn_ok.gif></td><td>&nbsp</td><td>Bewaar</td></tr></table></BUTTON>';
            } else {
                return '<input type=submit name=Bewaar value=Bewaar>';
            }
        break;
    case EDITMODE_DELETE:
        if (lib_isIE4orUp()) {
                return '<BUTTON type=submit name=Bewaar><table cellpadding=0 cellspacing=0><tr><td><IMG alt=Verwijder border=0 height=16 width=16 src=' . Application::get('base_path', '') . 'library/images/formbtn_del.gif></td><td>&nbsp</td><td>Verwijder</td></tr></table></BUTTON>';
            } else {
                return '<input type=submit name=Verwijder value=Verwijder>';
            }
        break;
    }
}
// Creates the text for the Send button
// returns a string for the button
/**
* Editmodesendbutton
*
*/
function EditModeSendButton()
{
    if (lib_isIE4orUp()) {
        return '<BUTTON type=submit name=Bewaar><table cellpadding=0 cellspacing=0><tr><td><IMG alt=Verstuur border=0 height=11 width=24 src=' . Application::get('base_path', '') . 'library/images/formbtn_mail.gif></td><td>&nbsp;</td><td>Verstuur</td></tr></table></BUTTON>';
    } else {
        return '<input type=submit name=Bewaar value=Verstuur>';
    }
}
// Creates the text for the Print button
// returns a string for the button
/**
* Editmodeprintbutton
*
*/
function EditModePrintButton()
{
    if (lib_isIE4orUp()) {
        return '<BUTTON name=Print onclick=printit() onError=""><table cellpadding=0 cellspacing=0><tr><td><IMG alt=Afdrukken border=0 height=18 width=18 src=' . Application::get('base_path', '') . 'library/images/formbtn_prn.gif></td><td>&nbsp;</td><td>Afdrukken</td></tr></table></BUTTON>';
    } else {
        return '<input type=button name=Print value=Afdrukken onclick=printit() onError="">';
    }
}
// Creates the text for the Cancel button
// TODO: returns a string for the button  Use this one
/**
* Editmodecancelbutton
*
*/
function EditModeCancelButton()
{
    if (lib_isIE4orUp()) {
        return '<BUTTON OnClick=Javascript:history.go(-1)><table cellpadding=0 cellspacing=0><tr><td><IMG alt=Annuleer border=0 height=16 width=16 src=' . Application::get('base_path', '') . 'library/images/formbtn_nok.gif></td><td>&nbsp</td><td>Annuleer</td></tr></table></BUTTON>';
    } else {
        return '<input type=button OnClick="Javascript:history.go(-1)" value=Annuleer name=Annuleer>';
    }
}
?>
