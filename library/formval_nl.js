// Ensure lib_addEvent is available (normally provided by library.js)
if (typeof lib_addEvent === 'undefined') {
	var lib_addEvent = function(elm, evType, fn, useCapture) {
		elm.addEventListener(evType, fn, useCapture);
	};
}

// future dev: http://demos.usejquery.com/ketchup-plugin/index.html?db-skill=on&db-skill=
// also nice: http://www.useragentman.com/blog/2010/06/20/visibleif-html5-custom-data-attributes-with-javascript-make-dynamic-interactive-forms/
//
// Form level
// - data-ios-clear
// - data-show-tooltip	if waarde = N dan geen tooltips 
//
// field level custimisation
// - data-validation-type
// - data-required -> N = niet.
// - data-length
// - data-length-max
// - data-errorypos : where to put the error caption horizontally
// 
// data-button-name         Label van de knop waarop gedrukt moet worden
// data-form-init  			Is het form al geinitialiseerd?
// data-label 	   			Gebruikersvriendelijke naam voor het veld
// data-disable-checkmark 	Indien waarde yes, dan geen checkmark tonen
// 
// future, consider using: http://jsfiddle.net/trixta/qTV3g/ 
//
var strValidationError = '';
var fldTooltip= null;	// field the tooltip is shown for

if (window.addEventListener){
	window.addEventListener("load", form_init_all);
} else if (window.attachEvent){
	window.attachEvent("onload", form_init_all);
}

// Eenvoudige validatie, velden die met 'required-' beginnen zijn verplicht (of velden die in het veld Required staan, gescheiden door ; of ,)
// aanroep in form definitie => onsubmit="return form_valid(this)"
function form_valid(form) {
	var tel;
	var objfield;
	var objFocus=null;
	var pSubmit="Verstuur";
	
	if (form){
		
		if (!(form.getAttribute("data-form-init"))) {
			form_init( form );
		}
		// jQuery might not be installed
		try {
			pSubmit    = jQuery(form).find("input[type=submit]").val();
		} 
		catch(e) {}
		if (pSubmit+""=="" || pSubmit+""=="undefined" ) {	
			pSubmit    = 'Verstuur';
		}		
		pClassname = 'forgotten';
		
		strValidationError='';

		for (tel=0;tel<form.length;tel++) {
			
			objfield = form.elements[tel];
			form_set_field_label( objfield );
			if (objfield.type) {
				switch (objfield.type) {
					case 'checkbox':
					case 'radio':
						var reqAttr = (objfield.getAttribute("data-required") || '').toUpperCase();
		if (reqAttr && reqAttr !== 'N' && reqAttr !== 'FALSE' && reqAttr !== '0' && reqAttr !== 'NO') {
							strfieldname = objfield.name;
							if (!form_check_required_radio_checkbox(form,strfieldname)) {
								form_valid_add_error( objfield, '<strong>' + objfield.getAttribute("data-label") + '</strong> is niet ' + (objfield.type=='checkbox'?'aangevinkt':'geselecteerd'), (objfield.type=='checkbox' ? 'Veld is niet aangevinkt':'Geen waarde geselecteerd') );
								if (!objFocus) {objFocus = objfield}
							} else {
								form_valid_add_error( objfield, '','');
							}
							// skip all other instances of this element
							if (tel+1<form.length) {
								while (form[tel].name.toLowerCase()==strfieldname.toLowerCase() && tel+1<form.length) tel++;
							}
							// skip back; als het niet de laatste is...
							if (!(form[tel].name.toLowerCase()==strfieldname.toLowerCase() && tel+1==form.length)) tel--;
						}
						break; 
						
					default:
						if (!(form_valid_field( objfield ))) {
							if (!objFocus) {objFocus = objfield}
						}
					
				}
			}

		}
		
		if (objFocus) {
			if (typeof jQuery != 'undefined') {
				var objActiveTabPanel = null;
				var cur_elt = objFocus.parentElement;
				while (cur_elt) {
					// assumption: jQuery tab has element Role set to tab
					if (cur_elt.getAttribute("role")=="tabpanel")
						objActiveTabPanel = cur_elt;
					cur_elt = cur_elt.parentElement;
				}
				if (objActiveTabPanel) {
					var all_tabs = $('#' + objActiveTabPanel.parentElement.id);
					all_tabs.tabs('select', objActiveTabPanel.id);
					tooltip.fade(-1);
				}
			}
			form_field_show_error( objFocus );
			objFocus.focus();
		}

		
		// custom form level validation indicated by data-validation on the form element		
		var sFormValidate = form.getAttribute("data-validation");
		if (sFormValidate) {
			eval(sFormValidate);
		}
		var sButtonName = form.getAttribute("data-button-name");
		if (sButtonName) {
			pSubmit=sButtonName;
		}

		// process it
		if (strValidationError=='') {
			if (document.all) {
				for (tel=0;tel<form.length;tel++) {
					objfield=form[tel];
					if (objfield.type=="submit"||objfield.type=="button")  
						objfield.disabled = true;
				} 
			}
			return true;
		} else {
			form_valid_report( strValidationError, pSubmit )
			return false;
		}
	} else {
		console.error("Form_valid : invalid form parameter")
	}
}

