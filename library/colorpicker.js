// Requires library.js to be included (for cookies support)
//
// Color Picker Script from Flooble.com
// For more information, visit 
//	http://www.flooble.com/scripts/colorpicker.php
// Copyright 2003 Animus Pactum Consulting inc.
// You may use and distribute this code freely, as long as
// you keep this copyright notice and the link to flooble.com
// if you chose to remove them, you must link to the page
// listed above from every web page where you use the color
// picker code.
//
// ---------------------------------------------------------
//
// Highly modified to support RGB input and 9 last used colors stored in cookies
// simply put the following line in your HTML:
// 	 ColorPicker( "picker2", "#f0f0f0" ) 
// this will create a field called picker2 defaulted to the specified color
//
//
//
// Color table based upon http://www.google.com/codesearch?hl=nl&q=show:ZT-D5ILXtRw:NCXKinTtJRY:U7hcN0nEdZ4&sa=N&ct=rd&cs_p=http://www.hannibalworks.net/programs/portalapp.zip&cs_f=innova/scripts/colors.js
 

var RGB = new Array(256);
var k = 0;
var RGB_Used = new Array(9);
var COLORPICKER_Cookie = "js_usedColor";
var hex = Array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');

for (i = 0; i < 16; i++) {
	for (j = 0; j < 16; j++) {
		RGB[k] = hex[i] + hex[j];
		k++;
	}
}

// Fallback cookie reader if lib_readCookie is not available yet
function _colorpicker_readCookie(name) {
	if (typeof lib_readCookie === 'function') {
		return lib_readCookie(name);
	}
	// Fallback: read cookie directly
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for (var i = 0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) === ' ') c = c.substring(1, c.length);
		if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
	}
	return null;
}

for (i = 0; i < 9; i++) {
	RGB_Used[i] = _colorpicker_readCookie(COLORPICKER_Cookie+i.toString())
}


// 	Generic function to switch images, requires names to end with '_on' or '_off' (followed by .gif or .jpg)
//

function img_onoff(id, blnActivate) {
    var img
    if (typeof (id) == 'string') {
        img = my$(id);
    } else {
        img = id;
    } if (img) {
		var strNewImg=img.src;
		img.src = strNewImg.substring(0,strNewImg.lastIndexOf('_')+1)+(blnActivate?'on':'off')+img.src.substring(strNewImg.lastIndexOf('.')); 
	}
}

function RGBconvert(form)
{
    while ((form.red.value > 255) || (form.red.value < 0)) {
        alert("RGB waarden moeten tussen 0 en 255 liggen.")
        form.red.value = 0;
    }

    while ((form.green.value > 255) || (form.green.value < 0)) {
        alert("RGB waarden moeten tussen 0 en 255 liggen.")
        form.green.value = 0;
    }       

    while ((form.blue.value > 255) || (form.blue.value < 0)) {
        alert("RGB waarden moeten tussen 0 en 255 liggen.")
        form.blue.value = 0;
    }

    form.red.value   = lib_dropLeadingZeros(form.red.value);
    form.green.value = lib_dropLeadingZeros(form.green.value);
    form.blue.value  = lib_dropLeadingZeros(form.blue.value);

    if (form.red.value   == "") form.red.value = 0;
    if (form.green.value == "") form.green.value = 0;
    if (form.blue.value  == "") form.blue.value = 0;

    return "#" + RGB[form.red.value] + RGB[form.green.value] + RGB[form.blue.value];   
}

function findRGB( hexcode ) {
	if (hexcode!=null && hexcode!='') {
		for (var t2=0;t2<256;t2++)  {
			if (RGB[t2]==hexcode.toUpperCase()) return t2.toString();
		}
	}
	return '';
}

var perline = 9;
var divSet  = false;
var curId;
var colorLevels = Array('0', '3', '6', '9', 'C', 'F');
var ie = false;
var nocolor = 'none';
var sPlate_Cache = '';

if (document.all) { ie = true; nocolor = ''; }

