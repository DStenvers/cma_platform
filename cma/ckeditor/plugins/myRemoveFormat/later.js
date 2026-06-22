/*
 

// sub-function to eliminate unwanted tags and properties
// 
function StripTags( content ) {
    var sOriginal = content;

	// USE content = content.replace(/<XXX[^>]*>/g,""); for removing XXX !
	// Remove some other Word-only css style attributes.
	content = content.replace(/mso-[^:]*:"[^"]*";/gi, "");
	content = content.replace(/mso-[^;'"]*;*(\n|\r)*/gi, "");
	content = content.replace(/BACKGROUND-COLOR: transparent/gi,"");
	content = content.replace(/ class=MsoTableGrid/gi,"");
	content = content.replace(/ class=MsoNormal/gi,"");
	content = content.replace(/ dir=ltr/gi,"");
	content = content.replace(/ style=['"]tab-interval:[^'"]*['"]/gi, "");
	content = content.replace(/<V:[^>]*>/gi,"");
	content = content.replace(/<\/V:[^>]*>/gi,"");
	content = content.replace(/<W:[^>]*>/gi,"");
	content = content.replace(/<\/W:[^>]*>/gi,"");

	content = content.replace(/<METRICCONVERTER[^>]*>/gi,"");
	content = content.replace(/<\/METRICCONVERTER[^>]*>/gi,"");
	
	content = content.replace(/<st1:[^>]*>/gi, "");
	content = content.replace(/<O:[^>]*>/gi, "");
	content = content.replace(/<pre>/gi, "");
	content = content.replace(/<\/pre>/gi, "");
	
	if (navigator.appVersion.search("MSIE 5")==-1) {
		// werkt niet altijd met IE 5.0 -> disabled!
		content = content.replace(/<\/st1:[^>]*>/gi, "");		   
		content = content.replace(/<\?xml:.*?\/>/gi, "");	   
		content = content.replace(/<\/O:[^>]*>/gi, "");
	}

	while (content.search("<BR>\r\n")!=-1) 
    	content = content.replace(/<BR>\r\n/gi,"<BR>");
    	
    // skip double white lines
	while (content.search("<BR><BR><BR>")!=-1) 
		content = content.replace(/<BR><BR><BR>/gi,"<BR><BR>");

	// inside TD cleanup
	content = content.replace(/<TD><BR>/gi,"<TD>");
	content = content.replace(/<BR><\/TD>/gi,"</TD>");
	content = content.replace(/<TD><P>/gi,"<TD>");
	
	// /SPAN /DIV and /P inside a table 	
	// content = content.replace(/<\/DIV[^>]*><\/TD[^>]*>/gi,"</TD>");
	content = content.replace(/<\/SPAN[^>]*><\/TD[^>]*>/gi,"</TD>");
	content = content.replace(/<\/P[^>]*><\/TD[^>]*>/gi,"</TD>");

	// SPAN tag
	content = content.replace(/<SPAN[^>]*>/gi,"");
	content = content.replace(/<\/SPAN[^>]*>/gi,"");
	content = content.replace(/<SPAN>/gi, "");
	content = content.replace(/<\/SPAN>/gi, "");

	// TBODY tag
	content = content.replace(/<TBODY[^>]*>/gi,"");
	content = content.replace(/<\/TBODY[^>]*>/gi,"");
	content = content.replace(/<THEAD[^>]*>/gi,"");
	content = content.replace(/<\/THEAD[^>]*>/gi,"");
	
	// strip H1 - H6 elements
	content = content.replace(/<H1[^>]*>/gi,"");
	content = content.replace(/<\/H1[^>]*>/gi,"");
	content = content.replace(/<H2[^>]*>/gi,"");
	content = content.replace(/<\/H2[^>]*>/gi,"");
	content = content.replace(/<H3[^>]*>/gi,"");
	content = content.replace(/<\/H3[^>]*>/gi,"");
	content = content.replace(/<H4[^>]*>/gi,"");
	content = content.replace(/<\/H4[^>]*>/gi,"");
	content = content.replace(/<H5[^>]*>/gi,"");
	content = content.replace(/<\/H5[^>]*>/gi,"");
	content = content.replace(/<H6[^>]*>/gi,"");
	content = content.replace(/<\/H6[^>]*>/gi,"");

	// skip those empty para's and tag pairs that make no sense!
	content = content.replace(/<![if !supportEmptyParas]>&nbsp;<![endif]>/gi, " ");
	content = content.replace(/<FONT[^>]*>&nbsp;<\/FONT>/gi, "");
	content = content.replace(/<FONT[^>]*><\/FONT>/gi, "");
	content = content.replace(/<P>&nbsp;<\/P>/gi, "<BR>");
	content = content.replace(/<P><\/P>/gi, "<BR>");
	content = content.replace(/<P> <\/P>/gi, "<BR>");
	content = content.replace(/<B><\/B>/gi,"");
	content = content.replace(/<I><\/I>/gi,"");
	content = content.replace(/style=""/gi,"");

	// <P> and <BR replacement, rudimentary, but quite effective!
	content = content.replace(/<P>/gi, "<BR>");
	content = content.replace(/<\/P>/gi, "");

	// empty font tags
	content = content.replace(/<FONT>/gi, "");
	
	// als er geen <FONT tags zijn, dan alle </font tags weg...
	if (content.toLowerCase().indexOf("<font")==-1){
		content = content.replace(/<\/FONT>/gi, "");
	}
	
	// replace some tags
	content = content.replace(/<STRONG>/gi, "<B>");
	content = content.replace(/<\/STRONG>/gi, "</B>");
	content = content.replace(/<EM>/gi, "<I>");
	content = content.replace(/<\/EM>/gi, "</I>");

	content = content.replace(/<\/LI>/gi, "");
	
	// skip trailing and leading <BR>'s and &nbsp;'s
	while ( (content.substr(0,4)).toLowerCase()=='<br>') 
		content = content.substr(4);
    while (content.substr(content.length-4,4).toLowerCase()=="<br>")  
        content = content.substr(0,content.length-4);
	while (content.substr(0,6).toLowerCase()=="&nbsp;") 
		content = content.substr(6);
	while (content.substr(content.length-6,6).toLowerCase()=="&nbsp;") 
		content = content.substr(0,content.length-6);

	// eliminate those extra empty para's after a UL and OL
	content = content.replace(/<\/UL><BR><BR>/gi,"</UL>")
	content = content.replace(/<\/OL><BR><BR>/gi,"</OL>")
		
	// TODO Expand this to the complete listing of special characters! imode trick
	content = content.replace(/é/gi,"&#233;");
	content = content.replace(/ë/gi,"&#235;");

	// weird bugs
	content = content.replace(/<TABLETABLE/gi,"<TABLE")