//
//	Overridable function to report all errors on form-level
//
function form_valid_report( strValidErrors, strSubmitButton) {
	lib_alertbox('De volgende velden behoeven nog aandacht:<br><br>'+strValidErrors+'<br>Graag aanpassen en opnieuw op <b>'+strSubmitButton+'</b> drukken.', "Het formulier is niet compleet", "form");
}

//
//	Validate a single form field
//
function form_valid_field( objfield ) {
	var bfld_error  = false;

	if (objfield.name) {
		strfieldname = objfield.name;		
		sNiceField = objfield.getAttribute("data-label");
		
		// reset error
		// form_valid_add_error( objfield, "", ""); 
		objfield.setAttribute("data-error", "")
		objfield.setAttribute("data-error-short", "")
		
		// trim value
		if (objfield.type=="text") { 
			objfield.value = objfield.value.trim();
		}
		
		form_set_field_label( objfield );
		
		var reqAttr = (objfield.getAttribute("data-required") || '').toUpperCase();
		if (reqAttr && reqAttr !== 'N' && reqAttr !== 'FALSE' && reqAttr !== '0' && reqAttr !== 'NO') {
			if (objfield.type) {
				switch (objfield.type) {
					case 'checkbox':
					case 'radio':
						if (!form_check_required_radio_checkbox(objfield.form,strfieldname)) {
							form_valid_add_error( objfield, '<strong>' + objfield.getAttribute("data-label") + '</strong> is niet ' + (objfield.type=='checkbox' ? 'aangevinkt' : 'geselecteerd'), 'Geen waarde ' + (objfield.type=='checkbox' ? 'aangevinkt' : 'geselecteerd'));
							bfld_error = true;
						} else {
							form_valid_add_error( objfield, '');
						}
						break; 
						
					default:
						if (objfield.value=='') {
							form_valid_add_error( objfield, '<strong>'+sNiceField+'</strong> is niet ' + (objfield.type=='select-one'?'geselecteerd':'ingevuld'), 'Een waarde is vereist' );
							bfld_error = true;
						}
						break;
				}
			}
		}
		
		//?? undefined sometimes pops up as a value!?
		if (!bfld_error && objfield.value!='' && objfield.value!='undefined') {
		
			// see if other validation types are indicated
			var sFixedLength = objfield.getAttribute("data-length");
			if (sFixedLength) {
				if (objfield.value.length!=sFixedLength) {
					form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> moet '+sFixedLength+' karakters lang zijn','Dit veld moet '+sFixedLength+' karakters lang zijn');
					bfld_error = true;
				}
			}
	
			var sMaxLength = objfield.getAttribute("data-length-max");
			if (sMaxLength) {
				if (objfield.value.length>sMaxLength) {
					form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> mag niet langer zijn dan '+sMaxLength+' karakters','Dit veld moet maximaal '+sMaxLength+' karakters bevatten');
					bfld_error = true;
				}
			}
	
			var sValidate = objfield.getAttribute("data-validation-type");
			switch ((sValidate+'').toLowerCase()) {
			
				case 'ip-address':
					var re = /[0-9\.\;]$/;
					objfield.value = objfield.value.lib_trim_all();
					if (objfield.value.length>0) {
						if (!re.test( objfield.value)) {
							form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> ongeldig ip-adres','Een IP adres mag alleen nummers, punten en ; bevatten');	
							bfld_error = true;
						}
					}
					break; 
					
				case 'number':
					objfield.value = objfield.value.lib_trim_all();
					if (objfield.value.length>0) {
						if (isNaN(objfield.value.replace(",", "."))) {
							form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> mag alleen nummers bevatten','Alleen nummers zijn toegestaan');	
							bfld_error = true;
						}
					}
					break;
					
				case 'huisnummer_met_toevoeging':
					var re = /[0-9]{1,7}[a-zA-Z-]{0,5}$/;
					objfield.value = objfield.value.lib_trim_all();
					if (objfield.value.length>0) {
						if (!re.test( objfield.value)) {
							form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> moet nummers bevatten','Een huisnummer moet nummers bevatten');	
							bfld_error = true;
						}
					}
					break;	
					
				case 'address', 'adres':
					if (objfield.value.length>0) {
						if (!objfield.value.lib_contains_numbers()) {
							form_valid_add_error( objfield, 'bij <strong>' + sNiceField + '</strong> ontbreekt het huisnummer','Het huisnummer ontbreekt');
							bfld_error = true;
						}
						if (objfield.value.length>0 && objfield.value.length<3) {
							form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is geen volledig adres','Geen volledig adres');
							bfld_error = true;
						}
					}
					break;
					
				case 'email':
					objfield.value = objfield.value.lib_trim_all();
					if (objfield.value.length>0) {
						if (!lib_form_valid_email(objfield)) {
							form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is geen geldig email adres','Ongeldig email adres');
							bfld_error = true;
						}
					}
					break;
					
				case 'time':
					var re = /^(\d{1,2})$/; 
					if(regs = objfield.value.match(re)) {
						objfield.value=objfield.value+":00";
					}
					var re = /^(\d{1,2}):$/; 
					if(regs = objfield.value.match(re)) {
						objfield.value=objfield.value+"00";
					}
					var re = /^(\d{1,2})(\D{1})1$/; 
					if(regs = objfield.value.match(re)) {
						objfield.value=objfield.value+"5";
					}
					var re = /^(\d{1,2})(\D{1})3$/; 
					if(regs = objfield.value.match(re)) {
						objfield.value=objfield.value+"0";
					}
					var re = /^(\d{1,2})(\D{1})4$/; 
					if(regs = objfield.value.match(re)) {
						objfield.value=objfield.value+"5";
					}
					objfield.value=objfield.value.replace(" ", ":")
					var re = /^(\d{1,2}):(\d{1,2})(:00)?([ap]m)?$/; 
					if(regs = objfield.value.match(re)) { 
						if(regs[4]) { 
							// 12-hour time format with am/pm 
							if(regs[1] < 1 || regs[1] > 12) { 
								form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> bevat een ongeldige uren-indicatie ' + regs[1],'Ongeldige uur-indicatie'); 
								bfld_error = true;
							} 
						} else { 
							// 24-hour time format 
							if(regs[1] > 23) { 
								form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> bevat een ongeldige uren-indicatie ' + regs[1],'Ongeldige uren-indicatie'); 
								bfld_error = true;
							} 
						} 
						if (regs[2] < 0 || regs[2] > 59) { 
							form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> bevat een ongeldige minuten-indicatie ' + regs[2],'Ongeldige minuten-indicatie'); 
							bfld_error = true;
						} 
						// assure time has right format, adding zero's
						objfield.value = regs[1].toString()+":"+lib_right('0'+regs[2].toString(),2);
						
					} else { 
					
						form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is een ongeldig tijdformaat (uu:mm)', 'Ongeldige tijd (uu:mm)'); 
						bfld_error = true;
						
					} 
					break;
					
				case 'datum':
					var currentDate = new Date();
					var tmp 		= objfield.value;				
					var dMaximum 	= (objfield.getAttribute("date-maximum") ? $.trim(objfield.getAttribute("date-maximum")) : "");
					var dMinimum 	= (objfield.getAttribute("date-minimum") ? $.trim(objfield.getAttribute("date-minimum")) : ""); 
				
					var re = /^(\d{8})$/;
					// geen streepjes
					// 01012016
					if(regs = objfield.value.match(re)) {	
						objfield.value = tmp.substring(0,2) + "-" + tmp.substring(2).substring(0,2) + "-" + tmp.substring(2).substring(2)
					}	

					var re = /^(\d{1,2})$/;
					// geen maand/jaar
					// 
					if(regs = objfield.value.match(re)) {
//						console.log (objfield.value.substring(0,2) + "-" + lib_right('0'+currentDate.getMonth().toString()) + "-" + currentDate.getFullYear().toString() );
						objfield.value = objfield.value + "-" + lib_right('0'+(currentDate.getMonth()+1).toString(),2) + "-" + currentDate.getFullYear().toString();
					}
					
					// geen streepjes en geen jaar 
					// 0101
					var re = /^(\d{4})$/;
					if(regs = objfield.value.match(re)) {	
						objfield.value = tmp.substring(0,2) + "-" + tmp.substring(2) + "-" + currentDate.getFullYear().toString()
					}
		

					// regular expression to match required date format (2do: specify the date range in data- fields)
					// hardcoded in dutch format, for english i suggest making a new type: date!
					// 2do: skip entire year!
					var re = /^(\d{1,2})-(\d{1,2})$/; 
					// geen jaar ingevuld, even toevoegen
					if(regs = objfield.value.match(re)) { 
						objfield.value = lib_right('0'+regs[1].toString(),2)+"-"+lib_right('0'+regs[2].toString(),2)+"-"+currentDate.getFullYear().toString()
					}
					
							
					var re = /^(\d{1,2})-(\d{1,2})-(\d{0,4})$/; 
					minYear = 1900;
					maxYear = 2100;
					
					if(regs = objfield.value.match(re)) { 
						if(regs[1] < 1 || regs[1] > 31) { 
							form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> bevat een ongeldige dag ' + regs[1], 'Ongeldig dag'); 
							bfld_error = true;
						} else if(regs[2] < 1 || regs[2] > 12) { 
							form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> bevat een ongeldige maand ' + regs[2], 'Ongeldig maand'); 										
							bfld_error = true;
						} else {
							if (regs[3]<=40) { 
								regs[3]=parseInt(regs[3])+2000 
							}
							if (regs[3]<100) { 
								regs[3]=parseInt(regs[3])+1900 
							}
							if (regs[3]<1000) { 
								regs[3]=parseInt(regs[3])+1000 
							}
							if (regs[3] < minYear || regs[3] > maxYear) { 
								form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> bevat een ongeldig jaar ' + regs[3], 'Ongeldig jaar'); 
								bfld_error = true;
							}

							var chkDate = regs[3] + "/" + regs[2] + "/" + regs[1];
							if (bfld_error == false && dMinimum != "") { 
								var minDate = dMinimum.replace(/-/g,'/');
								if(minDate > chkDate) {
									form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> bevat een datum in het verleden', 'Datum in verleden'); 
									bfld_error = true;
								}
							} 
							if (bfld_error == false && dMaximum != "") { 
								var maxDate = dMaximum.replace(/-/g,'/');
								if(maxDate < chkDate) {
									form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> bevat een datum te ver in de toekomst', 'Datum te ver in toekomst'); 
									bfld_error = true;
								}
							} 

						} 
						// assure date has right format, adding zero's
						objfield.value = lib_right('0'+regs[1].toString(),2)+"-"+lib_right('0'+regs[2].toString(),2)+"-"+regs[3].toString()
					} else { 
						form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is een ongeldig datumformaat (dd-mm-jjjj)', 'Ongeldige datum (dd-mm-jjjj)'); 
						bfld_error = true;
					} 
					break;
					
				case 'telefoon':
					var re = /(^\+[0-9]{2}|^\+[0-9]{2}\(0\)|^\(\+[0-9]{2}\)\(0\)|^00[0-9]{2}|^0)([0-9]{9}$|[0-9\-\s]{10}$)/;
					// strip spaces, outer and inner
					objfield.value = objfield.value.lib_trim_all();
					if (!re.test( objfield.value)) {
						form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is een ongeldig telefoonnummer (10 cijfers)', 'Ongeldig telefoonummer'); 
						bfld_error = true;
					};
					break;
					
				case 'telephone':
					var re = /[0-9\-\+\)\(\s]{10,15}?/;
					// strip spaces, outer and inner
					objfield.value = objfield.value.lib_trim_all();
					if (!re.test( objfield.value)) {
						form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is een ongeldig telefoonnummer', 'Ongeldig telefoonummer'); 
						bfld_error = true;
					};
					break;
					
				case 'postcode':
					var re = /[0-9]{4}\s*[a-zA-Z]{2}$/;
					objfield.value = objfield.value.lib_trim_all();
					if (!re.test( objfield.value)) {
						form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is een ongeldige postcode', 'Ongeldige postcode'); 
						bfld_error = true;
					} else {
						objfield.value = objfield.value.toUpperCase();
					}
					break;
					
				case 'postalcode':
					var re = /[0-9|a-z|A-Z]{4,8}?/;
					// strip spaces, outer and inner
					objfield.value = objfield.value.lib_trim_all();
					if (!re.test( objfield.value)) {
						form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is een ongeldige postcode', 'Ongeldige postcode'); 
						bfld_error = true;
					};
					break;
					
				case 'url':
					if (objfield.value.length>0) {
						if (objfield.value.substring(0,6).toLowerCase()!="tel://" && objfield.value.substring(0,9).toLowerCase()!="mailto://" && objfield.value.substring(0,7).toLowerCase()!="http://" && objfield.value.substring(0,8).toLowerCase()!="https://" ) {
							objfield.value = "https://" + objfield.value 
						}
						objfield.value = objfield.value.replace(" ","%20");
						var re = /(http|ftp|https):\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&amp;:\/~\+#]*[\w\-\@?^=%&amp;\/~\+#])?/;
                        if (!re.test(objfield.value.toLowerCase())) {
							form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is een ongeldig internet adres', 'Ongeldig internet adres'); 
							bfld_error = true;
						}
					}
					break;
					
				// Customer specific codes
				case 'rino_opleidingscode':
					objfield.value = objfield.value.replace(" ","");
					objfield.value = objfield.value.toUpperCase();
					var re = /[A-Z]{1,5}[0-9]{1,4}[A-Z]{0,1}?/;
					if (!re.test( objfield.value)) {
						form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is geen geldige opleidingscode', 'Ongeldige opleidingscode'); 
						bfld_error = true;
					}
					break;					
					
				// Customer specific codes
				case 'rino_big_opleidingscode':
					objfield.value = objfield.value.replace(" ","");
					objfield.value = objfield.value.toUpperCase();
					var re = /(KP|GZ|PT|PJ|NP|KNP|OG)[1-9]{2,4}[A-Z]{0,1}?/;
					if (!re.test( objfield.value)) {
						form_valid_add_error( objfield, '<strong>' + sNiceField + '</strong> is geen geldige BIG-opleidingscode', 'Ongeldige BIG-opleidingscode'); 
						bfld_error = true;
					}
					break;	
			}
		}
	}
	return !bfld_error;
}

