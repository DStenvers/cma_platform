//   versie 3.0.59 - 26 december 2025
//
//
// Conditional logging helper - delegates to LibLog if available
// Use libLog instead of console.log for debug-only output
// LibLog provides: console interception, batching, server-side logging
var libLog = (function() {
    // Check if LibLog is loaded (from lib-log.js)
    if (window.LibLog) {
        return window.LibLog;
    }
    // Fallback if LibLog not loaded - simple conditional logging
    // Use CMA_CONSOLE_LOGGING (from cookie preference) instead of CMA_DEBUG (hostname-based)
    return {
        log: function() { if (window.CMA_CONSOLE_LOGGING) console.log.apply(console, arguments); },
        warn: function() { if (window.CMA_CONSOLE_LOGGING) console.warn.apply(console, arguments); },
        warning: function() { if (window.CMA_CONSOLE_LOGGING) console.warn.apply(console, arguments); },
        info: function() { if (window.CMA_CONSOLE_LOGGING) console.info.apply(console, arguments); },
        debug: function() { if (window.CMA_CONSOLE_LOGGING) console.log.apply(console, arguments); },
        error: function() { console.error.apply(console, arguments); } // Always log errors
    };
})();
var isIE      		= (navigator.userAgent.match(/Trident/) || navigator.userAgent.match(/Edge/));
var isIE11 			= (!!navigator.userAgent.match(/Trident\/7.0/) && document.documentMode==11);
var jQueryLoaded	= (typeof window.jQuery != 'undefined');

var lib_form_saving_prefix = "__FRM_save_";
var lib_form_saving_days = 365;

var fader_zindex = 999000;
var fader_name = '__lib_fader';
var lib_caption_height = 36;
var lib_win_counter = 0;

/**
 * Unified z-index manager for all overlay components (windows, sidepanels, dialogs)
 * Ensures proper stacking order regardless of which component type is opened
 *
 * IMPORTANT: When inside an iframe (sidepanel), this proxies to the top window's manager
 * to ensure all overlays share the same z-index stack.
 */
/**
 * Centralized z-index manager for overlays (sidepanels, dialogs, datepickers).
 *
 * Uses a stack-based approach: each overlay gets baseZIndex + (depth * 10).
 * All frames share one stack via getTopManager(), which delegates to the top
 * window's manager when called from an iframe. This is essential for web
 * components like lib-dialog and lib-datepicker that open inside sidepanel
 * iframes and need z-index above the sidepanel itself.
 *
 * IMPORTANT: lib_OpenSidePanel and lib_CloseSidePanel bypass getTopManager()
 * and use topWindow.lib_zindex_manager directly, because they already hold a
 * topWindow reference (from lib_alertbox_getbody_doc_element). This avoids a
 * timing edge case where the iframe's library.js isn't fully initialized when
 * the sidepanel opens, causing getTopManager() to fall back to the iframe's
 * empty local stack and assign a duplicate z-index (999000 instead of 999010+).
 * Do NOT "simplify" those call sites back to lib_zindex_manager.push() — the
 * direct reference is intentional.
 */
var lib_zindex_manager = (function() {
    var baseZIndex = 999000;
    var stack = []; // Array of { id: string, type: string, zIndex: number }

    /**
     * Get the correct z-index manager (top window's manager for iframes).
     * Used by lib-dialog, lib-datepicker, and other components that don't
     * have their own topWindow reference. For code that already has topWindow,
     * use topWindow.lib_zindex_manager directly instead (see note above).
     */
    function getTopManager() {
        try {
            // If we're in an iframe and the top window has a z-index manager, use it
            if (self !== top && top.lib_zindex_manager && top.lib_zindex_manager !== lib_zindex_manager) {
                return top.lib_zindex_manager;
            }
        } catch (e) {
            // Cross-origin - can't access top window, use local manager
        }
        return null; // Use local manager
    }

    return {
        /**
         * Get the next z-index for a new overlay
         * @param {string} id - Unique identifier for the overlay
         * @param {string} type - Type of overlay ('window', 'sidepanel', 'dialog')
         * @returns {number} The z-index to use
         */
        push: function(id, type) {
            var topManager = getTopManager();
            if (topManager) {
                return topManager.push(id, type);
            }
            var zIndex = baseZIndex + (stack.length * 10);
            stack.push({ id: id, type: type, zIndex: zIndex });
            return zIndex;
        },

        /**
         * Remove an overlay from the stack
         * @param {string} id - Unique identifier of the overlay to remove
         */
        pop: function(id) {
            var topManager = getTopManager();
            if (topManager) {
                return topManager.pop(id);
            }
            for (var i = stack.length - 1; i >= 0; i--) {
                if (stack[i].id === id) {
                    stack.splice(i, 1);
                    return;
                }
            }
        },

        /**
         * Get current z-index for an overlay (for backdrop which needs to be below)
         * @param {string} id - Unique identifier of the overlay
         * @returns {number} The z-index of the overlay, or baseZIndex if not found
         */
        get: function(id) {
            var topManager = getTopManager();
            if (topManager) {
                return topManager.get(id);
            }
            for (var i = 0; i < stack.length; i++) {
                if (stack[i].id === id) {
                    return stack[i].zIndex;
                }
            }
            return baseZIndex;
        },

        /**
         * Get the topmost overlay's z-index
         * @returns {number} The highest z-index currently in use
         */
        getTop: function() {
            var topManager = getTopManager();
            if (topManager) {
                return topManager.getTop();
            }
            if (stack.length === 0) return baseZIndex;
            return stack[stack.length - 1].zIndex;
        },

        /**
         * Get count of overlays in stack
         * @returns {number} Number of overlays
         */
        count: function() {
            var topManager = getTopManager();
            if (topManager) {
                return topManager.count();
            }
            return stack.length;
        },

        /**
         * Check if an overlay is the topmost
         * @param {string} id - Unique identifier of the overlay
         * @returns {boolean} True if this overlay is on top
         */
        isTop: function(id) {
            var topManager = getTopManager();
            if (topManager) {
                return topManager.isTop(id);
            }
            if (stack.length === 0) return false;
            return stack[stack.length - 1].id === id;
        },

        /**
         * Bring an overlay to the top
         * @param {string} id - Unique identifier of the overlay
         * @returns {number} The new z-index
         */
        bringToTop: function(id) {
            var topManager = getTopManager();
            if (topManager) {
                return topManager.bringToTop(id);
            }
            for (var i = 0; i < stack.length; i++) {
                if (stack[i].id === id) {
                    var item = stack.splice(i, 1)[0];
                    item.zIndex = baseZIndex + (stack.length * 10);
                    stack.push(item);
                    return item.zIndex;
                }
            }
            return baseZIndex;
        },

        /**
         * Get a z-index for UI dropdowns (filter menus, select2, etc.)
         * These need to be above regular content but work correctly with overlays.
         * Returns a z-index that's:
         * - Above regular content (1000+)
         * - Just below any open overlays (if any)
         * - Or at baseZIndex - 100 if no overlays are open
         * @returns {number} The z-index to use for dropdowns
         */
        getDropdownZIndex: function() {
            var topManager = getTopManager();
            if (topManager) {
                return topManager.getDropdownZIndex();
            }
            // If there are overlays, dropdown should be just below the lowest one
            // If no overlays, use a reasonable high value (but below overlay range)
            if (stack.length === 0) {
                return baseZIndex - 100; // 998900 - high but below any future overlays
            }
            // Return 5 below the lowest overlay z-index
            return stack[0].zIndex - 5;
        }
    };
})();

/**
 * Visibility checker and fixer utility
 * Ensures an element is actually visible on screen. If not, tries to fix it.
 * Uses jQuery to check visibility and attempts to increase z-index if hidden.
 *
 * @param {jQuery|HTMLElement|string} element - Element to check (jQuery object, DOM element, or selector)
 * @param {Object} options - Options
 * @param {boolean} options.fix - If true, attempt to fix visibility issues (default: true)
 * @param {boolean} options.throwOnFail - If true, throw error if element cannot be made visible (default: true)
 * @param {string} options.context - Description of what's being checked (for error messages)
 * @returns {boolean} True if element is visible
 */
function lib_ensureVisible(element, options) {
    options = options || {};
    var fix = options.fix !== false;
    var throwOnFail = options.throwOnFail !== false;
    var context = options.context || 'element';

    // Ensure we have jQuery
    if (typeof jQuery === 'undefined') {
        console.error('[lib_ensureVisible] jQuery not available');
        return false;
    }

    // Convert to jQuery object if needed
    var $el = (element instanceof jQuery) ? element : jQuery(element);

    if ($el.length === 0) {
        var msg = '[lib_ensureVisible] ' + context + ': Element not found in DOM';
        console.error(msg);
        if (throwOnFail) {
            throw new Error(msg);
        }
        return false;
    }

    // Check if element is visible using jQuery's :visible selector
    var isVisible = $el.is(':visible');

    // Also check computed styles
    var computedStyle = window.getComputedStyle($el[0]);
    var displayNone = computedStyle.display === 'none';
    var visibilityHidden = computedStyle.visibility === 'hidden';
    var zeroOpacity = computedStyle.opacity === '0';
    var zeroSize = $el[0].offsetWidth === 0 && $el[0].offsetHeight === 0;

    // libLog.log('[lib_ensureVisible] ' + context + ': initial check', {
    //     jQueryVisible: isVisible,
    //     display: computedStyle.display,
    //     visibility: computedStyle.visibility,
    //     opacity: computedStyle.opacity,
    //     offsetWidth: $el[0].offsetWidth,
    //     offsetHeight: $el[0].offsetHeight,
    //     zIndex: computedStyle.zIndex
    // });

    if (isVisible && !displayNone && !visibilityHidden && !zeroOpacity && !zeroSize) {
        // Element appears to be visible - check if it's actually in viewport
        var rect = $el[0].getBoundingClientRect();
        var inViewport = rect.top >= 0 && rect.left >= 0 &&
                         rect.bottom <= window.innerHeight && rect.right <= window.innerWidth;

        if (!inViewport) {
            libLog.warn('[lib_ensureVisible] ' + context + ': Element exists but is outside viewport', {
                top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right,
                viewportHeight: window.innerHeight, viewportWidth: window.innerWidth
            });
        }
        return true;
    }

    // Element is not visible - try to fix if requested
    if (!fix) {
        libLog.warn('[lib_ensureVisible] ' + context + ': Element not visible (fix=false)');
        return false;
    }

    // libLog.log('[lib_ensureVisible] ' + context + ': Attempting to fix visibility...');

    // Try various fixes
    var fixed = false;

    // 1. If display:none, try to show it
    if (displayNone) {
        // libLog.log('[lib_ensureVisible] ' + context + ': Setting display:block');
        $el.css('display', 'block');
        fixed = true;
    }

    // 2. If visibility:hidden, try to show it
    if (visibilityHidden) {
        // libLog.log('[lib_ensureVisible] ' + context + ': Setting visibility:visible');
        $el.css('visibility', 'visible');
        fixed = true;
    }

    // 3. If opacity:0, set to 1
    if (zeroOpacity) {
        // libLog.log('[lib_ensureVisible] ' + context + ': Setting opacity:1');
        $el.css('opacity', '1');
        fixed = true;
    }

    // 4. Try increasing z-index (might be hidden behind other elements)
    var currentZIndex = parseInt(computedStyle.zIndex, 10);
    if (isNaN(currentZIndex) || currentZIndex < 999999) {
        // libLog.log('[lib_ensureVisible] ' + context + ': Increasing z-index from', computedStyle.zIndex, 'to 999999');
        $el.css('z-index', '999999');
        fixed = true;
    }

    // 5. Check parent elements - they might be hiding this element
    var $parent = $el.parent();
    var parentIssues = [];
    while ($parent.length && $parent[0] !== document.body) {
        var parentStyle = window.getComputedStyle($parent[0]);
        if (parentStyle.display === 'none') {
            parentIssues.push('Parent ' + ($parent[0].tagName + ($parent[0].id ? '#' + $parent[0].id : '')) + ' has display:none');
        }
        if (parentStyle.visibility === 'hidden') {
            parentIssues.push('Parent ' + ($parent[0].tagName + ($parent[0].id ? '#' + $parent[0].id : '')) + ' has visibility:hidden');
        }
        if (parentStyle.overflow === 'hidden') {
            var parentRect = $parent[0].getBoundingClientRect();
            var elRect = $el[0].getBoundingClientRect();
            if (elRect.right < parentRect.left || elRect.left > parentRect.right ||
                elRect.bottom < parentRect.top || elRect.top > parentRect.bottom) {
                parentIssues.push('Element clipped by overflow:hidden on ' + ($parent[0].tagName + ($parent[0].id ? '#' + $parent[0].id : '')));
            }
        }
        $parent = $parent.parent();
    }

    if (parentIssues.length > 0) {
        libLog.warn('[lib_ensureVisible] ' + context + ': Parent element issues found:', parentIssues);
    }

    // Re-check visibility after fixes
    var newStyle = window.getComputedStyle($el[0]);
    var nowVisible = $el.is(':visible') &&
                     newStyle.display !== 'none' &&
                     newStyle.visibility !== 'hidden' &&
                     newStyle.opacity !== '0';

    // libLog.log('[lib_ensureVisible] ' + context + ': After fixes', {
    //     nowVisible: nowVisible,
    //     display: newStyle.display,
    //     visibility: newStyle.visibility,
    //     opacity: newStyle.opacity,
    //     zIndex: newStyle.zIndex
    // });

    if (!nowVisible && throwOnFail) {
        var errorMsg = '[lib_ensureVisible] FAILED: ' + context + ' could not be made visible. ' +
            'display=' + newStyle.display + ', visibility=' + newStyle.visibility +
            ', opacity=' + newStyle.opacity + ', zIndex=' + newStyle.zIndex;
        if (parentIssues.length > 0) {
            errorMsg += '. Parent issues: ' + parentIssues.join('; ');
        }
        console.error(errorMsg);

        // Report to error handler if available
        if (typeof window.CmaErrorHandler !== 'undefined') {
            window.CmaErrorHandler.report('visibility', errorMsg);
        }

        throw new Error(errorMsg);
    }

    return nowVisible;
}

