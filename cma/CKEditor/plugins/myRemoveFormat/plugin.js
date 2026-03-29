/**
 * Custom development
 */

CKEDITOR.plugins.add( 'myRemoveFormat', {
	lang: 'af,ar,bg,bn,bs,ca,cs,cy,da,de,el,en,en-au,en-ca,en-gb,eo,es,et,eu,fa,fi,fo,fr,fr-ca,gl,gu,he,hi,hr,hu,id,is,it,ja,ka,km,ko,ku,lt,lv,mk,mn,ms,nb,nl,no,pl,pt,pt-br,ro,ru,si,sk,sl,sq,sr,sr-latn,sv,th,tr,tt,ug,uk,vi,zh,zh-cn', 
	icons: 'myremoveformat', 
	hidpi: false, 
	init: function( editor ) {
		editor.addCommand( 'myRemoveFormat', CKEDITOR.plugins.myRemoveFormat.commands.myRemoveFormat );
		editor.ui.addButton && editor.ui.addButton( 'myRemoveFormat', {
			label: editor.lang.removeformat.toolbar,
			command: 'myRemoveFormat',
			toolbar: 'cleanup,10'
		} );
	}
} );

CKEDITOR.plugins.myRemoveFormat = {
	commands: {
		myRemoveFormat: {
			exec: function( editor ) {
/*				
				var tagsRegex = editor._.removeFormatRegex || ( editor._.removeFormatRegex = new RegExp( '^(?:' + editor.config.removeFormatTags.replace( /,/g, '|' ) + ')$', 'i' ) );

				var removeAttributes = editor._.removeAttributes || ( editor._.removeAttributes = editor.config.removeFormatAttributes.split( ',' ) ),
					filter = CKEDITOR.plugins.myRemoveFormat.filter,
					range = new CKEDITOR.dom.range( editor.document ),
					iterator = range.createIterator(),
					isElement = function( element ) {
						return element.type == CKEDITOR.NODE_ELEMENT;
					};
*/									
				editor.document.$.body = StripDom( editor );
				alert("Opmaak is verwijderd.");
			}
		}
	}
};

// 
// sub-function to strip the DOM
// 
function StripDom (editor) {
	
	var whole_doc = editor.document.$.body;
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<![if !supportEmptyParas]>&nbsp;<![endif]>/gi, " ");
	// als het nu niet lukt?!
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/&nbsp;/gi, " ");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/&amp;nbsp;/g,"");			
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/\u00a0/g, " ");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/\xA0/g,' ');

	whole_doc.innerHTML = whole_doc.innerHTML.replace(/•/gi, '<li>');
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/Ã¢€“/gi, '-'); 
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/â€“/gi, '-'); 
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/&ndash;/gi, '-');
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/%E2%80%93/gi, '-');

	whole_doc.innerHTML = whole_doc.innerHTML.replace(/ dir=ltr/gi,"");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<V:[^>]*>/gi,"");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/V:[^>]*>/gi,"");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<W:[^>]*>/gi,"");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/W:[^>]*>/gi,"");
 
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<METRICCONVERTER[^>]*>/gi,"");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/METRICCONVERTER[^>]*>/gi,"");

	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<st1:[^>]*>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<O:[^>]*>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<pre>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/pre>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\?xml:.*?\/>/gi, "");	   
	
	
	// remove all <br>'s at the end, 2do: make a function out of this; not full-proof, a space will mess this up.
	var aRemove = [' ', '<br>', '&nbsp;', '\r', '\n', '\r\n']; 
	whole_doc.innerHTML = removeAtEnd( whole_doc.innerHTML, aRemove);	
	// 2do: surrounding div 

	var root = editor._.elementsPath.list;
	for (var intLoop=0; intLoop<root.length; intLoop++) {
		var par_el = root[intLoop].$;
		
		for ( var t=0; t<par_el.childNodes.length; t++ ) { 
			clean_elt ( editor, par_el.childNodes[t] );
		}
	}
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<span[^>]*>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/span>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<font>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/font>/gi, "");

	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<h1[^>]*>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/h1>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<h2[^>]*>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/h2>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<h3[^>]*>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/h3>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<h4[^>]*>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/h4>/gi, "");

