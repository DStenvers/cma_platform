<?php

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
require_once __DIR__ . '/classes/class_tabs.inc';

// Forward slashes: these paths are concatenated onto base_path and used on
// Linux/WSL deployments too, where a literal 'cache\' backslash becomes part
// of the filename. Matches library/config/constants.php.
if (!defined("XSLT_PATH")) define("XSLT_PATH", 'assets/xslt/'); // moved to assets/xslt/ in migration 9.17/9.18
if (!defined("CACHE_PATH")) define("CACHE_PATH", '.cache/'); // consolidated to .cache/ in migration 9.16
if (!defined("CACHE_DIR_INDENT")) define("CACHE_DIR_INDENT", 'dirs');
define("CONSTFILEFORREADING", 1);
define("CONSTFILEFORWRITING", 2);
define("CONSTFILEATTRREADONLY", 1);
define("ADOPENREADONLY", 3);
?>