//
//	Enkele velds validatie
//
function form_valid_add_error( fld, cLongError, cShortError) {
	if (!cShortError) {
		cShortError = cLongError.replace("<strong>","").replace("</strong>","")
	}
	if (cLongError!="") {
		if (strValidationError.indexOf( cLongError )==-1) {
			strValidationError+=(' - ' + cLongError + '<br/>');
		}
	}
	if (fld) {
		fld.setAttribute( "data-error", cLongError);
		fld.setAttribute( "data-error-short", cShortError);
		form_field_show_error( fld );
	}
}

//
//	Overridable function to show a field error
//
function form_field_show_error( fld ) {
	if (!fld) return;
	var cShortError = fld.getAttribute( "data-error-short");
	var obj_pos = fld;

	form_field_set_valid_classname (fld, !(cShortError));
	if (fld.type=='checkbox' || fld.type=='radio') {
		obj_pos = form_find_suitable_parent(obj_pos);
	} 
	if (cShortError) {
		if (fld.form.getAttribute("data-show-tooltip")!="N") {
			var leftPos = lib_getAbsoluteOffsetLeft(obj_pos);
			if (leftPos==0 && obj_pos.parentElement) {
				leftPos = lib_getAbsoluteOffsetLeft(obj_pos.parentElement);
			}
			leftPos += obj_pos.offsetWidth
			if (fld.getAttribute("data-errorypos")) {
				leftPos = lib_getAbsoluteOffsetLeft(obj_pos) + parseInt( fld.getAttribute("data-errorypos") );
			}
			var nTopPos = lib_getAbsoluteOffsetTop(obj_pos);
			if (nTopPos==0 && obj_pos.parentElement) {
				nTopPos = lib_getAbsoluteOffsetTop(obj_pos.parentElement);
			}
			tooltip.show(cShortError, null, leftPos, nTopPos  );	
			fldTooltip = fld;
		}
	}
}