//	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<b>/gi, "");
//	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/b>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<u>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/u>/gi, "");
//	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<i>/gi, "");
//	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/i>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<strong>/gi, "");
	whole_doc.innerHTML = whole_doc.innerHTML.replace(/<\/strong>/gi, "");

	return whole_doc;
	
}

function removeAtEnd( cText, aRemove ) {
	var nLength;
	var cRemove; 
	var bRemoved = true;
	
	while (bRemoved) { 
		bRemoved = false;
		for	(index = 0; index < aRemove.length; index++) {
			cRemove = aRemove[index];
			nLength = cRemove.length;
			if (cText.length>nLength) { 
				while (cText.substr(cText.length-nLength,nLength).toLowerCase()==cRemove.toLowerCase()) {
					cText = cText.substr(0,cText.length-nLength);
					bRemoved = true;
				}
			} else { 
				if (cText==cRemove) {
					cText = "" 
					bRemoved = true;
				}
			}
		}
	}
	return cText; 
}
//
//	Enkel element opschonen
//
function clean_elt ( editor, el ) {
	
	if (el.tagName) { 
		var sTagName = el.tagName.toUpperCase();
		
		el.removeAttribute("style");
		if (sTagName!="TD") {
			el.removeAttribute("align");
		}
		if (sTagName!="TD" && sTagName!="TR") {
			el.removeAttribute("valign");
		}
		if (sTagName!="TABLE") {
			el.removeAttribute("border");
//			if (editor.config.qtStyle) {
//				el.createAttribute( "style",editor.config.qtStyle );
//			}
		}
		if (sTagName=="A") {
			el.innerHTML = el.innerHTML.replace(/<u>/g,"")
			el.innerHTML = el.innerHTML.replace(/<\/u>/g,"")
			$(el).removeClass("tooltipstered");			
		} else {
			el.removeAttribute("class");
		}
		el.removeAttribute("color");
		el.removeAttribute("designtimesp");
		el.removeAttribute("dir");
		el.removeAttribute("face");
		el.removeAttribute("height");
		el.removeAttribute("hspace");
		el.removeAttribute("lang");
		el.removeAttribute("scayt-misspell-word");
		el.removeAttribute("data-scayt-lang");
		el.removeAttribute("data-scayt-word");
		if (sTagName!="HR") {
			el.removeAttribute("size");
		}
		el.removeAttribute("width");
		
		// remove empty tags
		if (sTagName=='LI' || sTagName=='A' || sTagName=='DIV' || sTagName=='SPAN' || sTagName=='METRICCONVERTER') {
			if (el.innerHTML=='') {
				el.parentNode.removeChild(el);
			}
		}

		// an <A> without a target-> set to _blank! (if it is not javascript, a mailto or a bookmark reference)
		// if (sTagName=='A') {
		//	if (el.href.indexOf("mailto:")==-1 && el.href.indexOf("javascript:")==-1 && el.href.indexOf("#")==-1) {
		//		if (el.getAttribute("target")==null || el.getAttribute("target")=="" ) {
		//			DOM_SetAttribute( el, "target", el.href.indexOf('./')==-1?"_blank":"_self")
		//		}
		//	}
		// }
		
		// remove all align=left in P and TD elements 
		if (sTagName=='P' || sTagName=='TD') {
			if (el.getAttribute("align")=="left") {el.removeAttribute("align");}
		}

		if (sTagName=='TD') {

			// remove all <br>'s at the end, 2do: make a function out of this
			while (el.innerHTML.substr(el.innerHTML.length-4,4).toLowerCase()=="<br>") {
				el.innerHTML = el.innerHTML.substr(0,el.innerHTML.length-4);
			}
			// always an &nbsp; inside a TD
			if (el.innerHTML=="" || el.innerHTML==null ) {
				el.innerHTML="&nbsp;"
			} else {
				// and after having entered data, remove the &nbsp; to prevent ugly spaces
				// 2do: make a function out of this
				if (el.innerHTML.substring(0,6)=="&nbsp;" && el.innerHTML.length>6) {
					el.innerHTML = el.innerHTML.substring(6,el.innerHTML.length);
				}
			} 
		}
		
		if (sTagName=='TABLE') {
			el.setAttribute("border","1");
		}
		
		// recurse ! 
		for (var y=0; y<el.childNodes.length; y++) {
			clean_elt ( editor, el.childNodes[y] );
		}
	}
}