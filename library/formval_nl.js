// Ensure lib_addEvent is available (normally provided by library.js)
if (typeof lib_addEvent === 'undefined') {
	var lib_addEvent = function(elm, evType, fn) {
		elm.addEventListener(evType, fn);
	};
}

// future dev: http://demos.usejquery.com/ketchup-plugin/index.html?db-skill=on&db-skill=
// also nice: http://www.useragentman.com/blog/2010/06/20/visibleif-html5-custom-data-attributes-with-javascript-make-dynamic-interactive-forms/
//
// Form level
// - data-ios-clear
// - data-show-tooltip	doet niets meer. Er zijn geen tooltips naast velden; fouten staan
//			in div.form_errors bovenaan het formulier. Het attribuut staat nog op
//			veel formulieren (het zette het vlaggetje uit) en wordt genegeerd.
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

// Naast strValidationError (die blijft, consumers geven hem door) een lijst met het
// veld erbij. Die is nodig omdat de samenvatting bovenaan het formulier per regel naar
// het bijbehorende veld moet kunnen springen — uit een aan elkaar geplakte HTML-string
// valt dat niet terug te halen. Een fout zonder veld (form_valid_add_error(null, ...))
// staat er ook in, met fld = null, zodat formulier-brede meldingen niet wegvallen.
var arrValidationErrors = [];
var form_errors_classname = 'form_errors';

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
		arrValidationErrors=[];

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
								form_valid_add_error( objfield, '<span class="libval__strong">' + objfield.getAttribute("data-label") + '</span> is niet ' + (objfield.type=='checkbox'?'aangevinkt':'geselecteerd'), (objfield.type=='checkbox' ? 'Veld is niet aangevinkt':'Geen waarde geselecteerd') );
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
			form_field_show_error( objFocus );
			form_field_reveal( objFocus );
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
			form_errors_clear( form );
			if (document.all) {
				for (tel=0;tel<form.length;tel++) {
					objfield=form[tel];
					if (objfield.type=="submit"||objfield.type=="button")  
						objfield.disabled = true;
				} 
			}
			return true;
		} else {
			form_valid_report( strValidationError, pSubmit, form )
			return false;
		}
	} else {
		console.error("Form_valid : invalid form parameter")
	}
}

//
//	Overridable function to report all errors on form-level
//
//	Zet de fouten in een div.form_errors bovenaan het formulier zelf, in plaats van in
//	een dialoog die je moet wegklikken voordat je kunt aanpassen. Elke regel springt naar
//	zijn veld. Zonder formulier (een oudere aanroeper die het derde argument niet
//	meegeeft) valt hij terug op de dialoog, zodat de melding nooit verdwijnt.
//
function form_valid_report( strValidErrors, strSubmitButton, form) {
	if (form && form_errors_render( form )) {
		return;
	}
	lib_alertbox('De volgende velden behoeven nog aandacht:<br><br>'+strValidErrors+'<br>Graag aanpassen en opnieuw op <b>'+strSubmitButton+'</b> drukken.', "Het formulier is niet compleet", "form");
}

//
//	De bestaande div.form_errors van dit formulier, of null.
//
function form_errors_find( form ) {
	if (!form || !form.getElementsByTagName) return null;
	var kandidaten = form.getElementsByTagName('div');
	for (var t=0; t<kandidaten.length; t++) {
		if ((' '+kandidaten[t].className+' ').indexOf(' '+form_errors_classname+' ')>-1) {
			return kandidaten[t];
		}
	}
	return null;
}

//
//	Haal de samenvatting weg (formulier is in orde).
//
function form_errors_clear( form ) {
	var box = form_errors_find( form );
	if (box && box.parentNode) {
		box.parentNode.removeChild( box );
	}
}

