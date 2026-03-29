(function() {
'use strict';

//
//	2DO : empty block: no remove element
//		  image select
//
var all_components = null;
var htmls = [];
var element_cnt = 0;
var pendingCKEditors = [];

var BLOCK_START = "<!--BLOCK"
var BLOCK_END = "-->"
// Content blocks JSON location - in site/assets (shared with front-end)
var BLOCK_DEFINITION_URL = "/cma/assets/contentblocks/contentblocks.json?v=" + (window.CMA_CACHE_VERSION || Date.now());
var BLOCK_START_HTML = "<div class=\"row\">"
var BLOCK_END_HTML = "</div>"

var CKEDITOR_ForceRecreate = true;
var CKEDITOR_DestroyNeeded = true;

function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.substr(1);
}

/**
 * Clear all blockedit containers - remove all block elements
 * Call this when switching to a new record or clearing the form
 */
function blockedit_clear() {
    jQuery(".blockedit").each(function() {
        // Remove all blockedit_block elements (the content blocks)
        jQuery(this).find('.blockedit_block').remove();
    });
    // Reset element counter
    element_cnt = 0;
    // Clear htmls array
    htmls = [];
    // Clear pending CKEditors array
    pendingCKEditors = [];
    // cmaLog.log('[BlockEdit] Cleared all blockedit containers');
}

//
//
//
function blockedit_init() {
	// Check if there are any .blockedit containers BEFORE loading contentblocks.json
	// This avoids unnecessary network requests for ~97% of forms that don't use blockedit
	var blockeditContainers = jQuery(".blockedit");
	if (blockeditContainers.length === 0) {
		return;
	}

	if (!all_components) {
		jQuery.ajax( {
			url: BLOCK_DEFINITION_URL,
			cache: true
		} ).done( function( result ) {
			if (typeof result==="object") {
				all_components = result;
			} else {
				if (result) {
					try {
						all_components = jQuery.parseJSON( result );
					} catch (e) {
						cmaLog.error('[BlockEdit] Failed to parse block definitions:', e.message);
						return;
					}
				}
			}
			blockedit_init_elements();
		}).fail(function(jqXHR, textStatus, errorThrown) {
			cmaLog.error('[BlockEdit] Failed to load block definitions:', textStatus, errorThrown, 'status:', jqXHR.status);
		});
	} else {
		blockedit_init_elements();
	}
}

//
//
//
function blockedit_init_elements() {

	if (all_components) {
		var blockeditContainers = jQuery(".blockedit");
		cmaLog.log('[blockedit_init_elements] Found .blockedit containers:', blockeditContainers.length);

		blockeditContainers.each( function(index) {
			// get the current HTML
			var sFld = jQuery(this).attr("data-field");
			// cmaLog.log('[blockedit_init_elements] Container[' + index + '] data-field:', sFld);

			if (sFld!="") {
				window.setTimeout( function() {
//					jQuery("#cke_" + sFld).css("height","0px");
				}, 700);
				var textarea = jQuery("textarea[name='" + sFld + "']");
				var sAllHTML = textarea.val();
				// cmaLog.log('[blockedit_init_elements] Textarea found:', textarea.length > 0, 'value length:', sAllHTML ? sAllHTML.length : 0);

				if (sAllHTML!="") {
					var hasBlockStart = sAllHTML.indexOf(BLOCK_START) >= 0;
					// cmaLog.log('[blockedit_init_elements] Has BLOCK_START marker:', hasBlockStart);

					var arrBlocks = sAllHTML.split(BLOCK_START);
					if ( !hasBlockStart ) {
						// cmaLog.log('[blockedit_init_elements] No blocks found, wrapping in Body block');
						sAllHTML = sAllHTML.replace(/<script>/ig, "").replace(/<\/script>/ig,"");
						sAllHTML = BLOCK_START + '{"type":"Body", "variables":{"body_text":' + JSON.stringify(sAllHTML) + '},"visible":true}' + BLOCK_END + sAllHTML;
						arrBlocks = sAllHTML.split(BLOCK_START);
					}
					// cmaLog.log('[blockedit_init_elements] Block count:', arrBlocks.length);

					if (arrBlocks.length>0) {
						for (var block=0; block<arrBlocks.length; block++) {
							if (arrBlocks[block]!="") {
								var arrElts = arrBlocks[block].split(BLOCK_END);
								var cJSONRaw = arrElts[0];
								if (cJSONRaw) {
									// cmaLog.log('[blockedit_init_elements] Parsing block[' + block + '], JSON length:', cJSONRaw.length);
									var parsed = my_parse( cJSONRaw );
									// cmaLog.log('[blockedit_init_elements] Block[' + block + '] parsed result:', parsed ? parsed.type : 'null');
									blockedit_add_new_element( jQuery(this), parsed );
								}
							}
						}
					}
				} else {
					// cmaLog.log('[blockedit_init_elements] Textarea is empty');
				}
			} else {
				// cmaLog.log('[blockedit_init_elements] Container has no data-field attribute');
			}
			// create elements with JSONencoded data

			// create an block for creating new elements
			// cmaLog.log('[blockedit_init_elements] Adding empty element selector');
			blockedit_add_new_element( jQuery(this), null );
		});

		// Process any pending CKEditors after page fully renders
		setTimeout(function() {
			// cmaLog.log('[blockedit_init_elements] Post-init check for pending CKEditors');
			blockedit_process_pending_ckeditors();
		}, 300);

		// cmaLog.log('[blockedit_init_elements] EXIT - processed', blockeditContainers.length, 'containers');
	} else {
		cmaLog.error('[blockedit_init_elements] EXIT - all_components is null/undefined');
	}
}
 
//
//
//
function my_parse( cJSONString ) {
	if (!cJSONString || typeof cJSONString !== 'string') {
		return null;
	}

	// Trim whitespace
	cJSONString = cJSONString.trim();

	// Check if it looks like JSON (starts with { or [)
	if (!cJSONString.startsWith('{') && !cJSONString.startsWith('[')) {
		cmaLog.warn('[BlockEdit] my_parse: Content is not JSON, skipping:', cJSONString.substring(0, 50) + '...');
		return null;
	}

	// Sanitize JSON string before parsing
	var originalLength = cJSONString.length;
	cJSONString = cJSONString
		// Remove CKEditor-specific attributes that contain problematic escape sequences
		// Handle malformed cases: data-cke-saved-href=\ or data-cke-saved-href=\\ followed by space
		.replace(/\s*data-cke-saved-[a-z]+=\\*\s/gi, ' ')
		// Handle proper cases: data-cke-saved-href=\"...\" or data-cke-saved-src=\"...\"
		.replace(/\s*data-cke-saved-[a-z]+=(\\"|")[^"\\]*(\\"|")/gi, '')
		// Remove control characters that break JSON parsing
		.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
	if (cJSONString.length !== originalLength) {
		// cmaLog.log('[BlockEdit] Sanitized JSON: removed ' + (originalLength - cJSONString.length) + ' chars');
	}
	cJSONString = cJSONString
		// Normalize line endings
		.replace(/\r\n/g, '\\n')
		.replace(/\r/g, '\\n')
		.replace(/\n/g, '\\n')
		.replace(/\t/g, '\\t')
		// Fix invalid escape sequences: \X where X is not a valid JSON escape char
		// Valid escapes are: " \ / b f n r t u
		.replace(/\\(?!["\\/bfnrtu])/g, '\\\\');

	// Attempt to repair corrupted JSON (encoding corruption can lose closing chars)
	function attemptRepair(str) {
		var trimmed = str.trim();

		// Remove ALL U+FFFD replacement characters (corruption markers) anywhere in string
		var fffdCount = (trimmed.match(/\uFFFD/g) || []).length;
		if (fffdCount > 0) {
			cmaLog.warn('[BlockEdit] Found ' + fffdCount + ' U+FFFD chars - removing them');
			trimmed = trimmed.replace(/\uFFFD/g, '');
		}

		// Count open/close braces to determine how many we need to add
		var openBraces = 0, closeBraces = 0;
		var openBrackets = 0, closeBrackets = 0;
		var inString = false;
		var prevChar = '';
		var lastStringStart = -1;
		var lastCommaOutsideString = -1;

		for (var i = 0; i < trimmed.length; i++) {
			var c = trimmed.charAt(i);
			if (c === '"' && prevChar !== '\\') {
				if (!inString) lastStringStart = i;
				inString = !inString;
			} else if (!inString) {
				if (c === '{') openBraces++;
				else if (c === '}') closeBraces++;
				else if (c === '[') openBrackets++;
				else if (c === ']') closeBrackets++;
				else if (c === ',') lastCommaOutsideString = i;
			}
			prevChar = c;
		}

		var missingBraces = openBraces - closeBraces;
		var missingBrackets = openBrackets - closeBrackets;

		// cmaLog.log('[BlockEdit] Brace/bracket count: {=' + openBraces + '/' + closeBraces +
		//     ' [=' + openBrackets + '/' + closeBrackets +
		//     ' inString=' + inString + ' lastComma=' + lastCommaOutsideString);

		// If we're in the middle of a string, close it first
		if (inString) {
			trimmed += '"';
			// cmaLog.log('[BlockEdit] Added closing quote');
		}

		// Add missing closing braces/brackets
		for (var j = 0; j < missingBrackets; j++) {
			trimmed += ']';
		}
		for (var k = 0; k < missingBraces; k++) {
			trimmed += '}';
		}

		if (missingBraces > 0 || missingBrackets > 0 || inString || fffdCount > 0) {
			// cmaLog.log('[BlockEdit] Repaired JSON, added: ' + (inString ? '"' : '') +
			//     ']'.repeat(missingBrackets) + '}'.repeat(missingBraces));
		}

		return trimmed;
	}

	// Try more aggressive repair by truncating at last valid comma
	function attemptAggressiveRepair(str) {
		var trimmed = str.trim();

		// Remove all U+FFFD
		trimmed = trimmed.replace(/\uFFFD/g, '');

		// Find the last position where we have a complete value followed by comma or close
		var lastValidPos = -1;
		var inString = false;
		var prevChar = '';
		var braceDepth = 0;
		var bracketDepth = 0;

		for (var i = 0; i < trimmed.length; i++) {
			var c = trimmed.charAt(i);
			if (c === '"' && prevChar !== '\\') {
				inString = !inString;
			} else if (!inString) {
				if (c === '{') braceDepth++;
				else if (c === '}') {
					braceDepth--;
					// After closing brace outside arrays, this is a valid point
					if (braceDepth >= 0 && bracketDepth === 0) lastValidPos = i;
				}
				else if (c === '[') bracketDepth++;
				else if (c === ']') {
					bracketDepth--;
					if (bracketDepth >= 0) lastValidPos = i;
				}
				else if (c === ',') {
					// Comma is a good truncation point - complete element before it
					if (braceDepth >= 0 && bracketDepth >= 0) lastValidPos = i - 1;
				}
			}
			prevChar = c;
		}

		// cmaLog.log('[BlockEdit] Aggressive repair: lastValidPos=' + lastValidPos + ' of ' + trimmed.length);

		// If we found a valid truncation point before the end, try truncating there
		if (lastValidPos > 0 && lastValidPos < trimmed.length - 10) {
			// Try truncating just before what looks like a corrupted element
			// Find the last complete element by looking for ", before the corruption
			var truncated = trimmed.substring(0, lastValidPos + 1);

			// Recount braces/brackets for the truncated string
			var openBraces = 0, closeBraces = 0;
			var openBrackets = 0, closeBrackets = 0;
			inString = false;
			prevChar = '';

			for (var j = 0; j < truncated.length; j++) {
				var ch = truncated.charAt(j);
				if (ch === '"' && prevChar !== '\\') {
					inString = !inString;
				} else if (!inString) {
					if (ch === '{') openBraces++;
					else if (ch === '}') closeBraces++;
					else if (ch === '[') openBrackets++;
					else if (ch === ']') closeBrackets++;
				}
				prevChar = ch;
			}

			// Close the truncated string properly
			for (var m = 0; m < (openBrackets - closeBrackets); m++) truncated += ']';
			for (var n = 0; n < (openBraces - closeBraces); n++) truncated += '}';

			// cmaLog.log('[BlockEdit] Aggressive: truncated to ' + truncated.length + ' chars');
			return truncated;
		}

		return null; // Aggressive repair not possible
	}

	try {
		var result = JSON.parse(cJSONString);
		// cmaLog.log('[BlockEdit] my_parse SUCCESS: type=' + (result ? result.type : 'null') + ', keys=' + (result ? Object.keys(result).join(',') : 'none'));
		return result;
	}
	catch(e){
		// First parse failed - try basic repair for corrupted JSON
		var repaired = attemptRepair(cJSONString);
		if (repaired !== cJSONString) {
			try {
				var result = JSON.parse(repaired);
				cmaLog.warn('[BlockEdit] my_parse RECOVERED after basic repair: type=' + (result ? result.type : 'null'));
				return result;
			} catch (e2) {
				// Basic repair didn't help - try aggressive repair
				cmaLog.warn('[BlockEdit] Basic repair failed:', e2.message);
			}
		}

		// Try aggressive repair (truncate at last valid position)
		var aggressive = attemptAggressiveRepair(cJSONString);
		if (aggressive) {
			try {
				var result = JSON.parse(aggressive);
				cmaLog.warn('[BlockEdit] my_parse RECOVERED after aggressive repair: type=' + (result ? result.type : 'null'));
				return result;
			} catch (e3) {
				cmaLog.warn('[BlockEdit] Aggressive repair also failed:', e3.message);
			}
		}

		// Extract position from error message for debugging
		var posMatch = e.message.match(/position\s+(\d+)/i);
		var errorContext = ' | String length: ' + cJSONString.length;

		if (posMatch) {
			var pos = parseInt(posMatch[1], 10);
			var start = Math.max(0, pos - 30);
			var end = Math.min(cJSONString.length, pos + 30);
			var snippet = cJSONString.substring(start, end);
			var charCode = pos < cJSONString.length ? cJSONString.charCodeAt(pos) : -1;
			errorContext += ' | Around pos ' + pos + ': "...' + snippet + '..."';
			errorContext += ' | Char code: ' + charCode;

			// Check for truncation (unterminated string)
			if (e.message.indexOf('Unterminated') >= 0 || charCode === -1) {
				errorContext += ' | TRUNCATED - last 50 chars: "...' + cJSONString.substring(cJSONString.length - 50) + '"';
				// Check if it ends with proper JSON closing
				var trimmed = cJSONString.trim();
				if (!trimmed.endsWith('}') && !trimmed.endsWith(']')) {
					errorContext += ' | Missing closing brace/bracket';
				}
			}
		}

		cmaLog.error('[BlockEdit] my_parse:', e.message + errorContext);
		return null;
	}
}

//
//	Create a single field ( RECURSIVE !)
// 
function blockedit_createfield( template, key, data_block) { 
	var sNewField = ""
	var attrObj   = template.variables[key];
	var sValue 	  = "";
	var sMaxLength = "";
	
	if (data_block) {
		try {
			sValue = data_block[key];
			if (!sValue) { sValue = string_JSON_fetch( data_block.variables[key] ) }
		}
		catch(e) {
			cmaLog.warn('[BlockEdit] Failed to extract field value for key:', key, e.message);
		}
	}
	if (!sValue) { sValue=""}
	var sType = attrObj.type.toLowerCase();
	sNewField += "<tr class='form_elt'>";
	sNewField += "<td class='label" + (attrObj.required ? " required" : "") + "'>" + ucfirst(attrObj.description) + "</td>";
	sNewField += "<td class='field' data-elt='" + key + "'>";
// 	sRequired = ( attrObj.required ? (data_block ? " data-required='Y'" : "required='J'") : "");
	var sRequired = "";
	sMaxLength = (attrObj.maxlength ?  " maxlength=" + attrObj.maxlength + " ": "");

	element_cnt++;
	if (!attrObj.control_id) {
		attrObj.control_id = "blockedit_" + element_cnt.toString() + "_" + sType + "_" + key + "_" + Date.now().toString();
	}
	var id = attrObj.control_id;
	
	switch (sType) { 
	
		case "longtext":
			id = "blockedit_" + element_cnt.toString() + "_" + sType + "_" + key + "_" + Date.now().toString();
			sNewField += "<textarea " + sRequired + sMaxLength + " id='" + id + "' name='" + id + "' data-value='" + key + "'>" + sValue + "</textarea>";
			htmls.push( id );
			break;
			
		case "text":
			sNewField += "<input " + sRequired + sMaxLength + " value=\"" + sValue + "\" id='" + id + "' type='text' name='" + key + "'>";
			break;
			
		case "url":
			sNewField +=  "<input " + sRequired + sMaxLength + " value=\"" + sValue + "\" id='url_" + id + "' data-validation-type='url' type='text' name='" + key + "'>";
			break;
			
		case "image":				
			var sControlName = "blockedit_image" + element_cnt.toString() + "_" + sType + "_" + key + "_" + Date.now().toString();
			// var sControlName = "image" + id; 				
			sNewField += "<img align=top name=" + id + "_preview id=" + sControlName + "_preview src='"
			if (sValue=="") { 
				sNewField += "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
			} else { 
				sNewField += sValue
			}
			sNewField += "' border=1 style=\"max-width:120px;max-height:120px\"></a>" 
			sNewField += "<input type=\"hidden\" value='" + sValue + "' " + sRequired + " id='" + sControlName + "' name='" + key + "'>";
			sNewField += "<input type=\"hidden\" id='" + sControlName + "_height' name='" + key + "_height'>";
			sNewField += "<input type=\"hidden\" id='" + sControlName + "_width' name='" + key + "_width'>";
			sNewField += "<a style=\"cursor:pointer\" onclick=\"return blockedit_image_select('" + sControlName + "',0, "+ (attrObj.maxheight ? attrObj.maxheight : 456).toString() + ", " +( attrObj.maxwidth ? attrObj.maxwidth : 948).toString() + ")\">[Selecteer]</a>"
			sNewField += "<a style=\"cursor:pointer\" onclick=\"javascript:blockedit_image_clear('" + sControlName + "')\">[wis]</a>"
			sNewField += "&nbsp; (max afm. h " + (attrObj.maxheight ? attrObj.maxheight : 456).toString() + " x b " +( attrObj.maxwidth ? attrObj.maxwidth : 948) + ")";
			break;

		case "file":				
			var sControlName = "file" + element_cnt.toString() + "_" + sType + "_" + key + "_" + Date.now().toString();		
			sNewField += "<input type=\"hidden\" value='" + sValue + "' " + sRequired + " id='" + sControlName + "' name='" + key + "'>";
			sNewField += "<a style=\"cursor:pointer\" onclick=\"return blockedit_file_select('" + sControlName + "')\">[Selecteer bestand]</a>"
			sNewField += "<a style=\"cursor:pointer\" onclick=\"javascript:blockedit_image_clear('" + sControlName + "')\">[wis]</a>"
			break;	
			
		case "date":
			sNewField += "<input " + sRequired + " value='" + sValue + "' id='text_" + id + "' type='text' data-validation-type='date' name='" + key + "'>";
			break;

		case "boolean":
			sNewField += "<div class=\"radiocontrolgroup\">" + sValue + 
						 "<input " + (sValue=="J" ? "checked": "") + " " + sRequired + " type=radio value=J id='radio" + id + "_J' name='" + key + "'><label class=left for='radio" + id + "_J'>Ja</label>" +  
						 "<input " + (sValue=="N" ? "checked": "") + " " + sRequired + " type=radio value=N id='radio" + id + "_N' name='" + key + "'><label class=right for='radio" + id + "_N'>Nee</label>"+
						 "</div>";
			break;
			
		case "switch":
			var sControlName = "switch_" + element_cnt.toString() + "_" + sType + "_" + key + "_" + Date.now().toString();		
			sNewField += "<div class=\"radiocontrolgroup\">";
			for (var opt=0;opt<attrObj.options.length;opt++) {
				sNewField += "<input " + sRequired + " type=radio " + (sValue.toLowerCase()==attrObj.options[opt].toLowerCase() ? "checked": "") + " value=" + attrObj.options[opt] + " id='" + sControlName + "_" + opt + "' name='" + sControlName + "'><label for='" + sControlName + "_" + opt + "' " + ( opt==0 ? "class=left" : ( opt==(attrObj.options.length-1) ? "class=right" : "" )) + ">"+attrObj.options[opt] + "</label>";
			}
			sNewField += "</div>"
			break;
		
		case "array":
			sNewField += "<div class=\"array_elements\">";
			if (!data_block) { 
				sNewField += blockedit_add_array_element( 0, template, null, template.title, key, null);
			} else {
				for (var variable=0;data_block.variables && variable<data_block.variables.length;variable++) {
					sNewField += blockedit_add_array_element( variable, template, null, template.title, data_block.type, data_block.variables[variable]);
				}
			}
			sNewField += "</div>";
			sNewField += "<div class=\"plus\" alt=\"Voeg element van type '" + template.title + "' toe\" onclick=\"blockedit_array_add_array_element(this, null, '" + template.title + "','" + key + "',null)\">";
			break;
			
		default: 
			sNewField += "Onbekend type veld: " + sType;
			break;
	}
	
	sNewField += '</td></tr>';
	
	return sNewField;
}

//
//	Maakt een blok met elementen aan binnen een array
// 
function blockedit_array_add_array_element( elt, template, cTemplateTitle, key, data_block ) {
	var cBaseElt = (elt ? $(elt).parent().find('.array_elements') : null);
	var sResult = "";
	var items = 0;
	
	if (cBaseElt) { 
		items = cBaseElt[0].childNodes.length;
	}
	sResult += blockedit_add_array_element( items + 1, template, cBaseElt, cTemplateTitle, key, data_block  )	

	if (cBaseElt) {
		blockedit_init_array_dragdrop(cBaseElt[0]);
	}

	blockedit_create_htmls(); 
	
	if (elt) {
		jQuery(cBaseElt).parent().find(".blockedit_elt input[required='J']").attr("data-required", "Y");			
		jQuery(elt).parent().find(".array_element").each( function() { 
			var nTopPos = $(this).position().top;
			var nScrollPos = $(document).scrollTop();
			var nHeight = window.innerHeight;
			if (nTopPos>nScrollPos+nHeight-$(this).height()) {
				$(document).scrollTop( nTopPos - nHeight + $(this).height() );
			}
		});
	}
	
    return sResult;
}


//
//	Verwijder een compleet blok binnen 1 array
//
function blockedit_array_delete_array_element( elt ) {
	var elt_verw = jQuery(elt);
	if (elt_verw.hasClass("array_element")) {
		elt_verw.remove();
	}
}

//
//
//
function blockedit_add_new_element( to_elt, data_block ) {
	var sNewContent = "";
	var sRequired = "";
	var current_data;
	var bVisible  = true;
	var cEltClass = "";
	var sValue = "";
	var sDataType = "";
	
	if (data_block && data_block.type) {
		bVisible = data_block.visible;
		sDataType = " data-type=\"" + data_block.type + "\"";
		// cmaLog.log('[BlockEdit] add_new_element: datablock is filled');
	} else {
		// cmaLog.log('[BlockEdit] add_new_element: datablock is empty');
	}
	sNewContent  = "<div class='blockedit_block" + (bVisible ? " " : " hide") + "'" + sDataType + ">";
	// leidt tot lege blokken: later oplossen.. 
	sNewContent += "<div class='blockedit_rb blockedit_up' onclick='blockedit_move_up(this);return false'></div>"
	sNewContent += "<div class='blockedit_rb blockedit_down' onclick='blockedit_move_down(this);return false'></div>";
	sNewContent += "<div class='blockedit_rb blockedit_visible" + (bVisible ? " " : " hide") + "' onclick='blockedit_visible(this)'></div>";
	sNewContent += "<div class='blockedit_rb blockedit_del' onclick='blockedit_verwijder(this.parentElement);return false'></div> ";
	for (var i = 0; i < all_components.templates.length; i++) {
		var template = all_components.templates[i];
		template.title = ucfirst(template.title.replace(" Component",""));

		var bShow = true;
		if (data_block && data_block.type) {
			bShow = (data_block.type.toLowerCase()==template.title.toLowerCase());
			var cEltClass = (bShow ? " opened " : "")
		}

		if (bShow) { 
			sNewContent += "<div class='blockedit_elt" + cEltClass + "' onclick='blockedit_click(this)'><a class=elt_titel href=# data-tooltip='" + template.description + "'>" + template.title  + "</a><table class=frm>";
			for (var key in template.variables){
				// element_cnt++;
				sNewContent += blockedit_createfield( template, key, data_block);
			}
			sNewContent += "</table></div>";
		}
	}
	sNewContent += "<div style=clear:both></div></div>";
	to_elt.append( sNewContent );

	blockedit_create_htmls();

	// Attach validation handlers to newly created fields using formval_nl.js
	// This reuses the same validation logic as the main form
	if (typeof form_init_container === 'function') {
		form_init_container(to_elt[0]);
	}

	// Initialize native drag-drop (replaces jQuery UI sortable)
	blockedit_init_dragdrop();
}

/**
 * Initialize native HTML5 drag-drop for blockedit blocks
 * Replaces jQuery UI sortable - handles CKEditor save/destroy/recreate on move
 */
function blockedit_init_dragdrop() {
    jQuery(".blockedit").each(function() {
        var container = this;

        // Skip if already initialized
        if (container.dataset.dragdropInit) return;
        container.dataset.dragdropInit = 'true';
        container.dataset.sortable = 'true'; // For CSS styling

        // Make blocks draggable
        jQuery(container).find('.blockedit_block').each(function() {
            this.draggable = true;
        });

        var draggedItem = null;
        var placeholder = null;

        container.addEventListener('dragstart', function(e) {
            var block = e.target.closest('.blockedit_block');
            if (!block) return;

            draggedItem = block;
            block.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', ''); // Required for Firefox

            // Save CKEditor content before drag
            var ckeElement = jQuery(block).find(".cke");
            if (ckeElement.length) {
                var id_textarea = ckeElement.attr("id");
                try {
                    if (id_textarea) {
                        CKEDITOR.instances[id_textarea].updateElement();
                    }
                } catch (ex) {
                    cmaLog.warn('[Blockedit] Failed to update CKEditor before drag:', id_textarea, ex.message);
                }
            }

            // Create placeholder
            placeholder = document.createElement('div');
            placeholder.className = 'blockedit_placeholder';
            placeholder.style.height = block.offsetHeight + 'px';
            placeholder.style.border = '2px dashed #204496';
            placeholder.style.borderRadius = '4px';
            placeholder.style.backgroundColor = '#f0f4ff';
            placeholder.style.marginBottom = '-1px';
        });

        container.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            if (!draggedItem || !placeholder) return;

            var targetBlock = e.target.closest('.blockedit_block');
            if (!targetBlock || targetBlock === draggedItem) return;

            // Remove existing placeholder
            if (placeholder.parentNode) {
                placeholder.remove();
            }

            // Insert placeholder based on mouse position
            var rect = targetBlock.getBoundingClientRect();
            var midY = rect.top + rect.height / 2;

            if (e.clientY < midY) {
                targetBlock.parentNode.insertBefore(placeholder, targetBlock);
            } else {
                targetBlock.parentNode.insertBefore(placeholder, targetBlock.nextSibling);
            }
        });

        container.addEventListener('dragend', function(e) {
            if (!draggedItem) return;

            draggedItem.classList.remove('dragging');

            if (placeholder && placeholder.parentNode) {
                // Move the dragged item to placeholder position
                placeholder.parentNode.insertBefore(draggedItem, placeholder);
                placeholder.remove();

                // Handle CKEditor recreate after move
                if (CKEDITOR_ForceRecreate) {
                    var ckeElement = jQuery(draggedItem).find(".cke");
                    if (ckeElement.length) {
                        var id_textarea = ckeElement.attr("id");
                        if (id_textarea) {
                            id_textarea = id_textarea.replace("cke_", "");
                            var save_config = CKEDITOR.instances[id_textarea] ? CKEDITOR.instances[id_textarea].config : null;
                            if (save_config) {
                                if (CKEDITOR_DestroyNeeded) CKEDITOR.instances[id_textarea].destroy();
                                if (CKEDITOR_DestroyNeeded) CKEDITOR.replace(id_textarea, my_saveconfig(save_config));
                                lib_Form_Scale_htmleditors(1);
                            }
                        }
                    }
                }
            }

            draggedItem = null;
            placeholder = null;
        });

        // Handle drag leave to clean up placeholder
        container.addEventListener('dragleave', function(e) {
            // Only remove placeholder if leaving the container entirely
            if (!container.contains(e.relatedTarget) && placeholder && placeholder.parentNode) {
                placeholder.remove();
            }
        });
    });
}

/**
 * Initialize native drag-drop for array elements within a block
 * Simpler version without CKEditor handling (array elements don't have CKEditor)
 */
function blockedit_init_array_dragdrop(container) {
    if (!container) return;

    // Skip if already initialized
    if (container.dataset.dragdropInit) return;
    container.dataset.dragdropInit = 'true';

    // Make array elements draggable
    jQuery(container).find('.array_element').each(function() {
        this.draggable = true;
    });

    var draggedItem = null;
    var placeholder = null;

    container.addEventListener('dragstart', function(e) {
        var element = e.target.closest('.array_element');
        if (!element) return;

        draggedItem = element;
        element.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');

        placeholder = document.createElement('div');
        placeholder.className = 'array_element_placeholder';
        placeholder.style.height = element.offsetHeight + 'px';
        placeholder.style.border = '2px dashed #204496';
        placeholder.style.borderRadius = '4px';
        placeholder.style.backgroundColor = '#f0f4ff';
        placeholder.style.marginBottom = '4px';
    });

    container.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        if (!draggedItem || !placeholder) return;

        var targetElement = e.target.closest('.array_element');
        if (!targetElement || targetElement === draggedItem) return;

        if (placeholder.parentNode) {
            placeholder.remove();
        }

        var rect = targetElement.getBoundingClientRect();
        var midY = rect.top + rect.height / 2;

        if (e.clientY < midY) {
            targetElement.parentNode.insertBefore(placeholder, targetElement);
        } else {
            targetElement.parentNode.insertBefore(placeholder, targetElement.nextSibling);
        }
    });

    container.addEventListener('dragend', function(e) {
        if (!draggedItem) return;

        draggedItem.classList.remove('dragging');

        if (placeholder && placeholder.parentNode) {
            placeholder.parentNode.insertBefore(draggedItem, placeholder);
            placeholder.remove();
        }

        draggedItem = null;
        placeholder = null;
    });

    container.addEventListener('dragleave', function(e) {
        if (!container.contains(e.relatedTarget) && placeholder && placeholder.parentNode) {
            placeholder.remove();
        }
    });
}

// standard ckeditor config save is crap; the replace fails if i use that one..
// 
function my_saveconfig(cke_config) { 
    var new_config = {} 

    new_config.contentsCss = cke_config.contentsCss;
    new_config.language = cke_config.language;
    new_config.contentsLanguage = cke_config.contentsLanguage;
    new_config.defaultLanguage = cke_config.defaultLanguage;
    new_config.scayt_sLang = cke_config.scayt_sLang;
    new_config.skin = cke_config.skin;
    new_config.pasteFromWordPrompt = cke_config.pasteFromWordPrompt;
//    new_config.height = cke_config.height;    // 
    new_config.scayt_autoStartup = cke_config.scayt_autoStartup; 
    new_config.allowedContent = cke_config.allowedContent;
    new_config.extraAllowedContent = cke_config.extraAllowedContent;
    new_config.toolbar_Full = cke_config.toolbar_Full;
 //   new_config.extraPlugins = cke_config.extraPlugins;
    new_config.startupOutlineShy = cke_config.startupOutlineShy;
    new_config.startupShowBorders = cke_config.startupShowBorders;
    new_config.toolbarCanCollapse = cke_config.toolbarCanCollapse;
    new_config.switchBarSimple = cke_config.switchBarSimple;
    new_config.switchBarReach = cke_config.switchBarReach;
    new_config.switchBarDefault = cke_config.switchBarDefault;
    new_config.resize_enabled = cke_config.resize_enabled;
    new_config.entities = cke_config.entities;
    new_config.basicEntities = cke_config.basicEntities;
    new_config.latinEntities = cke_config.latinEntities;
    new_config.greekEntities = cke_config.greekEntities;
    new_config.toolbar = cke_config.toolbar;
    new_config.stylesSet = cke_config.stylesSet;
    new_config.enterMode = cke_config.enterMode;
    new_config.qtBorder = cke_config.qtBorder;
    new_config.qtCellPadding = cke_config.qtCellPadding;
    new_config.qtCellSpacing = cke_config.qtCellSpacing;
    new_config.qtStyle = cke_config.qtStyle;
    new_config.qtClass = cke_config.qtClass;
    new_config.qtWidth = cke_config.qtWidth;

    return new_config;
}

function blockedit_create_htmls() {
	// cmaLog.log('[blockedit_create_htmls] ENTRY - htmls array length:', htmls.length);
	if (htmls.length === 0) {
		// cmaLog.log('[blockedit_create_htmls] No textareas to process');
		return;
	}

	// Check if CKEditor is available - it may be loaded with defer
	if (typeof CKEDITOR === 'undefined') {
		cmaLog.warn('[blockedit_create_htmls] CKEDITOR not yet loaded, waiting...');
		// Wait for CKEditor to load and retry
		var retryCount = 0;
		var maxRetries = 50; // 5 seconds max wait
		var waitForCKEditor = function() {
			retryCount++;
			if (typeof CKEDITOR !== 'undefined') {
				// cmaLog.log('[blockedit_create_htmls] CKEDITOR now available after', retryCount * 100, 'ms');
				blockedit_create_htmls_internal();
			} else if (retryCount < maxRetries) {
				setTimeout(waitForCKEditor, 100);
			} else {
				cmaLog.error('[blockedit_create_htmls] CKEDITOR not available after 5 seconds, giving up');
				// Show textareas as fallback
				for (var j = 0; j < htmls.length; j++) {
					var ta = document.getElementById(htmls[j]);
					if (ta && ta.style.visibility === 'hidden') {
						ta.style.visibility = 'visible';
					}
				}
				htmls = [];
			}
		};
		setTimeout(waitForCKEditor, 100);
		return;
	}

	blockedit_create_htmls_internal();
}

function blockedit_create_htmls_internal() {
	// cmaLog.log('[blockedit_create_htmls_internal] Processing', htmls.length, 'textareas');

	for (var i = 0; i < htmls.length; i++) {
		blockedit_createCKEditor(htmls[i]);
	}

	htmls = [];
}
//
// 	add a single element for type array element defined by Key, either to a string or live to the screen
// 
// 	2do: arr_index is now ignored!
// 
function blockedit_add_array_element( arr_index, template, containerElt, cTemplateTitle, key, data_block ) {
	var sElement = "";

try { 	
	// find type and loop through fields
	var attrObj = template_find_elt( cTemplateTitle, key);
	if (attrObj) { 
		sElement = "<div class=\"array_element\">";
		if (typeof attrObj.sortfield != 'undefined') {								
			sElement += "<div class='blockedit_rb blockedit_up' onclick='blockedit_array_move_up(this.parentElement);return false'></div><div class='blockedit_rb blockedit_down' onclick='blockedit_array_move_down(this.parentElement);return false'></div>";
		}
		sElement += "<div class='blockedit_rb blockedit_del' onclick='blockedit_array_delete_array_element(this.parentElement);return false'></div><table class=\"subfrm\">";
		for (var key in attrObj.variables) {
			sElement += blockedit_createfield( attrObj, key, data_block); 
		}
		sElement += "</table></div>";
	}
	// compose string
	if (containerElt) {
		// array elements are directly shown, make required
		if (arr_index>0) {
			sElement = sElement.replace("required='J'", "data-required='Y'")
		}
		// add sElement to parentElt
		$(containerElt).append( sElement );
	
	} else {
		return sElement;
	}
}
catch (e) {
	cmaLog.error('[BlockEdit] blockedit_add_array_element error:', e.message || e);
	return sElement;
}
}

//
// Create a simple CKEditor for a blockedit textarea
// Automatically defers if textarea is in a collapsed accordion
// Returns: 'created', 'deferred', 'exists', or 'error'
//
function blockedit_createCKEditor(fieldId) {
	var textarea = document.getElementById(fieldId);
	if (!textarea) {
		cmaLog.warn('[blockedit_createCKEditor] Textarea not found:', fieldId);
		return 'error';
	}

	// Already has CKEditor?
	if (CKEDITOR.instances && CKEDITOR.instances[fieldId]) {
		return 'exists';
	}

	// Check if in collapsed accordion - defer if so
	var blockeditElt = jQuery(textarea).closest('.blockedit_elt');
	if (blockeditElt.length && !blockeditElt.hasClass('opened')) {
		if (pendingCKEditors.indexOf(fieldId) === -1) {
			pendingCKEditors.push(fieldId);
			// cmaLog.log('[blockedit_createCKEditor] DEFERRED:', fieldId);
		}
		return 'deferred';
	}

	try {
		// Get contentsCss from global config if available
		var contentsCss = '';
		if (typeof CMA !== 'undefined' && CMA.formConfig && CMA.formConfig.editorConfig && CMA.formConfig.editorConfig.customCSS) {
			contentsCss = CMA.formConfig.editorConfig.customCSS;
		}

		cmaLog.log('[blockedit_createCKEditor] Creating CKEditor for:', fieldId, { contentsCss: contentsCss || '(none)' });

		var editor = CKEDITOR.replace(fieldId, {
			language: 'nl',
			height: 100,
			contentsCss: contentsCss,
			allowedContent: true,
			toolbar: [{ name: 'basic', items: ['Bold', 'Italic', '-', 'BulletedList', 'NumberedList', '-', 'Link', 'Unlink'] }],
			resize_enabled: false
		});

		if (editor) {
			// Set dirty flag when CKEditor content changes
			editor.on('change', function() {
				if (typeof CMA !== 'undefined' && CMA.form && CMA.form.setDirty) {
					CMA.form.setDirty();
				}
			});
			cmaLog.log('[blockedit_createCKEditor] SUCCESS:', fieldId);
			return 'created';
		} else {
			cmaLog.error('[blockedit_createCKEditor] FAILED - replace returned null:', fieldId);
			return 'error';
		}
	} catch (e) {
		cmaLog.error('[blockedit_createCKEditor] ERROR:', fieldId, e.message);
		return 'error';
	}
}

//
// Process pending CKEditors - tries to create them, re-defers if still collapsed
//
function blockedit_process_pending_ckeditors() {
	if (pendingCKEditors.length === 0) return;

	var toProcess = pendingCKEditors.slice(); // copy
	pendingCKEditors = []; // clear - createCKEditor will re-add if still collapsed

	setTimeout(function() {
		for (var i = 0; i < toProcess.length; i++) {
			blockedit_createCKEditor(toProcess[i]);
		}
	}, 150);
}

//
//	Select the type of control
//
function blockedit_click( elt ) {
	if ( jQuery( elt ).hasClass("opened") ) {
		jQuery(elt).parent().find(".blockedit_elt input[required='J']").attr("data-required", "Y");
	} else {
		jQuery(elt).parent().find(".blockedit_elt").hide();
		jQuery(elt).parent().find(".blockedit_elt input").attr("data-required", "");
		jQuery(elt).show( 100 );
		jQuery(elt).addClass("opened");
		var button_elt = jQuery(elt).find("a")[0];
		if (button_elt) {
			jQuery(elt).parent().attr("data-type", button_elt.innerHTML );
		}
		blockedit_add_new_element( jQuery(elt).parent().parent(), null);

		// Process any pending CKEditors that were deferred while hidden
		blockedit_process_pending_ckeditors();
	}
}

//
//	Verwijder een compleet blok
//
function blockedit_verwijder( elt ) {
	var elt_verw = jQuery(elt);
	if (elt_verw.hasClass("blockedit_block")) {
		elt_verw.remove();
		// 2do: alleen als er geen meer is: 
		blockedit_add_new_element( jQuery(elt).parent(), null );
	}
}

//
//
//
function blockedit_visible( elt ) {
	jQuery(elt).toggleClass("hide");
	jQuery(elt).parent().toggleClass("hide");
}

//
//
//
function blockedit_array_move_down ( elt ) {
	var next_elt = elt.nextSibling;
	if (next_elt) {
		if (jQuery(next_elt).hasClass("array_element")) {				
			var tmp = elt.innerHTML;
			elt.innerHTML = next_elt.innerHTML;
			next_elt.innerHTML = tmp;
		}
	}
}

//
//
//
function blockedit_array_move_up( elt ) {
		
	var prev_elt = elt.previousSibling;
	if (prev_elt) {
		if (jQuery(prev_elt).hasClass("array_element")) { 
			var tmp = elt.innerHTML;
			elt.innerHTML = prev_elt.innerHTML;
			prev_elt.innerHTML = tmp;
		}
	}
}

//
// Unified block move function - handles both up and down directions
// @param {Element} elt - The element triggering the move (button inside block)
// @param {string} direction - 'up' or 'down'
//
function blockedit_move_block(elt, direction) {
    var currentElt = elt.parentElement;
    var adjacentElt = (direction === 'up')
        ? currentElt.previousSibling
        : currentElt.nextSibling;

    if (!adjacentElt || !jQuery(adjacentElt).hasClass("blockedit_block")) {
        return; // No valid adjacent block to swap with
    }

    // Save CKEditor state if needed
    var ckeStates = {};
    if (CKEDITOR_ForceRecreate) {
        ckeStates = blockedit_save_ckeditor_states([currentElt, adjacentElt]);
    }

    // Determine insertion reference point
    var isFirst = !currentElt.previousSibling;
    var refElement = isFirst ? currentElt.parentElement : currentElt.previousSibling;

    // Detach both elements
    $(currentElt).detach();
    $(adjacentElt).detach();

    // Reinsert in swapped order based on direction
    if (direction === 'up') {
        // Moving up: current goes before adjacent
        if (isFirst) {
            $(refElement).prepend(adjacentElt);
            $(refElement).prepend(currentElt);
        } else {
            $(adjacentElt).insertAfter(refElement);
            $(currentElt).insertAfter(refElement);
        }
    } else {
        // Moving down: adjacent goes before current
        if (isFirst) {
            $(refElement).prepend(currentElt);
            $(refElement).prepend(adjacentElt);
        } else {
            $(currentElt).insertAfter(refElement);
            $(adjacentElt).insertAfter(refElement);
        }
    }

    // Restore CKEditor instances
    if (CKEDITOR_ForceRecreate) {
        blockedit_restore_ckeditor_states(ckeStates);
    }

    lib_Form_Scale_htmleditors(1);
    blockedit_collect_htmls();
}

//
// Helper: Save CKEditor states for elements
// @param {Element[]} elements - Array of block elements
// @returns {Object} Map of editor IDs to their configs
//
function blockedit_save_ckeditor_states(elements) {
    var states = {};
    elements.forEach(function(elt) {
        var ckeContainer = $(elt).find(".cke");
        if (!ckeContainer.length) return;

        var editorId = ckeContainer.attr("id");
        if (!editorId) return;

        editorId = editorId.replace("cke_", "");
        try {
            CKEDITOR.instances[editorId].updateElement();
            states[editorId] = CKEDITOR.instances[editorId].config;
            if (CKEDITOR_DestroyNeeded) {
                CKEDITOR.instances[editorId].destroy();
            }
            $(editorId).show();
        } catch (e) {
            cmaLog.warn('[Blockedit] Failed to save/destroy CKEditor state:', editorId, e.message);
        }
    });
    return states;
}

//
// Helper: Restore CKEditor instances from saved states
// @param {Object} states - Map of editor IDs to their configs
//
function blockedit_restore_ckeditor_states(states) {
    for (var editorId in states) {
        if (states.hasOwnProperty(editorId)) {
            CKEDITOR.replace(editorId, my_saveconfig(states[editorId]));
        }
    }
}

//
// Legacy wrapper for backward compatibility
//
function blockedit_move_up(elt) {
    blockedit_move_block(elt, 'up');
}

//
// Legacy wrapper for backward compatibility
//
function blockedit_move_down(elt) {
    blockedit_move_block(elt, 'down');
}

//
//	Ophalen alle blokken
//
function blockedit_collect_htmls(  ) {
	
	if (all_components) { 
		var cDataField;
		var cTotalHTML = "";
		var bVisible;
		
		jQuery(".blockedit").each( function() {
			var cHTML = '';
			cDataField = $(this).attr("data-field");
			$( this ).find(".blockedit_block").each( function() {
				
				var cDataType = $(this).attr("data-type");
				var aVariables = {};
				bVisible = !($(this).hasClass("hide"));

				if (cDataType) {

// console.log( "Data field: " + cDataField + ", data type: " + cDataType );

					var type_obj = template_find_type(cDataType);
					cHTML = type_obj.html;

					$( this ).find(".blockedit_elt.opened tr td.field").each( function() {
						var cDataEltName = $( this ).attr("data-elt");

						var sValue = "";
						var aValues = [];
						var elt_obj = template_find_elt(cDataType, cDataEltName);

						if (elt_obj) {
							if (elt_obj.required) {
								// do something
							}
							switch (elt_obj.type) {
								case "url":
								case "text":
								case "date":
								case "boolean":
								case "image":
								case "file":
									sValue = $( this ).find("input[name='"+cDataEltName+"']").val();
									break;

								case "switch":
									sValue = $( this ).find("input[type='radio']:checked").val();
// console.log( "Value of " + elt_obj.type + "  control " + cDataEltName + " is now retrieved : " + sValue)
									break;
									
								case "longtext":
									var sControlName = $( this ).find("textarea")[0].id;
									try { 
										CKEDITOR.instances[sControlName].updateElement();
									}
									catch (e) { cmaLog.error("Updating longtext failed:", e); }
									sValue = document.getElementById(sControlName).value;
// console.log( "HTML field " + sControlName + " is now retrieved : " + sValue)
									break;
									
								case "array":
									// velden vinden 
									cHTML = "";
									aValues = [];
									$( this ).find("div.array_element").each ( function (){
										var aSingleRecord = {};
										for (var array_key in elt_obj.variables) {

											var cFind = "";
											switch (  elt_obj.variables[array_key].type ) { 
												case "switch": 
												case "radio": 
													cFind = "input[type='radio']:checked";
													break; 
												case "text":
												case "image":
												case "file":
												case "url":
													cFind = "input[name='" + array_key + "']";
													break;
												case "longtext": 												
													cFind = "textarea[data-value='" + array_key + "']";													
													break;
											} 
											
											var sValue = "";
											$( this ).find( cFind ).each ( function () {
												
												if ( elt_obj.variables[array_key].type=="longtext") { 
													var sControlName = $(this)[0].id;
													try { 
														CKEDITOR.instances[sControlName].updateElement();
													}
													catch (e) { cmaLog.error("Updating array longtext " + sControlName + " failed:", e); }
												}
												sValue = $(this).val();
// console.log ( "cFind: " + cFind + " waarde: " + sValue + " array_key:" + array_key);												
												aSingleRecord[ array_key ] = string_JSON_prepare( sValue );
// if (elt_obj.variables[array_key].type=="longtext" ) {
//		console.log ( "Retrieved " + array_key + "longtext value: " +  sValue + " aSingleRecord " + aSingleRecord[ array_key ]);
// }
											});	
										}
										
										aValues.push ( aSingleRecord );
									} );				
// console.log (aSingleRecord)	

// console.log ( "Resulting HTML" + blockedit_compose_html( cDataType.toLowerCase(), aValues ));
									
									cHTML = cHTML + blockedit_compose_html( cDataType.toLowerCase(), aValues )
									break;
							}
							// per type?! 
							if (elt_obj.type=="array") {  
								if (typeof type_obj.sortfield != 'undefined') {
									aValues.sort(function( a, b) { 
										if (a[type_obj.sortfield].toLowerCase()<b[type_obj.sortfield].toLowerCase()) {
												return -1;
										} else {
											if (a[type_obj.sortfield].toLowerCase()>b[type_obj.sortfield].toLowerCase()) {
												return 1;
											} else {
												return 0;
											}
										}
									} );
								}
								aVariables = aValues;							
							} else { 
								// if (sValue) { 
								cHTML = cHTML.replace( "[" + cDataEltName + "]", sValue );
								if (cHTML=="") {
									cHTML = sValue;
								}
								aVariables[cDataEltName] = string_JSON_prepare( sValue );
								// }
							}
						} else {
// console.error ('Kan het object ' + cDataType + ' elementtype ' + cDataEltName + ' niet vinden')
						}
// console.log ("Veld " + cDataEltName + " gevonden met type " + cDataType + " => " + sValue);

					});
					
					var aField = {};
					aField["type"] = cDataType;
					aField["variables"] = aVariables;
					aField["visible"] = bVisible;
					
					if (!bVisible) { cHTML = "" }
						
					cSpecifier = BLOCK_START + JSON.stringify( aField ) + BLOCK_END;
					// cTotalHTML = ( cTotalHTML!="" ? cTotalHTML + "\r\n" : "") + cSpecifier + "\r\n" + cHTML;
					// console.log ( cDataType )
					cTotalHTML = cTotalHTML + cSpecifier + BLOCK_START_HTML + cHTML + BLOCK_END_HTML;
				}
			});
			// safeguard
			// console.log( "Totale html "+ cDataEltName + " : " + cTotalHTML );
			if (cTotalHTML!="") {
				if (CKEDITOR.instances[cDataField]) {
					CKEDITOR.instances[cDataField].setData(cTotalHTML);
				} else {
					// Fallback: write directly to textarea (e.g. in storybook context)
					var ta = jQuery('textarea[name="' + cDataField + '"]');
					if (ta.length) ta.val(cTotalHTML);
				}
			}
		} );	
	}
}


function blockedit_image_select(sControl, resizeType, resizeHeight, resizeWidth) {
	var sPath = '/images/html/';
	var sValue = document.getElementById(sControl).value.replace(sPath, '');
	var params = 'basepath=' + encodeURIComponent(sPath) + '&fieldname=' + encodeURIComponent(sControl) + '&image=1&layout=1';
	if (resizeType) params += '&resizetype=' + resizeType;
	if (resizeWidth) params += '&resizewidth=' + resizeWidth;
	if (resizeHeight) params += '&resizeheight=' + resizeHeight;

	blockedit_open_file_browser(params, sControl);
}

function blockedit_file_select(sControl) {
	var sPath = '/images/html/';
	var params = 'basepath=' + encodeURIComponent(sPath) + '&fieldname=' + encodeURIComponent(sControl) + '&image=0';

	blockedit_open_file_browser(params, sControl);
}

// Open the file browser in a lib-dialog (replaces legacy ShowWizard)
function blockedit_open_file_browser(params, sControl) {
	var dialogId = 'blockedit-file-browser-dialog';
	var dialog = document.getElementById(dialogId);

	if (!dialog) {
		dialog = document.createElement('lib-dialog');
		dialog.id = dialogId;
		dialog.setAttribute('heading', 'Bestandsbrowser');
		dialog.setAttribute('size', 'fullscreen');
		dialog.setAttribute('modal', '');
		var iframe = document.createElement('iframe');
		iframe.id = 'blockedit-fb-iframe';
		iframe.style.cssText = 'width: 100%; height: 100%; border: none; display: block;';
		dialog.appendChild(iframe);
		document.body.appendChild(dialog);
	}

	var iframe = document.getElementById('blockedit-fb-iframe');
	iframe.src = '/cma/wizards/file-browser.php?' + params;

	// Listen for file selection via postMessage
	var messageHandler = function(e) {
		if (e.origin !== window.location.origin) return;
		if (!e.data || e.data.type !== 'file-browser-select') return;
		if (e.data.fieldName !== sControl) return;

		window.removeEventListener('message', messageHandler);
		blockedit_image_set(sControl, '', e.data.value);
		dialog.close();
	};
	window.addEventListener('message', messageHandler);

	dialog.open();
}

function blockedit_image_set(sControl, sPath, filename) {
	var prev_Elt = document.getElementById(sControl + '_preview');

	if (filename) {
		var fullPath = (sPath || '/images/html/') + filename;
		jQuery('#' + sControl).val(fullPath);
		if (prev_Elt) { prev_Elt.src = fullPath; }
	} else {
		jQuery('#' + sControl).val('');
		if (prev_Elt) { prev_Elt.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'; }
	}
}

function blockedit_image_clear(sControl) {
	blockedit_image_set(sControl, '', '');
}
//
//	Find a specific variable within a block type 
// 
function template_find_elt( cTemplate, cElt ) {

// console.log ("template_find_elt(" + cTemplate + "," + cElt + ")");
	for (var i = 0; i < all_components.templates.length; i++) {
		var template = all_components.templates[i];
		if (template.title==cTemplate) {
			for (var key in template.variables) {
				if (key.toLowerCase()==cElt.toLowerCase()) {
					return template.variables[key];
				}
				if (template.variables[key].variables) {
					for (var array_key in template.variables[key].variables) {
						if (array_key==cElt) {
							return template.variables[key].variables[array_key];
						}
					}
				}
			}
		}
	}
	return null;
} 

//
//	Find a specific block type 
// 
function template_find_type( cTemplate ) {
	for (var i = 0; i < all_components.templates.length; i++) {
		var template = all_components.templates[i];
		if (template.title==cTemplate) {
			return template;
		}
	}
	return null;
} 

function string_JSON_prepare( sValue ) {
	// Remove CKEditor-specific attributes before saving (they contain problematic characters)
	sValue = sValue.replace(/\s*data-cke-saved-[a-z]+="[^"]*"/gi, '');
	return sValue.replace( '"', '\"' ),sValue.replace( "'", '\'' ).replace(/(?:\r\n|\r|\n)/g, "").replace(/(\r)/g, "").replace(/(\n)/g, "").replace( String.fromCharCode(10), "").replace( String.fromCharCode(13), "");
}

function string_JSON_fetch( sValue ) {
	return sValue;
}


function blockedit_compose_html( cVeldType, aData ) {
	var sRetval = "";
	
	switch (cVeldType.toLowerCase()) { 
		case "accordeon":
			sRetval = "<div class=\"cb cb--accordeon col-xs-12 col-lg-8\"><div class=\"accordeon\">"; 
            for (var t = 0;t<aData.length;t++) {
				sRetval += "<button id=\"accordeon_" + t.toString() + "\" class=\"accordeon__trigger\">" + aData[t].accordeon_title + "<span class=\"accordeon__triggerIconWrapper\"><i class=\"accordeon__triggerIcon\"></i></span></button><div class=\"accordeon__content\"><p>" + aData[t].accordeon_content + "</p></div>"
			}
			sRetval += "</div></div>"
			break;
			
		case "beeldengalerij":	
			sRetval = "<div class=\"cb cb--imageGallery col-xs-12\"><div class=\"imageGallery row\">";
			for (var t = 0;t<aData.length;t++) {
				sRetval += "<div class=\"imageGallery__wrapper col-xs-6 col-sm-4 col-md-3 col-lg-2\"><figure class=\"imageGallery__figure\"><img class=\"imageGallery__image\" src=\"" + aData[t].imagegallery_url + "\" alt=\"" + aData[t].imagegallery_alt + "\" /></figure></div>";
			}
			sRetval += "</div></div>"
			break;	
			
		case "docenten":
			sRetval = "<div class=\"row\"><div class=\"col-d-12 col-md-8 col-md-offset-2\"><div class=\"row\">";
			for (var t = 0;t<aData.length;t++) {
				sRetval += "<button class=\"blockTeachers__teacherContainer col-d-6 col-sm-3\"><figure class=\"blockTeachers__teacherFigure\"><img class=\"blockTeachers__teacherImage\" src=\"" + aData[t].docent_image + "\"></figure><h3 class=\"blockTeachers__teacherName\">" + aData[t].docent_naam + "</h3></button>";
			}
			sRetval += "</div></div></div>"
			break;
			
		case "downloads":
			sRetval = "<div class=\"cb cb--downloadsList col-xs-12 col-lg-8\"><div class=\"downloadsList\"><ul class=\"downloadsList__list\">";
			for (var t = 0;t<aData.length;t++) {
				sRetval += "<li class=\"downloadsList__listItem\"><a href=\""  + aData[t].downloads_url + "\" class=\"downloadsList__link\" target=\"_blank\">" + aData[t].downloads_label + " <span class=\"downloadsList__filetype\">("  + aData[t].downloads_extentie + " "  + aData[t].downloads_grootte + ")</span></a></li>";
			}
			sRetval += "</ul></div></div>"
			break;	
	

		case "logogalerij":
			sRetval = "<div class=\"cb cb--linksGallery col-xs-12\"><div class=\"linksGallery row\">";
			for (var t = 0;t<aData.length;t++) {
				sRetval += "<div class=\"linksGallery__wrapper col-xs-6 col-sm-4 col-md-3 col-lg-2\">";
				sRetval += "<a href=\"" + aData[t].logogalerij_link + "\" class=\"linksGallery__link\" target=\"_blank\" rel=\"nofollow\">";
				sRetval += "<div class=\"linksGallery__text\">" + aData[t].logogalerij_alt + "</div>";
				sRetval += "<figure class=\"linksGallery__figure\"><img class=\"linksGallery__image\" src=\"" + aData[t].logogalerij_url + "\" alt=\"" + aData[t].logogalerij_alt + "\" /></figure><div class=\"linksGallery__label\"><i class=\"icon icon--arrow\"></i>Ga naar de website</div></a>";
				sRetval += "</div>";
			}
			sRetval += "</div></div>"
			break;	
			
		case "linklist":
			sRetval = "<div class=\"cb cb--linkList col-xs-12 col-lg-8\"><div class=\"linkList\"><ul class=\"linkList__list\">";
			for (var t = 0;t<aData.length;t++) {
				sRetval += "<li class=\"linkList__listItem\"><a target=\"" + aData[t].linklist_target + "\" href=\""  + aData[t].linklist_url + "\" class=\"linkList__link\">" + aData[t].linklist_label + "</a></li>";
			}
			sRetval += "</ul></div></div>"
			break;
			
		case "tabel":
			sRetval = "<div class=\"cb cb--table col-xs-12 col-lg-8\"><div class=\"tableWrapper\"><table class=\"table\">";
			for (var t = 0;t<aData.length;t++) {
				sRetval += "<tr><td class=\"table__rowTitle\">" + aData[t].table_label + "</td><td>" + aData[t].table_col1 + "</td>";
				if (aData[t].table_col2+''!='') { 
					sRetval += "<td>" + aData[t].table_col2 + "</td>";
				}
				sRetval += "</tr>";
			}			
			sRetval += "</table></div></div>";
			break;
			
		case "tijdslijn":	
			sRetval = "<ul class=\"timeline\">";

			for (var t = 0;t<aData.length;t++) {
				sRetval += "<li class=\"event\">" +
							"<input type=\"radio\" name=\"tl-group\"><label></label>" + 
							"<div class=\"thumb\" style=\"background-image:url('" + aData[t].tijdslijn_beeld + "')\"></div>" + 
							"<div class=\"content-perspective\">" + 
							"<div class=\"content\">" + 
							"<div class=\"content-inner\">" + 
							"<span class=\"label\">" + aData[t].tijdslijn_label + "</span>" +
							"<h3>" + aData[t].tijdslijn_titel + "</h3>" +
							"<p>" + aData[t].tijdslijn_inhoud + "</p>" +
							"</div></div></div></li>";
			}
			sRetval += "</ul>"
			break;	
			
		case "vacatures":
			sRetval = "<div class=\"cardsRelated\">";
			sRetval += "<div class=\"cardsRelated__cards\" id=\"cardsRelated__allcards\">";
			for (var t = 0;t<aData.length;t++) {
				
				sRetval += "<a href=\"" + aData[t].vacature_link + "\" target=\"_self\" title=\"Bekijk vacature\" class=\"card card--big\">";
				sRetval += "<span>";
				sRetval += "<div class=\"card__inner\">";
				sRetval += "<div class=\"card__text\">";
				sRetval += "<h2 class=\"card__title\" style=\"margin-top:0px;\">" + aData[t].vacature_titel +"</h2>";
				sRetval += "<br>";
				sRetval += "<div class=\"card__text\">";
				sRetval += "<span class=\"card__body\">" + (aData[t].vacature_afdeling+""!="" 	? "Afdeling : " + aData[t].vacature_afdeling 	: "") + "</span><br>";
				sRetval += "<span class=\"card__body\">" + (aData[t].vacature_uren+""!="" 		? "Uren : " + aData[t].vacature_uren 			: "") + "</span><br>";
				sRetval += "<span class=\"card__body\">" + (aData[t].vacature_periode+""!="" 	? "Periode : " + aData[t].vacature_periode 	: "") + "</span><br>" ;
				
				sRetval += "<br>";
				sRetval += "</div>";
				sRetval += "</div>";
				sRetval += "<div class=\"bekijkoproep\">&gt; Bekijk vacature</div>";
				sRetval += "</div>";
				sRetval += "</span>";
				sRetval += "</a>";
				
			}
			sRetval += "</div>"
			sRetval += "</div>"
			break;
	}
	return sRetval;
}

// Export public functions to window (needed by external callers and onclick handlers in generated HTML)
window.blockedit_init = blockedit_init;
window.blockedit_clear = blockedit_clear;
window.blockedit_collect_htmls = blockedit_collect_htmls;
window.blockedit_click = blockedit_click;
window.blockedit_verwijder = blockedit_verwijder;
window.blockedit_visible = blockedit_visible;
window.blockedit_move_up = blockedit_move_up;
window.blockedit_move_down = blockedit_move_down;
window.blockedit_move_block = blockedit_move_block;
window.blockedit_array_move_up = blockedit_array_move_up;
window.blockedit_array_move_down = blockedit_array_move_down;
window.blockedit_array_add_array_element = blockedit_array_add_array_element;
window.blockedit_array_delete_array_element = blockedit_array_delete_array_element;
window.blockedit_image_select = blockedit_image_select;
window.blockedit_image_clear = blockedit_image_clear;
window.blockedit_image_set = blockedit_image_set;
window.blockedit_file_select = blockedit_file_select;

})();