function drawPlate() {
	var sRetval = '';
	
	if (sPlate_Cache=='') {
		sRetval = "<table cellpadding=0 cellspacing=0 style=\"position:relative;width:164px;height:122px;margin-bottom:4px\"><tr><td>";
			
		arr1 = [["00FF","33FF","66FF","99FF","CCFF","FFFF"],
				["00CC","33CC","66CC","99CC","CCCC"],
				["0099","3399","6699","9999","FFCC"],
				["0066","3366","6666","CC99"],
				["0033","3333","9966","FF99"],
				["0000","6633","CC66"],
				["3300","9933","FF66"],
				["6600","CC33"],
				["9900","FF33"],
				["CC00"],
				["ff00"]];

		arrComp1 = new Array("FF","CC","99");

		for(var i=0;i<arr1.length;i++) {
			sRetval += "<table id='id1"+i+"' cellpadding=0 cellspacing=0 style=\"position:absolute\"><tr>";
			for(var j=0;j<arr1[i].length;j++) {
				for(var k=0;k<arrComp1.length;k++) {
					C=arr1[i][j]+arrComp1[k]+"";
					sRetval += "<td onclick=\"setColor('#"+C+"');\" title=\"#"+C+"\" onmouseover=\"doMouseOver('#"+C+"');\" class=\"colorpicker-cell\" style='cursor:hand;background-color:#"+C+";border:1px solid #"+C+"'><img src=/library/images/filler.gif style=width:7px;height:9px></td>";
				}
			}
			sRetval +="</tr></table>";
		}
			
		arr2 = [["FF00"],
				["CC00"],
				["FF33","9900"],
				["CC33","6600"],
				["FF66","9933","3300"],
				["CC66","6633","0000"],
				["FF99","9966","3333","0033"],
				["CC99","6666","3366","0066"],
				["FFCC","9999","6699","3399","0099"],
				["CCCC","99CC","66CC","33CC","00CC"],
				["FFFF","CCFF","99FF","66FF","33FF","00FF"]]
					
		arrComp1 = new Array("00","33","66");

		for(var i=0;i<arr2.length;i++) {
			sRetval += "<table id='id2"+i+"' cellpadding=0 cellspacing=0 style=\"position:absolute\"><tr>";
			for(var j=0;j<arr2[i].length;j++) {
				for(var k=0;k<arrComp1.length;k++) {
					C = arr2[i][j]+arrComp1[k]+"";
					sRetval += "<td onclick=\"setColor('#"+C+"');\" title=\"#"+C+"\" onmouseover=\"doMouseOver('#"+C+"');\" class=\"colorpicker-cell\" style='cursor:hand;background-color:#"+C+";border:1px solid #"+C+"'><img src=/library/images/filler.gif style=width:7px;height:9px></td>";
				}
			}
			sRetval += "</tr></table>"; 
		}

		sRetval += "</td></tr></table>"; 
		// caching caused problems
		if (ie) sPlate_Cache = sRetval;
	} else {
		sRetval = sPlate_Cache;
	}
	return sRetval;
}
	
function doMouseOver(color) {
//	idPreview.style.backgroundColor=color
//	idPreviewText.innerText=color
//	document.forms.RGB.red.value   = findRGB( color.substr(1,2) );
//	document.forms.RGB.green.value = findRGB( color.substr(3,2) );
//	document.forms.RGB.blue.value  = findRGB( color.substr(5,2) );
}
    
function setColor(color) {
	if (curId) {		
		var link = lib_getObj(curId+'preview');
		var field = lib_getObj(curId);
		var picker = lib_getObj('colorpicker');
		
		if (color) {
			color=color.toUpperCase();
		} else {
			color="";
		}
		field.value = color;

		// store color
		iFound = -1;
		for (i = 0; i<9; i++) {
			if (RGB_Used[i]==color) iFound = i;
		}	
		if (iFound>-1) {
			// if found, swap to first position and move the rest downwards
			for (t=iFound; t>0; t--) {
				RGB_Used[t]=RGB_Used[t-1];
			}
		} else { 
			for (t=8; t>0; t--) {
				RGB_Used[t]=RGB_Used[t-1];
			}
		}
		RGB_Used[0] = color;

		// store array in cookies
		for (i=0;i<9;i++){
			if (RGB_Used[i]!=null && RGB_Used[i]!="") {
				lib_createCookie(COLORPICKER_Cookie+i.toString(), RGB_Used[i], 365)
			}
		}
		if (color == '') {
			link.style.background = nocolor;
			link.style.color = nocolor;
			color = nocolor;
		} else {
			link.style.background = color;
			link.style.color = color;
		}
	//	control_deleteshim( picker.id );
		picker.style.display = 'none';
		eval(lib_getObj(curId).title);
		
		relateColor( curId+'preview', color );
	}
}
    
