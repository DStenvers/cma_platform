<?php
use App\Library\Application;
use Cma\ToolbarHelper;
use App\Library\Cookie;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\File;

require_once __DIR__ . '/../bootstrap.inc';

/**
* Main
*/
function main()
{
    Response::noCache();
    define("VIEW_LIST", '0');
    define("VIEW_THUMB", '1');
    define("VIEW_COOKIE", 'CMA_Listview');

    $basePath = Application::get('base_path', '');
    $serverName = Request::server('SERVER_NAME', 'localhost');
    $protocol = (!empty(Request::server('HTTPS')) && Request::server('HTTPS') !== 'off') ? 'https://' : 'http://';
    $queryString = Request::server('QUERY_STRING');

    echo '<HTML lang="nl"><head>';
    cma_error_handler();
    echo '<script>(function(){if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches){document.documentElement.style.backgroundColor="#1a1a1a";}})();</script>';
    echo '<style> html, body {overflow-x:hidden} </style>';
    echo '<script type="text/javascript">
    var oldobj;
    function setbg( elt )
    {
        // reset previous
        if (oldobj) $(oldobj).removeClass("current");
        // set new, make sure it\'s the TR
        oldobj = elt;
        if (oldobj.tagName.toLowerCase()!="div" || oldobj.className.toLowerCase()=="img_container") {oldobj=oldobj.parentElement};
        $(oldobj).addClass("current")
    }
    function setlisttype( iType )
    {
        lib_createCookie("' . VIEW_COOKIE . '", iType.toString(), 365);
        get_data();
    }
    function get_data()
    {
        fetch("' . $protocol . $serverName . $basePath . 'cma/wizards/file_list_ajaxdata.php?' . $queryString . '", {
            cache: "no-store"
        })
        .then(function(response) { return response.text(); })
        .then(function(data) {
            document.getElementById("list_container").innerHTML = data;
            oldobj = document.getElementById("default");
        });
    }
    document.addEventListener("DOMContentLoaded", function() {
        get_data();
    });
    </script>';
    echo '<style>
    div.file_list div {cursor:pointer}
    </style>';
    echo '</head>';
    echo '<body style="height:100%" class=wizardcontent>';

    $currentView = Cookie::get(VIEW_COOKIE, '');
    $sRootURL = Request::query('basepath', '');
    $sSubPath = Request::query('path', '');

    if ($sRootURL == '') {
        $sRootURL = '/';
    }

    $actualFilePath = $sRootURL . $sSubPath;
    $actualFilePath = str_replace('//', '/', $actualFilePath);

    $mappedPath = Server::mapPath($actualFilePath);
    if (!is_dir($mappedPath)) {
        File::createFolder($mappedPath);
    }

    echo '<fieldset style="margin:0px;width:100%;min-height:100%"><legend>Server: ' . htmlspecialchars($sRootURL . $sSubPath) . '</legend>';
    ToolbarHelper::writeJS();
    ToolbarHelper::start(false);
    ToolbarHelper::button('<a href=javascript:setlisttype(' . VIEW_LIST . ')>', "<img src=../images/tb_file_list.gif title='Lijstweergave'", true, '');
    ToolbarHelper::button('<a href=javascript:setlisttype(' . VIEW_THUMB . ')>', "<img src=../images/tb_file_thumb.gif title='Thumbnails'", true, '');
    ToolbarHelper::end(false);
    echo '<div id="list_container">';
    echo '</div></fieldset></body></html>';
}

// Call main function
main();
?>
