<?php
/**
 * Email::resolveSender() and the fallback notice — where a mail's From comes
 * from, in order: MAIL_FROM, email_from in app.php, an address left in the
 * older email_fromname, else the fallback address with a notice.
 *
 *   php tests/TestRunner.php EmailResolveSenderTest
 */
require_once __DIR__ . '/TestRunner.php';

use App\Library\Email;
use App\Library\Settings;

class EmailResolveSenderTest extends TestCase
{
    private $appSnapshot;

    public function setUp(): void
    {
        $this->appSnapshot = $GLOBALS['Application'] ?? null;
        $GLOBALS['Application'] = [];
        Settings::reset();
        $this->clearEnv();
    }

    public function tearDown(): void
    {
        $this->clearEnv();
        if ($this->appSnapshot === null) {
            unset($GLOBALS['Application']);
        } else {
            $GLOBALS['Application'] = $this->appSnapshot;
        }
    }

    private function clearEnv(): void
    {
        foreach (['MAIL_FROM', 'MAIL_FROM_NAME', 'MAIL_FALLBACK_ADDRESS', 'COMPANY', 'ADMIN_EMAIL'] as $k) {
            unset($_ENV[$k]);
            putenv($k);
        }
    }

    private function fallback(Email $e, string $body): string
    {
        $m = new ReflectionMethod(Email::class, 'applySenderFallback');
        $m->setAccessible(true);
        return $m->invoke($e, $body);
    }

    public function testNothingConfiguredMeansTheFallbackAddressAndANotice(): void
    {
        $s = Email::resolveSender();
        $this->assertEquals('dstenvers@gmail.com', $s['email'], 'the registry default of MAIL_FALLBACK_ADDRESS');
        $this->assertFalse($s['configured']);
        $this->assertEquals('fallback', $s['source']);
        $this->assertEquals('dstenvers@gmail.com', $s['name'], 'no name anywhere: the address');

        $e = new Email();
        $body = $this->fallback($e, '<p>inhoud</p>');
        $this->assertStringContainsString('geen afzenderadres ingesteld', $body);
        $this->assertStringContainsString('Systeeminstellingen', $body);
        $this->assertStringContainsString('<p>inhoud</p>', $body);
    }

    public function testTheFallbackAddressIsASetting(): void
    {
        $_ENV['MAIL_FALLBACK_ADDRESS'] = 'vangnet@voorbeeld.nl';
        $this->assertEquals('vangnet@voorbeeld.nl', Email::resolveSender()['email']);
    }

    public function testAppPhpSenderIsConfiguredWithoutANotice(): void
    {
        $GLOBALS['Application'] = ['email_from' => 'info@site.nl', 'email_fromname' => 'De Site', 'company' => 'Acme'];
        $s = Email::resolveSender();
        $this->assertEquals('info@site.nl', $s['email']);
        $this->assertEquals('De Site', $s['name']);
        $this->assertTrue($s['configured']);
        $this->assertEquals('app', $s['source']);
        $e = new Email();
        $this->assertEquals('<p>x</p>', $this->fallback($e, '<p>x</p>'), 'no notice for a configured sender');
    }

    public function testMailFromSettingBeatsAppPhp(): void
    {
        $GLOBALS['Application'] = ['email_from' => 'info@site.nl'];
        $_ENV['MAIL_FROM'] = 'noreply@site.nl';
        $_ENV['MAIL_FROM_NAME'] = 'Site';
        $s = Email::resolveSender();
        $this->assertEquals('noreply@site.nl', $s['email']);
        $this->assertEquals('Site', $s['name']);
        $this->assertEquals('env', $s['source']);
    }

    public function testLegacyAddressInFromNameStillWorks(): void
    {
        $GLOBALS['Application'] = ['email_fromname' => 'legacy@site.nl', 'company' => 'Acme'];
        $s = Email::resolveSender();
        $this->assertEquals('legacy@site.nl', $s['email']);
        $this->assertEquals('Acme', $s['name'], 'an address is not a name; the organisation is');
        $this->assertEquals('legacy', $s['source']);
        $this->assertTrue($s['configured']);
    }

    public function testCompanyNoLongerDefaultsToAVendor(): void
    {
        $this->assertEquals('', Settings::get('company'));
        $GLOBALS['Application'] = ['email_from' => 'info@site.nl'];
        $this->assertEquals('info@site.nl', Email::resolveSender()['name'], 'without a name the address is the name');
    }

    public function testAnExplicitSetFromIsHonouredWhateverTheCompanyIsCalled(): void
    {
        $GLOBALS['Application'] = ['company' => 'RINO Groep'];
        $e = new Email();
        $e->setFrom('x@klant.nl', 'Klant');
        $body = $this->fallback($e, 'b');
        $r = new ReflectionProperty(Email::class, 'fromEmail');
        $r->setAccessible(true);
        $this->assertEquals('x@klant.nl', $r->getValue($e), 'no rewrite of an explicit sender');
        $this->assertEquals('b', $body, 'and no notice');
    }

    public function testAnEmptySetFromFallsBackWithANotice(): void
    {
        $GLOBALS['Application'] = ['email_from' => 'info@site.nl'];
        $e = new Email();
        $e->setFrom('');
        $body = $this->fallback($e, 'b');
        $r = new ReflectionProperty(Email::class, 'fromEmail');
        $r->setAccessible(true);
        $this->assertEquals('dstenvers@gmail.com', $r->getValue($e));
        $this->assertStringContainsString('geen afzenderadres', $body);
    }

    public function testAdminEmailSettingBeatsAppPhpAndTakesAList(): void
    {
        $GLOBALS['Application'] = ['app_beheerder_email' => 'oud@site.nl'];
        $_ENV['ADMIN_EMAIL'] = 'a@site.nl, b@site.nl';
        $e = new Email();
        $r = new ReflectionProperty(Email::class, 'mailer');
        $r->setAccessible(true);
        $bcc = array_map(fn ($a) => $a[0], $r->getValue($e)->getBccAddresses());
        $this->assertEquals(['a@site.nl', 'b@site.nl'], $bcc);
    }

    public function testAfterSendGetsTheCleanedFromName(): void
    {
        $GLOBALS['Application'] = ['email_from' => 'info@site.nl', 'email_fromname' => 'mijn <font class=green>&bull;</font> Site', 'local' => true];
        $seen = null;
        $previous = Email::$afterSend;
        Email::$afterSend = static function (array $data) use (&$seen) { $seen = $data; };
        try {
            $e = new Email();
            $e->setSubject('t')->setBody('b')->addRecipient('u@site.nl');
            ob_start();
            $ok = $e->send();
            ob_end_clean();
        } finally {
            Email::$afterSend = $previous;
        }
        $this->assertTrue($ok);
        $this->assertNotNull($seen, 'the hook ran');
        $this->assertEquals('mijn • Site', $seen['fromName'], 'markup stripped, entity decoded — the name the mail carried');
        $this->assertEquals('info@site.nl', $seen['from']);
    }
}