var dialog_center_interval = null;

//  -			-			-			-			-			Quick functions

function my$(id) {
	return document.getElementById(id);
}

//  -			-			-			-			-			Debug function, styling in library.css


var _lib_dbg = false;
function lib_debug_active( bOn ) { _lib_dbg = bOn;}
function lib_debug_write ( sLine ) {
	if (_lib_dbg) {
		var element_ID = "lib_debug_window";
		var mObj = document.getElementById(element_ID);

		if (!mObj) {
			mObj = document.getElementsByTagName("body")[0].appendChild(document.createElement("div"));
			mObj.id	= element_ID;
		}
		mObj.innerHTML = "<div class=\"lib_debug_window_line\">" + sLine  + "</div>" + mObj.innerHTML;
	}
	// libLog.log( sLine )
}

//   -			-			-			-			-			EVENTS

function lib_event_get_key( evt ) {
	return (evt.charCode ? evt.charCode : (evt.keyCode ? evt.keyCode : (evt.which ? evt.which : (window.event ? window.event.keyCode : null) )));
}

function lib_addEvent(elm, evType, fn, useCapture) {
	elm.addEventListener(evType, fn, useCapture);
}


//   -			-			-			-			-			ANTI-SPAM function
// let op : deze functie word aangeroepen vanuit ASP library code: niet wijzigen!
function lib_mail(u,d,s){
	document.write('<a href=\"mailto:'+u+'@'+d+(s=='' ? '' : '?subject='+s)+'\">')
}

//   -			-			-			-			-			FORMS


// 	use this in a keydown of a control to surpress any other characters than numbers
//	now supports the clipboard: please note that copying text is allowed this way
function lib_form_digitsonly (evt) {
	var charCode = lib_event_get_key( evt );
	var shift = evt ? evt.shiftKey : false;
	var ctrl  = evt ? evt.ctrlKey  : false;
 	// 189 = - for negative numbers
	var bKeyOk = ( ctrl || ( (!shift) || charCode==189 ||  charCode==16 || charCode==9) && ((charCode >= 35 && charCode <= 39) || (charCode >= 48 && charCode <= 57)  || (charCode >= 96 && charCode <= 105) || charCode==188 || charCode==190 || charCode==9 || charCode==16 || charCode==8 || charCode==44 || charCode==45 || charCode==46) );
	if (isIE) {
		evt.returnValue=bKeyOk;
	} else {
		if (!bKeyOk) {
			evt.preventDefault();
		}
	}
	return bKeyOk;
}

// 	use <textarea maxlength="40" onkeyup="return lib_form_check_maxlength(this)"></textarea>
//
function lib_form_check_maxlength(obj){
	var maxlen=obj.getAttribute?parseInt(obj.getAttribute("maxlength")) : 90;
	if (obj.getAttribute && obj.value.length>maxlen) obj.value=obj.value.substring(0,maxlen);
}

//	Key characters for a time field
//
function lib_form_timekey (evt) {
	var charCode = lib_event_get_key( evt );
	var ctrl  = evt ? evt.ctrlKey  : false;
 	if (charCode==32) {
		// change into a : character (not supported in Mozilla
		if (window.event) window.event.keyCode = 58;
		return true;
	} else {
		// : is in Mozilla 59 and 186 in IE?!
		var bOk = (ctrl || (charCode==107 || charCode==58 || charCode==59 ||charCode==13 || (charCode >= 35 && charCode <= 39) || (charCode >= 48 && charCode <= 57) || charCode==190 || charCode==9 || charCode==16 || charCode==8 || charCode==46 || charCode==186 || charCode==189));
		return bOk;
	}
}


// 	use this in a keydown of a control to surpress spaces
//
function lib_form_nospaces (evt) {
	var charCode = lib_event_get_key( evt );
	return (charCode != 32)
}

//	Save the content of all fields in a form to separate cookies (currently igoring hidden end password fields)
//
//	exclude fields by setting their class to lib_nosave
//
function lib_form_save_content(frm) {
	var string;
	var blnStoreField;

	try {
		if (frm) {
			var n = frm.length;
			for (i = 0; i < n; i++) {
				var e = frm[i].name;
				if (e) {
					var e_clean=e;
					if (e_clean.substring(0,9).toLowerCase()=='required-')
						e_clean = e_clean.substring(9);
					blnStoreField = !frm[i].disabled && (frm[i].className.search(/lib_nosave/i)==-1);
					if (blnStoreField) {
						fieldValue  = frm[i].value;
						fieldType   = frm[i].type;
						string 		= "";

						if (fieldType == "radio") {
							for (x=0; x < frm.elements[e].length; x++) {
								if (frm.elements[e][x].checked) {string = frm.elements[e][x].value};
							}
							if (i+1<frm.length)
								while (frm[i].name && frm[i].name.toLowerCase()==e.toLowerCase() && i+1<frm.length) i++;
							while (frm[i].name && frm[i].name.toLowerCase()!=e.toLowerCase()) i--;
						}
						if ((fieldType == "text") || (fieldType == "textarea")) {
							string = frm.elements[e].value;
						}
						if (fieldType == "select-one") {
							string = frm[i].options[frm[i].selectedIndex].text;
						}
						if (fieldType == "checkbox") {
							// store all other values of this checkbox in the same cookie
							if (i+1<frm.length) {
								while (frm[i].name && frm[i].name.toLowerCase()==e.toLowerCase() && i+1<frm.length) {
									string = string + (frm[i].checked==true ? ((string=="" ? "" : ",") + frm[i].value) : "");
									i++;
								}
								while (frm[i].name && frm[i].name.toLowerCase()!=e.toLowerCase()) i--;
							} else {
								string = frm[i].checked==true ? fieldValue : "";
							}
						}

						// also save empty values: this can be informational as well! , save them for a year
						if (fieldType!="hidden" && fieldType!="password" && fieldType!="select-multiple") {
							lib_createCookie(lib_form_saving_prefix + e_clean.toLowerCase(), string, lib_form_saving_days);
						}
					}
				}
			}
		}
	}
	catch(err) {}
}

/**
* Lib Setquerystringparameter
*/
function lib_SetQueryStringParameter(uri, key, value)
{
    var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
    var separator = uri.indexOf('?') !== -1 ? "&" : "?";
    return uri.match(re) ? uri.replace(re, '$1' + key + "=" + value + '$2') : uri + separator + key + "=" + value;
}


//	TODO: Multiple selection listboxes
//	exclude fields by setting their class to lib_nosave
//
function lib_form_load_content(frm) {
	var blnRestoreField;

	try {
		if (frm) {
			var n = frm.length;
			for (var i = 0; i < n; i++) {
					var e = frm[i].name;
					if (e) {
						var e_clean = e;
						if (e_clean.substring(0,9).toLowerCase()=='required-')
							e_clean = e_clean.substring(9);

						blnRestoreField = !frm[i].disabled && (frm[i].className.search(/lib_nosave/i)==-1);

						if (blnRestoreField) {
							var fieldValue = lib_readCookie(lib_form_saving_prefix + e_clean.toLowerCase());
							if (fieldValue) {
								var fieldType  = frm[i].type.toLowerCase();
								if (fieldValue!="undefined") {
									if ((fieldType == "text") || (fieldType == "textarea")) {
										frm[i].value = fieldValue;
									}
									if (fieldType == "select-one") {
										lib_form_group_select(frm, e, fieldValue);
									}
									if (fieldType == "checkbox") {
										lib_form_setCheckbox(frm, e, fieldValue);
										if (i+1<frm.length)
											while (frm[i+1] && frm[i+1].name && frm[i+1].name.toLowerCase()==e.toLowerCase() && i+1<frm.length) i++;
									}
									if (fieldType == "radio") {
										lib_form_setRadio(frm, e, fieldValue)
										if (i+1<frm.length)
											while (frm[i+1] && frm[i+1].name && frm[i+1].name.toLowerCase()==e.toLowerCase() && i+1<frm.length) i++;
									}
								}
							}
						}
					}
			}
		}
	}
	catch(err) {}
}

//	Sets the select field to the tip (like type your name here..)
//
function lib_form_edit_select_tip(oControl, sDefault) {
	if (oControl.value!=sDefault) { oControl.select() } else { oControl.value='' }
}

//	Adds a number to a specified form field (for + and - button)
//
function lib_form_add_number(oForm, elt_name, nOffset) {
	var elt = oForm.elements[elt_name];
	if (elt) {
		var cur = parseInt(elt.value);
		if (isNaN(cur)) cur=0;
		var new_value = Math.max(0,cur + nOffset);
		oForm.elements[elt_name].value = (new_value==0 ? '' : new_value.toString());
	}
}

// 	Sets the value of a radio button
//
function lib_form_setRadio(oFrm, radio_name, new_value) {
	try {
		for (var y=0; y < oFrm.elements.length; y++) {
			if (oFrm.elements[y].name) {
				if (oFrm.elements[y].name.toLowerCase()==radio_name.toLowerCase()) {
					oFrm.elements[y].checked = (oFrm.elements[y].value==new_value);
				}
			}
		}
	}
	catch(e) {}
}

