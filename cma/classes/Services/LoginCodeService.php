<?php

namespace Cma\Services;

use App\Library\Cookie;
use App\Library\Database;
use App\Library\Email;
use App\Library\Request;
use App\Library\SQL;
use Cma\SecurityHelper;

/**
 * Inloggen in het CMA met een eenmalige e-mailcode, voor als de SSO down is (SsoService::isDown()).
 *
 * De code staat gehasht in tblUsers (login_code_hash, sha256 over code + guid van de gebruiker),
 * nooit leesbaar. Regels (zelfde als de login van de site):
 *  - 6 cijfers, GELDIG_MIN minuten geldig;
 *  - hooguit MAX_GEBRUIK keer succesvol te gebruiken;
 *  - na MAX_FOUT foute pogingen is de code dood (anders is een 6-cijferige code te raden);
 *  - een nieuwe aanvraag vervangt de vorige en mag pas HERZEND_SEC seconden na de vorige;
 *  - de aanvraag geeft nooit prijs of het adres bestaat.
 * Kolommen: migratie 9.26.0. Alleen gebruikers met een e-mailadres in tblUsers.
 */
class LoginCodeService
{
    public const GELDIG_MIN = 15;
    public const MAX_GEBRUIK = 2;
    public const MAX_FOUT = 5;
    public const HERZEND_SEC = 60;

    /** Zes cijfers, cryptografisch willekeurig. */
    public static function maakCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Hash zoals opgeslagen; de guid (of anders het ID) maakt dezelfde code bij twee gebruikers tot twee hashes. */
    public static function hash(string $code, string $zout): string
    {
        return hash('sha256', $code . '|' . strtolower(trim($zout, '{} ')));
    }

    /** Zout voor een rij: userGUID, anders het gebruikers-ID. */
    private static function zout(array $rij): string
    {
        $guid = trim((string) ($rij['userGUID'] ?? ''));
        return $guid !== '' ? $guid : 'u' . (int) ($rij['ID'] ?? 0);
    }

    /**
     * Beoordeelt een ingevoerde code tegen de opgeslagen rij. Geen database, dus testbaar.
     * @return string 'ok' | 'geen_code' | 'verlopen' | 'opgebruikt' | 'geblokkeerd' | 'onjuist'
     */
    public static function beoordeel(array $rij, string $code, int $nu): string
    {
        $hash = (string) ($rij['login_code_hash'] ?? '');
        if ($hash === '') {
            return 'geen_code';
        }
        if ((int) ($rij['login_code_fout'] ?? 0) >= self::MAX_FOUT) {
            return 'geblokkeerd';
        }
        if ((int) ($rij['login_code_gebruikt'] ?? 0) >= self::MAX_GEBRUIK) {
            return 'opgebruikt';
        }
        $verstuurd = $rij['login_code_verstuurd'] ?? '';
        $t = is_int($verstuurd) ? $verstuurd : strtotime((string) $verstuurd);
        if ($t === false || $t === null || $nu - $t > self::GELDIG_MIN * 60) {
            return 'verlopen';
        }
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($code === '' || !hash_equals($hash, self::hash($code, self::zout($rij)))) {
            return 'onjuist';
        }
        return 'ok';
    }

