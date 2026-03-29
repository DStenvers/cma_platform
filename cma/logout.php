<?php
/**
 * CMA Logout Handler
 *
 * Verwijdert alle sessie cookies en redirect naar de login pagina.
 * Ondersteunt ook SSO logout (verwijderen van SSO gerelateerde cookies).
 */

use App\Library\Cookie;
use App\Library\Response;
use App\Library\Session;
use Cma\SecurityHelper;
use Cma\Services\SsoService;

require_once __DIR__ . '/bootstrap.inc';

Response::noCache();

// Verwijder alle CMA cookies
Cookie::delete(SecurityHelper::COOKIE_USERID);
Cookie::delete(SecurityHelper::COOKIE_USERGUID);
Cookie::delete(SecurityHelper::COOKIE_LAST_LOGIN);

// Clear cached user data
SecurityHelper::clearUserCache();

// Verwijder SSO gerelateerde cookies
SsoService::clearSsoCookies();

// Verwijder session migration check flag
Session::remove('_migration_check_done');
Session::remove('_migration_needed');

// Redirect naar default page (toont login formulier)
// Gebruik window.top voor iframe compatibiliteit (classic frameset mode)
echo '<!DOCTYPE html><html><head><script>window.top.location="default.php";</script></head><body></body></html>';