//
//	Default handler for field focus, shows tooltip if needed
//
function form_field_focus( e ) {
	var fld = e.target ? e.target : e.srcElement;
	var cShortError = fld.getAttribute( "data-error-short");
	if (cShortError) {
		var obj_pos = fld;
		if (fld.type=='checkbox' || fld.type=='radio') {
			obj_pos = form_find_suitable_parent(fld);
		} 
		if (fld.form.getAttribute("data-show-tooltip")!="N") {
			tooltip.show(cShortError, null, lib_getAbsoluteOffsetLeft(obj_pos) + obj_pos.offsetWidth, lib_getAbsoluteOffsetTop(obj_pos) );		
			fldTooltip = fld;
		}
	}
}

//
//	Default handler for field focus loss, always hides tooltip
//
function form_field_blur( e ) {
	var fld = e.target ? e.target : e.srcElement;
	if (form_valid_field( fld ) ) {
		form_field_set_valid_classname ( fld, true);
		if (fldTooltip==fld) { 
			tooltip.fade(-1);
			fldTooltip = null;
		}
	}
}


//
//	Default handler for field click (initialised for checkboxes and radiobuttons)
//
function form_field_click( evt ) {
	var elt = document.activeElement ? document.activeElement : evt.currentTarget;
	// clear error and tooltip
	elt.setAttribute( "data-error-short", "");
	tooltip.fade(-1);
	// re-evaluate
	form_valid_field( elt );
}

