<?php
/**
* Main
*/
use App\Library\LibUpload;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;

/**
 * Main
 */
function main()
{
    Response::noCache();
    $objUpload = new LibUpload();
    $blnOptimize = false;
    $sFile = null;
    $ext = null;
    $objUpload->Random = Request::query('random', '') != '';
    $objUpload->Path = Request::query('path', '');
    $objUpload->Fieldname = 'Blob';
    // the upload wizard version 2 also uploads several other file formats and saves them as .tif
    $sFile = $objUpload->Filename();
    if ((($pos = strrpos($sFile, '.')) !== false ? $pos + 1 : 0) > 0) {
        $ext = (is_null(substr($sFile, max(0, (($pos = strrpos($sFile, '.')) !== false ? $pos + 1 : 0) + 1 - 1))) ? "" : strtolower(substr($sFile, max(0, (($pos = strrpos($sFile, '.')) !== false ? $pos + 1 : 0) + 1 - 1))));
        switch ($ext) {
        case 'gif':
        break;
    case 'png':
    break;
default:
    $sFile = substr($sFile, 0, max(0, min((($pos = strrpos($sFile, '.')) !== false ? $pos + 1 : 0), strlen($sFile)))) . 'jpg';
        $blnOptimize = true;
    break;
}
} else {
$sFile .= '.jpg';
}
$objUpload->Filename = $sFile;
$objUpload->Save();
if ($blnOptimize) {
    Image::optimize(Server::mapPath($objUpload->FullLocalPath . $sFile));
}
// strange, but this is the way to communicate with the caller app!
echo $objUpload->Filename();
// Release upload object from memory
$objUpload = null;
}
// Call main function
main();
?>
