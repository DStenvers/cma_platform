<?php

// Include-guard: dit bestand weigert zichzelf een tweede keer uit te voeren.
// require_once dekt het gewone geval al — die lost het pad eerst op — maar niet een
// kale require/include, en niet een pad dat via een junction anders uitkomt. De naam
// is uniek per bestand, zodat de guard niet van de aanroeper afhangt.
if (defined('INC_LIBRARY')) { return; }
define('INC_LIBRARY', 1);

require_once __DIR__ . '/cma_fldtypes.inc';
require_once __DIR__ . '/cma_connect.inc';
require_once __DIR__ . '/lib_404.inc';
require_once __DIR__ . '/lib_browdet.inc';
require_once __DIR__ . '/lib_cache.inc';
require_once __DIR__ . '/lib_combobox.inc';
require_once __DIR__ . '/lib_dir.inc';
require_once __DIR__ . '/lib_externhtml.inc';
require_once __DIR__ . '/lib_filename.inc';
require_once __DIR__ . '/lib_form.inc';
require_once __DIR__ . '/lib_formtext.inc';
require_once __DIR__ . '/lib_general.inc';
require_once __DIR__ . '/lib_geocode.inc';
require_once __DIR__ . '/lib_html.inc';
require_once __DIR__ . '/lib_password.inc';
require_once __DIR__ . '/lib_search.inc';
require_once __DIR__ . '/lib_sendmail.inc';
require_once __DIR__ . '/lib_table.inc';
require_once __DIR__ . '/lib_url.inc';
require_once __DIR__ . '/lib_xmlsnippets.inc';
require_once __DIR__ . '/classes/class_mailer.inc';
require_once __DIR__ . '/classes/class_marketurl.inc';
require_once __DIR__ . '/classes/class_stringbuffer.inc';
require_once __DIR__ . '/classes/class_table.inc';

// Forward slashes: these paths are concatenated onto base_path and used on
// Linux/WSL deployments too, where a literal 'cache\' backslash becomes part
// of the filename. Matches library/config/constants.php.
if (!defined("CACHE_PATH")) define("CACHE_PATH", '.cache/'); // consolidated to .cache/ in migration 9.16
if (!defined("CACHE_DIR_INDENT")) define("CACHE_DIR_INDENT", 'dirs');
define("CONSTFILEFORREADING", 1);
define("CONSTFILEFORWRITING", 2);
define("CONSTFILEATTRREADONLY", 1);
define("ADOPENREADONLY", 3);
?>
