<?php
require_once __DIR__ . '/bootstrap.inc';

/**
* Main
*/
function main()
{
    echo '<HTML><HEAD> ';

    echo '<TITLE>Cel eigenschappen</TITLE>';

    echo '<script src="minify.php?f=wizards/wizard.js,../library/library.js,../library/colorpicker.js"></script>';

    echo '<script>;
    var selectedCell = window.dialogArguments["table_cell"];
    var cellAlign = selectedCell.align;
    var cellvAlign = selectedCell.vAlign;
    var blnNowrap = selectedCell.noWrap;

    /**
    * Init
    */
    function init()
    {
        tableForm.cell_width.value  = selectedCell.width;
        tableForm.nowrap.checked = selectedCell.noWrap;
        //  tableForm.header.checked = selectedCell.tagName == \'TH\';
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

        if (tableForm.cell_width.value=="0") {
            libAlert("Minimale breedte mag geen 0 zijn!");
            error = 1;
            tableForm.cell_width.select()
            tableForm.cell_width.focus()
        }
        if (error != 1) {
            selectedCell.width = tableForm.cell_width.value;
            if (tableForm.bgColor.value != "") {
                selectedCell.bgColor = tableForm.bgColor.value;
            } else {
                selectedCell.removeAttribute(\'bgColor\',0)
            }
            if (tableForm.align[tableForm.align.selectedIndex].value != "") {
                selectedCell.align = tableForm.align[tableForm.align.selectedIndex].value;
            } else {
                selectedCell.removeAttribute(\'align\',0)
            }

            if (tableForm.valign[tableForm.valign.selectedIndex].value != "") {
                selectedCell.vAlign = tableForm.valign[tableForm.valign.selectedIndex].value;
            } else {
                selectedCell.removeAttribute(\'vAlign\',0)
            }

            selectedCell.noWrap = tableForm.nowrap.checked;

            //       if (tableForm.header.checked) {
                //			 selectedCell.outerHTML.replace(\'<TD\',\'<TH\')
                //			 selectedCell.outerHTML.replace(\'TD>\',\'TH>\')
                //		} else {
                //			 selectedCell.outerHTML.replace(\'<TH\',\'<TD\')
                //			 selectedCell.outerHTML.replace(\'TH>\',\'TD>\')
                //
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
    * Printalign
    */
    function printAlign()
    {
        if ((cellAlign != undefined) && (cellAlign != "")) {
            document.write(\'<option selected>\' + cellAlign)
            document.write(\'<option>' . ($lang_geen) . '' . '\')
        } else {
            document.write(\'<option selected>' . ($lang_geen) . '' . '\')
        }
    }

    /**
    * Printvalign
    */
    function printvAlign()
    {
        if ((cellvAlign != undefined) && (cellvAlign != "")) {
            document.write(\'<option selected>\' + cellvAlign)
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

    echo '  <script>ColorPicker("bgColor", selectedCell.bgColor);</script>';

    echo '</td>';

    echo '</tr>';

    echo '	<tr height=30>';

    echo '	<td>Minimale breedte:&nbsp;</td>';

    echo '	<td><input type="text" name="cell_width" size="4" maxlength="4" ONKEYPRESS="event.returnValue=IsDigit();" ></td>';

    echo '    <td nowrap>Percentage t.o.v. de pagina<br>of een vaste waarde in beeldpunten</td>';

    echo '	</tr>';

    echo '	<tr height=30>';

    echo '	<td>Horizontale positie inhoud:&nbsp;</td>';

    echo '	<td>';

    echo '		<SELECT name=align>';

    echo '		<script>printAlign()</script>';

    echo '		<option>';

    echo '		<option value=Left>Links';

    echo '		<option value=Center>Centreer';

    echo '		<option value=Right>Rechts</option>';

    echo '		</select>';

    echo '	</td>';

    echo '	</tr>';

    echo '	<tr height=30>';

    echo '	<td>Verticale positie inhoud:&nbsp;</td>';

    echo '	<td>';

    echo '		<SELECT name=valign>';

    echo '		<script>printvAlign()</script>';

    echo '		<option>';

    echo '		<option value=Top>Bovenaan';

    echo '		<option value=Middle>In het midden';

    echo '		<option value=Bottom>Onderaan</option>';

    echo '		</select>';

    echo '	</td>';

    echo '	</tr>';

    echo '	<tr height=30>';

    echo '	<td>Tekst altijd op één regel:&nbsp;</td>';

    echo '	<td><input type=CHECKBOX NAME=nowrap></td>';

    echo '	</tr>';

    echo '<!--';

    echo '	<tr height=30>';

    echo '	<td>Titel cel:&nbsp;</td>';

    echo '	<td><input type=CHECKBOX NAME=header></td>';

    echo '	</tr>';

    echo '-->';

    echo '</table>';

    echo '</form>';

    echo '</div>';

    echo '</body>';

    echo '</html>';

}

// Call main function
main();
?>