function form_init_all(){
	for (var tel=0;tel<document.forms.length;tel++) {
		form_init( document.forms[tel] );
	}
}

//
//	Initialize a single dynamically created field
//	Call this for fields added to the DOM after form_init has run
//
function form_init_field(objfield) {
	if (!objfield || !objfield.name) return;

	// Skip if already initialized
	if (objfield._formvalInit) return;
	objfield._formvalInit = true;

	// Set up field label
	form_set_field_label(objfield);

	// Set up validation-type specific handlers
	var validationType = objfield.getAttribute("data-validation-type");
	if (validationType) {
		switch (validationType.toLowerCase()) {
			case 'number':
				if (typeof isIE !== 'undefined' && isIE && !isIE10) {
					lib_addEvent(objfield, "keydown", lib_form_digitsonly);
				} else {
					objfield.setAttribute("onkeydown", "return lib_form_digitsonly(event);");
				}
				break;
			case 'time':
				if (typeof isIE !== 'undefined' && isIE && !isIE10) {
					lib_addEvent(objfield, "keydown", lib_form_timekey);
				} else {
					objfield.setAttribute("onkeydown", "return lib_form_timekey(event);");
				}
				objfield.setAttribute("maxlength", "5");
				break;
			case 'datum':
				objfield.setAttribute("maxlength", "10");
				break;
		}
	}

	// Set up checkmark disabling for short fields and passwords
	if ((objfield.getAttribute("maxlength") && parseInt(objfield.getAttribute("maxlength")) < 3) || (objfield.type == "password")) {
		objfield.setAttribute("data-disable-checkmark", "yes");
	}

	// Attach blur and focus handlers
	lib_addEvent(objfield, "blur", form_field_blur);
	lib_addEvent(objfield, "focus", form_field_focus);

	// Checkbox/radio specific
	if (objfield.type == 'checkbox' || objfield.type == 'radio') {
		objfield.setAttribute("data-disable-checkmark", "yes");
		lib_addEvent(objfield, "click", form_field_click);
	}
}

