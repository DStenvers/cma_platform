<?php
use App\Library\Response;

require_once __DIR__ . '/bootstrap.inc';

/**
* Main
*/
function main()
{
    Response::cacheExpires(1440);
    cma_html_header();
    echo '<BODY class="m_body blockselect" style="border-top: 1px solid #cccccc;padding-left:8px;padding-top:4px">versie ';
    echo STRCMAVERSION;
    echo '</BODY></HTML>';
    // </td> td width=20% nowrap align=right style=padding-right:8px> <a href="https://www.stenversonline.nl" target="_blank"><img src="images/logo.svg" style="height:24px;width:109px;border:0px;margin-top:-2px"></a></td></tr></table>
}
// Call main function
main();
?>