    /** CMA-gebruiker met dit e-mailadres (userEMail), of null. */
    public static function zoekGebruiker(string $email): ?array
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $rs = Database::openRS(
            'SELECT ID, userLogin, userFullName, userEMail, userGUID, login_code_hash, login_code_verstuurd, login_code_gebruikt, login_code_fout'
            . ' FROM tblUsers WHERE LCASE(userEMail)=' . SQL::postString(strtolower($email)),
            'users', adOpenForwardOnly);
        if ($rs === null || $rs->EOF) {
            return null;
        }
        return $rs->fields;
    }

    /**
     * Maakt een code en mailt hem, als het adres bij een CMA-gebruiker hoort. Doet niets (en zegt
     * ook niets) bij een onbekend adres of een te snelle herhaling. Geeft true als er een mail is gegaan.
     */
    public static function aanvragen(string $email): bool
    {
        $rij = self::zoekGebruiker($email);
        if ($rij === null) {
            return false;
        }
        $vorig = strtotime((string) ($rij['login_code_verstuurd'] ?? ''));
        if ($vorig !== false && (string) ($rij['login_code_hash'] ?? '') !== '' && time() - $vorig < self::HERZEND_SEC) {
            return false;
        }

        $code = self::maakCode();
        Database::executeOn('users', 'UPDATE tblUsers SET login_code_hash=?, login_code_verstuurd=?, login_code_gebruikt=0, login_code_fout=0 WHERE ID=?',
            [self::hash($code, self::zout($rij)), date('Y-m-d H:i:s'), (int) $rij['ID']]);

        $host = str_replace('www.', '', strtolower((string) Request::server('SERVER_NAME', '')));
        $naam = htmlspecialchars((string) ($rij['userFullName'] ?? ''), ENT_QUOTES, 'UTF-8');
        $mailer = new Email();
        $mailer->setSubject('Inlogcode CMA ' . $host);
        $mailer->setBody('Hallo ' . $naam . ',<br><br>Single Sign-On is op dit moment niet beschikbaar. Met deze code log je in op de CMA van '
            . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . ':<br><br><b>' . $code . '</b><br><br>De code is ' . self::GELDIG_MIN
            . ' minuten geldig en werkt ' . self::MAX_GEBRUIK . ' keer. Heb je dit niet aangevraagd, dan kun je deze mail negeren.');
        $mailer->addRecipient((string) $rij['userEMail']);
        $verstuurd = $mailer->send();
        if (!$verstuurd) {
            Logger::error('CMA logincode: mail niet verstuurd', ['error' => $mailer->getError()]);
        }
        return $verstuurd;
    }

    /**
     * Controleert de code en telt het gebruik op. Geeft de gebruiker (zoals zoekGebruiker) bij succes,
     * anders null (de reden gaat in $reden, alleen voor de log: de gebruiker krijgt altijd dezelfde melding).
     */
    public static function gebruik(string $email, string $code, string &$reden = ''): ?array
    {
        $rij = self::zoekGebruiker($email);
        if ($rij === null) {
            $reden = 'onbekend';
            return null;
        }
        $reden = self::beoordeel($rij, $code, time());
        $id = (int) $rij['ID'];
        if ($reden === 'onjuist') {
            Database::executeOn('users', 'UPDATE tblUsers SET login_code_fout=login_code_fout+1 WHERE ID=?', [$id]);
            return null;
        }
        if ($reden !== 'ok') {
            return null;
        }
        // Voorwaardelijk ophogen: twee gelijktijdige requests kunnen zo niet beide de laatste beurt pakken.
        $geraakt = Database::executeOn('users', 'UPDATE tblUsers SET login_code_gebruikt=login_code_gebruikt+1 WHERE ID=? AND login_code_gebruikt<?',
            [$id, self::MAX_GEBRUIK]);
        if ($geraakt < 1) {
            $reden = 'opgebruikt';
            return null;
        }
        return $rij;
    }

    /** Logt de gebruiker in (zelfde cookies als wachtwoord- en SSO-login); maakt een userGUID als die ontbreekt. */
    public static function login(array $rij): void
    {
        $guid = trim((string) ($rij['userGUID'] ?? ''));
        if ($guid === '') {
            $guid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
            Database::executeOn('users', 'UPDATE tblUsers SET userGUID=? WHERE ID=?', [$guid, (int) $rij['ID']]);
        }
        $levensduur = (int) \App\Library\Settings::get('auth_cookie_lifetime');
        SecurityHelper::startSession((int) $rij['ID'], $guid, $levensduur);
        Cookie::set(SecurityHelper::COOKIE_LAST_LOGIN, (string) $rij['userLogin'], $levensduur);
    }
}
