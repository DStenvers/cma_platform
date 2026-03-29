<?php
use App\Library\Response;

require_once __DIR__ . '/../bootstrap.inc';

/**
* Main
*/
function main()
{
    Response::noCache();
    echo '<HTML><HEAD>';
    cma_error_handler();
    echo '<TITLE>Wizard implementatie !</TITLE>';
    echo '<script src="../minify.php?f=wizards/wizard.js,../library/library.js,../library/colorpicker.js"></script>';
    echo '<script type="text/javascript">;
    /**
    * Init
    */
    function init()
    {
        for ( elem in window.dialogArguments )
        {
            switch( elem )
            {
            case "NumRows":
                document.getElementById("numrows_id").value = window.dialogArguments["NumRows"];
                break;
            case "NumCols":
                document.getElementById("numcols_id").value = window.dialogArguments["NumCols"];
                break;
            }
        }
    }
    // ----------------------------------------- Wizards utils!
    /**
    * Call Wizardgetnextpage
    */
    function call_WizardGetNextPage( current_page )
    {
        return 2;
    }
    /**
    * Call Wizardfinishpressed
    */
    function call_WizardFinishPressed()
    {
        var editor = window.parent.window.dialogArguments["editor"];
        editor.insertHtml( ShowSample( false ) );
        return true;
    }
    /**
    * Call Wizardgetshowfinish
    */
    function call_WizardGetShowFinish( current_page )
    {
        return (current_page==1);
    }
    /**
    * Showsample
    */
    function ShowSample( blnContent )
    {
        nRows =  document.getElementById("numrows_id").value ?  document.getElementById("numrows_id").value : 1;
        nCols =  document.getElementById("numcols_id").value ?  document.getElementById("numcols_id").value : 1;
        style = \'style=\"border:\' +  document.getElementById("tableborderwidth_id").value + \'px solid \' + document.getElementById("TableBorderColor").value + \';BORDER-COLLAPSE:collapse\"\'
        sContent = "<TABLE "+style+" bordercolor=\'"+ document.getElementById("TableBorderColor").value +"\' border=\'" +  document.getElementById("tableborderwidth_id").value + "\' cellpadding=" +  document.getElementById("cellpadding_id").value + " cellspacing=0><TBODY>"
        for (nRow=0;nRow<nRows;nRow++) {
            sContent += "<TR valign=top>";
            for (nCol=0;nCol<nCols;nCol++) {
                sContent += "<TD>" + (blnContent ? (nRow.toString() + \'.\'+ nCol.toString()) : \'\') + "</TD>" ;
            }
            sContent += "</TR>";
        }
        sContent += "</TBODY></TABLE>";
        sample.innerHTML = sContent;
        return sContent;
    }
    /**
    * Redisplay
    */
    function Redisplay()
    {
        ShowSample( true );
        window.setTimeout ("Redisplay()", 500);
    }
    /**
    * Setbordercol
    */
    function SetBorderCol( sColor )
    {
        document.getElementById("TableBorderColor").value = sColor;
        colorsample.style.backgroundColor = sColor;
        ShowSample( true );
    }
    </script>';
    echo '</HEAD>';
    echo '<BODY class=wizardcontent style="margin:10 0 0 0" onload="init();if(window.parent && typeof window.parent.WizardActivatePage===\'function\')window.parent.WizardActivatePage(1)" onkeypress="if(window.parent && typeof window.parent.WizardButtonPressed===\'function\')window.parent.WizardButtonPressed(event.keyCode);return true;">';
    echo '<DIV ID=page1 style=display:none;height:180px>';
    echo '<TABLE width=100% CELLPADDING=0 cellspacing=0>';
    echo ' <TR height=30>';
    echo '  <TD nowrap width=1% >Aantal regels:';
    echo '  <TD><INPUT TYPE=TEXT SIZE=2 maxlength=2 id="numrows_id" NAME=NumRows	    ONKEYPRESS="event.returnValue=IsDigit();" value=2>';
    echo ' <TR height=30>';
    echo '  <TD nowrap>Aantal kolommen:';
    echo '  <TD><INPUT TYPE=TEXT SIZE=2 maxlength=2 id="numcols_id" NAME=NumCols	    ONKEYPRESS="event.returnValue=IsDigit();" value=2>';
    echo ' <TR height=30>';
    echo '  <TD nowrap>Cel ruimte:';
    echo '  <TD><INPUT TYPE=TEXT SIZE=2 maxlength=2 id="cellpadding_id" NAME=CellPadding_id  ONKEYPRESS="event.returnValue=IsDigit();" value=2>';
    echo '  <TD nowrap>Extra ruimte <b>in</b> cellen';
    echo ' <TR height=30>';
    echo '  <TD nowrap>Rand dikte&nbsp;:&nbsp;</TD>';
    echo '  <TD><input type=text name=TableBorderWidth id="tableborderwidth_id" style="width:20px" maxlength=2 value=1 ONKEYPRESS="event.returnValue=IsDigit();"> ';
    echo '  <TD nowrap>beeldpunten</TD>';
    echo ' <TR height=30>';
    echo '  <TD nowrap>Rand kleur:</TD>';
    echo '  <TD colspan=4 align=left><script>ColorPicker("TableBorderColor", "#c3c3c3");</SCRIPT></TD>';
    echo ' </TR>';
    echo '</TABLE> ';
    echo '</DIV>';
    echo '<H3><hr size=1 color=black>';
    echo 'Voorbeeld:';
    echo '<DIV id=sample style="height:150px;border:1px solid"></DIV>';
    echo '<script>Redisplay()</script> ';
    echo '</BODY>';
    echo '</HTML>';
}
// Call main function
main();
?>