function setDiv() {     
    if (!document.createElement) { return; }
    var elemDiv = document.createElement('div');
    if (typeof(elemDiv.innerHTML) != 'string') { return; }
    elemDiv.id = 'colorpicker';
	elemDiv.style.position = 'absolute';
    elemDiv.style.display = 'none';
    elemDiv.style.border = '#7F9DB9 1px solid';
    elemDiv.style.background = '#FFFFFF';
	elemDiv.style.padding = '5px';
//    elemDiv.style.filter = 'progid:DXImageTransform.Microsoft.Shadow("color=\'#666666\',Direction=135,Strength=2")';
	elemDiv.style.zIndex = 100;
    elemDiv.innerHTML = getColorTable();
    document.body.appendChild(elemDiv);

    divSet = true;
}
    
function pickColor( id ) {
    if (!divSet) { setDiv(); }
    var picker = lib_getObj('colorpicker');     	
	if (id == curId && picker.style.display == 'block') {
		control_deleteshim( picker.id );
		picker.style.display = 'none';
		return;
	} else {
		picker.innerHTML = getColorTable();
	}

	var nOffsetTop = isIE ? 0 : 0
	var nOffsetLeft = isIE ? 0 : 0
	var nExtraOffset = isIE ? 0 : 0
	
	document.getElementById("id10").style.top=0 + nOffsetTop 
	document.getElementById("id10").style.left=0 + nOffsetLeft 
	document.getElementById("id11").style.top=10 + nOffsetTop 
	document.getElementById("id11").style.left=0 + nOffsetLeft 
	document.getElementById("id12").style.top=20 + nOffsetTop 
	document.getElementById("id12").style.left=0 + nOffsetLeft 
	document.getElementById("id13").style.top=30 + nOffsetTop 
	document.getElementById("id13").style.left=0 + nOffsetLeft 
	document.getElementById("id14").style.top=40 + nOffsetTop 
	document.getElementById("id14").style.left=0 + nOffsetLeft 
	document.getElementById("id15").style.top=50 + nOffsetTop 
	document.getElementById("id15").style.left=0 + nOffsetLeft 
	document.getElementById("id16").style.top=60 + nOffsetTop 
	document.getElementById("id16").style.left=0 + nOffsetLeft 
	document.getElementById("id17").style.top=70 + nOffsetTop 
	document.getElementById("id17").style.left=0 + nOffsetLeft 
	document.getElementById("id18").style.top=80 + nOffsetTop 
	document.getElementById("id18").style.left=0 + nOffsetLeft 
	document.getElementById("id19").style.top=90 + nOffsetTop 
	document.getElementById("id19").style.left=0 + nOffsetLeft 
	document.getElementById("id110").style.top=100 + nOffsetTop 
	document.getElementById("id110").style.left=0 + nOffsetLeft 
		
	document.getElementById("id20").style.top=0+10+nExtraOffset + nOffsetTop 
	document.getElementById("id20").style.left=135+nExtraOffset + nOffsetLeft 
	document.getElementById("id21").style.top=10+10+nExtraOffset + nOffsetTop 
	document.getElementById("id21").style.left=135+nExtraOffset + nOffsetLeft 
	document.getElementById("id22").style.top=20+10+nExtraOffset + nOffsetTop 
	document.getElementById("id22").style.left=108+nExtraOffset + nOffsetLeft 
	document.getElementById("id23").style.top=30+10+nExtraOffset + nOffsetTop 
	document.getElementById("id23").style.left=108+nExtraOffset + nOffsetLeft 
	document.getElementById("id24").style.top=40+10+nExtraOffset + nOffsetTop 
	document.getElementById("id24").style.left=81+nExtraOffset + nOffsetLeft 
	document.getElementById("id25").style.top=50+10+nExtraOffset + nOffsetTop 
	document.getElementById("id25").style.left=81+nExtraOffset + nOffsetLeft 
	document.getElementById("id26").style.top=60+10+nExtraOffset + nOffsetTop 
	document.getElementById("id26").style.left=54+nExtraOffset + nOffsetLeft 
	document.getElementById("id27").style.top=70+10+nExtraOffset + nOffsetTop 
	document.getElementById("id27").style.left=54+nExtraOffset + nOffsetLeft 
	document.getElementById("id28").style.top=80+10+nExtraOffset + nOffsetTop 
	document.getElementById("id28").style.left=27+nExtraOffset + nOffsetLeft 
	document.getElementById("id29").style.top=90+10+nExtraOffset + nOffsetTop 
	document.getElementById("id29").style.left=27+nExtraOffset + nOffsetLeft 
	document.getElementById("id210").style.top=100+10+nExtraOffset	 + nOffsetTop 
	document.getElementById("id210").style.left=0+nExtraOffset + nOffsetLeft 


    curId = id;
    var thelink = lib_getObj(id+'preview');
    picker.style.top = lib_getAbsoluteOffsetTop(thelink) + 16;
    picker.style.left = lib_getAbsoluteOffsetLeft(thelink) - 2;     
	picker.style.display = 'block';

	// set initial RGB values #123456
	document.forms.RGB.red.value   = findRGB( lib_getObj(curId).value.substr(1,2) );
	document.forms.RGB.green.value = findRGB( lib_getObj(curId).value.substr(3,2) );
	document.forms.RGB.blue.value  = findRGB( lib_getObj(curId).value.substr(5,2) );

	control_createshim( picker.id );
}

