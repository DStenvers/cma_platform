<?php
/**
 * @deprecated This file serves the old menu and preferences/change password 
 */
use App\Library\Application;
use App\Library\Arr;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use App\Library\SQL;
use Cma\SecurityHelper;
use Cma\Services\SsoService;

require_once __DIR__ . '/bootstrap.inc';

/**
* Main
* @deprecated See file-level deprecation notice
*/
function main()
{
    Response::noCache();
    $strError = '';
    $sIPAdres = Request::server('REMOTE_HOST', '');
    $blnOK = $sIPAdres == '109.237.208.163' || $sIPAdres == '185.224.89.229';
    // always grant access from the server (given a valid login)
    $strPageAction = Request::query('pageaction', '');
    $blnLoggedIn = false;
    $SQL = null;
    $rs = null;
    $dbconn = null;
    $SQLGroup = null;
    $dummy = null;
    $blnShowForm = true;
    $intUserID = Cookie::get(SecurityHelper::COOKIE_USERID, '');

    // If cookie present but not a POST (return visit), validate session early
    // This prevents redirect loops: login → main → default → login
    if ($intUserID != '' && Request::post('naam', '') == '') {
        if (!SecurityHelper::isLoggedIn()) {
            // Stale cookie - clear it and show login form
            Cookie::delete(SecurityHelper::COOKIE_USERID);
            Cookie::delete(SecurityHelper::COOKIE_USERGUID);
            SecurityHelper::clearUserCache();
            $intUserID = '';
        }
    }

    $dbconn = Database::getConnection('users');
    if (Request::post('naam', '') != '') {
        // Case-insensitive login lookup
        // Use lower() with PHP fallback for ODBC Access where lower()/lcase() may not work
        $postLogin = strtolower(Request::post('Naam', '') ?? '');
        $SQL = 'select * from tblUsers WHERE lower(userLogin)=' . SQL::postString($postLogin);
        $rs = Database::openRS($SQL, $dbconn, adOpenForwardOnly);
        // If lower() fails (ODBC Access), fall back to scanning all users in PHP
        if ($rs !== null && $rs->EOF) {
            $SQL = 'select * from tblUsers';
            $rsAll = Database::openRS($SQL, $dbconn, adOpenForwardOnly);
            if ($rsAll !== null) {
                while (!$rsAll->EOF) {
                    if (strtolower($rsAll->fields['userLogin'] ?? '') === $postLogin) {
                        $rs = $rsAll; // found - use this recordset positioned at the match
                        break;
                    }
                    $rsAll->MoveNext();
                }
            }
        }
        if ($rs === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }
        if ($rs->EOF) {
            $strError = $lang_LoginInvalid;
        } else {
            $blnOK = true;
            if ((is_null(Request::post('wachtwoord', '')) ? "" : strtolower(Request::post('wachtwoord', ''))) != (is_null($rs->fields['userPassword']) ? "" : strtolower($rs->fields['userPassword']))) {
                $strError = $lang_LoginInvalid;
             } else {
                $blnOK = !Application::get('cma_ip_protect', '');
                $blnLoggedIn = true;
                if (!$blnOK) {
                    if (Application::get('local', '')) {
                        $blnOK = true;
                    } else {
                        if ((is_null(Request::post('naam', '')) ? "" : strtolower(Request::post('naam', ''))) == 'admin') {
                            $blnOK = true;
                        } else {
                            if ($rs->fields['userIPAddresses']!= '') {
                                $ipPatterns = Arr::splitAlways($rs->fields['userIPAddresses'], ';');
                                if (SecurityHelper::ipMatchesAnyPattern($sIPAdres, $ipPatterns)) {
                                    $blnOK = true;
                                }
                            }
                            if (!$blnOK) {
                                $SQLGroup = 'SELECT tblGroups.groupIPAddresses FROM tblGroups INNER JOIN tblGroupMembers ON tblGroups.ID = tblGroupMembers.fkGroup WHERE (tblGroups.groupIPAddresses Is Not Null) AND (fkUser=' . $rs->fields['ID'] . ')';
                                $rsGroup = Database::openRS($SQLGroup, $dbconn, adOpenForwardOnly, adLockReadOnly);
                                if ($rsGroup === null) {
                                    throw new \Exception('Database query failed: ' . Database::getLastError());
                                }
                                if ($rsGroup->EOF) {
                                    $blnOK = true;
                                } else {
                                    $strError = 'Niet geautoriseerd IP adres (' . $sIPAdres . ')';
                                    while (!$rsGroup->EOF) {
                                        $groupIpPatterns = Arr::splitAlways($rsGroup->fields['groupIPAddresses'], ';');
                                        if (SecurityHelper::ipMatchesAnyPattern($sIPAdres, $groupIpPatterns)) {
                                            $blnOK = true;
                                            $strError = '';
                                        }
                                        $rsGroup->MoveNext();
                                    }
                                }
                            }
                        }
                    }
                }
                if ($blnOK) {
                    $intUserID = $rs->fields['ID'];

                    // Set authentication cookies (userID + userGUID for dual validation)
                    Cookie::set(SecurityHelper::COOKIE_USERID, (string)$rs->fields['ID']);

                    // Get or generate userGUID for dual validation (consistent with SSO callback)
                    $row = $rs->fields;
                    $userGUID = $row['userGUID'] ?? '';
                    if (empty($userGUID)) {
                        $userGUID = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                            mt_rand(0, 0xffff),
                            mt_rand(0, 0x0fff) | 0x4000,
                            mt_rand(0, 0x3fff) | 0x8000,
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                        );
                        try {
                            $guidConn = Database::getConnection('users');
                            if ($guidConn) {
                                Database::query("UPDATE tblUsers SET userGUID = ? WHERE ID = ?", [$userGUID, intval($rs->fields['ID'])], $guidConn);
                            }
                        } catch (\Exception $e) {
                            // GUID column may not exist yet - non-fatal
                            error_log("[login] Could not save userGUID: " . $e->getMessage());
                        }
                    }
                    Cookie::set(SecurityHelper::COOKIE_USERGUID, $userGUID);
                    Cookie::set(SecurityHelper::COOKIE_LAST_LOGIN, $rs->fields['userLogin']);
                } else {
                    Cookie::delete(SecurityHelper::COOKIE_USERID);
                    Cookie::delete(SecurityHelper::COOKIE_USERGUID);
                }
            }
            if (Database::getFieldValue('users', 'select count(*) as Aantal from tblUsers where (userAdministrator=true)', '') < '1') {
                Database::execute("INSERT INTO tblUsers (userLogin, userFullName, userPassword, userAdministrator) VALUES ('Admin', 'Administrator', 'Admin', True)");
            }
            if (Database::getFieldValue('users', 'select count(*) as Aantal from tblGroups where (ID=0)', '') < '1') {
                Database::execute("INSERT INTO tblGroups (ID, grpName) VALUES (0, 'Iedereen')");
            }
        }
        $dbconn = null;
        $blnShowForm = Cookie::get(SecurityHelper::COOKIE_USERID, '') == '';
        if (!$blnShowForm) {
            // Check if using sidebar menu style - redirect to main.php
            // User preference cookie overrides application default
            $appMenuStyle = Application::get('cma_menu_style', 'sidebar');
            $menuStyle = Cookie::get('cma_menu_style', $appMenuStyle);
            if ($menuStyle === 'sidebar') {
                $query = Request::server('QUERY_STRING', '');
                $redirect = 'main.php' . ($query ? '?' . $query : '');
                header('Location: ' . $redirect);
                exit();
            }
            if (Request::query('formID', '') != '') {
                echo '<script>window.location=\'form.php?' . Request::server('QUERY_STRING', '') . '\';window.parent.frames[\'U\'].location.reload(false);</script>';
                exit();
            }
        }
    }
    // Preload main bundle while user types password - browser cache priming
    // cma_html_header will load the full bundle which includes cma.js
    $extraHead = '<link rel="preload" href="' . cma_css_url() . '" as="style">' . PHP_EOL;
    $extraHead .= '<link rel="preload" href="' . cma_js_url() . '" as="script">' . PHP_EOL;
    $extraHead .= '<link rel="preload" href="../library/jquery.min.js" as="script">' . PHP_EOL;
    // Only load form validation (language-specific, not in bundle)
    $extraHead .= '<script src="' . minify_asset('../library/formval_' . strtolower(Application::get('cma_language')) . '.js') . '"></script>';
    cma_html_header('', $extraHead, false);
    // force refresh of menu-bar
    if (Request::post('naam', '') != '' && $blnOK && $blnLoggedIn) {
        // Check if using sidebar menu style - redirect to main.php
        // User preference cookie overrides application default
        $appMenuStyle = Application::get('cma_menu_style', 'sidebar');
        $menuStyle = Cookie::get('cma_menu_style', $appMenuStyle);
        if ($menuStyle === 'sidebar') {
            echo '<script>window.top.location="main.php";</script>';
            exit();
        }
        echo '		<script>;
        if (window.parent.frames) {
            window.parent.frames[\'U\'].location.reload(false);
            window.parent.frames[\'C\'].location="login.php?dummy";
        }
        </script>';
        exit();
    }
    echo '</head>';
    // Use flexbox for vertical centering when showing login form only
    if ($blnShowForm && $intUserID == '') {
        echo '<body class="login-page" style="display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:linear-gradient(135deg, #1e1e2d 0%, #434372 100%);">';
        echo '<div>';
    } else {
        echo '<body class="' . ($blnShowForm ? '' : 'blockselect') . '" style="margin-top:35px">';
    }
    // Skip table structure for login-only centered layout
    if (!($blnShowForm && $intUserID == '')) {
        echo '<table style="width:100%">';
        echo '<tr valign=top>';
    }
    if ($intUserID != '') {
        // Gebruiker is ingelogd - redirect naar main.php
        header('Location: main.php');
        exit();
    } else {
        // For login-only centered layout, skip the empty td columns
        if (!($blnShowForm && $intUserID == '')) {
            echo '	<td style="width:25%"></td>';
            echo '	<td style="text-align:center;width:50%">';
        }
        if ($blnShowForm) {
            Response::cacheExpires(20);
            flush();
            ob_flush();
            $bShowForgotten = false;
            echo '<form onkeydown="if((13 == event.keyCode) || (13 == event.which)) {this.submit()}" data-button-name="Inloggen" action="login.php?dummy=' . date("H:i:s") . '&' . Request::server('QUERY_STRING', '') . '" data-show-tooltip="N" id="login" name="login" method="post" autocomplete="off">' . PHP_EOL;
            // Get app logo for login header
            $appLogoConfig = cma_get_app_logo();
            $logoPath = $appLogoConfig['logo'] ?? '';
            $bgColor = $appLogoConfig['backgroundColor'] ?? '#3F096E';
            $logoHeight = $appLogoConfig['height'] ?? 62;
            // Login box with logo header
            echo '<div class="kader">' . PHP_EOL;
            if (!empty($logoPath)) {
                echo '<div class="login-header" style="background:' . htmlspecialchars($bgColor) . ';padding:20px;text-align:center;">';
                echo '<img src="' . htmlspecialchars($logoPath) . '" alt="Logo" style="max-height:' . ($logoHeight + 20) . 'px;max-width:100%;height:auto;">';
                echo '</div>' . PHP_EOL;
            }
            // Toon SSO foutmelding indien aanwezig
            $ssoError = Request::query('sso_error', '');
            if (!empty($ssoError)) {
                echo '<lib-message type="error" closable style="margin:15px;">';
                echo htmlspecialchars($ssoError);
                echo '</lib-message>' . PHP_EOL;
            }
            // SSO Login knop (indien ingeschakeld)
            if (SsoService::isEnabled()) {
                $ssoProviderName = SsoService::getProviderName();
                echo '<div class="sso-login" style="padding:20px;text-align:center;">';
                echo '<a href="sso_login.php" class="btn btn-primary sso-button">';
                echo htmlspecialchars($ssoProviderName);
                echo '</a>';
                echo '</div>' . PHP_EOL;

                // Scheidingslijn met "of"
                echo '<div class="login-divider" style="display:flex;align-items:center;margin:10px 20px;">';
                echo    '<hr><span style="padding:0 15px;color:#666;font-size:var(--font-size-sm);">of</span><hr>';
                echo '</div>' . PHP_EOL;
            }
            if (Request::post('actie', '') == 'login_vergeten' && Request::post('email', '') != '') {
                $postEmail = strtolower(Request::post('email', '') ?? '');
                $SQL = 'select * from tblUsers WHERE userEMail=' . SQL::postString($postEmail);
                $rs = Database::openRS($SQL, 'users', adOpenDynamic, adLockOptimistic);
                if ($rs === null) {
                    throw new \Exception('Database query failed: ' . Database::getLastError());
                }
                if ($rs->EOF) {
                    if (Application::get('Mod_language', '') == 'UK') {
                        $strError = 'Email address not found';
                    } else {
                        $strError = 'Email adres niet gevonden';
                    }
                    $bShowForgotten = true;
                } else {
                    $Mailer = new LibMailer();
                    $Mailer->body = 'Zoals verzocht sturen wij je hierbij jouw login gegevens voor de Content Management Applicatie van ' . str_replace('www.', '', (is_null(Request::server('SERVER_NAME', '')) ? "" : strtolower(Request::server('SERVER_NAME', '')))) . '.<BR><BR>Login : ' . $rs->fields['userLogin'] . '<br>Wachtwoord : ' . $rs->fields['userPassword'] . '<br><br>Beide zijn niet hoofdlettergevoelig.';
                    $Mailer->subject = 'Login informatie CMA ' . str_replace('www.', '', (is_null(Request::server('SERVER_NAME', '')) ? "" : strtolower(Request::server('SERVER_NAME', ''))));
                    $Mailer->AddRecipient( Request::post('email', ''));
                    $Mailer->Send();
                    $Mailer = null;
                }
                if ($strError == '') {
                    echo 'Jouw login-gegevens zijn verstuurd naar ' . Request::post('email', '') . '.<br>';
                }
            }
            echo '<input type=hidden value="' . Request::server('QUERY_STRING', '') . '" name=nextpage />';
            echo '<table cellpadding=8 cellspacing=0 id="loginForm" ' . ($bShowForgotten ? 'style=display:none' : '') . '>';
            if ($strError) {
                echo '<tr><td colspan=9 class=loginerror>' . $strError . '</td></tr>';
            }
            echo '<tr><td><label class=naam for=txtLogin><span>naam</span></label><input type=text class=naam id="txtLogin" data-required=Y name=naam placeholder=Naam data-disable-checkmark=Y autocomplete="username" value="' . Cookie::get(SecurityHelper::COOKIE_LAST_LOGIN, '') . '"></td></tr>';
            echo '<tr><td><label class=password for=txtPW><span>wachtwoord</span></label><input class=wachtwoord type=password id="txtPW" data-required=Y placeholder=Wachtwoord data-disable-checkmark=Y maxlength=32 name=wachtwoord autocomplete="current-password"></td></tr>';
            echo '<tr><td colspan=9 nowrap style="padding-bottom:12px">';
            echo '<button type="submit" class="btn btn-primary" style="width:128px" id="btnLogin">Inloggen</button>';
            echo "<span class=login_vergeten><a href=# onclick=\"jQuery('#loginForm').hide();jQuery('#login_vergeten_form').show();jQuery('#email_id').focus()\">Inloggegevens vergeten?</a></span></td></tr></table></form>" . PHP_EOL;
            echo '<form onkeydown="if((13 == event.keyCode) || (13 == event.which)) {this.submit()}" action="login.php" data-button-name="Verstuur login" id="login_vergeten_frm" name="login_vergeten_frm" method="post">' . PHP_EOL;
                echo '<input type=hidden name=actie value=login_vergeten>' . PHP_EOL;
                echo '<table cellpadding=3 cellspacing=0 id=login_vergeten_form ' . ($bShowForgotten ? 'style=display:block' : '') . ' >';
                echo '<tr colspan=2><td>Geef je email adres, we sturen je login-gegevens daarnaar op.<br>&nbsp;</td></tr>';
                echo '<tr><td><label class=email for=email_id><span>email adres</span></label><input type=text class=email id=email_id data-validation-type=email data-required=Y maxlength=56 name=email placeholder="email adres" autocomplete="email"></td></tr>';
                echo '<tr><td><a class="btn-primary" style="width:128px" href="javascript:if (form_valid(document.forms.login_vergeten_frm)){document.forms.login_vergeten_frm.submit()}" id="go">Verstuur login</a><span class=login_vergeten><a href=# onclick="jQuery(\'#loginForm\').show();jQuery(\'#login_vergeten_form\').hide();jQuery(\'#email_id\').focus()">Terug naar inloggen</a></span></td></tr>';
                echo '</table>' . PHP_EOL;
            echo '</form>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
            echo '<script>';
            echo 'jQuery(document).ready(function() { ';
            if ($strError) {
                echo 'jQuery("form#login div.kader").addClass("shake");' . PHP_EOL;
            }
            if ($bShowForgotten) {
                echo 'document.getElementById("email_id").focus();' . PHP_EOL;
            } else {
                echo 'document.getElementById("' . (Cookie::get(SecurityHelper::COOKIE_LAST_LOGIN, '') != '' ? 'txtPW' : 'txtLogin') . '").focus();' . PHP_EOL;
            }
            echo '});</script>' . PHP_EOL;
        } else {
            ob_flush();
            flush();
        }
        // For login-only centered layout, skip closing td
        if (!($blnShowForm && $intUserID == '')) {
            echo '	</td>';
        }
    }
    // Skip password change section for login-only centered layout
    if ($blnShowForm && $intUserID == '') {
        // Don't show welcome section for login-only layout
    } else {
        echo '<td style="width:25%;text-align:right;padding-left:10px;padding-right:30px;vertical-align:top">';
        if (Cookie::get(SecurityHelper::COOKIE_USERID, '') != '') {
            // Welcome message with profile links
            echo '<div class="kader welkomkader">';
            echo    $lang_welcome . ' <b>' . SecurityHelper::getCurrentUserName() . '</b>!';
            echo    '<div class="profile-links">';
            echo      '<a href="preferences.php" target="C">' . (Application::get('cma_language', 'NL') === 'UK' ? 'Preferences' : 'Voorkeuren') . '</a>';
            echo      '<a href="password.php" target="C">' . $lang_password_change . '</a>';
            echo    '</div>';
            echo '</div>';
        }
        echo '</td></tr></table>';
    }
    // Close wrapper div for login-only centered layout
    if ($blnShowForm && $intUserID == '') {
        echo '</div>';
    }
    echo '</body>';
    echo '</html>';
}
// Call main function
main();
?>