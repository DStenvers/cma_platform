<?php
/**
* Main
*/
use App\Library\Response;

// Guarantee App\Library is loaded even when IIS auto_prepend of _bootstrap.php
// didn't fire for this request (otherwise Response::* fatals → HTTP 500).
require_once __DIR__ . '/bootstrap.inc';

/**
 * Main
 */
function main()
{
    Response::cacheExpires(1083.3333333333333);
    echo '<html lang="nl"><head><script>(function(){if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches){document.documentElement.style.backgroundColor="#1a1a1a";}})();</script></head><body class="contentbody"></body></html>';
}
// Call main function
main();
?>
