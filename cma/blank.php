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
    Response::cacheExpires(1083.3333333333333);
    echo '<html lang="nl"><head><script>(function(){if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches){document.documentElement.style.backgroundColor="#1a1a1a";}})();</script></head><body class="contentbody"></body></html>';
}
// Call main function
main();
?>