//	Sets the values for a range of checkboxes
//
function lib_form_setCheckbox(oFrm, checkbox_name, new_values) {
	try {
		if (new_values) {
			var arr_values = lib_array_split(new_values);
			for (var x=0; x < oFrm.elements[checkbox_name].length; x++) {
				oFrm.elements[checkbox_name][x].checked = (lib_array_find(arr_values, oFrm.elements[checkbox_name][x].value) > -1);
			}
		}
	}
	catch(e) {}
}

// 	Finds a form field
//
function lib_form_findfield(sName) {
	var fldObj = null;
	var the_frm;

	fldObj = my$(sName);
	if (!fldObj) {
	  for (var f=0; f<=document.forms.length; f++) {
		the_frm = document.forms[f];
		try {
			if (the_frm[sName]) return the_frm.elements[sName];
		}
		catch(err) {}
	  }
	}
	return fldObj;
}

//	Selects a value within a select box
//
function lib_form_group_select(oFrm, sField, sSelectedValue){
	try {
		var fld = oFrm.elements[sField];
		for (t=0;t<fld.options.length;t++) {
			if (fld.options[t].text.toLowerCase() == sSelectedValue.toLowerCase()) {
				fld.selectedIndex=t;
			}
		}
	}
	catch(e) {}
}

// Selects a group of checkboxes to a specified value
//
function lib_form_multiple_checkbox_select(oFrm, sField, bValue){
	for (t=0;t<oFrm.elements.length;t++) {
		var fld = oFrm[t];
		if (fld.name) {
			if (fld.name.toLowerCase() == sField) {
				fld.checked=bValue;
			}
		}
	}
}


//	Validates an email address, now supports ;-delimiters,now supporting longer extentions
//
function lib_form_valid_email(objFieldToCheck){
	var email = objFieldToCheck.value.split(';');
	var regEmail = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,13})+$/;

	if (email.length>0) {
		for (var i = 0; i < email.length; i++) {
			if (email[i].length > 0) {
				if (!regEmail.test( email[i] ) ) return false;
			}
		}
	}
	return true;
}

//   -			-			-			-			-			STRINGS