//
//	Bouw of verplaats de samenvatting bovenaan het formulier. Geeft de div terug, of
//	null als er niets te melden is (dan is de div ook weg).
//
function form_errors_render( form ) {
	if (!form) return null;

	// Alleen fouten van DIT formulier. arrValidationErrors is per validatieronde leeg
	// gemaakt, maar een veld kan inmiddels hersteld zijn zonder dat de ronde opnieuw
	// liep — daarom telt data-error van het veld, niet wat er ooit in de lijst kwam.
	var regels = [];
	for (var t=0; t<arrValidationErrors.length; t++) {
		var post = arrValidationErrors[t];
		if (post.fld) {
			if (post.fld.form !== form) continue;
			if (!post.fld.getAttribute('data-error')) continue;
		}
		regels.push( post );
	}

	if (!regels.length) {
		form_errors_clear( form );
		return null;
	}

	var box = form_errors_find( form );
	if (!box) {
		box = document.createElement('div');
		box.className = form_errors_classname;
		// role=alert laat een schermlezer de melding voorlezen zodra hij verschijnt.
		box.setAttribute('role', 'alert');
		form.insertBefore( box, form.firstChild );
	}
	while (box.firstChild) box.removeChild( box.firstChild );

	var kop = document.createElement('p');
	kop.className = form_errors_classname + '__intro';
	kop.appendChild( document.createTextNode('De volgende velden behoeven nog aandacht:') );
	box.appendChild( kop );

	var lijst = document.createElement('ul');
	for (var r=0; r<regels.length; r++) {
		lijst.appendChild( form_errors_regel( regels[r] ) );
	}
	box.appendChild( lijst );

	// De div staat bovenaan het formulier; bij een lang formulier of een fout in een
	// andere tab staat dat buiten beeld. Zonder dit lijkt het of er niets gebeurt.
	if (box.scrollIntoView) {
		try { box.scrollIntoView({block: 'nearest'}); } catch(e) { box.scrollIntoView(); }
	}
	return box;
}

//
//	Eén regel in de samenvatting. De fouttekst is HTML (hij bevat een
//	libval__strong-span met de veldnaam), dus die gaat als innerHTML naar binnen.
//
function form_errors_regel( post ) {
	var li = document.createElement('li');
	if (!post.fld) {
		li.innerHTML = post.html;
		return li;
	}
	var a = document.createElement('a');
	a.href = '#';
	a.innerHTML = post.html;
	lib_addEvent( a, 'click', function( e ) {
		if (e && e.preventDefault) e.preventDefault();
		form_field_reveal( post.fld );
		return false;
	});
	li.appendChild( a );
	return li;
}

