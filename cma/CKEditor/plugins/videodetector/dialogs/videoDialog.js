CKEDITOR.dialog.add( 'videoDialog', function( editor ) {
    return {
        title: 'Invoegen video (Youtube en Vimeo)',
        minWidth: 400,
        minHeight: 100,
        contents: [
            {
                id: 'tab-basic',
                label: 'Basis instellingen',
                elements: [
                    {
                        type: 'text',
                        id: 'url_video',
                        label: 'Youtube, Vimeo URL of embed code',
                        validate: CKEDITOR.dialog.validate.notEmpty( "Video-URL is vereist" )
                    }
                ]
            }
        ],
        onOk: function() {
            var dialog = this;


            //detectamos el video
            var respuesta = detectar();
            var url = "";

            if(respuesta.reproductor == "youtube"){
//                var url = "https://www.youtube.com/embed/"+respuesta.id_video+"?autohide=1&controls=1&showinfo=0";
                var url = "https://www.youtube-nocookie.com/embed/"+respuesta.id_video+"?autoplay=0&autohide=1&controls=1&showinfo=0";
            }
            else if(respuesta.reproductor == "vimeo"){
                var url = "https://player.vimeo.com/video/"+respuesta.id_video+"?portrait=0";
            }
            else if(respuesta.reproductor == "dailymotion"){
                var url = "https://www.dailymotion.com/embed/video/"+respuesta.id_video;
            }

            var p = new CKEDITOR.dom.element( 'div' );
            p.setAttribute("class", "videodetector");

            var iframe = new CKEDITOR.dom.element( 'iframe' );
            iframe.setAttribute("src", url);
            iframe.setAttribute("frameborder", "0");
			iframe.setAttribute("height", "270");
			iframe.setAttribute("width", "480");
            p.append( iframe );
            editor.insertElement(p);
        }
    };
});


//funcion para detectar el id y la plataforma (youtube, vimeo o dailymotion) de los videos
function detectar(){
    var getDialog     = document.getElementsByClassName('cke_dialog_contents').item(0);
    var url           = getDialog.getElementsByTagName('input').item(0).value;
    var id            = '';
    var reproductor   = '';
    var url_comprobar = '';

    if(url.indexOf('youtu.be') >= 0){
        reproductor = 'youtube';
        id          = url.substring(url.lastIndexOf("/")+1, url.length);
    }
    if(url.indexOf("youtube") >= 0){
        reproductor = 'youtube'
        if(url.indexOf("</iframe>") >= 0){
            var fin = url.substring(url.indexOf("embed/")+6, url.length)
            id      = fin.substring(fin.indexOf('"'), 0);
        }else{
            if(url.indexOf("&") >= 0)
                id = url.substring(url.indexOf("?v=")+3, url.indexOf("&"));
            else
                id = url.substring(url.indexOf("?v=")+3, url.length);
        }
        url_comprobar = "https://gdata.youtube.com/feeds/api/videos/" + id + "?v=2&alt=json";
        //"https://gdata.youtube.com/feeds/api/videos/" + id + "?v=2&alt=json"
    }
    if(url.indexOf("vimeo") >= 0){
        reproductor = 'vimeo'
        if(url.indexOf("</iframe>") >= 0){
            var fin = url.substring(url.lastIndexOf('vimeo.com/"')+6, url.indexOf('>'))
            id      = fin.substring(fin.lastIndexOf('/')+1, fin.indexOf('"',fin.lastIndexOf('/')+1))
        }else{
            id = url.substring(url.lastIndexOf("/")+1, url.length)
        }
        url_comprobar = 'http://vimeo.com/api/v2/video/' + id + '.json';
        //'http://vimeo.com/api/v2/video/' + video_id + '.json';
    }
    if(url.indexOf('dai.ly') >= 0){
        reproductor = 'dailymotion';
        id          = url.substring(url.lastIndexOf("/")+1, url.length);
    }
    if(url.indexOf("dailymotion") >= 0){
        reproductor = 'dailymotion';
        if(url.indexOf("</iframe>") >= 0){
            var fin = url.substring(url.indexOf('dailymotion.com/')+16, url.indexOf('></iframe>'))
            id      = fin.substring(fin.lastIndexOf('/')+1, fin.lastIndexOf('"'))
        }else{
            if(url.indexOf('_') >= 0)
                id = url.substring(url.lastIndexOf('/')+1, url.indexOf('_'))
            else
                id = url.substring(url.lastIndexOf('/')+1, url.length);
        }
        url_comprobar = 'https://api.dailymotion.com/video/' + id;
        // https://api.dailymotion.com/video/x26ezrb
    }
    return {'reproductor':reproductor,'id_video':id};
}

/*
var play_image = CKEDITOR.plugins.getPath('videosnapshot') + 'images/play.png';
element.setAttribute('href', this.getValue());
var key = this.getValue().split('watch?v=')[1];
if (key) {
	var video_url = 'https://www.youtube.com/watch?v=' + key;
	var image_url = 'https://i.ytimg.com/vi/' + key + '/hqdefault.jpg';
	element.setHtml('<img src="'+image_url+'" style="max-width:480px;margin:auto;"/><span style="background-image: url(' + play_image + '); background-repeat:no-repeat; background-position:center center; top:0; bottom:0; left:0; right:0; position:absolute; pointer-events:none; background-color:rgba(0,0,0,0.5);"> </span>');
}

<script type="text/javascript">
    $.ajax({
        type:'GET',
        url: 'http://vimeo.com/api/v2/video/' + video_id + '.json',
        jsonp: 'callback',
        dataType: 'jsonp',
        success: function(data){
            var thumbnail_src = data[0].thumbnail_large;
            $('#thumb_wrapper').append('<img src="' + thumbnail_src + '"/>');
        }
    });
</script>

*/