function lib_trim(s) {
	return s.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

//   -			-			-			-			-			CONVERSIONS

// 	formats a number with the specified numer of decimals
//
function lib_NumberFormat(num, decimalNum) {
    if (isNaN(parseInt(num))) return "NaN";
	var tmpNumStr = ((num.toFixed(decimalNum)).toString()).replace(".",",") ;
	return (tmpNumStr);		// Return our formatted string!
}


//
//	Geef een melding die weer automatisch verdwijnt..
//
// @deprecated Use libToast.success(), libToast.error(), libToast.warning(), or libToast.info() instead.
// This function is maintained for backward compatibility only.
function Lib_ToonTopNotificatie( cTekst, bFixed, cColor, cTextColor ) {
	console.warn('Lib_ToonTopNotificatie is deprecated. Use libToast.success/error/warning/info() instead.');
	// Use libToast if available
	if (typeof libToast !== 'undefined') {
		// Map colors to toast types
		var type = 'info';
		if (cColor) {
			var lowerColor = cColor.toLowerCase();
			if (lowerColor.includes('green') || lowerColor.includes('success') || lowerColor === '#28a745' || lowerColor === '#4caf50') {
				type = 'success';
			} else if (lowerColor.includes('red') || lowerColor.includes('error') || lowerColor.includes('danger') || lowerColor === '#dc3545' || lowerColor === '#f44336') {
				type = 'error';
			} else if (lowerColor.includes('orange') || lowerColor.includes('warning') || lowerColor.includes('yellow') || lowerColor === '#ffc107' || lowerColor === '#ff9800') {
				type = 'warning';
			}
		}
		var duration = bFixed ? 0 : 2500;
		libToast[type](cTekst, { duration: duration });
		return;
	}

	// Fallback to old implementation if libToast not available
	var cEltName = "notification_top"

	bFixed = bFixed || false;

	// sometimes second time not shown...
	$("#" + cEltName).remove();

	var notElt = document.getElementsByTagName("body")[0].appendChild(document.createElement("div"));
	notElt.id = cEltName;

	if (cColor) {
		$("#" + cEltName).css("background-color", cColor);
	}
	if (cTextColor) {
		$("#" + cEltName).css("color", cTextColor);
	}
	if (!bFixed) {
		setTimeout(function() {$("#"+cEltName).slideUp();},2500);
	} else {
		cTekst = "<span class=close></span>" + cTekst;
	}
	$("#" + cEltName).html(cTekst).slideDown().click( function() {$("#"+cEltName).slideUp()} );
}

//   -			-			-			-			-			ALERTS

var ALERT_BUTTON_TEXT = "OK";

if(window.alert) {
	window.modal_alert = window.alert;				// save original alert for situations in which the modality is really needed (just use modal_alert() instead of alert()
	window.alert = function(txt,title,icon) {
		lib_alertbox(txt,title,icon);
	}
}

//	alert box - uses libAlert from lib-dialog.js
//
function lib_alertbox(txt, title, icon, buttontext) {
	// Use libAlert if available
	if (typeof libAlert !== 'undefined') {
		// Map icon to type
		var type = 'info';
		if (icon) {
			var lowerIcon = icon.toLowerCase();
			if (lowerIcon.includes('error') || lowerIcon.includes('danger')) {
				type = 'error';
			} else if (lowerIcon.includes('warning') || lowerIcon.includes('warn')) {
				type = 'warning';
			} else if (lowerIcon.includes('success') || lowerIcon.includes('ok')) {
				type = 'success';
			}
		}
		libAlert(txt, {
			title: title || 'Melding',
			type: type,
			confirmText: buttontext || ALERT_BUTTON_TEXT
		});
		return;
	}

	// Fallback to modal_alert if libAlert not available
	window.modal_alert(txt);
}

// return the top window containing a body element : think frames
//
// For cypress we need NOT to go to the top window (cypress uses .iframes-container).
// Get the appropriate document for appending popups/alerts.
// In Cypress test mode, always use current document (app document).
// Otherwise, try to find the topmost accessible document.
//
function lib_alertbox_getbody_doc_element() {
	// Check if we're in Cypress test mode
	var inCypress = typeof window.Cypress !== 'undefined' || typeof top.Cypress !== 'undefined';

	// In Cypress, always use current document - top.document would be Cypress runner
	if (inCypress) {
		return document;
	}

	var top_elt = document;

	try {
		top_elt = top.document;
		var body_elt = top_elt.getElementsByTagName("body")[0];
		if (body_elt) {
			return top_elt;
		}
	}
	catch (e){
	//	console.err("lib_alertbox_getbody_doc_element : " + e);
	}
	// 2e poging
	try {
		if (parent) {
			top_elt = parent.document;
			var body_elt = top_elt.getElementsByTagName("body")[0];
			if (body_elt) {
				return top_elt;
			}
		}
	}
	catch (e){
		// console.err("lib_alertbox_getbody_doc_element, 2e poging : " + e);
	}
	// 3e poging
	try {
		if (top) {
			top_elt = top.document;
			var body_elt = top_elt.getElementsByTagName("body")[0];
			if (body_elt) {
				return top_elt;
			}
		}
	}
	catch (e) {
//		console.err("lib_alertbox_getbody_doc_element : " + e);
//		huidige document dan maar
		return document;
	}
	// if all else fails
	return document;
}



function lib_getAbsoluteOffsetTop(obj) {
    var top = obj.offsetTop;
    var parent = obj.offsetParent;
    while (parent && parent != document.body) {
     	top += (parent.offsetTop - parent.scrollTop);
     	parent = parent.offsetParent;
    }
    return top;
}

function lib_getAbsoluteOffsetLeft(obj) {
    var left = obj.offsetLeft;
    var parent = obj.offsetParent;
    while (parent && parent != document.body) {
     	left += (parent.offsetLeft - parent.scrollLeft);
     	parent = parent.offsetParent;
    }
    return left;
}


//	retrieves the scroll top
//
function lib_scrolltop() {
	if (document.body.scrollTop>0) {
		return document.body.scrollTop;	// most likely IE7
	} else {
		return (window.pageYOffset ? window.pageYOffset : document.getElementsByTagName("html")[0].scrollTop);
	}
}

//   -			-			-			-			-			WINDOWS

//  Old-school popup
//
function lib_OpenPopupCentered(adres, naam, win_width, win_height, title) {
	var params = ''

	try {
		var left   = Math.round( Math.max(1, (lib_top_screen_width()  - win_width )/2) );
		var top    = Math.round( Math.max(1, (lib_top_screen_height() - win_height)/2) );
		params = 'top='+top.toString()+', left='+left.toString();
	}
	catch (e) {
		params = 'top=1, left=1'
	}
    params = params + ', width=' + win_width.toString() + ', height = ' + win_height.toString() + ', directories=no, location=no, menubar=no, resizable=no, scrollbars=no, status=no, toolbar=no';
	newwin=window.open(adres,naam, params);
	if (window.focus) {newwin.focus()}
	return newwin;
}


// 	Open a centered DIV
//
// 	new: win_content to add content instead of an iframe
//
function lib_OpenWindowCentered(adres, naam, win_width, win_height, title, win_content) {
	var mObj = null;
	try {
		// Fix relative URLs when using clean URLs
		// form.php?... should resolve to /cma/form.php?... not /cma/form/xxx/form.php?...
		if (adres && !adres.startsWith('/') && !adres.startsWith('http')) {
			adres = '/cma/' + adres;
		}
		var top_elt = lib_alertbox_getbody_doc_element();
		if (top_elt.body) {
			// bepaal nieuw vensternummer
			lib_win_counter = lib_OpenWindowGetNextId();

			var outer_width  = win_width  + 2;
			var outer_height = win_height + lib_caption_height + 2;

			var windowId = "__lib_win" + lib_win_counter.toString();
			// Get z-index from unified manager
			var zIndex = lib_zindex_manager.push(windowId, 'window');

			var mObj = top_elt.getElementsByTagName("body")[0].appendChild(top_elt.createElement("div"));
			mObj.id			  		= windowId;
			mObj.className	= "lib_window_container";
			// Container is now fullscreen via CSS with its own backdrop - eliminates fader z-index sync issues
			mObj.style.zIndex 		= zIndex;

			// Click on backdrop (container) closes the window
			mObj.addEventListener('click', function(e) {
				if (e.target === mObj) {
					lib_OpenWindowCenteredClose();
				}
			});

			// note the webkit items; scrolling iframe on ios is a bitch..
			// Dialog size is set inline, container is fullscreen via CSS
			mObj.innerHTML 	= "<div class=\"lib_window_dialog\" style=\"width:" + outer_width + "px;height:" + outer_height + "px" + (win_content ? ';background-image:url()' : '') + "\"><div class=\"lib_window_caption\" id=\"lib_window_caption_"+lib_win_counter.toString()+"\">" +
				"<a href=\"javascript:lib_OpenWindowCenteredClose()\" title=\"Sluit venster\"><div class=\"lib_window_close\"></div></a>" +
				"<div class=\"lib_window_max\"  title=\"Vergroot/Verklein venster\" onclick=\"lib_OpenWindowCenteredMax()\"></div>" +
				(title ? "<div class=\"lib_window_caption_title\">" + title.firstUppercase() + "</div>" : "") + "</div>" +
                (win_content ? "<div class=\"popup-content\" style=\"height:calc(100% - 34px);overflow:auto;-webkit-overflow-scrolling:touch;\">" + win_content + "</div>" : "<div style=\"height:100%;width:100%;-webkit-overflow-scrolling:touch;overflow:auto\"><iframe id=\"__lib_win_iframe_" + lib_win_counter.toString() + "\" class=\"popup-content\" style=\"width:calc(100%);height:calc(100% - 34px)\" frameborder=0 src=\'"+adres+"\'></iframe></div>" ) +
				"</div>";

			lib_screen_fade( );

			// Drag the dialog (not container) by its caption bar
			// Dialog has position:relative so top/left changes move it within the flex container
			var dialogElt = mObj.querySelector('.lib_window_dialog');
			var captionElt = top_elt.getElementById("lib_window_caption_" + lib_win_counter.toString());
			if (dialogElt && captionElt) {
				dragElement(dialogElt, captionElt);
			}

			// Check content height and adjust dialog if content is smaller than container
			// libLog.log('lib_OpenWindowCentered: win_content =', !!win_content, 'win_height =', win_height);
			if (win_content) {
				// Embedded HTML content - check immediately after DOM is ready
				setTimeout(function() {
					// libLog.log('lib_OpenWindowCentered: checking embedded content height');
					var dialog = mObj.querySelector('.lib_window_dialog');
					// libLog.log('lib_OpenWindowCentered: dialog =', dialog);
					var contentDiv = dialog ? dialog.querySelector('div[style*="overflow:auto"]') : null;
					// libLog.log('lib_OpenWindowCentered: contentDiv =', contentDiv);
					if (dialog && contentDiv) {
						var contentHeight = contentDiv.scrollHeight;
						var containerHeight = win_height;
						// libLog.log('lib_OpenWindowCentered: contentHeight =', contentHeight, 'containerHeight =', containerHeight);

						// If content is smaller than container, adjust to auto height
						if (contentHeight < containerHeight - 50) {
							// libLog.log('lib_OpenWindowCentered: content is smaller, resizing');
							dialog.style.height = 'auto';
							contentDiv.style.height = 'auto';
							// libLog.log('lib_OpenWindowCentered: dialog resized to auto height');
						} else {
							// libLog.log('lib_OpenWindowCentered: content is NOT smaller, no resize needed');
						}
					} else {
						// libLog.log('lib_OpenWindowCentered: dialog or contentDiv not found');
					}
				}, 50);
			} else {
				// Iframe content - check after iframe loads
				var iframe = top_elt.getElementById("__lib_win_iframe_" + lib_win_counter.toString());
				// libLog.log('lib_OpenWindowCentered: iframe =', iframe);
				if (iframe) {
					iframe.onload = function() {
						// libLog.log('lib_OpenWindowCentered: iframe onload fired');
						try {
							var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
							var contentHeight = iframeDoc.body.scrollHeight;
							var containerHeight = win_height;
							// libLog.log('lib_OpenWindowCentered: iframe contentHeight =', contentHeight, 'containerHeight =', containerHeight);

							// Update caption title from iframe's document.title if caption is empty
							var captionTitle = mObj.querySelector('.lib_window_caption_title');
							if (!captionTitle && iframeDoc.title) {
								var caption = mObj.querySelector('.lib_window_caption');
								if (caption) {
									var titleDiv = document.createElement('div');
									titleDiv.className = 'lib_window_caption_title';
									titleDiv.textContent = iframeDoc.title;
									caption.appendChild(titleDiv);
								}
							}

							// If content is smaller than container, adjust to auto height
							if (contentHeight < containerHeight - 50) {
								// libLog.log('lib_OpenWindowCentered: iframe content is smaller, resizing');
								var dialog = mObj.querySelector('.lib_window_dialog');
								if (dialog) {
									dialog.style.height = 'auto';
									// libLog.log('lib_OpenWindowCentered: iframe dialog resized to auto height');
								}
							} else {
								// libLog.log('lib_OpenWindowCentered: iframe content is NOT smaller, no resize needed');
							}
						} catch(e) {
							// libLog.log('lib_OpenWindowCentered: iframe error (cross-origin?)', e);
						}
					};
				}
			}

		} else {
			lib_OpenPopupCentered(adres, naam, win_width, win_height, title);
		}
	}
	catch (e) {
		if (mObj) { mObj.innerHTML='';mObj.style.visibility = 'hidden';}
		lib_screen_fade();
		lib_OpenPopupCentered(adres, naam, win_width, win_height, title);
	}
}


function lib_OpenWindowCount() {
	// Count traditional popup windows - check all possible slots (windows may have gaps)
	var top_elt = lib_alertbox_getbody_doc_element();
	var popupCount = 0;
	for (var i = 1; i <= 20; i++) {
		if (top_elt.getElementById("__lib_win" + i.toString())) {
			popupCount++;
		}
	}

	// Also count sidepanels (if user prefers sidepanels)
	var sidepanelCount = top_elt.querySelectorAll('.lib_sidepanel_container').length;

	return popupCount + sidepanelCount;
}

function lib_OpenWindowGetElement( iCounter ) {
	var top_elt = lib_alertbox_getbody_doc_element();
	if (!iCounter) {
		// Backward compat: find topmost window when no argument
		return lib_OpenGetTopmostWindow();
	}
	return top_elt.getElementById("__lib_win" + iCounter.toString());
}

/**
 * Get the topmost (highest numbered) existing popup window element
 * Handles gaps in window numbering (e.g., win2 exists but win1 was closed)
 */
function lib_OpenGetTopmostWindow() {
	var top_elt = lib_alertbox_getbody_doc_element();
	// Count down from max to find highest numbered existing window
	for (var i = 20; i >= 1; i--) {
		var el = top_elt.getElementById("__lib_win" + i.toString());
		if (el) return el;
	}
	return null;
}

/**
 * Get the next available window ID for creating a new window
 * Returns highest existing window number + 1
 */
function lib_OpenWindowGetNextId() {
	var top_elt = lib_alertbox_getbody_doc_element();
	// Find highest numbered existing window
	for (var i = 20; i >= 1; i--) {
		if (top_elt.getElementById("__lib_win" + i.toString())) {
			return i + 1;
		}
	}
	return 1; // No windows exist, start at 1
}


// 	Maximize
//
function lib_OpenWindowCenteredMax() {
	var container = lib_OpenWindowGetElement();
	if (!container) return;

	// Target the dialog inside the fullscreen container
	var dialog = container.querySelector('.lib_window_dialog');
	if (!dialog) return;

	var $container = $(container);
	var $dialog = $(dialog);

	// is it already maximized? Restore original coordinates
	if ($container.hasClass("maximized")) {
		// Restore saved values from dialog
		$dialog.css("width", $dialog.attr("data-save-width"));
		$dialog.css("height", $dialog.attr("data-save-height"));
		$dialog.css("max-width", "");
		$dialog.css("max-height", "");
		$container.removeClass("maximized");
	} else {
		// Save current dialog values
		$dialog.attr("data-save-width", $dialog.css("width"));
		$dialog.attr("data-save-height", $dialog.css("height"));
		// Maximize dialog to fill container (with padding)
		$dialog.css({
			"width": "calc(100% - 20px)",
			"height": "calc(100% - 20px)",
			"max-width": "100%",
			"max-height": "100%"
		});
		$container.addClass("maximized");
	}
}

//
// Check if popup content has unsaved changes
//
function lib_OpenWindowHasUnsavedChanges() {
	try {
		var lw = lib_OpenGetTopmostWindow();
		if (lw) {
			// Check iframe content for unsaved changes
			var iframe = lw.querySelector('iframe');
			if (iframe && iframe.contentWindow) {
				// Check for CMA form controller isDirty (window.cmaForm)
				if (iframe.contentWindow.cmaForm && iframe.contentWindow.cmaForm.isDirty) {
					return true;
				}
				// Check for legacy form controller isDirty (window.formController)
				if (iframe.contentWindow.formController && iframe.contentWindow.formController.isDirty) {
					return true;
				}
				// Check for inline editor isDirty
				if (iframe.contentWindow.inlineEdit && iframe.contentWindow.inlineEdit.isDirty) {
					return true;
				}
				// Check for global isDirty flag
				if (iframe.contentWindow.isDirty) {
					return true;
				}
			}
		}
	} catch(e) {
		// Cross-origin or other error - assume no changes
	}
	return false;
}

//
// Close popup with optional unsaved changes check
//
async function lib_OpenWindowCenteredClose(skipConfirm) {
	// Default to true if skipConfirm is null or undefined
	if (skipConfirm == null) {
		skipConfirm = true;
	}

	// Check for unsaved changes unless skipConfirm is true
	if (!skipConfirm && lib_OpenWindowHasUnsavedChanges()) {
		var confirmed = await libConfirm('Je hebt niet-opgeslagen wijzigingen.', {
			title: 'Niet-opgeslagen wijzigingen',
			confirmText: 'Verlaat scherm',
			cancelText: 'Blijf op scherm',
			type: 'warning'
		});
		if (!confirmed) {
			return; // User cancelled, don't close
		}
	}

	try {
		// zoek het laatste window op!
		var top_elt = lib_alertbox_getbody_doc_element();
		var alertbox = top_elt.getElementById("alertBox");
		if (alertbox) {
			$(alertbox).remove();
		} else {

			var lw = lib_OpenGetTopmostWindow();
			if (!lw) {

				if( self != top ) {
					lw = window.parent.lib_OpenWindowCenteredClose(true); // Pass skipConfirm=true to parent
					// libLog.log("lib_OpenWindowCenteredClose: calling parent to close the window");
				} else {
					// libLog.log("lib_OpenWindowCenteredClose: Doing nothing");
				}

			} else {
				var windowId = "__lib_win" + lib_win_counter.toString();
				// libLog.log("lib_OpenWindowCenteredClose: Closing window with id", windowId);
				// Remove from z-index manager
				lib_zindex_manager.pop(windowId);
				$(lw).remove();
				// libLog.log("lib_OpenWindowCenteredClose: Closing window success");
				lib_screen_fade();
			}

		}

		if (dialog_center_interval) {
			clearInterval( dialog_center_interval );
			dialog_center_interval = null;
		}

		lib_screen_fade();
	}
	catch(e) {
		$("#alertBox").remove();
		// Clean up all possible window elements
		for (var i = 1; i <= 10; i++) {
			$("#__lib_win" + i).remove();
		}
		// Also remove fader
		$("#" + fader_name).remove();
		lib_screen_fade();
	}
}

// 	Open a centered window showing an image (usage : <a href="groteplaatje.jpg" onclick='lib_image_window_ImageZoom('groteplaatje.jpg');return false">)
//
function lib_window_ImageZoom( imagepath, sTitle ) {
	var top_elt = lib_alertbox_getbody_doc_element();
	var large_image = new Image();
    large_image.onload = function () {
		var win_width = Math.min( large_image.width, $(top_elt).width()-20);
		var win_height = Math.min( large_image.height, $(top_elt).height()-20);
		lib_OpenWindowCentered ("about:blank", "", win_width, win_height, (sTitle ? sTitle : win_height>200?"Vergroot venster":"Beeld"),"<script src=/library/library.min.js></script><img src='"+imagepath+"' onclick='javascript:lib_OpenWindowCenteredClose()' alt='Sluit venster'>");
	}
	large_image.onerror = function () {
		libAlert("error loading '" + imagepath +"'");
	}
	// should be placed below onload for IE
	large_image.src = imagepath;
}

//   -			-			-			-			-			COOKIES

function lib_createCookie(name,value,days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toUTCString();
	}
	else var expires = "";
	document.cookie = name+"="+value+expires+"; path=/";
}

function lib_readCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}

function lib_eraseCookie(name) {
	lib_createCookie(name,"",-1);
}

//   -			-			-			-			-			LOCAL STORAGE