//
//	Initialize all fields within a container (for dynamically added content)
//	Call this for containers added to the DOM after form_init has run
//
function form_init_container(container) {
	if (!container) return;

	var inputs = container.querySelectorAll('input, textarea, select');
	for (var i = 0; i < inputs.length; i++) {
		form_init_field(inputs[i]);
	}
}

//
//	Initialises form
//
function form_init( form ) {
	var strfieldname;
	var blnRequired;
	var flds_arr;
	
	if (!form) return;

	//
	//	Bewaar de required indicatie in data-required (oude notaties: hidden veld required en de required- prefix in de naam omzetten)
	//
	var req_fld = null;
	try {
		req_fld = form["required"];
	}
	catch(e){}
	if (req_fld) flds_arr=lib_array_split( req_fld.value );
	
	for (var tel=0;tel<form.length;tel++) {
		blnRequired = false
		objfield = form.elements[tel];
		if (objfield.name) {
			strfieldname = objfield.name;
			
			form_set_field_label( objfield );
			
			// niet het veld required zelf meenemen
			if (strfieldname.substring(0,8).toLowerCase()=='required' && (strfieldname.toLowerCase()!='required') ) {
				blnRequired = true;
			} else {
				if (flds_arr) blnRequired = ( lib_array_find(flds_arr, strfieldname) != -1 )
			} 

			// niet het required veld zelf op required zetten!
			if (blnRequired) {
				objfield.setAttribute("data-required","J");
			}
			if (objfield.getAttribute("data-validation-type")=='number') {
				
				if (isIE && !isIE10) {
					lib_addEvent(objfield, "keydown", lib_form_digitsonly);
				} else {
					objfield.setAttribute("onkeydown", "return lib_form_digitsonly(event);")
				} 
			}
			if (objfield.getAttribute("data-validation-type")=='time') {
				if (isIE && !isIE10) {
					lib_addEvent(objfield, "keydown", lib_form_timekey);
				} else {
					objfield.setAttribute("onkeydown", "return lib_form_timekey(event);")
				} 
				objfield.setAttribute("maxlength", "5")
			}
			if (objfield.getAttribute("data-validation-type")=='datum') {
				objfield.setAttribute("maxlength", "10")				
			}
			
			if ((objfield.getAttribute("maxlength") && parseInt(objfield.getAttribute("maxlength"))<3) || (objfield.type=="password")){
				objfield.setAttribute("data-disable-checkmark","yes");
			}		
			lib_addEvent(objfield, "blur" , form_field_blur);
			lib_addEvent(objfield, "focus", form_field_focus);
			
			if (objfield.type=='checkbox' || objfield.type=='radio') {
				objfield.setAttribute("data-disable-checkmark","yes");
				lib_addEvent(objfield, "click", form_field_click);			
			}
		}
	}

	if (form.getAttribute("data-ios-clear")) {
		var aFlds = form.getElementsByTagName("textarea");
		for (var i = 0; i < aFlds.length; i++) { 
			aFlds[i].style.paddingRight = "20px";
			aFlds[i].supports_clear = true;
			lib_addEvent(aFlds[i], "focus", form_control_focus);
		}
		// 
		var aFlds = form.getElementsByTagName("input");
		for (var i = 0; i < aFlds.length; i++) { 
			// IE: empty defaults to edit field
			aFlds[i].supports_clear = false;
			if (aFlds[i].type=="text" || aFlds[i].type=="") {
				// 2do: skip fields in the hidden required field list
				if ( !( aFlds[i].readOnly || aFlds[i].getAttribute("data-required") || aFlds[i].maxLength<=10 || aFlds[i].name.substring(0,8).toLowerCase()=='required')) {
					aFlds[i].style.paddingRight = "20px";
					aFlds[i].supports_clear = true;
				}
				// 2do call lib_form_digitsonly for numeric fields (dates?)
			}
			lib_addEvent(aFlds[i], "focus", form_control_focus);
		}
		var aFlds = form.getElementsByTagName("select");
		for (var i = 0; i < aFlds.length; i++) { 
			aFlds[i].supports_clear = false;
			lib_addEvent(aFlds[i], "focus", form_control_focus);
		}
	}
	form.setAttribute("data-form-init","J");
}

