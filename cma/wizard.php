<?php
use App\Library\Response;

require_once __DIR__ . '/bootstrap.inc';

/**
* Main
*/
function main()
{
    Response::noCache();
    $extraHead = '<STYLE>button{font-family:verdana;font-size:var(--font-size-xs)}</STYLE>';
    cma_html_header('Wizard', $extraHead, false);
    echo '<script>;
    var active_page = -1;
    var content_frame;
    var content_document;
    var intHistoryItems = 0;
    /**
    * Wizardbuttonpressed
    */
    function WizardButtonPressed( charcode )
    {
        if (charcode == 13) {
            if (document.getElementById("finish").style.display != "none") {
                WizardFinished();
            } else {
                WizardGetNextPage();
            }
        } else if (charcode == 27) {
            WizardCancelled();
        }
        return true;
    }
    /**
    * Wizardinit
    */
    function WizardInit()
    {
        // create history array
        arrHistory = new Array;
        // beschrijving vullen
        if (window.dialogArguments) {
            if (window.dialogArguments["descr"])
            document.getElementById("DESCR").innerHTML = window.dialogArguments["descr"];
            // als icoon gespecificieerd, zetten (anders pijltje)
            if (window.dialogArguments["icon"])
            document.getElementById("icon").src = window.dialogArguments["icon"];
            document.getElementById("content").height = (document.getElementsByTagName("body")[0].clientHeight - 95).toString() + "px";
            document.getElementById("content").width = (document.getElementsByTagName("body")[0].clientWidth - 30).toString() + "px";
            // zetten inhoud content frame op basis van parameter
            if (window.dialogArguments["content"])
            document.getElementById("content").src = window.dialogArguments["content"];
        } else {
            alert(\' Missing arguments\');
        }
        // TODO prevent too large space between buttons and bottom of dialog here
        // something like comparing the available value with parseInt(window.external.dialogHeight)
        // content frame should call WizardActivatePage(1) in onload event of BODY class
        // this ensures the content page is loaded before continuing
        // alert(parseInt(window.external.dialogHeight) + dbg.dbg.dbg);
    }
    /**
    * Wizardcancelled
    */
    function WizardCancelled()
    {
        window.returnValue = null;
        window.close();
    }
    /**
    * Wizardfinished
    */
    function WizardFinished()
    {
        // collect items
        window.returnValue = parent.content.call_WizardFinishPressed();
        if (window.returnValue) window.close();
    }
    /**
    * Wizardactivatepage
    */
    function WizardActivatePage( id )
    {
        var blnShowFinish
        // bewaar content frame voor later gebruik
        // kan pas hier, omdat de content frame bij de eerste call naar deze functie helemaal geladen is
        content_frame = (document.all ? document.frames[\'content\'] : top.document.getElementById("content") );
        content_document = (document.all ? content_frame.document : content_frame.contentDocument);
        // eventuele oude pagina de-activeren
        if (active_page!=-1) {
            content_document.getElementById(\'page\'+active_page.toString()).style.display = \'none\';
        }
        // nieuwe pagina activeren
        if (content_document.getElementById(\'page\'+id.toString())) {
            // annuleren aanzetten
            document.getElementById("cancel").style.display = \'\';
            content_document.getElementById(\'page\'+id.toString()).style.display = \'block\';
            // active pagina onthouden
            active_page = id;
            // Back knop activeren of niet?
            if ( active_page==1 ) {
                document.getElementById("back").style.display=\'none\';
            } else {
                document.getElementById("back").style.display=\'\';
                document.getElementById("back").disabled = (active_page==1);
            }
            // Next knop tonen, of de Finish knop?
            if (!parent.content.call_WizardGetShowFinish) {
                alert ("Wizard : call_WizardGetShowFinish() not implemented!");
            }
            blnShowFinish = parent.content.call_WizardGetShowFinish(active_page);
            document.getElementById("next").style.display = blnShowFinish ? \'none\' : \'\';
            document.getElementById("next").disabled = blnShowFinish;
            document.getElementById("finish").style.display = blnShowFinish ? \'\' : \'none\';
        } else {
            alert("Wizard: Page " + id + " does not seem to exist!")
        }
    }
    /**
    * Wizardgetnextpage
    */
    function WizardGetNextPage( )
    {
        // aan de client frame de nieuwe pagina opvragen
        if (!parent.content.call_WizardGetNextPage) {
            alert ("Wizard : call_WizardGetNextPage() not implemented!");
        }
        var intNewPage = parent.content.call_WizardGetNextPage( active_page );
        // als het een andere pagina is, zetten !
        if (intNewPage!=active_page) {
            intHistoryItems ++;
            arrHistory[ intHistoryItems ] = active_page;
            WizardActivatePage($intNewPage);
        }
    }
    /**
    * Wizardgetprevpage
    */
    function WizardGetPrevPage( )
    {
        // je kunt altijd terug, dus zo lang het pagina nummer > 1, dan gewoon toestaan..
        if (intHistoryItems>0) {
            // als het een andere pagina betreft, activeren !
            WizardActivatePage( arrHistory[ intHistoryItems ] );
            intHistoryItems --;
        }
    }
    </script>';
    echo '</HEAD>';
    echo '<BODY onload="WizardInit()" onkeypress="WizardButtonPressed( lib_event_get_key(event));return true;" class="wizardcontent" style="margin:0px;overflow:hidden">';
    echo '<TABLE ID="TOPROW" CELLPADDING=0 CELLSPACING=0 class=windowheader style=height:60px>';
    echo '<TR>';
    echo '<TD width=1% ><IMG SRC=IMAGES/pixel.gif width=8 height=50></TD>';
    echo '<TD width=97% ><H2><DIV ID=DESCR></DIV></H2></TD>';
    echo '<TD WIDTH=1% align=right><IMG id=icon SRC=images/wizard_img_arrow.gif height=50 width=39></TD>';
    echo '<TD width=1% ><IMG SRC=IMAGES/pixel.gif width=18 height=50></TD>';
    echo '</TR></TABLE>';
    echo '<iframe frameborder=0 style="overflow:auto;margin-left:12px;margin-right:12px" name="content" class="wizardcontent" id="content" scrollbars=no src=blank.php onkeypress="WizardButtonPressed(event.keyCode);return true;"></iframe>';
    echo '<TABLE ID=BUTTONBAR cellpadding=0 cellspacing=0 width=100%> ';
    echo '<TR valign=middle>';
    echo '<TD width="1%"><BUTTON ID="cancel" style="margin-left:12px;display:none" Onclick=WizardCancelled()>';
    echo $lang_wizard_cancel;
    echo '</BUTTON></TD>';
    echo '<TD width="98%" align="right">';
    echo '    <BUTTON ID="back"   ONCLICK=WizardGetPrevPage() DISABLED style=display:none>';
    echo $lang_wizard_back;
    echo '</BUTTON>';
    echo '	<BUTTON ID="next"   ONCLICK=WizardGetNextPage() DISABLED style=display:none>';
    echo $lang_wizard_next;
    echo '</BUTTON>';
    echo '	<BUTTON ID="finish" ONCLICK=WizardFinished() style=display:none>';
    echo $lang_wizard_finish;
    echo '</BUTTON>';
    echo '</TD>';
    echo '<TD width="1%"><IMG SRC=IMAGES/pixel.gif width=18 height=1></TD>';
    echo '</TR></TABLE></BODY></HTML>';
}
// Call main function
main();
?>
