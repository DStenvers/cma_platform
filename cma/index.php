<?php
/**
* Main
*/
use App\Library\Response;

/**
 * Main
 */
function main()
{
    Response::cacheExpires(168);
    Response::redirect('./default.php');
}
// Call main function
main();
?>
