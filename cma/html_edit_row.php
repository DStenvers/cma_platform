<?php

require_once __DIR__ . '/bootstrap.inc';

/**
* Main
*/
function main()
{
    echo '<HTML><HEAD> ';

    echo '<TITLE>Regel eigenschappen</TITLE>';

    echo '<script src="minify.php?f=wizards/wizard.js,../library/library.js,../library/colorpicker.js"></script>';

    echo '<script>;
    var selectedRow = window.dialogArguments["table_row"];
    var rowvAlign = selectedRow.vAlign;

    /**
    * Init
    */
    function init()
    {
        tableForm.table_height.value = selectedRow.height;
        this.focus();
    }

    /**
    * Call Wizardgetnextpage
    */
    function call_WizardGetNextPage( current_page )
    {
        return 1;
    }

    /**
    * Call Wizardfinishpressed
    */
    function call_WizardFinishPressed()
    {
        var arr = new Array();
        var error = 0;

        if (tableForm.table_height.value=="0") {
            libAlert("Minimale hoogte mag geen 0 zijn!");
            error = 1;
            tableForm.table_height.select()
            tableForm.table_height.focus()
        }
        if (error != 1) {
            selectedRow.height = tableForm.table_height.value;
            if (tableForm.bgColor.value != "") {
                selectedRow.bgColor = tableForm.bgColor.value;
            } else {
                selectedRow.removeAttribute(\'bgColor\',0)
            }

            if (tableForm.valign[tableForm.valign.selectedIndex].value != "") {
                selectedRow.vAlign = tableForm.valign[tableForm.valign.selectedIndex].value;
            } else {
                selectedRow.removeAttribute(\'vAlign\',0)
            }

            return arr;

        } else {

            return false;

        }
    }

    /**
    * Call Wizardgetshowfinish
    */
    function call_WizardGetShowFinish( current_page )
    {
        return (current_page==1);
    }

    /**
    * Printvalign
    */
    function printvAlign()
    {
        if ((rowvAlign != undefined) && (rowvAlign != "")) {
            document.write(\'<option selected>\' + rowvAlign)
            document.write(\'<option>' . ($lang_geen) . '' . '\')
        } else {
            document.write(\'<option selected>' . ($lang_geen) . '' . '\')
        }
    }
    </script>';

    echo '</head>';

    echo '<BODY class=wizardcontent style=margin:0px onload="init();if(window.parent && typeof window.parent.WizardActivatePage===\'function\')window.parent.WizardActivatePage(1)" onkeypress="if(window.parent && typeof window.parent.WizardButtonPressed===\'function\')window.parent.WizardButtonPressed(event.keyCode);return true;">';

    echo '<DIV ID=page1>';

    echo '<form name=tableForm>';

    echo '<table cellspacing=0 cellpadding=0>';

    echo '<tr height=30>';

    echo '<td>Achtergrond kleur:&nbsp;</td>';

    echo '<td>';

    echo '  <script>ColorPicker("bgColor", selectedRow.bgColor)</script>';

    echo '</td>';

    echo '</tr>';

    echo '<tr height=30><td>Minimale hoogte:</td>';

    echo '<td><input type="text" name="table_height" size="4" value="" maxlength="4"></td>';

    echo '<td nowrap>Percentage t.o.v. de pagina<br>of een vaste waarde in beeldpunten</td>';

    echo '</tr>';

    echo '<tr height=30>';

    echo '<td>Verticale positie inhoud:&nbsp;</td>';

    echo '<td>';

    echo '		<SELECT name=valign>';

    echo '		<script>printvAlign()</script>';

    echo '		<option>';

    echo '		<option value=Top>Bovenaan';

    echo '		<option value=Middle>In het midden';

    echo '		<option value=Bottom>Onderaan</option>';

    echo '		</select>';

    echo '</td>';

    echo '</tr>';

    echo '</table>';

    echo '</form>';

    echo '</div>';

    echo '</body>';

    echo '</html>';

}

// Call main function
main();
?>