function form_set_field_label ( objField ) {
	if (!(objField.getAttribute("data-label"))) {
		var theForm = objField.form;
		if (!theForm) return;
		var strfieldname = objField.name;
		if (!strfieldname) return;
		var sNiceField = '';
		var lbl_elt = theForm[strfieldname+'__label'];
		if (lbl_elt) {
			if (lbl_elt.value) {
				sNiceField = lbl_elt.value;
			}
		} 
		if (sNiceField=='') {
			// revert to fieldname
			sNiceField = strfieldname;
			if (strfieldname.substring(0,8).toLowerCase()=='required') {
				sNiceField = sNiceField.substring(8);
			}
			sNiceField=sNiceField.replace(/_/gi, " ");
			sNiceField=sNiceField.replace(/-/gi, "");
		}
		sNiceField = sNiceField.substring(0,1).toUpperCase() + sNiceField.substring(1);
		objField.setAttribute("data-label",sNiceField);
	}
}

function form_field_set_valid_classname ( fld, bValid) {

	if (fld.type!="button" && fld.type!="submit") {
		if (!fld.getAttribute("data-disable-checkmark")) {
			// eventuele oude weg
			if (fld.type=="checkbox" || fld.type=='radio') {
				fld = form_find_suitable_parent(fld);
			}
			fld.className = fld.className.replace( "invalid", "");
			fld.className = fld.className.replace( "valid", "");
			fld.className = fld.className.replace( "  ", " ");
			fld.className = (fld.className ? fld.className + ' ':'') + (bValid ? 'valid' : 'invalid');
		}
	}
}

