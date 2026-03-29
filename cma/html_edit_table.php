<?php
// LibTabs is in global namespace (loaded from library/classes/class_tabs.inc)

require_once __DIR__ . '/bootstrap.inc';

/**
* Main
*/
function main()
{
    echo '<HTML><HEAD> ';
    echo '<STYLE>.tab_elt{background-color:white;padding-left:8px}</STYLE>';
    echo '<script src="minify.php?f=wizards/wizard.js,../library/library.js,../library/colorpicker.js,../library/layoutpicker.js"></script>';
    echo '<script >;
    var selectedTable = window.parent.window.dialogArguments["table"] ;
    var selectedRow   = window.parent.window.dialogArguments["table_row"];
    var selectedCell  = window.parent.window.dialogArguments["table_cell"];
    var blnNowrap ;
    var tableForm;
    /**
    * Init
    */
    function init()
    {
        blnNowrap = selectedCell.noWrap;
        tableForm = document.getElementById(\'tableForm\');
        tableForm.table_padding.value = selectedTable.cellPadding;
        if (tableForm.table_padding.value == "") tableForm.table_padding.value = 1;
        tableForm.table_border.value = selectedTable.style.borderWidth.replace("px","");
        if (tableForm.table_border == "") tableForm.table_border = 0;
        tableForm.table_width.value = selectedTable.style.width;
        tableForm.table_height.value = selectedTable.style.height;
        tableForm.table_bordercolor.value = selectedTable.style.borderColor;
        //	if (tableForm.table_bordercolor.value+\'\'==\'\') {
            //		tableForm.table_bordercolor.value = selectedTable.borderColor)
            //
        }
        tableForm.row_height.value = selectedRow.style.height;
        tableForm.cell_width.value  = selectedCell.style.width;
        tableForm.nowrap.checked = selectedCell.noWrap;
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
        if (tableForm.table_padding.value == "") {
            tableForm.table_padding.value="2";
        }
        if (tableForm.table_border.value == "") {
            tableForm.table_border.value="0";
        }
        if ( tableForm.table_width.value == "0") {
            changeNavMenu(0)
            libAlert("Breedte mag geen 0 zijn")
            error = 1;
            tableForm.table_width.select()
            tableForm.table_width.focus()
        } else if ( tableForm.table_height.value == "0") {
            changeNavMenu(0)
            libAlert("Hoogte mag geen 0 zijn")
            error = 1;
            tableForm.table_height.select()
            tableForm.table_height.focus()
        }
        if (tableForm.row_height.value=="0") {
            changeNavMenu(1)
            libAlert("Minimale hoogte mag geen 0 zijn!");
            error = 1;
            tableForm.row_height.select()
            tableForm.row_height.focus()
        }
        if (tableForm.cell_width.value=="0") {
            changeNavMenu(2)
            libAlert("Minimale breedte mag geen 0 zijn!");
            error = 1;
            tableForm.cell_width.select()
            tableForm.cell_width.focus()
        }
        if (error != 1) {
            selectedTable.cellPadding = tableForm.table_padding.value;
            //		selectedTable.cellSpacing = tableForm.table_spacing.value
            if (document.getElementById("table_border").value!="") {
                selectedTable.setAttribute("border", document.getElementById("table_border").value);
            } else {
                selectedTable.clearAttribute("border");
            }
            selectedTable.style.borderWidth = tableForm.table_border.value+"px";
            selectedTable.style.borderStyle = (tableForm.table_border.value!="" ? "solid" : "");
            selectedTable.style.width = tableForm.table_width.value;
            selectedTable.style.height = tableForm.table_height.value ;
            selectedTable.style.backgroundColor = tableForm.table_bgColor.value;
            if (tableForm.table_align.value != "") {
                selectedTable.align = tableForm.table_align.value;
            } else {
                selectedTable.removeAttribute(\'align\',0)
            }
            selectedTable.style.marginTop    = (tableForm.table_margin_t.value!="") ? tableForm.table_margin_t.value +"px":"";
            selectedTable.style.marginLeft   = (tableForm.table_margin_l.value!="") ? tableForm.table_margin_l.value +"px":"";
            selectedTable.style.marginBottom = (tableForm.table_margin_b.value!="") ? tableForm.table_margin_b.value +"px":"";
            selectedTable.style.marginRight  = (tableForm.table_margin_r.value!="") ? tableForm.table_margin_r.value +"px":"";
            if (tableForm.table_bordercolor.value != "") {
                selectedTable.style.borderColor = tableForm.table_bordercolor.value;
                selectedTable.setAttribute(\'borderColor\',tableForm.table_bordercolor.value)
            } else {
                selectedTable.removeAttribute(\'borderColor\',0)
            }
            selectedRow.style.height = tableForm.row_height.value;
            selectedRow.style.backgroundColor = tableForm.row_bgColor.value;
            if (tableForm.row_valign.value != "") {
                selectedRow.vAlign = tableForm.row_valign.value;
            } else {
                selectedRow.removeAttribute(\'vAlign\',0)
            }
            selectedCell.style.width = tableForm.cell_width.value;
            selectedCell.style.backgroundColor = tableForm.cell_bgColor.value;
            if (tableForm.cell_align.value != "") {
                selectedCell.align = tableForm.cell_align.value;
            } else {
                selectedCell.removeAttribute(\'align\',0)
            }
            if (tableForm.cell_valign.value != "") {
                selectedCell.vAlign = tableForm.cell_valign.value;
            } else {
                selectedCell.removeAttribute(\'vAlign\',0)
            }
            selectedCell.noWrap = tableForm.nowrap.checked;
            selectedCell.style.paddingTop    = (tableForm.cell_padding_t.value!="") ? tableForm.cell_padding_t.value +"px":"";
            selectedCell.style.paddingLeft   = (tableForm.cell_padding_l.value!="") ? tableForm.cell_padding_l.value +"px":"";
            selectedCell.style.paddingBottom = (tableForm.cell_padding_b.value!="") ? tableForm.cell_padding_b.value +"px":"";
            selectedCell.style.paddingRight  = (tableForm.cell_padding_r.value!="") ? tableForm.cell_padding_r.value +"px":"";
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
    </script>';
    echo '</head>';
    echo '<BODY class=wizardcontent style="margin:8 0 0 0" onload="init();if(window.parent && typeof window.parent.WizardActivatePage===\'function\')window.parent.WizardActivatePage(1)" onkeypress="if(window.parent && typeof window.parent.WizardButtonPressed===\'function\')window.parent.WizardButtonPressed(event.keyCode);return true;">';
    echo '<table height=100% width=100% cellpadding=0 cellspacing=0>';
    echo '<tr><td style=height:1%;width:100% id=LibTabPlaceHolder></td></tr>';
    echo '<tr valign=top><td style="border-left:1px solid #949C9C;border-right:1px solid #949C9C;border-bottom:1px solid #949C9C;height:99%;width:400px">';
    echo '<DIV ID=page1>';
    echo '<form id=tableForm style=margin:0px>';
    echo '<div id=form0 style=display:none class=tab_elt>';
    echo '<table cellspacing=0 cellpadding=0 width=100% >';
    echo '<tr height=35>';
    echo '<td>Achtergrond kleur:&nbsp;</td>';
    echo '<td><script>ColorPicker("table_bgColor", selectedTable.style.backgroundColor)</script></td>';
    echo '</tr>';
    echo '<tr height=35><td>Cel ruimte:&nbsp;</td>';
    echo '<td><input type="text" id="table_padding" ONKEYPRESS="event.returnValue=IsDigit()" size="2" maxlength="2"></td>';
    echo '<td nowrap>Extra ruimte <b>in</b> cellen</td>';
    echo '</tr>';
    echo '<tr height=35><td>Rand dikte:&nbsp;</td>';
    echo '<td><input type="text" id="table_border" ONKEYPRESS="event.returnValue=IsDigit()" size="2" value="1" maxlength="2"></td>';
    echo '<td>Beeldpunten</td></tr>';
    echo '<tr><td>Rand kleur:</td>';
    echo '<td><script>ColorPicker("table_bordercolor", selectedTable.borderColor)</script></td>';
    echo '</tr>';
    echo '<tr height=35><td>Horizontale positie:&nbsp;</td>';
    echo '<td><script>LayoutPicker( "table_align", selectedTable.align, "left", "center", "right");</script></td>';
    echo '</tr>';
    echo '<tr height=35><td>Marges:&nbsp;</td>';
    echo '<td><script>PickMargins( "table_margin", selectedTable.style.marginTop, selectedTable.style.marginLeft, selectedTable.style.marginBottom, selectedTable.style.marginRight);</script></td>';
    echo '<td>Beeldpunten</td></tr>';
    echo '<tr height=35><td>Breedte:</td>';
    echo '<td><input type="text" name="table_width" size="6" value="" maxlength="6"></td>';
    echo '<td nowrap>Percentage t.o.v. de pagina (%)<br>of een vaste waarde in punten (px)</td>';
    echo '</tr>';
    echo '<tr height=35><td>Hoogte:</td>';
    echo '<td><input type="text" name="table_height" size="6" value="" maxlength="6"></td>';
    echo '<td nowrap>Percentage t.o.v. de pagina (%)<br>of een vaste waarde in punten (px)</td>';
    echo '</tr>';
    echo '</table>';
    echo '</div>';
    echo '<div id=form1 style=display:none class=tab_elt>';
    echo '<table cellspacing=0 cellpadding=0 width=100% >';
    echo '<tr height=35>';
    echo '<td>Achtergrond kleur:&nbsp;</td>';
    echo '<td>';
    echo '  <script>ColorPicker("row_bgColor", selectedRow.style.backgroundColor)</script>';
    echo '</td>';
    echo '</tr>';
    echo '<tr height=35><td>Minimale hoogte:</td>';
    echo '<td><input type="text" name="row_height" size="6" value="" maxlength="6"></td>';
    echo '<td nowrap>Percentage t.o.v. de pagina (%)<br>of een vaste waarde in punten (px)</td>';
    echo '</tr>';
    echo '<tr height=35>';
    echo '<td>Verticale positie inhoud:&nbsp;</td>';
    echo '<td>';
    echo '	<script>LayoutPicker( "row_valign", selectedRow.vAlign, "top", "middle", "bottom");</script>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '</div>';
    echo '<div id=form2 style=display:none class=tab_elt>';
    echo '<table cellspacing=0 cellpadding=0 width=100% >';
    echo '<tr height=35>';
    echo '<td>Achtergrond kleur:&nbsp;</td>';
    echo '<td>';
    echo '  <script>ColorPicker("cell_bgColor", selectedCell.style.backgroundColor);</script>';
    echo '</td>';
    echo '</tr>';
    echo '	<tr height=35>';
    echo '	<td>Minimale breedte:&nbsp;</td>';
    echo '	<td><input type="text" name="cell_width" size="6" maxlength="6" ONKEYPRESS="event.returnValue=IsDigit();" ></td>';
    echo '   	<td nowrap>Percentage t.o.v. de pagina (%)<br>of een vaste waarde in punten (px)</td>';
    echo '	</tr>';
    echo '	<tr height=35>';
    echo '	<td>Horizontale positie inhoud:&nbsp;</td>';
    echo '	<td>';
    echo '	    <script>LayoutPicker( "cell_align", selectedCell.align, "left", "center", "right");</script>';
    echo '	</td>';
    echo '	</tr>';
    echo '	<tr height=35>';
    echo '	<td>Verticale positie inhoud:&nbsp;</td>';
    echo '	<td>';
    echo '	<script>LayoutPicker( "cell_valign", selectedCell.vAlign, "top", "middle", "bottom");</script>';
    echo '	</td>';
    echo '	</tr>';
    echo '<tr height=35><td>Marges:&nbsp;</td>';
    echo '<td><script>PickMargins( "cell_padding", selectedCell.style.paddingTop, selectedCell.style.paddingLeft, selectedCell.style.paddingBottom, selectedCell.style.paddingRight);</script></td>';
    echo '<td>In beeldpunten</td>';
    echo '</tr>';
    echo '	<tr height=35>';
    echo '	<td>Tekst altijd op één regel:&nbsp;</td>';
    echo '	<td><input type=CHECKBOX NAME=nowrap></td>';
    echo '	</tr>';
    echo '</table>';
    echo '</div>';
    echo '</td></tr></table>';
    echo '</form>';
    echo '</div>';
    $myTabs = new LibTabs();
    $myTabs->AddTab();
    $myTabs->AddTab();
    $myTabs->AddTab();
    $myTabs->Render();
    $myTabs = null;
    echo '</body>';
    echo '</html>';
}
// Call main function
main();
?>