// Stores a value in localStorage with optional prefix
function lib_storage_set(name, value, prefix) {
	var key = (prefix ? prefix + '_' : '') + name;
	localStorage.setItem(key, value);
}

// Retrieves a value from localStorage
function lib_storage_get(name, prefix) {
	var key = (prefix ? prefix + '_' : '') + name;
	return localStorage.getItem(key);
}

// Removes a value from localStorage
function lib_storage_remove(name, prefix) {
	var key = (prefix ? prefix + '_' : '') + name;
	localStorage.removeItem(key);
}

// Debug panel for localStorage - shows all stored values with optional prefix filter
function lib_storage_debug(prefix) {
	var debugId = 'lib_storage_debug';
	var existing = document.getElementById(debugId);
	if (existing) {
		existing.parentNode.removeChild(existing);
	}

	var panel = document.createElement('div');
	panel.id = debugId;
	panel.style.cssText = 'position:fixed;bottom:10px;right:10px;width:400px;max-height:300px;overflow:auto;background:#fff;border:2px solid #333;border-radius:5px;font-family:monospace;font-size:var(--font-size-sm);z-index:999999;box-shadow:0 2px 10px rgba(0,0,0,0.3);';

	var header = '<div style="background:#333;color:#fff;padding:8px;font-weight:bold;">' +
		'localStorage Debug' + (prefix ? ' (prefix: ' + prefix + ')' : '') +
		'<span onclick="document.getElementById(\'' + debugId + '\').remove()" style="float:right;cursor:pointer;padding:0 5px;">✕</span>' +
		'<span onclick="lib_storage_debug(\'' + (prefix || '') + '\')" style="float:right;cursor:pointer;padding:0 10px;">↻</span>' +
		'</div>';

	var content = '<div style="padding:8px;">';
	var count = 0;

	for (var i = 0; i < localStorage.length; i++) {
		var key = localStorage.key(i);
		if (!prefix || key.indexOf(prefix + '_') === 0) {
			var value = localStorage.getItem(key);
			var displayValue = value && value.length > 50 ? value.substring(0, 50) + '...' : value;
			content += '<div style="margin-bottom:6px;padding:4px;background:#f5f5f5;border-radius:3px;">' +
				'<strong style="color:#0066cc;">' + key + '</strong><br>' +
				'<span style="color:#666;">' + (displayValue || '(empty)') + '</span>' +
				'<span onclick="lib_storage_remove(\'' + key + '\');lib_storage_debug(\'' + (prefix || '') + '\')" style="float:right;cursor:pointer;color:red;font-size:var(--font-size-2xs);">delete</span>' +
				'</div>';
			count++;
		}
	}

	if (count === 0) {
		content += '<div style="color:#999;text-align:center;padding:20px;">No items found</div>';
	}

	content += '</div>';
	panel.innerHTML = header + content;
	document.body.appendChild(panel);
}

//   -			-			-			-			-			SCREEN METRICS

// returns the VISIBLE part of the window
//
function lib_window_height () {
	return window.innerHeight ? window.innerHeight : document.getElementsByTagName("html")[0].offsetHeight;
}

// returns the VISIBLE part of the window
//
function lib_window_width () {
	return window.innerWidth ? window.innerWidth : document.getElementsByTagName("html")[0].offsetWidth;
}

// returns the TOTAL height of the page
//
function lib_screen_height () {
	return window.innerHeight ? window.innerHeight : document.body.clientHeight;
}

// returns the TOTAL width of the page
//
function lib_screen_width () {
	return window.innerWidth ? window.innerWidth : document.body.clientWidth;
}

// returns the TOTAL width of the page
//
function lib_top_screen_width () {
	var elt = window;
	try {
		elt = window.top.window;
	}
	catch(e) {}
	return elt.innerWidth ? elt.innerWidth : elt.document.body.clientWidth;
}

function lib_top_screen_height () {
	var elt = window;
	try {
		elt = window.top.window;
	}
	catch(e) {}
	return elt.innerHeight ? elt.innerHeight : elt.document.body.clientHeight;
}

// NOTE: lib_getAbsoluteOffsetTop and lib_getAbsoluteOffsetLeft are defined earlier (lines ~706-724)
//   -			-			-			-			-			SCREEN FADER

function lib_screen_fade( ) {
	// Each window container now has its own backdrop via CSS (position: fixed; inset: 0; background-color)
	// This eliminates z-index sync issues between fader and windows
	// We only need to keep the window counter in sync
	lib_OpenWindowCount();

	// Remove any legacy fader element that might exist
	try {
		var top_elt = lib_alertbox_getbody_doc_element();
		var fader = top_elt.getElementById(fader_name);
		if (fader) {
			$(fader).remove();
		}
	} catch (e) {
		// Ignore errors
	}
}


//   -			-			-			-			-			STRING FUNCTIONS -> TODO Put them all in prototype structure : Way better!


String.prototype.lib_contains_numbers = function () {
	return /\d/.test(this)
}

String.prototype.lib_trim = function () {
    return this.replace(/^\s*/, "").replace(/\s*$/, "");
}

String.prototype.lib_trim_all = function () {
	var strOrig = this;
    while (strOrig.search(" ")!=-1) {
	    strOrig=strOrig.replace(" ", "");
    }
    return strOrig;
}

String.prototype.firstUppercase = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}

function lib_dropLeadingZeros(num) {
	while (num.charAt(0) == "0") {
		newTerm = num.substring(1, num.length);
		num = newTerm;
	}

	if (num == "") num = "0";
	return num;
}

function lib_left(str, n){
	if (n <= 0)
	    return "";
	else if (n > String(str).length)
	    return str;
	else
	    return String(str).substring(0,n);
}

function lib_right(str, n){
    if (n <= 0)
       return "";
    else if (n > String(str).length)
       return str;
    else {
       var iLen = String(str).length;
       return String(str).substring(iLen, iLen - n);
    }
}