function getColorTable() {
    var tableCode = '<span style=font-family:Verdana;font-size:var(--font-size-2xs)>'
	// RGB_Used
	if (RGB_Used[0]!=null) {
		tableCode += 'Eerder gekozen kleuren:<br><table cellspacing=1 cellpadding=1 style=margin-bottom:4px>';
        for (i = 0; i<9; i++) {
			if (RGB_Used[i]!=null && RGB_Used[i]!='') {
				tableCode += '<td bgcolor='+RGB_Used[i]+' style="font-size:1px;cursor:hand;height:13px;width:13px;border:1px solid #7F9DB9" title="' 
        		      		+ RGB_Used[i] + '" onclick=setColor("' + RGB_Used[i] + '")>&nbsp;</td>'
			}
		}
		tableCode += '</table>';
	}
    tableCode += '</table>Standaard kleuren:<br>';
    tableCode += drawPlate();
	tableCode += '<button onclick="javascript:setColor(\'\');" style=font-family:Verdana;font-size:9px;margin-bottom:4px>Geen kleur</button><BR>';
	tableCode += 'RGB:<BR><form style=margin:0px name=RGB>R<input name=red maxlength=3 size=3 style=font-family:Verdana;font-size:9px;margin-left:3px;margin-right:6px>G<input name=green maxlength=3 size=3 style=font-family:Verdana;font-size:9px;margin-left:3px;margin-right:6px>B<input name=blue maxlength=3 size=3 style=font-family:Verdana;font-size:9px;margin-left:3px;margin-right:6px><input type=button onclick=setColor(RGBconvert(document.forms.RGB)) value=Ok style=font-family:Verdana;font-size:9px></form></span>';
   	return tableCode;
}

function relateColor(id, color) {
    var link = lib_getObj(id);
    
	link.style.border = '1px #7F9DB9 ' + (color == ''?'dotted':'solid');
    if (color == '') {
	    link.style.background = nocolor;
	    link.style.color = nocolor;
	    color = nocolor;
    } else {
	    link.style.background = color;
	    link.style.color = color;
	}
	eval(lib_getObj(id).title);
}


function ColorPicker(name, def_value ) {
	if (def_value==null) def_value="";
	document.write('<table cellpadding=0 cellspacing=0 style="height:17px;border:1px solid #7F9DB9;padding-right:1px"><tr><td style=height:15px;padding:1px><a href=\"javascript:pickColor(\'' + name + '\')\" id=' + name + 'preview style=\"height:15px;width:15px;border:1px '+(def_value==""?'dotted':'solid')+' #7F9DB9;margin-left:1px;background-color:'+def_value+'\"><img src=/library/images/filler.gif style=width:15px;height:15px;border:0px></a></td><td style=height:15px><input name=' + name + ' id=' + name + ' value=\"'+def_value+'\" size=7 style=border:0px;width:68px;font-size:var(--font-size-xs) maxlength=7 onChange=\"relateColor(\''+name+'preview\',this.value)\"></td><td><img onclick=\"pickColor(\'' + name + '\')\" id='+name+'_listimg onmouseout=img_onoff("'+name+'_listimg",false) onmouseover=img_onoff("'+name+'_listimg",true) src=/library/images/list_down_off.gif style=height:15px;width:15px;border:0px></td></tr></table>');
}