var lib_clear_elt_name = "lib_clear_editbutton"
//
// 2do: scrolling breaks link between control and clear button
//
function form_create_clearcontrol( elt ) {
	library_clear_control_elt = $(lib_clear_elt_name);
	if (!library_clear_control_elt) {
		library_clear_control_elt = document.getElementsByTagName("body")[0].appendChild(document.createElement("a"));
	}
	library_clear_control_elt.className = "lib_clear_editbutton";
	library_clear_control_elt.id = lib_clear_elt_name;
	library_clear_control_elt.style.top = (lib_getAbsoluteOffsetTop(elt) + 2).toString() + "px";
	library_clear_control_elt.style.left = (lib_getAbsoluteOffsetLeft(elt) + elt.offsetWidth - library_clear_control_elt.offsetWidth - 3).toString() + "px";
	library_clear_control_elt.title = "Maak veld leeg";
	library_clear_control_elt.edit_field = elt;
	lib_addEvent(library_clear_control_elt, "click", form_clear_fieldvalue);
}

// for now, creates the clear control, but named it general for future enhancements (popup help for instance)
// 
function form_control_focus( evt ) {
	var elt = document.activeElement ? document.activeElement : evt.currentTarget;
	if (elt.supports_clear) {
		form_create_clearcontrol( elt );
	} else {
		form_hide_clearcontrol( );
	}
}

function form_hide_clearcontrol( ) {
	var library_clear_control_elt = $(lib_clear_elt_name);
	if (library_clear_control_elt) {
		library_clear_control_elt.style.top="-30px";
	}
}

function form_clear_fieldvalue( elt ) {
	var library_clear_control_elt = $(lib_clear_elt_name);
	var edt = library_clear_control_elt.edit_field;
	if (edt) {	
		edt.value="";
		edt.focus();
	}	
}

function form_check_required_radio_checkbox( frm, rad_name ) {
	var bRet=false;

	for (var t=0;t<frm.length;t++) {
		if (frm[t].name) {
		    if (frm[t].name.toLowerCase()==rad_name.toLowerCase()) {
			    if (frm[t].checked) bRet=true;
			}
		}
	}
	return bRet; 
}

//	Zoekt het formulier bij een invoerveld
//
function form_find_form( elt ) {
	var cur_elt = elt;
	var the_form = null;
	while (cur_elt.parentNode && !the_form ) {
		if (cur_elt.tagName.toLowerCase()=="form") the_form = cur_elt;
		cur_elt = cur_elt.parentNode;
	}
	return the_form;
}

//	Zoekt een parent van een checkbox/radiobutton waarin de layout kan vallen
//
function form_find_suitable_parent( elt ) {
	var the_elt = null;
	var cur_elt = elt;
	while (cur_elt.parentNode && !the_elt ) {
		if (cur_elt.tagName.toLowerCase()=="td" || cur_elt.tagName.toLowerCase()=="div" || cur_elt.tagName.toLowerCase()=="span") the_elt = cur_elt;
		cur_elt = cur_elt.parentNode;
	}
	return the_elt;
}