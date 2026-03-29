<?php
use App\Library\Application;
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Cookie;
use App\Library\Debug;
use App\Library\Error;
use App\Library\Response;
use App\Library\Str;
use Cma\SecurityHelper;

require_once __DIR__ . '/bootstrap.inc';
require_once __DIR__ . '/menurep.inc';

Response::noCache();

// Define constants BEFORE they're used
define("APP_ID", 0);
define("APP_LOGO", 1);
define("APP_LOGO_WIDTH", 2);
define("APP_LOGO_HEIGHT", 3);
define("APP_LOGO_URL", 4);
define("LOGO_MARGIN", 6);

$sMenu = '';
$bLoggedIn = SecurityHelper::isLoggedIn();
// cma.js is now included in the centralized bundle loaded by cma_html_header
cma_html_header('', '', false);
echo '<script >';
Debug::setActive(false);
$arrApp = array();
main();
/**
* Menu_AddMainItem - Menu-specific version (different from subform AddMainItem)
*/
function Menu_AddMainItem($ID, $strText)
{
    global $sMenu;
    $sMenu = $sMenu . '<li id=t' . $ID . '><a href=javascript:changeNavMenu(' . $ID . ')>' . $strText . '</a></li>';
    echo 'l[' . $ID . ']=new Array();n[' . $ID . ']=new Array();f[' . $ID . ']=new Array();';
}
/**
* Menu_AddSubItem - Menu-specific version
*/
function Menu_AddSubItem($intParentID, $intID, $strURL, $strFunc, $strTitle)
{
    echo 'l[' . $intParentID . '][' . $intID . ']=' . Str::JscriptSafe($strURL) . ';n[' . $intParentID . '][' . $intID . ']=' . Str::JscriptSafe($strTitle) . ';f[' . $intParentID . '][' . $intID . ']=' . Str::JscriptSafe($strFunc) . ';';
}
/**
* Main
*
*/
function main()
{
    global $bLoggedIn, $sMenu;
    $sLevel1 = null;
    $iLevel1 = null;
    $iLevel2 = null;
    $strTitle = "";
    $strURL = "";
    $intStartMenuID = 0;
    $arrMenu = array();
    $Y = null;
    $strFunc = "";
    // Laad applicatie config uit menu.json via MenuService
    $appConfig = \Cma\Services\MenuService::getApplicationConfig();
    // Converteer naar legacy flat array formaat voor de rest van de code
    $arrApp = [
        APP_ID => [0],
        APP_LOGO => [$appConfig['logo'] ?? ''],
        APP_LOGO_WIDTH => [$appConfig['logoWidth'] ?? 200],
        APP_LOGO_HEIGHT => [$appConfig['logoHeight'] ?? 50],
        APP_LOGO_URL => [$appConfig['url'] ?? ''],
    ];
    if (!$bLoggedIn) {
    } else {
        echo PHP_EOL . 'var l=[],n=[],f=[];' . PHP_EOL;
        Menu_AddMainItem(0, 'Start');
        Menu_AddSubItem(0, 0, 'login.php target=C', "parent.window.frames['C'].location='login.php';", $lang_menu_startpage);
        $intStartMenuID = 1;
        if (SecurityHelper::isAdmin()) {
            Menu_AddSubItem(0, 1, 'form.php?form=users target=C', "loadPage('form.php?form=users');", $lang_usersMaintenance);
            Menu_AddSubItem(0, 2, 'form.php?form=groups target=C', "loadPage('form.php?form=groups');", $lang_groupsMaintenance);
            Menu_AddSubItem(0, 3, 'tools_clearcache.php target=C', "parent.window.frames['C'].location='tools_clearcache.php';", 'Cache leegmaken');
            $intStartMenuID = 4;
            if (Application::get('CMA_show_module_settings', '')) {
                Menu_AddSubItem(0, $intStartMenuID, 'mod_list.php target=C', "parent.window.frames['C'].location='mod_list.php';", $lang_module_settings);
                $intStartMenuID = $intStartMenuID + 1;
            }
        }
        // Profile menu items - before logout
        Menu_AddSubItem(0, $intStartMenuID, 'preferences.php target=C', "parent.window.frames['C'].location='preferences.php';", Application::get('cma_language', 'NL') === 'UK' ? 'Preferences' : 'Voorkeuren');
        $intStartMenuID = $intStartMenuID + 1;
        Menu_AddSubItem(0, $intStartMenuID, 'password.php target=C', "parent.window.frames['C'].location='password.php';", $lang_password_change);
        $intStartMenuID = $intStartMenuID + 1;
        Menu_AddSubItem(0, $intStartMenuID, 'logout.php target=C', '', $lang_Logout);
        $arrMenu = loadMenuData();
        $iLevel1 = 0;
        $sLevel1 = '_';
        if (Arr::isArray($arrMenu) && isset($arrMenu[MENU_MENUNAME])) {
            $menuCount = count($arrMenu[MENU_MENUNAME]);
            for ($Y = 0; $Y < $menuCount; $Y++) {
                // Check access rights
                $menuItemId = $arrMenu[MENU_MENUITEMID][$Y] ?? 0;
                $accessRights = SecurityHelper::checkRights(SecurityHelper::TYPE_MENU, $menuItemId);

                if ($accessRights > SecurityHelper::ACCESS_NONE) {
                    $menuName = $arrMenu[MENU_MENUNAME][$Y] ?? '';
                    $menuItemName = $arrMenu[MENU_MENUITEMNAME][$Y] ?? '';
                    $formName = $arrMenu[MENU_FORMNAME][$Y] ?? '';
                    $menuItemHref = $arrMenu[MENU_MENUITEMHREF][$Y] ?? '';

                    if ($sLevel1 != $menuName) {
                        $iLevel1 = $iLevel1 + 1;
                        $iLevel2 = 0;
                        $sLevel1 = $menuName;
                        Menu_AddMainItem($iLevel1, $sLevel1);
                    }
                    if ($menuItemName != '') {
                        $strTitle = $menuItemName;
                    } elseif (!empty($formName)) {
                        // Get title from JSON form definition
                        $formDef = \Cma\JsonFormLoader::loadRaw($formName);
                        $strTitle = $formDef['title'] ?? ucfirst($formName);
                    } else {
                        $strTitle = '';
                    }
                    if (!empty($formName)) {
                        $strURL = "form.php?form=" . urlencode($formName);
                        $strFunc = "loadPage('form.php?form=" . urlencode($formName) . "')";
                    } else {
                        $sLink = $menuItemHref;
                        // Replace all .asp with .php for converted pages
                        $sLink = str_ireplace('.asp', '.php', $sLink);
                        // Note: contentframe.php wrapper removed - pages load directly
                        $strURL = $sLink . ' target=C';
                        $strFunc = "parent.window.frames['C'].location='" . $sLink . "';";
                    }
                    Menu_AddSubItem($iLevel1, $iLevel2, $strURL, $strFunc, $strTitle);
                    $iLevel2 = $iLevel2 + 1;
                } else {
                    // Empty else block
                }
            }
        }
    }
    $sMenu = $sMenu;
}
echo '</script>';
echo '</head>';
echo '<body ';
echo ($bLoggedIn ? 'class="m_body' . (Application::get('test', '') ? ' test' : '') . '" onLoad="menu_init()"' : '');
echo ' style="margin-top:0px;padding-top:0px;height:';
echo ($arrApp[APP_LOGO_HEIGHT][0] < 60 ? 60 : $arrApp[APP_LOGO_HEIGHT][0]);
echo 'px">';
if ($arrApp[APP_LOGO][0]!= '') {
    echo '	<div style="float:right;display:inline-block;clear:both;padding:';
    echo LOGO_MARGIN;
    echo 'px;">';
    echo '		<a target="_blank" href="';
    echo ($arrApp[APP_LOGO_URL][0]!= '' ? $arrApp[APP_LOGO_URL][0] : '../');
    echo '" title="Ga naar de site">';
    echo '			<img id="logo" src="';
    echo Application::get('base_path', '') . $arrApp[APP_LOGO][0];
    echo '" style="margin-top:';
    echo ($arrApp[APP_LOGO_HEIGHT][0] < 60 ? 60 - $arrApp[APP_LOGO_HEIGHT][0] - 2 * LOGO_MARGIN / 2 : 0);
    echo 'px" ';
    echo ($arrApp[APP_LOGO_WIDTH][0]!= '' ? ' width=' . $arrApp[APP_LOGO_WIDTH][0] : '');
    echo ($arrApp[APP_LOGO_HEIGHT][0]!= '' ? ' height=' . $arrApp[APP_LOGO_HEIGHT][0] : '');
    echo '>';
    echo '		</a>';
    echo '	</div>';
}
echo '<div style="position:relative;left:0px;width:100%;z-index:2;padding-right:';
echo ($arrApp[APP_LOGO][0] != '' ? $arrApp[APP_LOGO_WIDTH][0] + 2 * LOGO_MARGIN : '0');
echo 'px;height:52px">';
echo ($bLoggedIn ? '<div><ul id="glow_tabs">' . $sMenu . '<li class="shadow"></li></ul></div><div id="submenu"></div>' : '');
echo '</div>';
echo '</body>';
echo '</html>';
?>
