<?php
use App\Library\Application;
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Debug;
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\SQL;
use App\Library\Server;
use App\Library\Str;

require_once __DIR__ . '/../../library/library.inc';

define("COOKIE_ID", 'USID');
define("COOKIE_NAME", 'UNAME');
define("COOKIE_GUID", 'CHK');
define("COOKIE_CARTAID", 'UCID');
define("QUERYPAR_RET", 'ReturnURL');
define("FIELD_RETURNURL", 'ReturnURL');
// Toegevoegd op 12-1-2021 : inloggen via een sessieID, via een parameter of (in de stap daarna) een cookie
// ================================================================================== Custom login information
define("COOKIE_TYPE", 'TYPE');
// De actieve rol van de ingelogde gebruiker
define("COOKIE_OPL", 'OPL');
// De IDs van de opleidingen waar de gebruiker toegang toe heeft, bji de assitent de eigen opleidingen
define("COOKIE_ALLEOPL", 'ALLE_OPL');
// De IDs van de opleidingen waar de gebruiker toegang toe heeft, bij de assistent alles van de afdeling
define("COOKIE_HOOFDOPL", 'HOOFDOPL');
// De IDs van de opleidingen waar de gebruiker jaargroepopleiderrechten heeft
define("COOKIE_ID_DE", 'ID_DE');
// Deelnemer ID van de ingelogde gebruiker
define("COOKIE_ID_DO", 'ID_DO');
// Docent ID van de ingelogde gebruiker
define("COOKIE_ID_PO", 'ID_PO');
// Praktijkopleider ID van de ingelogde gebruiker
define("COOKIE_ID_AS", 'ID_AS');
// Assistent ID van de ingelogde gebruiker
define("COOKIE_ID_POPL", 'ID_POPL');
// P-opleider ID van de ingelogde gebruiker
define("COOKIE_ID_SRH_SP", 'ID_SRH_SP');
// SRH_ServicePakket ID van de ingelogde gebruiker
define("COOKIE_ID_CONT_INV", 'ID_CI');
// Contactpersoon ID van de ingelogde gebruiker
define("COOKIE_ID_SUPERVISOR", 'ID_SUPERVISOR');
// Supervisor ID van de ingelogde gebruiker
define("COOKIE_ID_WERKBEGELEIDER", 'ID_WERKBEGELEIDER');
// Werkbegeleider ID van de ingelogde gebruiker
define("COOKIE_ID_KLANTCP", 'ID_KLANTCP');
// Klant Contactperson ID van de ingelogde gebruiker
define("COOKIE_DELEGEER", 'DELEGEER_WEERGAVE');
// P-opleider : welke delegeer weergave?
define("COOKIE_SESSIE", 'SESSIONID');
define("COOKIE_WEERGAVE", 'WEERGAVE');
// Naam cookie ID voor geselecteerde opleiding
define("COOKIE_WEERGAVETYPE", 'WEERGAVE_TYPE');
// Naam cookie voor de agenda weergave (gesprekken/rooster)
define("COOKIE_AANTAL_ROLLEN", 'AANTALROLLEN');
// Het aantal rollen die (dat?) iemand bekleed
define("COOKIE_ROL", 'GEWENSTE_ROL');
// Opslag voor verzoek om wissel van de rol, bewaart de laatste
define("COOKIE_EXTERN_MANAGER", 'EXTERN');
// Is deze manager extern?
define("COOKIE_OPL_EXCL_CURS", 'OPL_EXCL_CURS');
// Voor een deelnemer: alle opleidingen exclusief degene waar alleenCursorisch staat of waar BNSOpleiding aan staat!
define("COOKIE_PRAKTIJKOPL", 'PRAKTOPL');
// Registreert de praktijkopleiders bij een P-opleider
define("COOKIE_VERSLAG_OPLEIDING", 'verslag_opleiding');
// gebruikt voor actieve keuze (half)jaarverslagen
define("COOKIE_VERSLAG_PERIODE", 'verslag_periode');
// gebruikt voor actieve keuze (half)jaarverslagen
define("COOKIE_DOELGROEPEN", 'doelgroepen');
// behoort tot ; kommagescheiden lijst met doelgroepn
define("COOKIE_VERKLARING_OPLEIDING", 'verklaring_opleiding');
// gebruikt voor actieve keuze verklaringen
define("COOKIE_OPROEP", 'oproep');
// gebruikt voor bijhouden of in de huidige sessie de profielfoto oproep al is gedaan
define("COOKIE_ORIGINAL_LOGIN", 'original_login');
// gebruikt om de originele login te bewaren als er login_als gebruikt is
define("COOKIE_ZALENCENTRUM", 'ZC');
// Zalencentrum Ja/Nee
define("COOKIE_PLANNING", 'plan');
// Plannings-scherm Ja/Nee
define("COOKIE_SERVICEBUREAU", 'serv');
// Servicebureau Ja/Nee
define("QUERYSTRING_SESSIE", 'sessieID');
define("QUERYSTRING_ROL", 'rol');
define("USER_TYPE_DEELNEMER", 1423);
define("USER_TYPE_DOCENT", 2224);
define("USER_TYPE_DOCENT_BIG_HO_GZ", 8123);
define("USER_TYPE_HOOFDDOC", 5480);
define("USER_TYPE_HOOFDOPL", 7291);
define("USER_TYPE_JAARGROEPOPL", 9912);
define("USER_TYPE_PRAKTIJKOPL", 7873);
define("USER_TYPE_P_PRAKTOPL", 3455);
// P-opleiders
define("USER_TYPE_ASSISTENT", 8312);
// LET OP: Dit kunnen ook managers zijn!
define("USER_TYPE_CONTACTP_INV", 9989);
// NOG niet in gebruik in Adam
define("USER_TYPE_SUPERVISOR", 8300);
// niet in gebruik in Adam
define("USER_TYPE_WERKBEGELEIDER", 8301);
// niet in gebruik in Adam
define("USER_TYPE_KLANTCP", 9131);
// niet in gebruik in Adam
// Initialise Login module
$myLogin = null;
Login::$strPostLogin = 'Login_GetRelated';
Login::$strCustomPwdSQL = ' SELECT  	tblLogins.ID,  	tblLogins.Login,  	tblLogins.toegangscode,  	tblLogins.guid,  	tblLogins.Roepnaam,  	tblLogins.email_primair AS loginemail  FROM  	tblklantcontactpersonen RIGHT JOIN ( 	 tblPraktijkOpleiders    RIGHT JOIN ( 	  tblWerkbegeleiders      RIGHT JOIN ( 	   tblSupervisoren         RIGHT JOIN ( 	    tblDocenten             RIGHT JOIN ( 	     tblDeelnemers           RIGHT JOIN ( 	      tblSRHServicePakket     RIGHT JOIN ( 	       tblSRHForumLeden        RIGHT JOIN  	        tblLogins  	          ON tblSRHForumLeden.ID        = tblLogins.fkSRHForumLid 	       ) ON tblSRHServicePakket.ID     = tblLogins.fkSRHServicePakket 	      ) ON tblDeelnemers.ID           = tblLogins.fkDeelnemer 	     ) ON tblDocenten.ID             = tblLogins.fkDocent 	    ) ON tblSupervisoren.ID         = tblLogins.fkSupervisor 	   ) ON tblWerkbegeleiders.ID      = tblLogins.fkWerkbegeleider 	  ) ON tblPraktijkOpleiders.ID    = tblLogins.fkPraktijkOpleider 	 ) ON tblklantcontactpersonen.ID = tblLogins.fkKlantContactPersoon  WHERE ' . " 	tblLogins.email_primair = '%[email]%'";
// deze stonden er ook bij, maar dat kan tot 'interessante resultaten' leiden
// " 	 OR (tblSRHServicePakket.email     LIKE '%[email]%')" & ' " 	 OR (tblSRHForumLeden.email        LIKE '%[email]%')" & ' " 	 OR (tblDeelnemers.email           LIKE '%[email]%')" & ' " 	 OR (tblDocenten.email             LIKE '%[email]%')" & ' " 	 OR (tblSupervisoren.email         LIKE '%[email]%')" & ' " 	 OR (tblWerkbegeleiders.email      LIKE '%[email]%')" & ' " 	 OR (tblPraktijkOpleiders.email    LIKE '%[email]%')" & ' " 	 OR (tblklantcontactpersonen.email LIKE '%[email]%')"
$UserOpleidingen = null;
// opleiding(en) die getoond moeten worden / filtering
$UserAlleOpleidingen = null;
// alle opleidingen waar gebruiker bij betrokken is (alleen voor selectie opleiding gebruiken)
$UserJaargroepopleiderOpl = null;
$UserPraktijkopleiders = null;
$UserAantalRollen = null;
// het aantal rollen dat iemand bekleed
$UserOplExclCursorisch = null;
// Alle opleidingen exclusief degene waar alleenCursorisch aanstaat
$UserPlanning = null;
// Is de gebruiker een assistent die toegang heeft tot plannings-scherm?
$USER_bServiceBureau = null;
$LoginType = null;
$deelnemerID = '';
$docentID = '';
$praktijkopleiderID = '';
$p_praktoplID = '';
$assistentID = '';
$ContInvID = '';
$SRHServicepakketID = '';
$SupervisorID = '';
$WerkbegeleiderID = '';
$klantContactpersoonID = '';
// Base class for login
if (class_exists('Login')) { return; }
class Login
{
    public static $strPostLogin;
    public static $blnFirstLogin;
    public static $datLastLogin;
    public static $sReturnURL;
    public static $strTableName;
    public static $strFieldLoginName;
    public static $strFieldPwdName;
    public static $strFieldLastLoginName;
    public static $strCustomPwdSQL;
    public static $strLoginVolledigeNaam;
    public $strLoginRoepnaam;
    public static $PromptusID;
    private static $strLoginError;
    /**
    * Loginas
    *
    */
    public function LoginAs($userGuid)
    {
        global $GLOBALS_current_row, $guid, $id, $previous, $userID;
        $previous = null;
        $previous = $guid;
        $original = null;
        $original = Cookie::get(COOKIE_ORIGINAL_LOGIN, '');
        $guidSQL = null;
        $guidRS = null;
        $guidRS = null;
        $userID = null;
        $userID = '';
        // Get the userID based upon the guid
        $guidSQL = 'select ID, PromptusID from ' . $strTableName . ' WHERE Guid=' . SQL::postGuid($userGuid);
        Database::openRS($guidRS, $guidSQL, 'data', adOpenForwardOnly);
        if ($guidRS === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }
        $guidRS->MoveNext();
        if (!$guidRS->eof) {
            $userID = $guidRS['ID'];
            self::$PromptusID = $guidRS['PromptusID'];
        }
        $guidRS = null;
        if ($userID != '') {
            $this->TryLogin($userID);
            if ($original== $id . '') {
                Cookie::delete(COOKIE_ORIGINAL_LOGIN);
            } else {
                if ($id !== '') {
                    Cookie::set(COOKIE_ORIGINAL_LOGIN, ($previous == "" ? '-1' : $previous));
                }
            }
        }
    }
    /**
    * Loginbyemailaddress
    *
    */
    public function LoginByEmailAddress($sUserEmail)
    {
        global $GLOBALS_current_row, $bRetval, $guid, $previous, $userID;
        $previous = null;
        $previous = $guid;
        $sUserEmailSQL = null;
        $rUserEmailRS = null;
        $rUserEmailRS = null;
        $userID = null;
        $userID = '';
        $bRetval = null;
        $bRetval = false;
        // Get the userID based upon the LOGIN EMAIL address
        $sUserEmailSQL = ' SELECT ID, PromptusID  FROM ' . $strTableName . ' WHERE (actief=true)  	AND (email_primair="' . $sUserEmail . '"	OR login="' . $sUserEmail . '")';
        Database::openRS($rUserEmailRS, $sUserEmailSQL, 'data', adOpenForwardOnly);
        if ($rUserEmailRS === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }
        $rUserEmailRS->MoveNext();
        if (!$rUserEmailRS->eof) {
            $userID = $rUserEmailRS['ID'];
            self::$PromptusID = $rUserEmailRS['PromptusID'];
        }
        $rUserEmailRS = null;
        if ($userID != '') {
            $bRetval = $this->TryLogin($userID);
        }
        return $bRetval;
    }
    /**
    * Storevalue
    *
    */
    public static function StoreValue($sName, $sValue)
    {
        global $id, $sValue;
        $id = Cookie::get(COOKIE_ID, '');
        if ($id !== '') {
            Application::set('login_setting_' . $id . '_' . $sName, $sValue);
        }
    }
    /**
    * Retrievevalue
    *
    */
    public static function RetrieveValue($sName)
    {
        global $id;
        $id = Cookie::get(COOKIE_ID, '');
        if ($id !== '') {
            return Application::get('login_setting_' . $id . '_' . $sName, '');
        }
    }
    /**
    * Try
    *
    */
    public function TryLogin($userID)
    {
        global $GLOBALS_current_row, $rs, $rs_current_row, $sUserName, $sql;
        $rs = null;
        $sql = null;
        $sUserName = null;
        $sPassword = null;
        $strInfo = "";
        $parSessie = null;
        $parSessie = Request::query(QUERYSTRING_SESSIE, '') != '';
        if ($userID === "") {
            $userID = Cookie::get(COOKIE_ID, '');
        }
        if ($userID === '' && $parSessie != '') {
            $this->id = $this->LoginWithSessionID();
            if ($this->id!= '') {
                $this->StoreValue(COOKIE_TYPE, USER_TYPE_DEELNEMER);
                if (Request::query(QUERYSTRING_ROL, '') != '') {
                    $aRol = null;
                    $aRol = Arr::splitAlways(Request::query(QUERYSTRING_ROL, ''), ',');
                    $cRol = null;
                    $cRol = $aRol["0"];
                    $this->StoreValue(COOKIE_TYPE, $cRol);
                    $LoginType = intval(round(floatval($cRol)));
                    $this->StoreValue(COOKIE_ROL, $cRol);
                    Login_GetRelated();
                }
            }
        }
        if ($userID !== '') {
            $SQL = 'select ID, PromptusID, [Guid], ' . $strFieldLastLoginName . ', WeergaveNaam, Roepnaam, Login from ' . $strTableName . ' WHERE actief=true and ID=' . $userID;
        } else {
            $sUserName = Request::post('Login', '');
            $sPassword = Request::post('Pwd', '');
            $SQL = 'select ID, PromptusID, [Guid], ' . $strFieldLastLoginName . ', WeergaveNaam, Roepnaam, Login from ' . $strTableName . ' WHERE (actief=true) and (' . $strFieldLoginName . '=' . SQL::postString($sUserName) . ' or email_primair=' . SQL::postString($sUserName) . ') and password_enc=' . SQL::postString(Encryption::sha256($sPassword));
        }
        $libSQL_KeepOffAmp = true;
        $rs = Database::openRS($sql, 'data', adOpenForwardOnly, adLockReadOnly);
        if ($rs === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }
        $rs->MoveNext();
        if (!$rs->EOF) {
            $this->id = Arr::field($rs_current_row, 'ID') . '';
            $this->guid = Arr::field($rs_current_row, 'GUID') . '';
            self::$PromptusID = Arr::field($rs_current_row, 'PromptusID') . '';
            if ($this->id == '') {
                Error::show('Cookies niet toegestaan<br>Het lijkt erop dat je browser het niet toestaat om een cookie te plaatsen.<br>Zet cookies aan en probeer opnieuw in te loggen.');
                exit();
            } else {
                self::$blnFirstLogin = is_null(Arr::field($rs_current_row, $strFieldLastLoginName));
                self::$datLastLogin = Arr::field($rs_current_row, $strFieldLastLoginName);
                Cookie::set('login_naam', (Request::post('save_login', '') != '' ? Arr::field($rs_current_row, 'Login') : ''));
                Cookie::get('login_naam', '');
                /* ERROR: Could not convert statement */
                Cookie::set('email', Arr::field($rs_current_row, 'Login'));
                Cookie::get('email', '');
                /* ERROR: Could not convert statement */
                self::$strLoginVolledigeNaam = Str::trim((Arr::field($rs_current_row, 'WeergaveNaam')) ?? '');
                $this->strloginroepnaam = Str::trim((Arr::field($rs_current_row, 'Roepnaam') . '') ?? '');
                if (self::$strLoginVolledigeNaam !== '') {
                    Cookie::set(COOKIE_NAME, self::$strLoginVolledigeNaam);
                    Cookie::get(COOKIE_NAME, '');
                }
                if ($this->strloginroepnaam != '') {
                    Cookie::set('UROEPNAAM', $this->strloginroepnaam);
                    Cookie::get('UROEPNAAM', '');
                }
                Cookie::set(COOKIE_CARTAID, self::$PromptusID);
            }
        }
        $rs = null;
        if ($this->isLoggedIn()) {
            return true;
            if ($strPostLogin !== '') {
                eval($strPostLogin);
            }
            self::$strLoginError = '';
        } else {
            return false;
            if (Application::get('Mod_language', '') == 'UK') {
                self::$strLoginError = 'Login not succeeded';
            } else {
                self::$strLoginError = 'Login niet geslaagd';
            }
        }
    }
    /**
    * Forceloginandreturn
    *
    */
    public function ForceLoginAndReturn()
    {
        $strRedirectUrl = "";
        if (Application::get('force_sso', '')) {
            $strRedirectUrl = Application::get('sso_client_base_url', '') . Application::get('sso_login_page', '');
            $strRedirectUrl = $strRedirectUrl . '?returnUrl=' . Server::urlEncode(Request::getOriginalScript() . '?' . str_replace('&', '%26', Request::server('QUERY_STRING', '')));
        } else {
            $strRedirectUrl = '/?returnUrl=' . Server::urlEncode(Request::getOriginalScript() . '?' . str_replace('&', '%26', Request::server('QUERY_STRING', '')));
        }
        Response::redirect($strRedirectUrl);
    }
    /**
    * Showform
    *
    */
    public function ShowForm()
    {
        global $sUrl;
        $cookie = null;
        echo PHP_EOL . '<form onkeydown="if((13 == event.keyCode) || (13 == event.which)) {this.submit()}" data-show-tooltip=N id=login name=login method=post action="' . Server::htmlEncode(($sReturnURL !== '' ? Application::get('base_path', '') . 'index.php?' . FIELD_RETURNURL . '=' . $sReturnURL : '')) . '">';
        echo '<textarea id="ID_SupportInfo" name="SupportInfo" style="height:0px;width:0px;border:0px;display:none">Javascript: UIT</textarea>';
        echo '<input type="hidden" name="formactie" value="login"/>';
        if (Request::server('QUERY_STRING', '') != '') {
            $sUrl = null;
            $sUrl = '';
            if (Request::query(QUERYPAR_RET, '') != '') {
                $sUrl = str_replace(QUERYPAR_RET . '=', '', Request::server('QUERY_STRING', ''));
            } else {
                $sUrl = Request::getOriginalScript() . '?' . str_replace('&', '%26', Request::server('QUERY_STRING', ''));
            }
            echo '<input type="hidden" name="' . FIELD_RETURNURL . '" value="' . $sUrl . '"/>';
        } else {
            echo '<input type="hidden" name="' . FIELD_RETURNURL . '" value="' . $sReturnURL . '"/>';
        }
        echo '<h3>Login</h3><div class=line></div>';
        echo '<div class=kader><table cellspacing=0>';
        echo '<tr><td colspan=9><font class=loginerror>' . self::$strLoginError . '</font></td></tr>';
        echo "<tr><td><label class=naam><span>login</span></label><input tabindex=1 type=text class=naam maxlength=78 data-required=Y name=login id=login_id placeholder='E-mailadres' data-disable-checkmark=Y value=\"" . Cookie::get('login_naam', '') . '"></td></tr>';
        echo '<tr><td>';
        echo "<span class=vergeten><a tabindex=3 class=tooltip href=# title=\"Wachtwoord vergeten?\" onclick=\"javascript:lib_OpenWindowCentered('login_forgotten.php','pop',300,175,'Wachtwoord vergeten')\">Wachtwoord vergeten</a></span>";
        echo '<label class=password><span>wachtwoord</span></label><input tabindex=2 class=wachtwoord type=password id=pwd_id data-required=Y placeholder=Wachtwoord data-disable-checkmark=Y maxlength=78 name=pwd>';
        echo '</td></tr>';
        // NOTE: '		echo '<tr><td><input type=checkbox value=Y id=id_save_login name=save_login ' & iif(request.cookies("login_naam")<>"","checked","") & "><label for=id_save_login>bewaar e-mailadres <span class=note>(alleen als u de enige gebruiker van deze computer bent)</span></label>&nbsp;</td></tr>"
        echo '<tr><td colspan=9 nowrap>';
        echo '<a class=GenButton tabindex=4 onmouseout="window.status=\'\';return true;" onmouseover="window.status=\'Login\';return true" href=javascript:document.forms.login.submit()>inloggen</a>&nbsp;';
        echo '</td></tr></table></div>';
        echo '</form>' . PHP_EOL;
        echo '<script>';
        echo 'function InitLoginForm(){';
        echo ' document.forms.login.' . (Cookie::get('login_naam', '') != '' ? 'pwd' : 'login') . '.focus();';
        echo '}' . PHP_EOL;
        echo 'window.setTimeout("$this->InitLoginForm()",100);';
        echo '</script>' . PHP_EOL;
    }
    /**
    * Logout
    *
    */
    public function Logout()
    {
        global $AppVal, $arrRemove, $iAppStringLen, $sRedirectTo, $sRemove, $strAppString;
        $AppVal = null;
        $arrRemove = array();
        $sRemove = null;
        $strAppString = "";
        $iAppStringLen = null;
        $sRedirectTo = null;
        $sRedirectTo = Application::get('base_path', '');
        // '& iif(myLogin.$this->RetrieveValue( COOKIE_TYPE )=USER_TYPE_ASSISTENT or $this->originalLogin()<>"", "mw" , "")
        Cookie::delete(COOKIE_ID);
        Cookie::delete(COOKIE_GUID);
        Cookie::delete(COOKIE_NAME);
        Cookie::delete(COOKIE_CARTAID);
        Cookie::delete(COOKIE_ORIGINAL_LOGIN);
        Cookie::delete(COOKIE_OPL);
        Cookie::delete(COOKIE_ALLEOPL);
        Cookie::delete(COOKIE_SESSIE);
        Cookie::delete(COOKIE_ROL);
        $strAppString = 'login_setting_' . $this->id . '_';
        $iAppStringLen = (is_null($strAppString) ? 0 : strlen($strAppString ?? ''));
        foreach (Application::getAll() as $AppVal => $__value) {
            if ((is_null(substr($Appval, 0, max(0, min($iAppStringLen, strlen($Appval ?? ''))))) ? "" : strtoupper(substr($Appval, 0, max(0, min($iAppStringLen, strlen($Appval ?? '')))))) == $strAppString) {
                if (is_object(Application::get($Appval, ''))) {
                    Application::set($Appval, null);
                }
                $sRemove = $sRemove . $Appval . '|||';
            }
        }
        if ($sRemove != '') {
            $arrRemove = Arr::splitAlways($sRemove, '|||');
            for ($t = 0; $t <= (count($arrRemove) - 1); $t++) {
                if ($arrRemove[$t] != '') {
                    Application::get('/* error: with context not available for .remove() */', '');
                }
            }
        }
        Response::redirect($sRedirectTo);
    }
    /**
    * Check
    *
    */
    public function Check()
    {
        global $cEncryptUrl, $checkSQL, $sLoginTypeField;
        $cEncryptUrl = null;
        $cEncryptUrl = Request::url();
        $checkSQL = null;
        $sLoginTypeField = null;
        $cEncryptUrl = str_ireplace('?', '%3F', $cEncryptUrl);
        $cEncryptUrl = str_ireplace('&', '%26', $cEncryptUrl);
        $cEncryptUrl = str_ireplace('=', '%3D', $cEncryptUrl);
        $cEncryptUrl = str_ireplace('#', '%23', $cEncryptUrl);
        if (!$this->isLoggedIn()) {
        } else {
            if ($this->RetrieveValue(COOKIE_AANTAL_ROLLEN) == '' || Cookie::get(COOKIE_OPL, '') == '') {
                Login_GetRelated();
            } else {
                Login_InitVars();
            }
            if ($loginType!= '') {
                $sLoginTypeField = User_GetPersoonsTypeField($loginType);
                if ($sLoginTypeField != '') {
                    $checkSQL = 'select ' . $sLoginTypeField . '  from ' . $strTableName . ' WHERE (actief=true and ID=' . $this->id . ')';
                    if (Database::getFieldValue('data', $checkSQL, '') == '') {
                        Response::redirect(Application::get('base_path', '') . '/?' . QUERYPAR_RET . '=' . $cEncryptUrl);
                    }
                }
            }
        }
    }
    /**
    * Isloggedin
    *
    */
    public function isLoggedIn()
    {
        global $bRetval, $login_check_cache_id, $sCheckSQL;
        $login_check_cache_id = null;
        $login_check_cache_id = 'login_chk_' . $this->id . '_' . $this->guid;
        $bRetval = null;
        $bRetval = false;
        $sCheckSQL = null;
        $sCheckSQL = '';
        // new
        if (Request::query(QUERYSTRING_SESSIE, '') != '') {
            $this->id = $this->LoginWithSessionID();
            $this->guid = Database::getFieldValue('data', 'SELECT guid FROM ' . $strTableName . ' WHERE id=' . $this->id, '');
        }
        if ($this->id == '') {
            $bRetval = false;
        } else {
            if (Application::get($login_check_cache_id, '')!= '') {
                $bRetval = Application::get($login_check_cache_id, '');
            } else {
                if ($this->guid != '') {
                    $sCheckSQL = 'SELECT guid FROM ' . $strTableName . ' WHERE id=' . $this->id;
                    $bRetval = Database::getFieldValue('data', $sCheckSQL, '')== $this->guid . '';
                } else {
                    $bRetval = false;
                }
            }
        }
        return $bRetval;
    }
    /**
    * Isloggedinas
    *
    */
    public function isLoggedInAs()
    {
        return Cookie::get(COOKIE_ORIGINAL_LOGIN, '')!= '' && Cookie::get(COOKIE_ORIGINAL_LOGIN, '')!= Cookie::get(COOKIE_ID, '') . '';
    }
    /**
    * Originallogin
    *
    */
    public function originalLogin()
    {
        global $sRetval;
        $sRetval = null;
        $sRetval = Cookie::get(COOKIE_ORIGINAL_LOGIN, '') . '';
        if ($sRetval== '-1') {
            $sRetval = '';
        }
        return $sRetval;
    }
    /**
    * Forgotpassword
    *
    */
    public function ForgotPassword()
    {
        global $GLOBALS_current_row, $Mailer, $rs, $rs_current_row, $sql, $strError, $strResetLink;
        $strResetLink = "";
        if (Request::post('email', '') != '') {
            $sql = null;
            $strError = "";
            $rs = null;
            // VBS: $SQL = 'SELECT * from tblLogins where (ucase(email_primair)="' . (is_null(Request::post('email', '')) ? "" : strtoupper((Request::post('email', '')) ?? '')) . '" or ucase(email_primair_onbevestigd)="' . (is_null(Request::post('email', '')) ? "" : strtoupper((Request::post('email', '')) ?? '')) . '")';
            Database::openRS($rs, $sql, 'data', adOpenForwardOnly);
            if ($rs === null) {
                throw new \Exception('Database query failed: ' . Database::getLastError());
            }
            $rs->MoveNext();
            if ($rs->EOF) {
                if (Application::get('Mod_language', '') == 'UK') {
                    $strError = 'Email address not found';
                } else {
                    $strError = 'E-mailadres niet gevonden';
                }
            } else {
                $Mailer = null;
                $Mailer = new LibMailer();
                // strResetLink = Request::currentDomain() & Application("base_path") & "login_reset.php?chk=" & rs.fields("guid")
                $strResetLink = Request::currentDomain() . Application::get('base_path', '') . 'bevestig_email.php?uuid=' . CleanGuid(Arr::field($rs_current_row, 'guid'));
                $Mailer->body = str_replace('<telefoon>', '020 625 08 03', str_ireplace('<contactgegevens_infodesk>', 'Infodesk (helpdesk@rino.nl,  020 625 08 03)', str_ireplace('<contactgegevens_it>', 'Support (helpdesk@rino.nl,  020 625 08 03)', str_ireplace('<contactgegevens_om>', ' de opleidingsmedewerker van jouw cursus/opleiding', str_ireplace('<handtekening_ict>', '<br><br>Met vriendelijke groet,<br><br>' . MaakHandtekening('Infodesk', '', '020 625 08 03'), str_ireplace('<voornaam>', Arr::field($rs_current_row, 'Roepnaam') . '', str_ireplace('<roepnaam>', Arr::field($rs_current_row, 'Roepnaam') . '', str_ireplace('<link>', $strResetLink, getTekst('login_reset') . ''))))))));
                $Mailer->subject = GetOnderwerp('login_reset');
                $Mailer->AddRecipient();
                Request::post('email', '');
                $Mailer->AddRecipientsBCC();
                Application::get('app_beheerder_email', '');
                $Mailer->AddRecipientsBCC();
                Application::get('developer_email', '');
                $Mailer->Send();
                $Mailer = null;
                Database::execute('update tblLogins set resetrequested=' . SQL::postDateTime(date("Y-m-d H:i:s")) . ' where id=' . Arr::field($rs_current_row, 'id'), []);
            }
            if ($strError == '') {
                echo 'We hebben de link waarmee je jouw wachtwoord opnieuw in kunt stellen verstuurd naar ' . Request::post('email', '') . '.';
                echo '<BR><BR><span class=GenButton><a href=javascript:top.lib_OpenWindowCenteredClose()>Ok</a></span>';
            } else {
                echo "<script>modal_alert('" . $strError . "','Wachtwoord wijzigen','form');</script><span class=GenButton><a href=javascript:top.lib_OpenWindowCenteredClose()>Ok</a></span>";
            }
            $rs = null;
        } else {
            echo '<script src=library/formval_' . Application::get('mod_language', '') . '.js></script>';
            echo '<form onkeydown="if((13 == event.keyCode) || (13 == event.which)) {if(form_valid(this))this.submit()}" method=post name=pwd id=pwd data-show-tooltip=N target=_self style=margin:0px>';
            echo '<table cellpadding=6 cellspacing=0 style=margin-left:-4px>';
            echo '<tr><td colspan=9 class=gentd>Vul je e-mailadres in, dan ontvang je een e-mail om je wachtwoord opnieuw in te stellen.</td></tr>';
            echo '<tr><td nowrap    class=gentd>E-mailadres</td><td class=gentd><input type=email data-required=Y data-validation-type=email name=email style=width:200px maxlength=78></td></tr>';
            echo '<tr><td colspan=9 class=gentd><br/>';
            echo '<span class=GenButton><a href="javascript:if (form_valid(document.forms.pwd)) document.forms.pwd.submit()">Ok</a></span>&nbsp;';
            echo '<span class="GenButton annuleer"><a href="javascript:top.lib_OpenWindowCenteredClose()">Annuleer</a></span>';
            echo '</td></tr></table>';
            echo '</form>';
            echo '<script>window.setTimeout("document.forms.pwd.email.focus()",100);</script>';
        }
    }
    /**
    * Displaypasswordform
    *
    */
    public function DisplayPasswordForm()
    {
        global $GLOBALS_current_row, $bShowForm, $login_rs, $login_sql, $parChk, $strError, $strRegel, $tmpID;
        $strError = "";
        $login_rs = null;
        $login_sql = null;
        $parChk = null;
        $parChk = Request::queryIntAndGuid('chk');
        $tmpID = null;
        $strRegel = "";
        $strRegel = 'Het wachtwoord moet minstens 13 tekens bevatten';
        $bShowForm = null;
        $bShowForm = true;
        $tmpID = $this->id;
        if ($tmpID == '') {
            if ($parChk != '') {
                $login_rs = null;
                $login_SQL = 'select * from ' . $strTableName . ' WHERE guid=' . SQL::postGuid($parChk) . " and DateDiff( 'm', [resetrequested], date())<60";
                $login_SQL = SQL::processSQL('data', $login_SQL);
                Database::openRS($login_rs, $login_SQL, 'data', adOpenForwardOnly);
                if ($login_rs === null) {
                    throw new \Exception('Database query failed: ' . Database::getLastError());
                }
                $login_rs->MoveNext();
                if (!$login_rs->eof) {
                    $tmpID = $login_rs['ID'];
                }
                $login_rs = null;
                echo '<h3 class=nohelp_icon>Wachtwoord resetten</h3>' . (Request::postAll() ? '<div class=line></div><div class=kader_wit>' . $strRegel . '<br>&nbsp;' : '');
            }
            if ($tmpID == "") {
                Error::show('Ongeldige link of link verlopen. ');
                exit();
            }
        }
        if (Request::post('new_confirm', '') != '') {
            $bShowForm = false;
            $strError = lib_FormValRequired();
            if ($strError != '') {
                Error::form($strError);
                $bShowForm = true;
            } else {
                $login_rs = null;
                $login_SQL = 'select * from ' . $strTableName . ' WHERE (ID=' . $tmpID . ')';
                if ($parChk == '') {
                    $login_SQL = $login_SQL . ' and (password_enc=' . SQL::postString(Encryption::sha256(Request::post('old', ''))) . ')';
                }
                $login_SQL = SQL::processSQL('data', $login_SQL);
                $login_rs = Database::openRS($login_SQL, 'data', 3, 3);
                if ($login_rs === null) {
                    throw new \Exception('Database query failed: ' . Database::getLastError());
                }
                $login_rs->MoveNext();
                if ($login_rs->EOF) {
                    $strError = 'Oude wachtwoord is niet correct';
                } else {
                    if (!$this->login_checkPassword(trim((Request::post('new', '')) ?? ''))) {
                        $strError = $strRegel;
                    } else {
                        if ((is_null(Request::post('new', '')) ? "" : strtolower((Request::post('new', '')) ?? '')) == (is_null(Request::post('new_confirm', '')) ? "" : strtolower((Request::post('new_confirm', '')) ?? ''))) {
                            Arr::field($login_rs_current_row, $strFieldPwdName == Encryption::sha256(Request::post('new', '')));
                            Arr::field($login_rs_current_row, 'toegangscode') = Request::post('new', '');
                        } else {
                            $strError = 'Wachtwoorden komen niet overeen.';
                        }
                    }
                }
                if ($strError == '') {
                    echo 'Je wachtwoord is gewijzigd.';
                    if ($parChk == '') {
                        echo '<BR><BR><span class=GenButton><a href="javascript:top.lib_OpenWindowCenteredClose()">Ok</a></span>';
                    } else {
                        echo '<BR><BR><span class=GenButton><a href="/">Ok</a></span>';
                    }
                } else {
                    Error::show($strError);
                    $bShowForm = true;
                }
                $login_rs = null;
            }
        } else {
            $bShowForm = true;
        }
        if ($bShowForm) {
            echo '<script src=library/formval_' . Application::get('mod_language', '') . '.js></script>';
            echo '<form onkeydown="if((13 == event.keyCode) || (13 == event.which)) {if(form_valid(this)){this.submit()}}" method=post data-show-tooltip=N name=pwd id=pwd target=_self style=margin:0px>';
            echo '<input type=hidden name=required value=old;new;new_confirm>';
            echo '<table cellpadding=0 cellspacing=0 style="height:100%;width:100%" >';
            if ($parChk == '') {
                echo '<tr><td class=gentd style="width:20%">Oud wachtwoord</td><td class=gentd><font class=required>*</font><td class=gentd><input type=password name=old style="width:calc(100% - 20px)" maxlength=78><span class=pwd_view title=\'Toon wachtwoord\' onclick="ShowPwd(this, \'old\')">&nbsp;</span></td></tr>';
            }
            echo '<tr><td class=gentd style="width:20%">Nieuw wachtwoord&nbsp;&nbsp;</td><td class=gentd><font class=required>*</font><td class=gentd><input type=password name=new style=width:100% maxlength=78 data-label="Nieuw wachtwoord"><span class=pwd_view title=\'Toon wachtwoord\' onclick="ShowPwd(this, \'new\')">&nbsp;</span></td></tr>';
            echo '<tr><td class=gentd style="width:20%"nowrap>Nieuw wachtwoord herhalen&nbsp;&nbsp;</td><td class=gentd><font class=required>*</font>&nbsp;&nbsp;<td class=gentd><input type=password name=new_confirm style="width:100%" maxlength=78 data-label="Nieuw wachtwoord - bevestiging"><span class=pwd_view title=\'Toon wachtwoord\' onclick="ShowPwd(this, \'new_confirm\')">&nbsp;</span></td></tr>';
            echo '<tr><td class=gentd colspan=9>';
            echo '<span class=GenButton><a href="javascript:if(form_valid(document.forms.pwd)){document.forms.pwd.submit()}">Ok</a></span>&nbsp;';
            echo '<span class=GenButton><a href="javascript:top.lib_OpenWindowCenteredClose()">Annuleer</a></span>';
            echo '</td></tr></table></form>';
            echo '<script>window.setTimeout("document.forms.pwd.old.focus()",100);</script>';
        }
        if ($parChk != '') {
            echo '</div>';
        }
    }
    /**
    * Debug
    */
    public function Debug()
    {
        echo 'Login test-informatie:<hr>';
        echo 'IsLoggedIn : ' . IsLoggedIn() . '<br>';
        echo 'Huidige rol : ' . Login_GetTypeString() . '<br>';
        echo 'Aantal rollen : ' . $UserAantalRollen . '<br>';
        echo 'Login ID   : ' . $this->id . '<br>';
        echo 'Login Guid : ' . $this->guid . '<br>';
        echo 'Doelgroep ids: ' . $this->RetrieveValue(COOKIE_DOELGROEPEN) . '<br>';
        echo '<hr>';
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        echo 'Aantal actieve opleiding(en) : ' . null . (null < 10 ? ' namelijk: ' . $useropleidingen : '') . '<br>';
        /* ERROR: Could not convert statement */
        /* ERROR: Could not convert statement */
        echo 'Totaal aantal opleiding(en) : ' . null . '<br>';
        echo 'deelnemer ID : ' . $deelnemerID . '<br>';
        echo 'Cookie CartaID : ' . Cookie::get(COOKIE_CARTAID, '') . '<br>';
        echo 'docent ID : ' . $docentID . '<br>';
        echo 'praktijkopleider ID : ' . $praktijkopleiderID . '<br>';
        echo 'P-opleider ID : ' . $p_praktoplID . '<br>';
        echo 'Assistent ID : ' . $assistentID . '<br>';
        echo 'Contactpersoon Inventarisatie ID : ' . $ContInvID . '<br>';
        echo 'SRH Servicepakket ID : ' . $SRHServicepakketID . '<br>';
        echo 'Supervisor ID : ' . $SupervisorID . '<br>';
        echo 'Werkbegeleider ID : ' . $WerkbegeleiderID . '<br>';
        echo 'Klantcontactpersoon ID : ' . $klantContactpersoonID . '<br>';
        echo '<hr>';
    }
    /**
    * Login Checkpassword
    *
    */
    public function login_checkPassword($cNew)
    {
        global $bRetval, $sValue;
        $sValue = null;
        $sValue = Str::trim($cNew ?? '');
        $bRetval = null;
        $bRetval = (is_null($sValue) ? 0 : strlen($sValue ?? '')) >= 13;
        // dim valid_char
        // dim cur_chr
        // dim iPos
        // dim bFound
        // if bRetval then
        // if len(cNew)>=13 then
        // endd if
        // ' 2do: iets van lib_String_CharIn ? zelfde als bij PHP
        // '
        // valid_char = "01234567890"
        // iPos = 1
        // bFound = False
        // While iPos <= Len(sValue) and (not bFound)
        // cur_chr = Mid(sValue, iPos, 1)
        // if instr(1, valid_char, cur_chr)>0 then
        // bFound = True
        // end if
        // iPos = iPos + 1
        // Wend
        // if not bFound then
        // bRetval = false
        // end if
        // '
        // ' ' EN " MAAR EVEN NIET
        // '
        // valid_char = "§±´!@#$%^&*()_+-={}|[]\:;<>?,./"
        // iPos = 1
        // bFound = false
        // While iPos <= Len(sValue) and (not bFound)
        // cur_chr = Mid(sValue, iPos, 1)
        // if instr(1, valid_char, cur_chr)>0 then
        // bFound = True
        // end if
        // iPos = iPos + 1
        // Wend
        // if not bFound then
        // bRetval = false
        // end if
        // end if
        return $bRetval;
    }
    /**
    * Loginwithsessionid
    *
    */
    public function LoginWithSessionID()
    {
        // 2do : Kijken of de sessie nog wel geldig is!
        $sessieID = null;
        $sessieID = Request::query(QUERYSTRING_SESSIE, '') . Request::query('sessie', '');
        $sqlSessie = null;
        $sqlSessie = 'SELECT tblLogins.ID FROM tblLogins INNER JOIN tblSessions ON tblLogins.ID = tblSessions.fklogin WHERE tblSessions.guid=' . SQL::postGuid($sessieID);
        $rol = null;
        $rol = Request::query(QUERYSTRING_ROL, '');
        if ($sessieID!= '' || $this->id == '') {
            if ($sessieID == '') {
                $sessieID = Cookie::get(COOKIE_SESSIE, '');
            } else {
                Cookie::set(COOKIE_SESSIE, $sessieID);
            }
            if ($rol!= '') {
                Cookie::set(COOKIE_ROL, $rol);
                $loginType = intval(round(floatval($rol)));
            }
            if ($sessieID!= '' && $sessieID!= '{}') {
                $this->id = Database::getFieldValue('data', str_replace('{sessieID}', $sessieID, $sqlSessie), '');
            }
        }
        return $this->id;
    }
    /**
    *   Construct
    */
    public function __construct()
    {
        self::$strPostLogin = '';
        self::$strTableName = 'tblLogins';
        self::$strFieldLoginName = 'login';
        self::$strFieldPwdName = 'password_enc';
        self::$strFieldLastLoginName = 'LaatstIngelogd';
        self::$strCustomPwdSQL = '';
        self::$blnFirstLogin = false;
        self::$sReturnURL = Request::post(FIELD_RETURNURL, '');
        if (self::$sReturnURL === "") {
            self::$sReturnURL = Request::query(QUERYPAR_RET, '');
        }
        self::$strLoginVolledigeNaam = '';
        // Enables a function to be called after a successful login
        // For returning to the original url
        // Some data model variations
        // Login as a specific user, potential security risk, basic check wether user is logged in
        // Bewaren login-gerelateerde data : eerst in cookies, nu in Application, gecentraliseerd zodat - indien wenselijk - het ook nog ergens anders kan (db?)
        // Ophalen login-gerelateerde data : eerst in cookies, nu in Application
        // Geeft de login ID
        // Zet de login ID in een cookie
        // Geeft de login guid
        // Zet de login guid in een cookie
        // Volledige naam
        // Afhandeling login request
        // Redirect to homepage and save the returnUrl
        // Toont een loginscherm, styling via CSS classes
        // Uitloggen door de ID Cookie leeg te maken en te verwijzen naar de index pagina
        // Check op login, indien niet ingelogd, dan naar de index pagina verwijzen
        // Geeft een Boolean of de bezoeker ingelogd is, controleer ook de id-guid combinatie
        // Ondersteunen van inloggen met sessieID en rol als parameter/cookie
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
    }
}
// Deze functie word door de module Login aangeroepen
/**
* Login Getrelated
*
*/
function Login_GetRelated()
{
    $LoginType = $LoginType ?? '';
    global $UserAlleOpleidingen, $UserJaargroepopleiderOpl, $UserOplExclCursorisch, $UserOpleidingen, $UserPlanning, $UserPraktijkopleiders, $datTest, $ContInvID, $LoginType, $SupervisorID, $USER_bServiceBureau, $WerkbegeleiderID, $arrDetails, $assistentID, $bOldDebug, $cWhere, $rs, $rs_current_row, $sql;
    $intRollen = 0;
    $intGewensteRol = Cookie::get(COOKIE_ROL, '');
    $sDoelgroep = '';
    $bCGOCorner = false;
    if (Cookie::get(COOKIE_ID, '')!= '') {
        $sql = ' SELECT * FROM tblLogins WHERE tblLogins.ID = ' . Cookie::get(COOKIE_ID, '') . (Cookie::get(COOKIE_GUID, '') != '' ? ' AND tblLogins.guid=' . SQL::postGuid(Cookie::get(COOKIE_GUID, '')) : '');
        $bOldDebug = Debug::getActive();
        Debug::setActive(false);
        Database::openRS($rs, $sql, 'data', adOpenForwardOnly);
        if ($rs === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }
        $rs->MoveNext();
        Debug::setActive($bOldDebug);
        if (!$rs->EOF) {
            /* ERROR: Could not convert statement */
            /* ERROR: Could not convert statement */
            /* ERROR: Could not convert statement */
            /* ERROR: Could not convert statement */
            /* ERROR: Could not convert statement */
            /* ERROR: Could not convert statement */
            /* ERROR: Could not convert statement */
            /* ERROR: Could not convert statement */
            /* ERROR: Could not convert statement */
            /* ERROR: Could not convert statement */
            $intRollen = null;
            Login::StoreValue(COOKIE_AANTAL_ROLLEN, $intRollen);
            Login::StoreValue(COOKIE_EXTERN_MANAGER, 'N');
            Login::StoreValue(COOKIE_PRAKTIJKOPL, '');
            $UserAlleOpleidingen = '';
            $UserJaargroepopleiderOpl = '';
            $UserOplExclCursorisch = '';
            $UserOpleidingen = '';
            $UserPraktijkopleiders = '';
            $UserPlanning = false;
            $USER_bServiceBureau = false;
            if (Arr::field($rs_current_row, 'fkSRHForumLid')!= '') {
                $ContInvID = Arr::field($rs_current_row, 'fkSRHForumLid');
                if ($intRollen == 1 || $intRollen > 1 && $intGewensteRol === '' || $intGewensteRol == USER_TYPE_CONTACTP_INV . '') {
                    Login::StoreValue(COOKIE_TYPE, USER_TYPE_CONTACTP_INV);
                    $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_CONTACTP_INV;
                }
            }
            if (Arr::field($rs_current_row, 'fkP_Praktopl') != '') {
                if ($intRollen == 1 || $intRollen > 1 && $intGewensteRol === '' || $intGewensteRol == USER_TYPE_P_PRAKTOPL . '') {
                    Login::StoreValue(COOKIE_TYPE, USER_TYPE_P_PRAKTOPL);
                    $UserPraktijkopleiders = GetPraktijkopleiders(Arr::field($rs_current_row, 'fkP_Praktopl'));
                    $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_PRAKTIJKOPL;
                    $UserAlleOpleidingen = $UserAlleOpleidingen . Login_GetOpl('SELECT DISTINCT  tblOpleidingen.CODE, tblDeelname.fkOpleiding FROM tblOpleidingen INNER JOIN tblDeelname ON tblOpleidingen.ID = tblDeelname.fkOpleiding WHERE (' . ($UserPraktijkopleiders !== '' ? 'fkPraktijkOpleider IN (' . $UserPraktijkopleiders . ') or ' : '') . ' tblDeelname.fkGedelegeerdPOpleider=' . Arr::field($rs_current_row, 'fkP_Praktopl') . ')  AND (tblDeelname.einddatum IS NULL OR tblDeelname.einddatum>date())  AND tblOpleidingen.datOnSite<=date()  AND ( tblDeelname.toegelaten $IN[1][2] or tblOpleidingen.fkOplsoort=34)ORDER BY CODE', true);
                    $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_P_PRAKTOPL;
                }
            }
            if (Arr::field($rs_current_row, 'fkPraktijkopleider') != '') {
                if ($intRollen == 1 || $intRollen > 1 && $intGewensteRol === '' || $intGewensteRol == USER_TYPE_PRAKTIJKOPL . '') {
                    if (Arr::field($rs_current_row, 'fkPraktijkopleider')!= '') {
                        Login::StoreValue(COOKIE_TYPE, USER_TYPE_PRAKTIJKOPL);
                        $UserPraktijkopleiders = Arr::field($rs_current_row, 'fkPraktijkopleider');
                        $UserAlleOpleidingen = Login_GetOpl(' SELECT DISTINCT  tblOpleidingen.CODE,  tblDeelname.fkOpleiding  FROM tblOpleidingen  INNER JOIN tblDeelname  ON tblOpleidingen.ID = tblDeelname.fkOpleiding  WHERE fkPraktijkOpleider=' . Arr::field($rs_current_row, 'fkPraktijkopleider') . ' AND (tblDeelname.certificaatdatum IS NULL OR tblDeelname.certificaatdatum>date())  AND tblOpleidingen.datOnSite<=date()  AND ( tblDeelname.toegelaten $IN[1][2] or tblOpleidingen.fkOplsoort=34)  ORDER BY CODE', true);
                        $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_PRAKTIJKOPL;
                    }
                }
            }
            $datTest = date("Y-m-d");
            if (Arr::field($rs_current_row, 'fkDeelnemer')!= '') {
                if ($intRollen == 1 || $intRollen > 1 && $intGewensteRol === '' || $intGewensteRol == USER_TYPE_DEELNEMER . '') {
                    Login::StoreValue(COOKIE_TYPE, USER_TYPE_DEELNEMER);
                    $UserAlleOpleidingen = Login_GetOpl('SELECT tblDeelname.fkOpleiding  FROM tblOpleidingen  INNER JOIN tblDeelname  ON tblOpleidingen.ID = tblDeelname.fkOpleiding  WHERE ( fkDeelnemer=' . Arr::field($rs_current_row, 'fkDeelnemer') . '  AND (tblDeelname.bPauze or (tblDeelname.Einddatum IS NULL OR tblDeelname.EindDatum > ' . SQL::postDateTime($datTest) . ')) AND tblDeelname.toegelaten<>3 ) ORDER BY CODE', true);
                    $UserOplExclCursorisch = Login_GetOpl('SELECT tblDeelname.fkOpleiding  FROM tblOpleidingen  INNER JOIN tblDeelname  ON tblOpleidingen.ID = tblDeelname.fkOpleiding  WHERE ( alleenCursorisch=False  AND tblOpleidingen.BNSOpleiding=False  AND fkDeelnemer=' . Arr::field($rs_current_row, 'fkDeelnemer') . '  AND (tblDeelname.Einddatum IS NULL OR tblDeelname.EindDatum > ' . SQL::postDateTime($datTest) . ')  AND tblDeelname.toegelaten<>3 ) ORDER BY CODE', true);
                    $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_DEELNEMER;
                }
            }
            if (Arr::field($rs_current_row, 'fkDocent')!= '') {
                if ($intRollen == 1 || $intRollen > 1 && $intGewensteRol === '' || $intGewensteRol == USER_TYPE_DOCENT . '') {
                    Login::StoreValue(COOKIE_TYPE, USER_TYPE_DOCENT);
                    $LoginType = USER_TYPE_DOCENT;
                    $UserAlleOpleidingen = Login_GetOpl('SELECT tblOpleidingenPerDocent.fkOpleiding FROM tblOpleidingen INNER JOIN tblOpleidingenPerDocent ON tblOpleidingen.ID = tblOpleidingenPerDocent.fkOpleiding WHERE fkDocent=' . Arr::field($rs_current_row, 'fkDocent') . ' ORDER BY CODE', true);
                    $UserOplExclCursorisch = Login_GetOpl('SELECT tblOpleidingenPerDocent.fkOpleiding FROM tblOpleidingen INNER JOIN tblOpleidingenPerDocent ON tblOpleidingen.ID = tblOpleidingenPerDocent.fkOpleiding WHERE (fkDocent=' . Arr::field($rs_current_row, 'fkDocent') . ' AND tblOpleidingen.BNSOpleiding=False) ORDER BY CODE', true);
                    $UserJaargroepopleiderOpl = Login_GetOpl('SELECT tblOpleidingenPerDocent.fkOpleiding FROM tblOpleidingen INNER JOIN tblOpleidingenPerDocent ON tblOpleidingen.ID = tblOpleidingenPerDocent.fkOpleiding WHERE (fkDocent=' . Arr::field($rs_current_row, 'fkDocent') . ' AND (blnHoofddocent=True or blnHoofdopleider=True) ) ORDER BY CODE', true);
                    $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_DOCENT;
                    Database::openRS($rsdocent, 'select * from tblDocenten where id=' . Arr::field($rs_current_row, 'fkDocent'), 'data', adOpenForwardOnly);
                    if ($rsdocent === null) {
                        throw new \Exception('Database query failed: ' . Database::getLastError());
                    }
                    $rsdocent->MoveNext();
                    if (!$rsdocent->EOF) {
                        if (Cookie::get(COOKIE_NAME, '') == '') {
                            Cookie::set(COOKIE_NAME, Arr::field($rsdocent_current_row, 'VolledigeNaam'));
                        }
                    }
                    $bCGOCorner = false;
                    if (Login_GetOpl('SELECT tblOpleidingenPerDocent.fkOpleiding FROM tblOpleidingen INNER JOIN tblOpleidingenPerDocent ON tblOpleidingen.ID = tblOpleidingenPerDocent.fkOpleiding WHERE (fkDocent=' . Arr::field($rs_current_row, 'fkDocent') . ' AND (blnHoofdOpleider=True) ) ORDER BY CODE', true) != '') {
                        $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_HOOFDOPL;
                        $bCGOCorner = true;
                    }
                    if (Login_GetOpl('SELECT tblOpleidingenPerDocent.fkOpleiding FROM tblOpleidingen INNER JOIN tblOpleidingenPerDocent ON tblOpleidingen.ID = tblOpleidingenPerDocent.fkOpleiding WHERE (fkDocent=' . Arr::field($rs_current_row, 'fkDocent') . ' AND (blnHoofdDocent=True) ) ORDER BY CODE', true) != '') {
                        $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_JAARGROEPOPL;
                        $bCGOCorner = true;
                    }
                    if (Login_GetOpl('SELECT tblOpleidingenPerDocent.fkOpleiding FROM tblOpleidingen INNER JOIN tblOpleidingenPerDocent ON tblOpleidingen.ID = tblOpleidingenPerDocent.fkOpleiding WHERE (fkDocent=' . Arr::field($rs_current_row, 'fkDocent') . ' AND (blnHoofdDocentNew=True) ) ORDER BY CODE', true) != '') {
                        $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_HOOFDDOC;
                        $bCGOCorner = true;
                    }
                }
            }
            if (Arr::field($rs_current_row, 'fkAssistent')!= '') {
                if ($intRollen == 1 || $intRollen > 1 && $intGewensteRol === '' || $intGewensteRol == USER_TYPE_ASSISTENT . '') {
                    $cWhere = '';
                    $arrDetails = array();
                    Login::StoreValue(COOKIE_TYPE, USER_TYPE_ASSISTENT);
                    $LoginType = USER_TYPE_ASSISTENT;
                    $assistentID = Arr::field($rs_current_row, 'fkAssistent');
                    Assistent_CheckOpleidingen();
                    $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_ASSISTENT;
                }
                if ($intGewensteRol === '') {
                    Cookie::set(COOKIE_ROL, USER_TYPE_ASSISTENT);
                    if (COOKIE_EXPIRE_SET) {
                        Cookie::get(COOKIE_ROL, '');
                    }
                }
            }
            if (Arr::field($rs_current_row, 'fkSupervisor')!= '') {
                if ($intRollen == 1 || $intRollen > 1 && $intGewensteRol === '' || $intGewensteRol == USER_TYPE_SUPERVISOR . '') {
                    Login::StoreValue(COOKIE_TYPE, USER_TYPE_SUPERVISOR);
                    $UserAlleOpleidingen = CGOCORNER_ID;
                    $UserOplExclCursorisch = '';
                    $UserJaargroepopleiderOpl = '';
                    $UserPraktijkopleiders = '';
                    $SupervisorID = Arr::field($rs_current_row, 'fkSupervisor');
                    $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_SUPERVISOR;
                }
            }
            if (Arr::field($rs_current_row, 'fkWerkbegeleider')!= '') {
                if ($intRollen == 1 || $intRollen > 1 && $intGewensteRol === '' || $intGewensteRol == USER_TYPE_WERKBEGELEIDER . '') {
                    Login::StoreValue(COOKIE_TYPE, USER_TYPE_WERKBEGELEIDER);
                    $UserOpleidingen = CGOCORNER_ID;
                    $UserOplExclCursorisch = '';
                    $UserJaargroepopleiderOpl = '';
                    $UserPraktijkopleiders = '';
                    $WerkbegeleiderID = Arr::field($rs_current_row, 'fkWerkbegeleider');
                    $sDoelgroep .= ($sDoelgroep !== '' ? ',' : '') . USER_TYPE_WERKBEGELEIDER;
                }
            }
            if ($UserOpleidingen !== '') {
                Cookie::set(COOKIE_OPL, $UserOpleidingen);
            }
            Login::StoreValue(COOKIE_ALLEOPL, $UserAlleOpleidingen);
            Login::StoreValue(COOKIE_OPL_EXCL_CURS, $UserOplExclCursorisch);
            Login::StoreValue(COOKIE_HOOFDOPL, $UserJaargroepopleiderOpl);
            Login::StoreValue(COOKIE_PRAKTIJKOPL, $UserPraktijkopleiders);
            Login::StoreValue(COOKIE_PLANNING, $UserPlanning);
            Login::StoreValue(COOKIE_SERVICEBUREAU, $USER_bServiceBureau);
            Login::StoreValue(COOKIE_ID_DE, Arr::field($rs_current_row, 'fkDeelnemer') . '');
            Login::StoreValue(COOKIE_ID_DO, Arr::field($rs_current_row, 'fkDocent') . '');
            Login::StoreValue(COOKIE_ID_PO, Arr::field($rs_current_row, 'fkPraktijkopleider') . '');
            Login::StoreValue(COOKIE_ID_AS, Arr::field($rs_current_row, 'fkAssistent') . '');
            Login::StoreValue(COOKIE_ID_POPL, Arr::field($rs_current_row, 'fkP_Praktopl') . '');
            Login::StoreValue(COOKIE_ID_SRH_SP, Arr::field($rs_current_row, 'fkSRHServicePakket') . '');
            Login::StoreValue(COOKIE_ID_CONT_INV, Arr::field($rs_current_row, 'fkSRHForumLid') . '');
            Login::StoreValue(COOKIE_ID_SUPERVISOR, Arr::field($rs_current_row, 'fkSupervisor') . '');
            Login::StoreValue(COOKIE_ID_WERKBEGELEIDER, Arr::field($rs_current_row, 'fkWerkbegeleider') . '');
            Login::StoreValue(COOKIE_ID_KLANTCP, Arr::field($rs_current_row, 'fkKlantContactpersoon') . '');
            Login::StoreValue(COOKIE_DOELGROEPEN, $sDoelgroep);
            $UserOpleidingen = $UserAlleOpleidingen;
            if (Login::$strLoginVolledigeNaam !== '') {
                Cookie::set(COOKIE_NAME, Login::strLoginVolledigeNaam());
                if (COOKIE_EXPIRE_SET) {
                    Cookie::get(COOKIE_NAME, '');
                }
            }
            Login_InitVars();
        }
    }
}
/**
* Assistent Checkopleidingen
*
*/
function Assistent_CheckOpleidingen()
{
    global $LoginType, $UserAlleOpleidingen, $assistentID, $UserOpleidingen, $USER_bServiceBureau, $UserJaargroepopleiderOpl, $UserPlanning, $arrDetails, $bResetCache, $cWhere, $cache_id;
    $cWhere = '';
    $cache_id = 'assistent_alleopleidingen_' . $assistentID . '_' . date("G", strtotime(date("H:i:s")));
    $bResetCache = Request::post('pwd', '')!= '' || Request::post('gewenste_rol', '') != '' || Request::query('assumeidentity', '') != '';
    if ($loginType == USER_TYPE_ASSISTENT) {
        if ($assistentID != '') {
            if ($useropleidingen == '' || $useralleopleidingen == '') {
                $arrDetails = Cache::retrieve('assistent_details_' . $assistentID . '_' . date("G", strtotime(date("H:i:s"))), 'data', 'SELECT bSuperUser, BIG, BNS, bPlanning, bServicebureau from tblContactpersonen where id=' . $assistentID);
                $bSuperUser = $arrDetails[0][0];
                $bAfdelingBIG = $arrDetails[1][0];
                $bAfdelingBNS = $arrDetails[2][0];
                $UserPlanning = $arrDetails[3][0] == true || $arrDetails[3][0] == 1;
                $USER_bServiceBureau = $arrDetails[4][0] == true || $arrDetails[4][0] == 1;
                if ($bAfdelingBIG) {
                    if ($bAfdelingBNS) {
                    } else {
                        $cWhere = 'where BNSOpleiding=false ';
                    }
                } else {
                    if ($bAfdelingBNS) {
                        $cWhere = 'where BNSOpleiding=true ';
                    } else {
                        $cWhere = 'where false';
                    }
                }
            }
            $cWhere .= ($cWhere !== '' ? ' AND ' : ' WHERE ') . ' (sluitdatum is null or  sluitdatum>getdate()) ';
            if ($useralleopleidingen == '') {
                if (Application::get($cache_id, '') != '' && !$bResetCache) {
                    $UserAlleOpleidingen = Application::get($cache_id, '');
                } else {
                    $UserAlleOpleidingen = Login_GetOpl('SELECT ID as fkOpleiding FROM tblOpleidingen ' . $cWhere . ' ORDER BY CODE ', false);
                    if ($UserAlleOpleidingen === "") {
                        $UserAlleOpleidingen = Login_GetOpl('SELECT ID as fkOpleiding FROM tblOpleidingen ' . $cWhere . ' AND (Contact3=' . $assistentID . ') ORDER BY CODE', false);
                        $UserJaargroepopleiderOpl = '';
                        Login::StoreValue(COOKIE_EXTERN_MANAGER, 'J');
                    }
                    $UserAlleOpleidingen = ($UserAlleOpleidingen !== '' ? $UserAlleOpleidingen . ',' : '') . CGOCORNER_ID;
                }
            }
            if ($UserAlleOpleidingen === '') {
                $UserAlleOpleidingen = Login_GetOpl('SELECT ID as fkOpleiding FROM tblOpleidingen ' . $cWhere . ' ORDER BY CODE', false);
                $UserOpleidingen = $UserAlleOpleidingen;
            }
        }
    }
}
// Initialisatie van de login variablelen, functie van gemaakt omdat dit na een login opnieuw moet gebeuren
/**
* Login Initvars
*
*/
function Login_InitVars()
{
    global $UserAlleOpleidingen, $UserOpleidingen, $ContInvID, $LoginType, $SRHServicepakketID, $SupervisorID, $USER_bServiceBureau, $UserAantalRollen, $UserJaargroepopleiderOpl, $UserOplExclCursorisch, $UserPlanning, $UserPraktijkopleiders, $WerkbegeleiderID, $assistentID, $deelnemerID, $docentID, $klantContactpersoonID, $p_praktoplID, $praktijkopleiderID;
    $defaultOpleiding = null;
    // deze gaat nog via cookies
    $UserOpleidingen = Cookie::get(COOKIE_OPL, '');
    // ID van de actieve rol van de ingelogde gebruiker
    $deelnemerID = Login::RetrieveValue(COOKIE_ID_DE);
    $docentID = Login::RetrieveValue(COOKIE_ID_DO);
    $assistentID = Login::RetrieveValue(COOKIE_ID_AS);
    $praktijkopleiderID = Login::RetrieveValue(COOKIE_ID_PO);
    $p_praktoplID = Login::RetrieveValue(COOKIE_ID_POPL);
    $SRHServicepakketID = Login::RetrieveValue(COOKIE_ID_SRH_SP);
    $ContInvID = Login::RetrieveValue(COOKIE_ID_CONT_INV);
    $SupervisorID = Login::RetrieveValue(COOKIE_ID_SUPERVISOR);
    $WerkbegeleiderID = Login::RetrieveValue(COOKIE_ID_WERKBEGELEIDER);
    $klantContactpersoonID = Login::RetrieveValue(COOKIE_ID_KLANTCP);
    $UserAlleOpleidingen = Login::RetrieveValue(COOKIE_ALLEOPL);
    $UserJaargroepopleiderOpl = Login::RetrieveValue(COOKIE_HOOFDOPL);
    $UserOplExclCursorisch = Login::RetrieveValue(COOKIE_OPL_EXCL_CURS);
    $UserPraktijkopleiders = Login::RetrieveValue(COOKIE_PRAKTIJKOPL);
    $UserPlanning = Login::RetrieveValue(COOKIE_PLANNING);
    $USER_bServiceBureau = Login::RetrieveValue(COOKIE_SERVICEBUREAU);
    if (Login::RetrieveValue(COOKIE_AANTAL_ROLLEN) != '') {
        $UserAantalRollen = floor(Login::RetrieveValue(COOKIE_AANTAL_ROLLEN));
    } else {
        $UserAantalRollen = 1;
    }
    if (Login::RetrieveValue(COOKIE_TYPE) != '') {
        $LoginType = Login::RetrieveValue(COOKIE_TYPE);
    }
    // zet de weergave opleidingen op de geselecteerde opleiding hetzij via de selectie, hetzij via parameters
    if ($parActiveOpl !== '') {
        if (Arr::findTrimmed(Arr::splitAlways($UserOpleidingen, ','), $parActiveOpl) > -1) {
            $UserOpleidingen = $parActiveOpl;
        }
    }
    if ($UserOpleidingen === "") {
        $UserOpleidingen = $UserAlleOpleidingen;
    }
}
// Een lijst met opleidingen ophalen, gebruikt om een cookie te maken met de opleidingen waarbij de huidige gebruiker
// betrokken is, can be used for SELECT in clauses
/**
* Login Getopl
*
*/
function Login_GetOpl($sql, $blnAlleenActieve)
{
    global $LoginType, $rs_opl, $rs_opl_current_row, $sql, $strTmp;
    if ($blnAlleenActieve) {
        if ($loginType > 0) {
            $sql = SQL::addWhere($sql, '((tblopleidingen.sluitdatum is null or tblopleidingen.sluitdatum>getdate()) and (tblOpleidingen.datOnSite<=getdate() or (tblOpleidingen.datDocentenOnline is not null and tblOpleidingen.datDocentenOnline<getdate())))');
        } else {
            $sql = SQL::addWhere($sql, '(tblOpleidingen.datOnSite<=getdate() and (tblopleidingen.sluitdatum is null or tblopleidingen.sluitdatum>getdate()))');
        }
    } else {
        if ($loginType == USER_TYPE_ASSISTENT) {
            $sql = SQL::addWhere($sql, '((tblopleidingen.sluitdatum is null or tblopleidingen.sluitdatum>getdate()) and (tblOpleidingen.datOnSite<=' . SQL::postDateTime(dateAdd('m', 6, date("Y-m-d"))) . ' or (tblOpleidingen.datDocentenOnline is not null and tblOpleidingen.datDocentenOnline<=' . SQL::postDateTime(dateAdd('m', 6, date("Y-m-d"))) . ')))');
        } else {
            $sql = SQL::addWhere($sql, '((tblopleidingen.sluitdatum is null or tblopleidingen.sluitdatum>getdate()) and (tblOpleidingen.datOnSite<=' . SQL::postDateTime(dateAdd('m', 3, date("Y-m-d"))) . '))');
        }
    }
    // echo '<!---' . PHP_EOL . 'Alleen actieve:' . $blnAlleenActieve . PHP_EOL & $sql . '-->"
    Database::openRS($rs_opl, $sql, 'data', adOpenForwardOnly);
    if ($rs_opl === null) {
        throw new \Exception('Database query failed: ' . Database::getLastError());
    }
    $rs_opl->MoveNext();
    while (!$rs_opl->EOF) {
        $strTmp = ($strTmp === '' ? '' : $strTmp . ',') . Arr::field($rs_opl_current_row, 'fkOpleiding');
        $rs_opl->MoveNext();
    }
    // ehmm WEIRD!, found a ; in the live site that messed up the system, better safe than sorry
    return str_replace(';', ',', $strTmp);
}
?>
