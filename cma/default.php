<?php
use App\Library\Application;
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Error;
use App\Library\Html;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;

require_once __DIR__ . '/bootstrap.inc';

// Check menu style setting - user preference cookie overrides application default
$appMenuStyle = Application::get('cma_menu_style', 'sidebar');
$menuStyle = Cookie::get('cma_menu_style', $appMenuStyle);

// Both menu styles now use main.php (AJAX-based layout)
// sidebar = vertical sidebar on left
// classic = horizontal tabs at top
if (SecurityHelper::isLoggedIn()) {
    header('Location: /cma/dashboard');
    exit;
}

// Not logged in - redirect to login.php
header('Location: /cma/login.php');
exit;
?>
