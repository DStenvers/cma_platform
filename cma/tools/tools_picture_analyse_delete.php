<?php
use App\Library\Request;
use Cma\ToolbarHelper;
use App\Library\Response;
use App\Library\Server;

require_once __DIR__ . '/../bootstrap.inc';
require_once __DIR__ . '/../library/lib_imgformat.inc';
require_once __DIR__ . '/../library/lib_cache.inc';
Response::noCache();
cma_html_header('CMA - Beelden verwijderen');
echo '<BODY class="contentbody tools">';
/**
* Main
*
*/
function main()
{
    $arrFiles = array();
    $n = null;
    $tel = 0;
    ToolbarHelper::report('Beelden analyse - Verwijderen ongebruikte beelden', false, false, false);
    echo '<div id="c" class="tools">';
    $arrFiles = explode(',', Request::post('delete', ''));
    if (Arr::isArray($arrFiles)) {
        for ($n = 0; $n < count($arrFiles); $n++) {
            $filePath = Server::mapPath($arrFiles[$n]);
            if (file_exists($filePath)) {
                @unlink($filePath);
                $tel++;
            }
        }
    }
    echo 'Er zijn ' . $tel . ' beelden verwijderd.</div></BODY></HTML>';
}
main();
?>