//	content = content.replace(/ designtimesp\=\d+/gi,"")
	content = content.replace(/ DESIGNTIMESP\=\d+/gi,"")
	
	// make sure cursor container is gone
	content = replaceString(const_CursorPos, "", content)
	content = replaceString("&#65279;", " ", content) 
		
    // email hack! 
	if (HTMLEdit.spamJS) {
        content = replaceString( "<A>__script__", "<script>", content)
        content = replaceString( "__script__", "<script>", content)
        content = replaceString( "__/script__", "</script>", content)
        content = replaceString( "__nobr__", "<nobr>", content)
        content = replaceString( "__/nobr__", "</nobr>", content)
    }
    
    // continue until no changes
    return ( sOriginal!=content ? StripTags(content) : content );
}
 
 
function getBasicHTML(elm) {  
	var arr = []; 
	repeat(elm);   
	
	function repeat(elm)  {   
		var tagName = elm.tagName.toLowerCase(),    
		    tags = ['<'+tagName+'>','<\/'+tagName+'>'],k=0, child;   
		
		if (tagName=="td") {
		    sTag = '<'+tagName;
		    if (elm.rowSpan!='1') sTag+=' rowspan='+elm.rowSpan;
		    if (elm.colSpan!='1') sTag+=' colspan='+elm.colSpan;
		    if (elm.align!='' && elm.align.toLowerCase()!='left') sTag+=' align='+elm.align;
		    arr.push(sTag+'>');
		    tmp = StripTags( elm.innerHTML );
		    arr.push(tmp);   
		} else 
		    arr.push(tags[0]);   
		    while(child=elm.children[k++])     
		       repeat(child);   
		arr.push(tags[1]);  
	} 
	return arr.join(''); 
}



*/