//
//	Breng een veld in beeld: activeer de tab waar het in zit en zet de focus erop.
//	Werd alleen door form_valid() gedaan; de regels in de samenvatting hebben het net zo
//	hard nodig, want die verwijzen vaak naar een veld in een tab die niet vooraan staat.
//
function form_field_reveal( fld ) {
	if (!fld) return;
	if (typeof jQuery != 'undefined') {
		var objActiveTabPanel = null;
		var cur_elt = fld.parentElement;
		while (cur_elt) {
			// assumption: jQuery tab has element Role set to tab
			if (cur_elt.getAttribute("role")=="tabpanel")
				objActiveTabPanel = cur_elt;
			cur_elt = cur_elt.parentElement;
		}
		if (objActiveTabPanel && objActiveTabPanel.parentElement) {
			try {
				$('#' + objActiveTabPanel.parentElement.id).tabs('select', objActiveTabPanel.id);
			} catch(e) {}
		}
	}
	try { fld.focus(); } catch(e) {}
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
							form_valid_add_error( objfield, '<span class="libval__strong">' + objfield.getAttribute("data-label") + '</span> is niet ' + (objfield.type=='checkbox' ? 'aangevinkt' : 'geselecteerd'), 'Geen waarde ' + (objfield.type=='checkbox' ? 'aangevinkt' : 'geselecteerd'));
							bfld_error = true;
						} else {
							form_valid_add_error( objfield, '');
						}
						break; 
						
					default:
						if (objfield.value=='') {
							form_valid_add_error( objfield, '<span class="libval__strong">'+sNiceField+'</span> is niet ' + (objfield.type=='select-one'?'geselecteerd':'ingevuld'), 'Een waarde is vereist' );
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
					form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> moet '+sFixedLength+' karakters lang zijn','Dit veld moet '+sFixedLength+' karakters lang zijn');
					bfld_error = true;
				}
			}
	
			var sMaxLength = objfield.getAttribute("data-length-max");
			if (sMaxLength) {
				if (objfield.value.length>sMaxLength) {
					form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> mag niet langer zijn dan '+sMaxLength+' karakters','Dit veld moet maximaal '+sMaxLength+' karakters bevatten');
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
							form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> ongeldig ip-adres','Een IP adres mag alleen nummers, punten en ; bevatten');	
							bfld_error = true;
						}
					}
					break; 
					
				case 'number':
					objfield.value = objfield.value.lib_trim_all();
					if (objfield.value.length>0) {
						if (isNaN(objfield.value.replace(",", "."))) {
							form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> mag alleen nummers bevatten','Alleen nummers zijn toegestaan');	
							bfld_error = true;
						}
					}
					break;
					
				case 'huisnummer_met_toevoeging':
					var re = /[0-9]{1,7}[a-zA-Z-]{0,5}$/;
					objfield.value = objfield.value.lib_trim_all();
					if (objfield.value.length>0) {
						if (!re.test( objfield.value)) {
							form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> moet nummers bevatten','Een huisnummer moet nummers bevatten');	
							bfld_error = true;
						}
					}
					break;	
					
				case 'address', 'adres':
					if (objfield.value.length>0) {
						if (!objfield.value.lib_contains_numbers()) {
							form_valid_add_error( objfield, 'bij <span class="libval__strong">' + sNiceField + '</span> ontbreekt het huisnummer','Het huisnummer ontbreekt');
							bfld_error = true;
						}
						if (objfield.value.length>0 && objfield.value.length<3) {
							form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> is geen volledig adres','Geen volledig adres');
							bfld_error = true;
						}
					}
					break;
					
				case 'email':
					objfield.value = objfield.value.lib_trim_all();
					if (objfield.value.length>0) {
						if (!lib_form_valid_email(objfield)) {
							form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> is geen geldig email adres','Ongeldig email adres');
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
								form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> bevat een ongeldige uren-indicatie ' + regs[1],'Ongeldige uur-indicatie'); 
								bfld_error = true;
							} 
						} else { 
							// 24-hour time format 
							if(regs[1] > 23) { 
								form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> bevat een ongeldige uren-indicatie ' + regs[1],'Ongeldige uren-indicatie'); 
								bfld_error = true;
							} 
						} 
						if (regs[2] < 0 || regs[2] > 59) { 
							form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> bevat een ongeldige minuten-indicatie ' + regs[2],'Ongeldige minuten-indicatie'); 
							bfld_error = true;
						} 
						// assure time has right format, adding zero's
						objfield.value = regs[1].toString()+":"+lib_right('0'+regs[2].toString(),2);
						
					} else { 
					
						form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> is een ongeldig tijdformaat (uu:mm)', 'Ongeldige tijd (uu:mm)'); 
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
					
							
					// Een kort ingetikt jaartal eerst uitschrijven, want dat is invoergemak
					// en geen datumvraag: "01-04-26" hoort 2026 te worden voordat er iets
					// over de geldigheid gezegd wordt.
					var re = /^(\d{1,2})-(\d{1,2})-(\d{1,4})$/;
					if(regs = objfield.value.match(re)) {
						var nJaar = parseInt(regs[3],10);
						if (regs[3].length < 4) {
							if (nJaar <= 40) { nJaar += 2000 }
							else if (nJaar < 100) { nJaar += 1900 }
							else { nJaar += 1000 }
							objfield.value = regs[1] + "-" + regs[2] + "-" + nJaar.toString();
						}
					}

					// Vanaf hier beslist lib_datum_ontleed() wat een datum is — dezelfde
					// regels als Date::normalize() op de server, zodat het formulier niets
					// doorlaat wat daar alsnog NULL wordt. Het herkent ook de jjjj-mm-dd
					// die <lib-datepicker> aflevert; die viel hier vroeger buiten het
					// patroon en gold dus altijd als "ongeldig datumformaat".
					var bIsoInvoer = /^\d{4}-/.test(lib_trim(objfield.value));
					var oDatum = lib_datum_ontleed(objfield.value);
					if (!oDatum.geldig) {
						var sReden = {
							'dag':          'bevat een ongeldige dag',
							'maand':        'bevat een ongeldige maand',
							'jaar':         'bevat een ongeldig jaar (1900 t/m 2099)',
							'bestaat niet': 'bevat een datum die niet bestaat'
						}[oDatum.reden] || 'is een ongeldig datumformaat (dd-mm-jjjj)';
						form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> ' + sReden, 'Ongeldige datum');
						bfld_error = true;
					} else {
						// Vergelijken op jjjj-mm-dd: dat sorteert als tekst en date-minimum
						// / date-maximum staan al in die volgorde.
						if (dMinimum != "" && lib_datum_normaliseer(dMinimum) > oDatum.iso) {
							form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> bevat een datum in het verleden', 'Datum in verleden');
							bfld_error = true;
						}
						if (bfld_error == false && dMaximum != "" && lib_datum_normaliseer(dMaximum) < oDatum.iso) {
							form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> bevat een datum te ver in de toekomst', 'Datum te ver in toekomst');
							bfld_error = true;
						}
						// Netjes uitgeschreven terug, in de notatie waarin het veld hem
						// aanleverde: een datumkiezer verwacht zijn eigen jjjj-mm-dd terug.
						objfield.value = bIsoInvoer ? oDatum.iso : lib_datum_nl(oDatum.iso);
					}
					break;
					
				case 'telefoon':
					var re = /(^\+[0-9]{2}|^\+[0-9]{2}\(0\)|^\(\+[0-9]{2}\)\(0\)|^00[0-9]{2}|^0)([0-9]{9}$|[0-9\-\s]{10}$)/;
					// strip spaces, outer and inner
					objfield.value = objfield.value.lib_trim_all();
					if (!re.test( objfield.value)) {
						form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> is een ongeldig telefoonnummer (10 cijfers)', 'Ongeldig telefoonummer'); 
						bfld_error = true;
					};
					break;
					
				case 'telephone':
					var re = /[0-9\-\+\)\(\s]{10,15}?/;
					// strip spaces, outer and inner
					objfield.value = objfield.value.lib_trim_all();
					if (!re.test( objfield.value)) {
						form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> is een ongeldig telefoonnummer', 'Ongeldig telefoonummer'); 
						bfld_error = true;
					};
					break;
					
				case 'postcode':
					var re = /[0-9]{4}\s*[a-zA-Z]{2}$/;
					objfield.value = objfield.value.lib_trim_all();
					if (!re.test( objfield.value)) {
						form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> is een ongeldige postcode', 'Ongeldige postcode'); 
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
						form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> is een ongeldige postcode', 'Ongeldige postcode'); 
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
							form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> is een ongeldig internet adres', 'Ongeldig internet adres'); 
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
						form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> is geen geldige opleidingscode', 'Ongeldige opleidingscode'); 
						bfld_error = true;
					}
					break;					
					
				// Customer specific codes
				case 'rino_big_opleidingscode':
					objfield.value = objfield.value.replace(" ","");
					objfield.value = objfield.value.toUpperCase();
					var re = /(KP|GZ|PT|PJ|NP|KNP|OG)[1-9]{2,4}[A-Z]{0,1}?/;
					if (!re.test( objfield.value)) {
						form_valid_add_error( objfield, '<span class="libval__strong">' + sNiceField + '</span> is geen geldige BIG-opleidingscode', 'Ongeldige BIG-opleidingscode'); 
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
		cShortError = cLongError.replace("<span class='libval__strong'>","").replace("</span>","")
	}
	if (cLongError!="") {
		if (strValidationError.indexOf( cLongError )==-1) {
			strValidationError+=(' - ' + cLongError + '<br/>');
			// Zelfde ontdubbeling als hierboven: dezelfde melding staat er één keer in,
			// ook als meerdere radio's van dezelfde groep hem opleveren.
			arrValidationErrors.push({ fld: fld || null, html: cLongError });
		}
	}
	if (fld) {
		fld.setAttribute( "data-error", cLongError);
		fld.setAttribute( "data-error-short", cShortError);
		// aria-invalid maakt de foutstatus ook zonder kleur kenbaar.
		if (fld.setAttribute) {
			if (cLongError) { fld.setAttribute("aria-invalid", "true"); }
			else { fld.removeAttribute("aria-invalid"); }
		}
		form_field_show_error( fld );
	}
}

