// Layout controls for misc dialogs in the Content Manager

// possible Call: LayoutPicker( "alignment", sDefaultValue, "left", "center", "right") 
//
// the icons are automatically called based upon the specified alignment option
function LayoutPicker( ) {
   var sName = arguments[0];
   var sDefault = arguments[1];

   if (sName==null || sDefault==null){ alert("Layoutpicker: parameter error, at least specify name and default value");}
     
   document.write ("<input type=hidden id=\"" + sName + "\" name=\"" + sName + "\">");
   document.write ("<table cellpadding=0 cellspacing=0 style=\"border:1px solid #7F9DB9\"><tr valign=top>" );
   var arg_count=2;
   while (arguments[arg_count]!=null) {
	 document.write ("<td class=toggle_img_"+(sDefault.toLowerCase()==arguments[arg_count].toLowerCase()?"on":"off")+" id="+sName+"_"+arguments[arg_count]+">");
	 document.write ("<img src=/library/images/layoutpicker_" + arguments[arg_count] + ".gif width=23 height=22 style=\"cursor:pointer\" onclick=javascript:layout_switch('"+sName+"','"+arguments[arg_count]+"')></td>");
	 arg_count++;
   }
   document.write ("</tr></table>");
   layout_switch( sName, sDefault);
}

function layout_switch( sName, sTo) {
   var sOld = lib_form_findfield( sName );
   
 //  if (sTo=="middle") {sTo="center"} ;
   
   if (sOld) {
       if (sOld.value!="") {
	     x = document.getElementById( sName+'_'+sOld.value );
		 if (x) {
			x.className = 'toggle_img_off';
		 }
       }
       if (sTo==sOld.value) sTo="";
   }
   if (sTo!="") {
		x = document.getElementById( sName+'_'+sTo );
		if (x) {
			x.className = 'toggle_img_on';
		}
   }
   if (sOld) sOld.value = sTo;
}

function PickMarginWrite (name, sdef_value) {
  var sVal = sdef_value;
  sVal = sVal.replace('pt','').replace('px','').replace('%','');
  document.write("<input size=2 style=width:20px maxlength=2 ")
  if (window.IsDigit) 
	 document.write ( " ONKEYPRESS=\"event.returnValue=IsDigit()\"" )
  else
	 document.write ( " ONKEYPRESS=\"event.returnValue=lib_form_digitsonly()\"" );
  document.write (" name="+name+" id="+name+" value=\""+sVal+"\">");
}

function PickMargins( name, t, l, b, r ) {
  document.write ( "<table cellpadding=0 cellspacing=2>");
  document.write ( "<tr><td rowspan=3>");
  PickMarginWrite( name+"_l", l);
  document.write ( "</td><td>");
  PickMarginWrite( name+"_t", t);
  document.write ( "</td><td rowspan=3>");
  PickMarginWrite( name+"_r", r);
  document.write ( "</td></tr>");
  document.write ( "<tr><td>&nbsp;</td></tr>");
  document.write ( "<tr><td>");
  PickMarginWrite( name+"_b", b);
  document.write ( "</td></tr>");
  document.write ( "</table>");
}