function lib_htmlencode(text)
{
    return text.replace(/&/g, '&amp').replace(/'/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

//   -			-			-			-			-			Custom control stuff
function control_createshim(control_divname) {return;}
function lib_control_deleteshim(control_divname) {return;}
function lib_activeX_activate() {
    var theObjects = document.getElementsByTagName("object");
    for (var i = 0; i < theObjects.length; i++) {
        theObjects[i].outerHTML = theObjects[i].outerHTML;
    }
}

function lib_DOM_getElementsByClass( searchClass,node,tag )
{
	var classElements = new Array();
	if (node == null) node = document;
	if (tag == null) tag = '*';
	var els = node.getElementsByTagName(tag);
	var elsLen = els.length;
	var pattern = new RegExp("(^|\\s)"+searchClass+"(\\s|$)");
	for (i = 0, j = 0; i < elsLen; i++) {
		if (pattern.test(els[i].className)) {
			classElements[j] = els[i];
			j++;
		}
	}
	return classElements;
}

//	Searches a single dimension array for a value, returns -1 if not found, otherwise the index within the array
//
function lib_array_find(arr, search_value) {
	var fld_num = 0;
	while (fld_num < arr.length) {
	  if (arr[fld_num].toLowerCase()==search_value.toLowerCase()) {
		 return fld_num;
	  }
	  fld_num+=1;
	}
	return -1;
}

//	Splits an string of values into an array
//
function lib_array_split(stringvalue) {
	if (stringvalue) {
		if (stringvalue.indexOf(';')!=-1) {
			return stringvalue.split(";");
		} else {
			return stringvalue.split(",");
		}
	}
}

function lib_IEVersion()
// Returns the version of Windows Internet Explorer or a -1
// (indicating the use of another browser).
{
   var rv = -1; // Return value assumes failure.
   if (navigator.appName == 'Microsoft Internet Explorer')
   {
      var ua = navigator.userAgent;
      var re  = new RegExp("MSIE ([0-9]{1,}[\.0-9]{0,})");
      if (re.exec(ua) != null)
         rv = parseFloat( RegExp.$1 );
   }
   return rv;
}

function dragElement(elmnt, grabElt) {
    var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;

    if (grabElt) {
        // if present, the grab Element (window title typically) is where you move the DIV from:
        grabElt.onmousedown = dragMouseDown;
    } else {
        // otherwise, move the DIV from anywhere inside the DIV:
        elmnt.onmousedown = dragMouseDown;
    }

    function dragMouseDown(e) {
        e = e || top.window.event;

        // Don't start drag if clicking on close or maximize buttons
        var target = e.target || e.srcElement;
        if (target.closest && (target.closest('.lib_window_close') || target.closest('.lib_window_max') || target.closest('a'))) {
            return; // Let the button/link handle the click
        }

        e.preventDefault();
        // get the mouse cursor position at startup:
        pos3 = e.clientX;
        pos4 = e.clientY;

        // Disable pointer events on iframes to prevent them from capturing mouse during drag
        var iframes = elmnt.querySelectorAll('iframe');
        for (var i = 0; i < iframes.length; i++) {
            iframes[i].style.pointerEvents = 'none';
        }

        top.document.onmouseup = closeDragElement;
        // call a function whenever the cursor moves:
        top.document.onmousemove = elementDrag;
    }

    function elementDrag(e) {
        e = e || top.window.event;
        e.preventDefault();
        // calculate the new cursor position:
        pos1 = pos3 - e.clientX;
        pos2 = pos4 - e.clientY;
        pos3 = e.clientX;
        pos4 = e.clientY;
        // set the element's new position:
        elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
        elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
    }

    function closeDragElement() {
        // stop moving when mouse button is released:
        top.document.onmouseup = null;
        top.document.onmousemove = null;

        // Re-enable pointer events on iframes
        var iframes = elmnt.querySelectorAll('iframe');
        for (var i = 0; i < iframes.length; i++) {
            iframes[i].style.pointerEvents = '';
        }
    }
}

// 	Drag object from www.youngpup.net
//
var Drag = {

	obj : null,

	init : function(o, oRoot, minX, maxX, minY, maxY, bSwapHorzRef, bSwapVertRef, fXMapper, fYMapper)
	{
		o.onmousedown	= Drag.start;

		o.hmode			= bSwapHorzRef ? false : true ;
		o.vmode			= bSwapVertRef ? false : true ;

		o.root = oRoot && oRoot != null ? oRoot : o ;

		if (o.hmode  && isNaN(parseInt(o.root.style.left ))) o.root.style.left   = "0px";
		if (o.vmode  && isNaN(parseInt(o.root.style.top  ))) o.root.style.top    = "0px";
		if (!o.hmode && isNaN(parseInt(o.root.style.right))) o.root.style.right  = "0px";
		if (!o.vmode && isNaN(parseInt(o.root.style.bottom))) o.root.style.bottom = "0px";

		o.minX	= typeof minX != 'undefined' ? minX : null;
		o.minY	= typeof minY != 'undefined' ? minY : null;
		o.maxX	= typeof maxX != 'undefined' ? maxX : null;
		o.maxY	= typeof maxY != 'undefined' ? maxY : null;

		o.xMapper = fXMapper ? fXMapper : null;
		o.yMapper = fYMapper ? fYMapper : null;

		o.root.onDragStart	= new Function();
		o.root.onDragEnd	= new Function();
		o.root.onDrag		= new Function();
	},

	start : function(e)
	{
		var o = Drag.obj = this;
		e = Drag.fixE(e);
		if (e) {
			var y = parseInt(o.vmode ? o.root.style.top  : o.root.style.bottom);
			var x = parseInt(o.hmode ? o.root.style.left : o.root.style.right);
			o.root.onDragStart(x, y);

			o.lastMouseX	= e.clientX;
			o.lastMouseY	= e.clientY;

			if (o.hmode) {
				if (o.minX != null)	o.minMouseX	= e.clientX - x + o.minX;
				if (o.maxX != null)	o.maxMouseX	= o.minMouseX + o.maxX - o.minX;
			} else {
				if (o.minX != null) o.maxMouseX = -o.minX + e.clientX + x;
				if (o.maxX != null) o.minMouseX = -o.maxX + e.clientX + x;
			}

			if (o.vmode) {
				if (o.minY != null)	o.minMouseY	= e.clientY - y + o.minY;
				if (o.maxY != null)	o.maxMouseY	= o.minMouseY + o.maxY - o.minY;
			} else {
				if (o.minY != null) o.maxMouseY = -o.minY + e.clientY + y;
				if (o.maxY != null) o.minMouseY = -o.maxY + e.clientY + y;
			}

			document.onmousemove	= Drag.drag;
			document.onmouseup		= Drag.end;
		}
		return false;
	},

	drag : function(e)
	{
		e = Drag.fixE(e);
		var o = Drag.obj;

		if (e) {
			var ey	= e.clientY;
			var ex	= e.clientX;
			var y = parseInt(o.vmode ? o.root.style.top  : o.root.style.bottom);
			var x = parseInt(o.hmode ? o.root.style.left : o.root.style.right);
			var nx, ny;

			if (o.minX != null) ex = o.hmode ? Math.max(ex, o.minMouseX) : Math.min(ex, o.maxMouseX);
			if (o.maxX != null) ex = o.hmode ? Math.min(ex, o.maxMouseX) : Math.max(ex, o.minMouseX);
			if (o.minY != null) ey = o.vmode ? Math.max(ey, o.minMouseY) : Math.min(ey, o.maxMouseY);
			if (o.maxY != null) ey = o.vmode ? Math.min(ey, o.maxMouseY) : Math.max(ey, o.minMouseY);

			nx = x + ((ex - o.lastMouseX) * (o.hmode ? 1 : -1));
			ny = y + ((ey - o.lastMouseY) * (o.vmode ? 1 : -1));

			if (o.xMapper)		nx = o.xMapper(y)
			else if (o.yMapper)	ny = o.yMapper(x)

			Drag.obj.root.style[o.hmode ? "left" : "right"] = nx + "px";
			Drag.obj.root.style[o.vmode ? "top" : "bottom"] = ny + "px";
			Drag.obj.lastMouseX	= ex;
			Drag.obj.lastMouseY	= ey;

			Drag.obj.root.onDrag(nx, ny);
		}
		return false;
	},

	end : function()
	{
		document.onmousemove = null;
		document.onmouseup   = null;
		Drag.obj.root.onDragEnd(	parseInt(Drag.obj.root.style[Drag.obj.hmode ? "left" : "right"]),
									parseInt(Drag.obj.root.style[Drag.obj.vmode ? "top" : "bottom"]));
		Drag.obj = null;
	},

	fixE : function(e)
	{
		if (typeof e == 'undefined') e = window.event;
		if (e) {
			if (typeof e.layerX == 'undefined') e.layerX = e.offsetX;
			if (typeof e.layerY == 'undefined') e.layerY = e.offsetY;
		}
		return e;
	}
};

var tooltip=function(){
	var id = 'tooltip';
	var top = -20;
	var left = 20;
	var maxw = 400;
	var minh = 21;
	var speed = 10;
	var timer = 20;
	var endalpha = 95;
	var alpha = 0;
	var speed = 200;
	var tt = null,c,h;

	return{
		show:function(v,w,x,y,height){
			if(!tt){
				tt = document.createElement('div');tt.setAttribute('id',id);tt.style.display='none';
				c = document.createElement('div');c.setAttribute('id','tt_arrow');
				tt.appendChild(c);
				c = document.createElement('div');c.setAttribute('id','tt_content');
				tt.appendChild(c);
				document.body.appendChild(tt);

			}
//			tt.style.display = 'block';
			c.innerHTML = v;
			tt.style.width = w ? w + 'px' : 'auto';
			tt.style.zIndex=fader_zindex-3;
			if (height) {
				if (height<minh) height=minh;
				tt.style.height = height + 'px';
			}
			if (!x||!y) {
//				document.onmousemove = this.pos;
			} else {
				tt.style.top = y.toString() + 'px';
				tt.style.left = x.toString() + 'px';
			}
			if(tt.offsetWidth > maxw){tt.style.width = maxw + 'px'}
			h = parseInt(tt.offsetHeight) + top;
			if (jQueryLoaded) {
				$("#tooltip").hide();
				$("#tooltip").fadeIn( speed );
			} else {
				tt.style.display = 'block';
			}
		},
		pos:function(e){
			var u, l;
			if (typeof event != 'undefined') {
				u = event.clientY ? event.clientY + document.documentElement.scrollTop : e.pageY;
				l = event.clientX ? event.clientX + document.documentElement.scrollLeft : e.pageX;
			} else {
				u = e.pageY;
				l = e.pageX;
			}
			tt.style.top = (u - h) + 'px';
			tt.style.left = (l + left) + 'px';
		},
		fade:function(d){
			if (jQueryLoaded) {
				if (d == -1) {
					$("#tooltip").fadeOut( speed );
				} else {
					$("#tooltip").fadeIn( speed );
				}
			} else {
				if(d == -1){
					if (tt) {tt.style.display='none'}
				}
			}
		},
		hide:function(){
			if (jQueryLoaded) {
				$("#tooltip").fadeOut( speed );
			} else {
				if (tt) {tt.style.display='none'}
			}
		}
	};
}();

//   -			-			-			-			-			Social sharing
//

function lib_share_facebook( url, title, text, desc ) {
	return "http://www.facebook.com/sharer.php?s=100&p[title]="+encodeURI(title)+"&p[summary]=" + (text ? text : desc) + "&p[url]=" + encodeURI(url)
}

function lib_share_linkedin( url, title, text, desc ) {
	// + (title ? "&title=" + lib_share_EncodeUrl(title) : "") +
	return "https://www.linkedin.com/shareArticle?mini=true" + ( text || desc ? "&summary=" + lib_share_EncodeUrl((text ? text : desc)) : "") + "&url=" + lib_share_EncodeUrl( url )
}
function lib_share_twitter( url, title, text, desc ) {
	return "https://twitter.com/intent/tweet?text=" + encodeURI(title) + "&url=" + encodeURI(url)
}

//
function lib_share_EncodeUrl( strBaseUrl ) {
	var retval = strBaseUrl;

	retval = retval.replace(/\=/gi,"%3D");
	retval = retval.replace(/\?/gi,"%3F");
	retval = retval.replace(/&/gi,"%26");
	retval = retval.replace(/\//gi,"%2F");
	retval = retval.replace(/:/gi,"%3A");
	retval = retval.replace(/ /gi,"%20");

	return retval;
}

//
// http://www.html-entities.org/ , used in detail page of CMA and editable draaiboek of RP
//
function lib_encode_to_html(sInput) {
    const map = {
        '&nbsp;': ' ',
        '• ': '<li>',
        '•': '<li>',
        'ä': '&auml;', 'á': '&aacute;', 'à': '&agrave;', 'ã': '&atilde;',
        'Ä': '&Auml;', 'Á': '&Aacute;', 'À': '&Agrave;', 'Ã': '&Atilde;',
        'ë': '&euml;', 'é': '&eacute;', 'è': '&egrave;', 'ê': '&ecirc;',
        'Ë': '&Euml;', 'É': '&Eacute;', 'È': '&Egrave;',
        'ï': '&iuml;', 'í': '&iacute;', 'ì': '&igrave;', 'î': '&icirc;',
        'Ï': '&Iuml;', 'Í': '&Iacute;', 'Ì': '&Igrave;',
        'ö': '&ouml;', 'ó': '&oacute;', 'ò': '&ograve;', 'ô': '&ocirc;',
        'Ö': '&Ouml;', 'Ó': '&Oacute;', 'Ò': '&Ograve;',
        'ü': '&uuml;', 'ú': '&uacute;', 'ù': '&ugrave;',
        'Ü': '&Uuml;', 'Ú': '&Uacute;', 'Ù': '&Ugrave;',
        'ç': '&ccedil;', 'ñ': '&ntilde;', 'Ñ': '&Ntilde;',
        '®': '&reg;', '©': '&copy;', '™': '&trade;',
        '€': '&euro;', '´': '&acute;', '‘': '&lsquo;', '’': '&rsquo;',
        '“': '&ldquo;', '”': '&rdquo;', '…': '&hellip;', 'ß': '&szlig;',
        '§': '&section;', '°': '&degree;', '¹': '&sup1;', '²': '&sup2;',
        '³': '&sup3;', '˜': '&tilde;', '%E2%80%93': '-'
    };

    // Build a regex that matches any key in the map
    const regex = new RegExp(Object.keys(map).join('|'), 'g');

    return sInput.replace(regex, match => map[match]);
}

// new version supporting minimal height
//
if (jQueryLoaded) {
	(function ( $ ) {
		$.fn.autoGrow = function(options) {
			return this.each(function() {
				var settings = jQuery.extend({
					extraLine: true,
					minHeight: 60,
				}, options);

				var createMirror = function(textarea) {
					if ( jQuery(textarea).attr("data-mirrored")!="true") {
						jQuery(textarea).after('<div class="autogrow-textarea-mirror"></div>');
						jQuery(textarea).attr("data-mirrored", "true")
					}
					return jQuery(textarea).next('.autogrow-textarea-mirror')[0];
				}

				var sendContentToMirror = function (textarea) {
					mirror.innerHTML = String(textarea.value)
						.replace(/&/g, '&amp;')
						.replace(/"/g, '&quot;')
						.replace(/'/g, '&#39;')
						.replace(/</g, '&lt;')
						.replace(/>/g, '&gt;')
						.replace(/ /g, '&nbsp;')
						.replace(/\n/g, '<br />') +
						(settings.extraLine? '&nbsp;<br/>&nbsp;' : '')
					;

					if (jQuery(textarea).height() != jQuery(mirror).height())
						jQuery(textarea).height( Math.max( settings.minHeight, jQuery(mirror).height()) );
					// added DS
					textarea.scrollTop = 0;
				}

				var growTextarea = function () {
					sendContentToMirror(this);
				}

				// Create a mirror
				var mirror = createMirror(this);

				// Style the mirror
				mirror.style.display = 'none';
				mirror.style.wordWrap = 'break-word';
				mirror.style.whiteSpace = 'normal';
				mirror.style.padding = jQuery(this).css('paddingTop') + ' ' +
					jQuery(this).css('paddingRight') + ' ' +
					jQuery(this).css('paddingBottom') + ' ' +
					jQuery(this).css('paddingLeft');
				mirror.style.width = jQuery(this).css('width');
				mirror.style.fontFamily = jQuery(this).css('font-family');
				mirror.style.fontSize = jQuery(this).css('font-size');
				mirror.style.lineHeight = jQuery(this).css('line-height');

				// Style the textarea, use auto in case resizing did not solve it.
				this.style.overflow = "auto";
				this.style.minHeight = settings.minHeight;

				// Bind the textarea's event
				this.onkeyup = growTextarea;

				// Fire the event for text already present
				sendContentToMirror(this);
			});
		};
	}( jQuery ));
}

// excelTableFilter (FilterMenu, FilterCollection, filtering_init) — removed, now handled by <lib-table> web component
function lib_Form_Scale_htmleditors( iCounter ) {
	var min_height = 20;
	var max_height = 500;

	var elts = $(".cke");
	if (elts.length>0 || iCounter<2) {
		elts.each( function() {
			 oBody =  $(this).find(".cke_wysiwyg_frame ").contents().find("body");
			 if (oBody[0]) {
				var elt =  $(this).find(".cke_contents");
				if (elt) {
					// top of contentblock editors should remain invisible
					if ($(elt).height()>min_height && $(elt).height()<max_height) {
						if (oBody[0].scrollHeight>$(elt).height()) {
							$(elt).height( Math.min( max_height, Math.max( oBody[0].scrollHeight, min_height) + 15) );
						}
					}
				}
			}
		});
		// eerste na .2 seconden, daarna .5 seconden, daarna iedere 1.5 of 5 seconden afhankelijk van het aantal
		window.setTimeout( function() { lib_Form_Scale_htmleditors( iCounter + 1) }, (iCounter==0 ? 200 : (iCounter==1 ? 500 : ( elts.length > 3 ? 5000 : 1500) ) ) );
	}
}

// lib_Table_UpdateView, filtering_init, lib_Table_CleanCopy — removed, now handled by <lib-table> web component

//   -			-			-			-			-			SIDEPANEL FUNCTIONS
//
// Sidepanel - sliding panel from the right side of the screen
// Alternative to popup dialogs, can contain iframes or HTML content
//

var lib_sidepanel_counter = 0;
var lib_sidepanel_stack = [];

/**
 * Get user preference for popup style
 * @returns {string} 'sidepanel' or 'popup'
 */
function lib_getPopupStylePreference() {
	try {
		return localStorage.getItem('cma_popup_style') || 'sidepanel';
	} catch (e) {
		return 'sidepanel';
	}
}

/**
 * Set user preference for popup style
 * @param {string} style 'sidepanel' or 'popup'
 */
function lib_setPopupStylePreference(style) {
	try {
		localStorage.setItem('cma_popup_style', style);
	} catch (e) {
		// localStorage not available
	}
}

/**
 * Open a sidepanel with iframe or HTML content
 * @param {string} url URL to load in iframe (use empty string for HTML content)
 * @param {string} name Panel identifier
 * @param {number} width Panel width in pixels
 * @param {string} title Panel title
 * @param {string} htmlContent Optional HTML content instead of iframe
 * @returns {HTMLElement} The sidepanel element
 */
function lib_OpenSidePanel(url, name, width, title, htmlContent) {
	// libLog.log('[lib_OpenSidePanel] Called with url:', url, 'name:', name, 'title:', title);
	var mObj = null;
	try {
		// Fix relative URLs when using clean URLs
		// form.php?... should resolve to /cma/form.php?... not /cma/form/xxx/form.php?...
		if (url && !url.startsWith('/') && !url.startsWith('http')) {
			// Relative URL - prepend /cma/ to ensure correct resolution
			url = '/cma/' + url;
			// libLog.log('[lib_OpenSidePanel] Converted relative URL to:', url);
		}
		var top_elt = lib_alertbox_getbody_doc_element();
		// libLog.log('[lib_OpenSidePanel] top_elt:', top_elt, 'body:', top_elt ? top_elt.body : 'N/A');
		if (top_elt.body) {
			// Use top window's counter to avoid ID conflicts when opening from iframe
			var topWindow = top_elt.defaultView || top_elt.parentWindow || window;
			if (typeof topWindow.lib_sidepanel_counter === 'undefined') {
				topWindow.lib_sidepanel_counter = 0;
			}
			topWindow.lib_sidepanel_counter++;
			var panelId = '__lib_sidepanel' + topWindow.lib_sidepanel_counter.toString();

			// Use top window's z-index manager directly (NOT lib_zindex_manager.push()).
			// See lib_zindex_manager doc block for why this bypasses getTopManager().
			var zIndexManager = (topWindow.lib_zindex_manager || lib_zindex_manager);
			var zIndex = zIndexManager.push(panelId, 'sidepanel');
			// libLog.log('[lib_OpenSidePanel] Created panel:', panelId, 'zIndex:', zIndex, 'stack depth:', zIndexManager.count());

			// Create backdrop - subtle darkening on the left side
			var backdrop = top_elt.getElementsByTagName("body")[0].appendChild(top_elt.createElement("div"));
			backdrop.id = panelId + '_backdrop';
			backdrop.className = 'lib_sidepanel_backdrop';
			backdrop.style.cssText = 'position:fixed;inset:0;background:transparent;z-index:' + (zIndex - 1) + ';opacity:0;transition:opacity 0.15s ease;';
			backdrop.onclick = function() { lib_CloseSidePanel(); };

			// Create panel container - stacked width for cascade effect
			// Each consecutive panel is narrower so underlying panels show on the left
			// Count existing sidepanels in DOM (more reliable than stack counter across iframes)
			var existingPanels = top_elt.querySelectorAll('.lib_sidepanel_container');
			var stackDepth = existingPanels ? existingPanels.length : 0;

			// Width reduction: first panel 85vw, then 80vw, 75vw, etc.
			// Larger reduction (5vw = ~50-100px) makes underlying panels clearly visible
			var baseWidth = 85;
			var widthReduction = stackDepth * 5;
			var panelWidth = Math.max(baseWidth - widthReduction, 50); // Minimum 50vw

			// Staggered animation delay based on stack depth
			var animDelay = stackDepth * 50; // 50ms per panel

			// libLog.log('[lib_OpenSidePanel] Stack depth:', stackDepth, 'Panel width:', panelWidth + 'vw');

			// Detect dark mode to prevent white flash
			// Check both the CMA dark-mode class AND the browser media query
			var htmlElement = top_elt.documentElement || document.documentElement;
			var isDarkMode = (htmlElement && htmlElement.classList.contains('dark-mode')) ||
				(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
			var panelBg = isDarkMode ? '#1e1e1e' : '#fff';
			var loadingBg = isDarkMode ? '#2d2d2d' : '#f5f5f5';
			var loadingColor = isDarkMode ? '#aaa' : '#666';
			var spinnerBorder = isDarkMode ? '#444' : '#ddd';

			mObj = top_elt.getElementsByTagName("body")[0].appendChild(top_elt.createElement("div"));
			mObj.id = panelId;
			mObj.className = 'lib_sidepanel_container';
			// Stacking top offset: first panel at header height, each subsequent adds toolbar height
			var topOffset = stackDepth === 0
				? 'var(--header-height)'
				: 'calc(var(--header-height) + ' + stackDepth + ' * var(--toolbar-height))';
			// All sidepanels are right-aligned (no cascade offset)
			// Width is clamped to available space: min(panelWidth vw, 100vw - sidebar-width) to prevent overlap with sidebar
			mObj.style.cssText = 'position:fixed;top:' + topOffset + ';right:0;bottom:0;width:min(' + panelWidth + 'vw, calc(100vw - var(--sidebar-width, 260px)));max-width:1400px;z-index:' + zIndex + ';background:' + panelBg + ';box-shadow:-8px 0 30px rgba(0,0,0,0.3),-2px 0 8px rgba(0,0,0,0.15);transform:translateX(100%);transition:transform 0.15s ease ' + animDelay + 'ms;display:flex;flex-direction:column;border-top-left-radius:8px;';

			// Panel header - matches cma-header height (50px)
			// Keep original title casing (Dutch conventions)
			// Note: Don't add 'wijzigen'/'toevoegen' here - the form controller's
			// updateSidepanelTitle() handles this correctly with proper singular form name
			var displayTitle = (title || '').trim();
			var header = '<div class="lib_sidepanel_header">' +
				'<div class="lib_sidepanel_title">' + displayTitle + '</div>' +
				'<button class="lib_sidepanel_maximize" onclick="lib_ToggleSidePanelMaximize(this)" title="Maximaliseren venstergrootte">' +
				'<span class="lnr lnr-frame-expand"></span></button>' +
				'<button class="lib_sidepanel_close" onclick="lib_CloseSidePanel()" title="Sluiten">' +
				'<span><span></span><span></span></span></button>' +
				'</div>';

			// Panel content - iframe hidden until loaded to prevent flash of empty content
			var content;
			var iframeId = panelId + '_iframe';
			if (htmlContent) {
				content = '<div class="lib_sidepanel_content">' + htmlContent + '</div>';
			} else {
				// Add loading spinner that shows while iframe loads (hidden initially, shown after 500ms delay)
				content = '<div class="lib_sidepanel_loading" id="' + panelId + '_loading" style="position:absolute;inset:0;display:none;align-items:center;justify-content:center;background:' + loadingBg + ';">' +
					'<div style="text-align:center;color:' + loadingColor + ';"><div class="spinner" style="width:40px;height:40px;border:3px solid ' + spinnerBorder + ';border-top-color:#204496;border-radius:50%;animation:cma-spin 1s linear infinite;margin:0 auto 10px;"></div>Laden...</div></div>' +
					'<iframe id="' + iframeId + '" class="lib_sidepanel_content" src="' + url + '" style="opacity:0;transition:opacity 0.2s ease;"></iframe>';
			}

			mObj.innerHTML = header + content;

			// When iframe loads, hide spinner and show content
			if (!htmlContent) {
				var iframe = mObj.querySelector('#' + iframeId);
				var loadingEl = mObj.querySelector('#' + panelId + '_loading');
				var loadingTimer = null;

				// Show spinner after 500ms delay (only if still loading)
				if (loadingEl) {
					loadingTimer = setTimeout(function() {
						loadingEl.style.display = 'flex';
					}, 500);
				}

				if (iframe) {
					iframe.onload = function() {
						// Cancel spinner timer if it hasn't fired yet
						if (loadingTimer) clearTimeout(loadingTimer);
						if (loadingEl) loadingEl.style.display = 'none';
						iframe.style.opacity = '1';
					};
				}
			}

			// Store in stack for proper closing (use top window's stack)
			if (typeof topWindow.lib_sidepanel_stack === 'undefined') {
				topWindow.lib_sidepanel_stack = [];
			}
			var previousTitle = topWindow.document.title;
			topWindow.lib_sidepanel_stack.push({
				id: panelId,
				panel: mObj,
				backdrop: backdrop,
				previousTitle: previousTitle
			});

			// Sync browser title with sidepanel title
			topWindow.document.title = displayTitle + ' - CMA';

			// Animate in after DOM update
			// libLog.log('[lib_OpenSidePanel] Panel created, starting animation. mObj:', mObj.id, 'current zIndex:', mObj.style.zIndex, 'current transform:', mObj.style.transform);
			setTimeout(function() {
				backdrop.style.opacity = '1';
				mObj.style.transform = 'translateX(0)';
				// libLog.log('[lib_OpenSidePanel] Animation triggered. mObj transform:', mObj.style.transform);
			}, 10);

			return mObj;
		}
	} catch (e) {
		console.error('lib_OpenSidePanel error:', e);
	}
	return null;
}

/**
 * Toggle maximize/restore on the sidepanel containing the clicked button
 * @param {HTMLElement} btn The maximize button element
 */
function lib_ToggleSidePanelMaximize(btn) {
	var panel = btn.closest('.lib_sidepanel_container');
	if (!panel) return;
	var icon = btn.querySelector('.lnr');
	if (!icon) return;

	if (panel.classList.contains('maximized')) {
		// Restore
		panel.classList.remove('maximized');
		panel.style.width = panel.dataset.saveWidth || '';
		panel.style.top = panel.dataset.saveTop || '';
		panel.style.maxWidth = panel.dataset.saveMaxWidth || '';
		panel.style.borderTopLeftRadius = '';
		icon.classList.remove('lnr-frame-contract');
		icon.classList.add('lnr-frame-expand');
		btn.title = 'Maximaliseren venstergrootte';
	} else {
		// Maximize - save current dimensions
		panel.dataset.saveWidth = panel.style.width;
		panel.dataset.saveTop = panel.style.top;
		panel.dataset.saveMaxWidth = panel.style.maxWidth;
		panel.classList.add('maximized');
		panel.style.width = '100vw';
		panel.style.top = '0';
		panel.style.maxWidth = '100vw';
		panel.style.borderTopLeftRadius = '0';
		icon.classList.remove('lnr-frame-expand');
		icon.classList.add('lnr-frame-contract');
		btn.title = 'Herstellen venstergrootte';
	}
}

/**
 * Close the topmost sidepanel
 * @param {boolean} skipConfirm Skip unsaved changes confirmation
 */
async function lib_CloseSidePanel(skipConfirm) {
	// Get top-level document (works across iframes)
	var top_elt = lib_alertbox_getbody_doc_element();
	var topWindow = top_elt.defaultView || top_elt.parentWindow || window;

	// Find all sidepanels in DOM and get the topmost one (highest z-index)
	var panels = top_elt.querySelectorAll('.lib_sidepanel_container');
	if (!panels || panels.length === 0) return;

	// Get the last panel (most recently added = topmost)
	var panel = panels[panels.length - 1];
	var panelId = panel.id;
	var backdrop = top_elt.getElementById(panelId + '_backdrop');

	// Check for unsaved changes
	if (!skipConfirm) {
		try {
			var iframe = panel.querySelector('iframe');
			if (iframe && iframe.contentWindow) {
				var hasChanges = false;
				if (iframe.contentWindow.cmaForm && iframe.contentWindow.cmaForm.isDirty) {
					hasChanges = true;
				} else if (iframe.contentWindow.formController && iframe.contentWindow.formController.isDirty) {
					hasChanges = true;
				} else if (iframe.contentWindow.isDirty) {
					hasChanges = true;
				}

				if (hasChanges) {
					var confirmed = await libConfirm('Je hebt niet-opgeslagen wijzigingen.', {
						title: 'Niet-opgeslagen wijzigingen',
						confirmText: 'Verlaat scherm',
						cancelText: 'Blijf op scherm',
						type: 'warning'
					});
					if (!confirmed) {
						return;
					}
				}
			}
		} catch (e) {
			// Cross-origin or other error
		}
	}

	// Use top window's z-index manager directly (NOT lib_zindex_manager.pop()).
	// See lib_zindex_manager doc block for why this bypasses getTopManager().
	var zIndexManager = (topWindow.lib_zindex_manager || lib_zindex_manager);
	if (zIndexManager) {
		zIndexManager.pop(panelId);
	}

	// Animate out
	panel.style.transform = 'translateX(100%)';
	if (backdrop) {
		backdrop.style.opacity = '0';
	}

	// Remove after animation
	setTimeout(function() {
		if (panel.parentNode) panel.parentNode.removeChild(panel);
		if (backdrop && backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
	}, 150);

	// Also remove from stack if it exists there (for title restoration)
	var stack = topWindow.lib_sidepanel_stack || [];
	for (var i = stack.length - 1; i >= 0; i--) {
		if (stack[i].id === panelId) {
			var stackEntry = stack[i];
			if (stackEntry.previousTitle) {
				topWindow.document.title = stackEntry.previousTitle;
			}
			stack.splice(i, 1);
			break;
		}
	}

	// Update URL to reflect current popup stack state
	// Uses clean URL format: /cma/form/formname/recordId/subform/subformId
	try {
		var currentStack = topWindow.lib_sidepanel_stack || [];

		if (topWindow.CMA && topWindow.CMA.url) {
			// Use clean URL format - supports up to 3 levels of nesting
			var urlState = topWindow.CMA.url.parse();
			var currentDepth = topWindow.CMA.url.getDepth();

			if (currentStack.length === 0) {
				// All sidepanels closed - back to list view
				topWindow.CMA.url.update({
					form: urlState.form,
					recordId: null,
					isNew: false,
					subform: null,
					subformId: null,
					isSubformNew: false,
					subsubform: null,
					subsubformId: null,
					isSubsubformNew: false
				}, true);
				// libLog.log('[lib_CloseSidePanel] All sidepanels closed, URL reset to list view');
			} else if (currentStack.length === 1) {
				// One sidepanel left - clear subform/subsubform state
				var remaining = currentStack[0];
				var iframe = remaining.panel ? remaining.panel.querySelector('iframe') : null;
				if (iframe && iframe.src) {
					var iframeUrl = new URL(iframe.src, topWindow.location.origin);
					var form = iframeUrl.searchParams.get('form') || urlState.form;
					var id = iframeUrl.searchParams.get('id') || iframeUrl.searchParams.get('ID') || null;
					var parentId = iframeUrl.searchParams.get('parentID') || '';

					if (parentId) {
						// This is a subform, parent is the main form
						topWindow.CMA.url.update({
							form: urlState.form,
							recordId: parentId,
							subform: form,
							subformId: id,
							isSubformNew: !id,
							subsubform: null,
							subsubformId: null,
							isSubsubformNew: false
						}, true);
					} else {
						// This is the main record
						topWindow.CMA.url.update({
							form: form,
							recordId: id,
							isNew: !id,
							subform: null,
							subformId: null,
							isSubformNew: false,
							subsubform: null,
							subsubformId: null,
							isSubsubformNew: false
						}, true);
					}
				}
				// libLog.log('[lib_CloseSidePanel] One sidepanel remaining, URL updated');
			} else if (currentStack.length === 2) {
				// Two sidepanels left - clear only subsubform state
				if (currentDepth === 3) {
					topWindow.CMA.url.update({
						form: urlState.form,
						recordId: urlState.recordId,
						isNew: urlState.isNew,
						subform: urlState.subform,
						subformId: urlState.subformId,
						isSubformNew: urlState.isSubformNew,
						subsubform: null,
						subsubformId: null,
						isSubsubformNew: false
					}, true);
					// libLog.log('[lib_CloseSidePanel] Two sidepanels remaining, subsubform cleared from URL');
				}
			}
			// For deeper stacks (4+), don't update URL - not supported
		} else {
			// Fallback to legacy popupStack format
			var params = new URLSearchParams(topWindow.location.search);

			if (currentStack.length === 0) {
				params.delete('popupStack');
				params.delete('popup');
				params.delete('popupID');
				params.delete('popupParentID');
				params.delete('popupParentField');
			} else {
				var stackStr = currentStack.map(function(entry) {
					var iframe = entry.panel ? entry.panel.querySelector('iframe') : null;
					if (iframe && iframe.src) {
						var iframeUrl = new URL(iframe.src, topWindow.location.origin);
						var form = iframeUrl.searchParams.get('form') || '';
						var id = iframeUrl.searchParams.get('id') || iframeUrl.searchParams.get('ID') || '0';
						var parentId = iframeUrl.searchParams.get('parentID') || '';
						var parentField = iframeUrl.searchParams.get('parentField') || '';
						return form + ':' + id + ':' + parentId + ':' + parentField;
					}
					return '';
				}).filter(function(s) { return s !== ''; }).join('|');

				if (stackStr) {
					params.set('popupStack', stackStr);
				} else {
					params.delete('popupStack');
				}
				params.delete('popup');
				params.delete('popupID');
				params.delete('popupParentID');
				params.delete('popupParentField');
			}

			var newUrl = topWindow.location.pathname + '?' + params.toString();
			if (newUrl.endsWith('?')) {
				newUrl = newUrl.slice(0, -1);
			}
			topWindow.history.replaceState(null, '', newUrl);
			// libLog.log('[lib_CloseSidePanel] Updated URL after close (legacy), stack depth:', currentStack.length);
		}
	} catch (e) {
		libLog.warn('[lib_CloseSidePanel] Could not update URL:', e.message);
	}

	// Dispatch event to notify that sidepanel closed (for resetting active rows)
	try {
		document.dispatchEvent(new CustomEvent('sidepanel-closed', { detail: { panelId: panelId } }));
	} catch (e) {
		// CustomEvent not supported
	}
}

/**
 * Get the current sidepanel's iframe element
 * @returns {HTMLIFrameElement|null}
 */
function lib_GetSidePanelIframe() {
	var top_elt = lib_alertbox_getbody_doc_element();
	var panels = top_elt.querySelectorAll('.lib_sidepanel_container');
	if (!panels || panels.length === 0) return null;
	var panel = panels[panels.length - 1];
	return panel.querySelector('iframe');
}

/**
 * Check if we're inside a sidepanel
 * @returns {boolean}
 */
function lib_IsInSidePanel() {
	try {
		// Check if parent has sidepanel stack with our iframe
		if (self !== top && parent.lib_sidepanel_stack && parent.lib_sidepanel_stack.length > 0) {
			var current = parent.lib_sidepanel_stack[parent.lib_sidepanel_stack.length - 1];
			var iframe = current.panel.querySelector('iframe');
			if (iframe && iframe.contentWindow === window) {
				return true;
			}
		}
	} catch (e) {
		// Cross-origin
	}
	return false;
}

/**
 * Open panel or popup based on user preference
 * @param {string} url URL to load
 * @param {string} name Window/panel name
 * @param {number} width Width
 * @param {number} height Height (only used for popup)
 * @param {string} title Title
 * @param {string} htmlContent Optional HTML content
 */
function lib_OpenPanel(url, name, width, height, title, htmlContent) {
	var pref = lib_getPopupStylePreference();
	// Sidepanels need a title for the caption bar - fall back to popup if no title
	if (pref === 'sidepanel' && title) {
		return lib_OpenSidePanel(url, name, width, title, htmlContent);
	} else {
		return lib_OpenWindowCentered(url, name, width, height, title, htmlContent);
	}
}

/**
 * Close panel or popup based on what's open
 * @param {boolean} skipConfirm Skip unsaved changes confirmation
 */
async function lib_ClosePanel(skipConfirm) {
	// Try sidepanel first (check top window's stack)
	var top_elt = lib_alertbox_getbody_doc_element();
	var topWindow = top_elt.defaultView || top_elt.parentWindow || window;
	var stack = topWindow.lib_sidepanel_stack;
	if (stack && stack.length > 0) {
		return lib_CloseSidePanel(skipConfirm);
	}
	// Fall back to popup
	return lib_OpenWindowCenteredClose(skipConfirm);
}

//   -			-			-			-			-			DIALOG FUNCTIONS
// NOTE: libConfirm() and libAlert() are now provided by lib-dialog.js web component

if (jQueryLoaded) {
	$(document).ready( function() {

		// Close popups/sidepanels on Escape key
		$(document).keydown( function( evt ) {
			if ( evt.which == 27 ) {
				// Skip if a lib-dialog is open (it handles its own Escape key)
				var openDialog = document.querySelector('lib-dialog[open]');
				if (openDialog) {
					return;
				}

				// Try to close sidepanel first (check top window's stack)
				var top_elt = lib_alertbox_getbody_doc_element();
				var topWindow = top_elt.defaultView || top_elt.parentWindow || window;
				var stack = topWindow.lib_sidepanel_stack;
				if (stack && stack.length > 0) {
					lib_CloseSidePanel();
					return;
				}
				// Then try popup
				if (top_elt.lib_OpenWindowCenteredClose) {
					top_elt.lib_OpenWindowCenteredClose();
				} else {
					if (window.parent.lib_OpenWindowCenteredClose) {
						window.parent.lib_OpenWindowCenteredClose();
					} else {
						lib_OpenWindowCenteredClose()
					}
				}
			}
		});

$("#notification_top, #notification_fixed").click( function () {
			$(this).hide( 200 )
		});

		// Use event delegation for switches (works with dynamically loaded content)
		$(document).on("click", ".switch", function (e) {
			if (! $(this).hasClass("disabled")) {
				$(this).toggleClass("checked");
				$(this).find("input[type='checkbox']").prop("checked", $(this).hasClass("checked"));
			}
		});
	});
}