//
//	Overridable function to show a field error
//
//	Markeert het veld (klasse invalid, zie library.css). Er wordt GEEN tooltip meer naast
//	het veld gezet: die werd absoluut gepositioneerd met lib_getAbsoluteOffsetLeft/Top, en
//	dat gaat mis zodra het veld in een tab, een scrollende container of een element met
//	offsetWidth 0 zit — dan stond het vlaggetje ergens anders dan bij het veld. Het
//	overzicht staat nu in div.form_errors bovenaan het formulier, waar het niet kan
//	verschuiven en waar in één oogopslag álles staat wat nog moet.
//
function form_field_show_error( fld ) {
	if (!fld) return;
	var cShortError = fld.getAttribute( "data-error-short");
	form_field_set_valid_classname (fld, !(cShortError));
}

//
//	Default handler for field focus
//
//	Toonde het vlaggetje opnieuw zodra je in een fout veld klikte. Zonder deze handler zou
//	het vlaggetje via de achterdeur terugkomen; het veld blijft gemarkeerd en de melding
//	staat in de samenvatting bovenaan.
//
function form_field_focus( e ) {
}

//
//	Default handler for field focus loss
//
//	Herstelt de gebruiker het veld, dan verdwijnt zijn regel meteen uit de samenvatting.
//	Anders zou daar een fout blijven staan die al opgelost is, en dat is precies het soort
//	melding waar niemand meer naar kijkt. Alleen bijwerken als de samenvatting al bestaat:
//	tijdens het invullen van een leeg formulier hoort er nog niets te verschijnen.
//
function form_field_blur( e ) {
	var fld = e.target ? e.target : e.srcElement;
	if (form_valid_field( fld ) ) {
		form_field_set_valid_classname ( fld, true);
		if (fld.removeAttribute) { fld.removeAttribute("aria-invalid"); }
	}
	// Bijwerken of het veld nu goed of fout werd: een fout die tijdens het invullen
	// ontstaat hoort meteen in de lijst te staan, niet pas bij de volgende submit —
	// het veld is dan al rood en de lijst zou dat tegenspreken.
	if (fld.form && form_errors_find( fld.form )) {
		form_errors_render( fld.form );
	}
}


//
//	Default handler for field click (initialised for checkboxes and radiobuttons)
//
function form_field_click( evt ) {
	var elt = document.activeElement ? document.activeElement : evt.currentTarget;
	// clear error
	elt.setAttribute( "data-error-short", "");
	// re-evaluate
	form_valid_field( elt );
	if (elt.form && form_errors_find( elt.form )) {
		form_errors_render( elt.form );
	}
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