<?php
/**
 * Tests for App\Library\Email — BUILDER surface only.
 *
 * Run with: php tests/TestRunner.php EmailTest
 *
 * These tests never call send() (that needs SMTP). They exercise the message
 * builder/getter methods and inspect internal state via reflection.
 *
 * $GLOBALS['Application'] is snapshotted in setUp and restored in tearDown so
 * the constructor's initialize() reads a controlled, deterministic config.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Email;

class EmailTest extends TestCase
{
    /** @var mixed */
    private $appSnapshot;
    /** @var string[] Temp files to unlink in tearDown */
    private array $tempFiles = [];

    public function setUp(): void
    {
        $this->appSnapshot = $GLOBALS['Application'] ?? null;
        // Clean, minimal config: no email_from, no admin BCC, dev env 'O'.
        $GLOBALS['Application'] = [];
        $this->tempFiles = [];
    }

    public function tearDown(): void
    {
        if ($this->appSnapshot === null) {
            unset($GLOBALS['Application']);
        } else {
            $GLOBALS['Application'] = $this->appSnapshot;
        }
        foreach ($this->tempFiles as $f) {
            if (is_file($f)) { @unlink($f); }
        }
        $this->tempFiles = [];
    }

    /** Read a private property off an Email instance. */
    private function priv(Email $e, string $prop)
    {
        $r = new ReflectionProperty(Email::class, $prop);
        $r->setAccessible(true);
        return $r->getValue($e);
    }

    /** Get the underlying PHPMailer to read its recipient lists. */
    private function mailer(Email $e)
    {
        return $this->priv($e, 'mailer');
    }

    // ========================================================================
    // Constructor + defaults
    // ========================================================================

    public function testConstructorDefaults(): void
    {
        $e = new Email();
        $this->assertEquals('', $e->getBody());
        $this->assertEquals('', $e->getSubject());
        $this->assertEquals('', $e->getError());
    }

    public function testConstructorDefaultFromAddressWhenNoConfig(): void
    {
        $e = new Email();
        // No email_from/email_fromname → hard default address, company as name.
        $this->assertEquals('webmaster@stenversonline.nl', $this->priv($e, 'fromEmail'));
        $this->assertEquals('RINO amsterdam', $this->priv($e, 'fromName'));
    }

    public function testConstructorReadsConfiguredFromAddress(): void
    {
        $GLOBALS['Application'] = [
            'email_from' => 'sender@example.com',
            'email_fromname' => 'Sender Name',
            'company' => 'Acme',
        ];
        $e = new Email();
        $this->assertEquals('sender@example.com', $this->priv($e, 'fromEmail'));
        $this->assertEquals('Sender Name', $this->priv($e, 'fromName'));
    }

    public function testConstructorLegacyAddressInFromName(): void
    {
        // Legacy sites store the ADDRESS in email_fromname; code recovers it.
        $GLOBALS['Application'] = [
            'email_fromname' => 'legacy@example.com',
            'company' => 'Acme',
        ];
        $e = new Email();
        $this->assertEquals('legacy@example.com', $this->priv($e, 'fromEmail'));
        // Name falls back to company because fromname is an address.
        $this->assertEquals('Acme', $this->priv($e, 'fromName'));
    }

    public function testConstructorAddsAdminBcc(): void
    {
        $GLOBALS['Application'] = ['app_beheerder_email' => 'admin@example.com'];
        $e = new Email();
        $bcc = $this->mailer($e)->getBccAddresses();
        $this->assertCount(1, $bcc);
        $this->assertEquals('admin@example.com', $bcc[0][0]);
    }

    public function testCreateFactoryReturnsEmail(): void
    {
        $e = Email::create();
        $this->assertTrue($e instanceof Email);
    }

    // ========================================================================
    // Body / Subject round-trip
    // ========================================================================

    public function testSetBodyGetBodyRoundTrip(): void
    {
        $e = new Email();
        $ret = $e->setBody('Hello World');
        $this->assertEquals('Hello World', $e->getBody());
        // Fluent: returns the Email itself.
        $this->assertTrue($ret instanceof Email);
    }

    public function testSetBodyConvertsCrlfToBr(): void
    {
        $e = new Email();
        $e->setBody("line1\r\nline2");
        $this->assertEquals('line1<BR>line2', $e->getBody());
    }

    public function testSetBodyFixesBulletEncoding(): void
    {
        $e = new Email();
        $e->setBody("a\xE2\x80\xA2b"); // 'â€¢' mojibake bytes
        $this->assertEquals('a•b', $e->getBody());
    }

    public function testSetSubjectCapitalizesAndTrims(): void
    {
        $e = new Email();
        $e->setSubject('  hello there  ');
        $this->assertEquals('Hello there', $e->getSubject());
    }

    public function testSetSubjectTestEnvPrefix(): void
    {
        $GLOBALS['Application'] = ['omgeving' => 'T'];
        $e = new Email();
        $e->setSubject('nightly report');
        $this->assertEquals('TEST-OMGEVING: Nightly report', $e->getSubject());
    }

    public function testSetSubjectAcceptanceEnvPrefix(): void
    {
        $GLOBALS['Application'] = ['omgeving' => 'A'];
        $e = new Email();
        $e->setSubject('nightly report');
        $this->assertEquals('ACCEPTATIE-OMGEVING: Nightly report', $e->getSubject());
    }

    public function testSetSubjectNoPrefixInDev(): void
    {
        $GLOBALS['Application'] = ['omgeving' => 'O'];
        $e = new Email();
        $e->setSubject('hello');
        $this->assertEquals('Hello', $e->getSubject());
    }

    // ========================================================================
    // setHeader
    // ========================================================================

    public function testSetHeaderStoredCapitalized(): void
    {
        $e = new Email();
        $e->setHeader('  welcome aboard ');
        $this->assertEquals('Welcome aboard', $this->priv($e, 'header'));
    }

    // ========================================================================
    // setFrom / setFromName / setReplyTo
    // ========================================================================

    public function testSetFromStoresEmailAndName(): void
    {
        $e = new Email();
        $e->setFrom('foo@bar.com', 'Foo Bar');
        $this->assertEquals('foo@bar.com', $this->priv($e, 'fromEmail'));
        $this->assertEquals('Foo Bar', $this->priv($e, 'fromName'));
    }

    public function testSetFromDefaultsNameToEmail(): void
    {
        $e = new Email();
        $e->setFrom('foo@bar.com');
        $this->assertEquals('foo@bar.com', $this->priv($e, 'fromName'));
    }

    public function testSetFromName(): void
    {
        $e = new Email();
        $e->setFromName('Just A Name');
        $this->assertEquals('Just A Name', $this->priv($e, 'fromName'));
    }

    public function testSetReplyTo(): void
    {
        $e = new Email();
        $e->setReplyTo('reply@bar.com');
        $this->assertEquals('reply@bar.com', $this->priv($e, 'replyTo'));
    }

    // ========================================================================
    // addRecipient(s) TO
    // ========================================================================

    public function testAddRecipientSingle(): void
    {
        $e = new Email();
        $e->addRecipient('user@example.com');
        $to = $this->mailer($e)->getToAddresses();
        $this->assertCount(1, $to);
        $this->assertEquals('user@example.com', $to[0][0]);
    }

    public function testAddRecipientDefaultsNameToEmail(): void
    {
        $e = new Email();
        $e->addRecipient('user@example.com');
        $to = $this->mailer($e)->getToAddresses();
        $this->assertEquals('user@example.com', $to[0][1]);
    }

    public function testAddRecipientsSemicolonList(): void
    {
        $e = new Email();
        $e->addRecipients('a@x.com; b@x.com ;c@x.com');
        $to = $this->mailer($e)->getToAddresses();
        $this->assertCount(3, $to);
    }

    public function testAddRecipientsAccumulate(): void
    {
        $e = new Email();
        $e->addRecipient('a@x.com')->addRecipient('b@x.com');
        $this->assertCount(2, $this->mailer($e)->getToAddresses());
    }

    public function testAddRecipientDeduplicates(): void
    {
        $e = new Email();
        $e->addRecipient('dup@x.com')->addRecipient('dup@x.com')->addRecipient('other@x.com');
        $this->assertCount(2, $this->mailer($e)->getToAddresses());
        $this->assertCount(2, $this->priv($e, 'recipientTracker'));
    }

    public function testAddRecipientEmptySkipped(): void
    {
        $e = new Email();
        $e->addRecipient('   ');
        $this->assertCount(0, $this->mailer($e)->getToAddresses());
    }

    public function testAddRecipientInvalidSetsErrorAndNotTracked(): void
    {
        $e = new Email();
        $e->addRecipient('notanemail');
        // PHPMailer rejects it: nothing added, tracker untouched, error set.
        $this->assertCount(0, $this->mailer($e)->getToAddresses());
        $this->assertCount(0, $this->priv($e, 'recipientTracker'));
        $this->assertStringContainsString('Invalid recipient', $e->getError());
    }

    // ========================================================================
    // CC
    // ========================================================================

    public function testAddRecipientCCSingle(): void
    {
        $e = new Email();
        $e->addRecipientCC('cc@example.com');
        $cc = $this->mailer($e)->getCcAddresses();
        $this->assertCount(1, $cc);
        $this->assertEquals('cc@example.com', $cc[0][0]);
    }

    public function testAddRecipientsCCSemicolonList(): void
    {
        $e = new Email();
        $e->addRecipientsCC('a@x.com;b@x.com');
        $this->assertCount(2, $this->mailer($e)->getCcAddresses());
    }

    public function testAddRecipientCCInvalidSetsError(): void
    {
        $e = new Email();
        $e->addRecipientCC('bogus');
        $this->assertCount(0, $this->mailer($e)->getCcAddresses());
        $this->assertStringContainsString('Invalid CC recipient', $e->getError());
    }

    // ========================================================================
    // BCC
    // ========================================================================

    public function testAddRecipientBCCSingle(): void
    {
        $e = new Email();
        $e->addRecipientBCC('bcc@example.com');
        $bcc = $this->mailer($e)->getBccAddresses();
        $this->assertCount(1, $bcc);
        $this->assertEquals('bcc@example.com', $bcc[0][0]);
    }

    public function testAddRecipientsBCCSemicolonList(): void
    {
        $e = new Email();
        $e->addRecipientsBCC('a@x.com;b@x.com');
        $this->assertCount(2, $this->mailer($e)->getBccAddresses());
    }

    public function testCrossTypeDeduplication(): void
    {
        // recipientTracker is shared across TO/CC/BCC: same address only lands once.
        $e = new Email();
        $e->addRecipient('same@x.com')->addRecipientCC('same@x.com')->addRecipientBCC('same@x.com');
        $this->assertCount(1, $this->mailer($e)->getToAddresses());
        $this->assertCount(0, $this->mailer($e)->getCcAddresses());
        $this->assertCount(0, $this->mailer($e)->getBccAddresses());
    }

    // ========================================================================
    // addAttachment
    // ========================================================================

    public function testAddAttachmentRealFileRecorded(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'emailtest_');
        file_put_contents($path, 'dummy');
        $this->tempFiles[] = $path;

        $e = new Email();
        $e->addAttachment($path);
        $this->assertContains($path, $this->priv($e, 'attachments'));
    }

    public function testAddAttachmentMissingFileStillRecorded(): void
    {
        // addAttachment only checks non-empty; existence is validated later in send().
        $missing = sys_get_temp_dir() . '/does_not_exist_' . uniqid() . '.pdf';
        $e = new Email();
        $e->addAttachment($missing);
        $this->assertContains($missing, $this->priv($e, 'attachments'));
    }

    public function testAddAttachmentEmptySkipped(): void
    {
        $e = new Email();
        $e->addAttachment('');
        $this->assertCount(0, $this->priv($e, 'attachments'));
    }

    // ========================================================================
    // setTemplate / setCMATemplate — applied via private applyTemplate()
    // ========================================================================

    public function testSetTemplateStored(): void
    {
        $e = new Email();
        $e->setTemplate('<div>[[body]]</div>');
        $this->assertEquals('<div>[[body]]</div>', $this->priv($e, 'template'));
    }

    public function testSetCMATemplateFlag(): void
    {
        $e = new Email();
        $this->assertFalse($this->priv($e, 'cmaTemplate'));
        $e->setCMATemplate(true);
        $this->assertTrue($this->priv($e, 'cmaTemplate'));
    }

    public function testApplyTemplateSubstitutesBody(): void
    {
        $e = new Email();
        $e->setUseTemplate(true)->setTemplate('X[[body]]Y')->setBody('hi');
        $r = new ReflectionMethod(Email::class, 'applyTemplate');
        $r->setAccessible(true);
        $this->assertEquals('XhiY', $r->invoke($e));
    }

    public function testApplyCMATemplateWrapsBody(): void
    {
        $e = new Email();
        $e->setCMATemplate(true)->setBody('BODYMARKER');
        $r = new ReflectionMethod(Email::class, 'applyTemplate');
        $r->setAccessible(true);
        $out = $r->invoke($e);
        $this->assertStringContainsString('BODYMARKER', $out);
        $this->assertStringContainsString('<table', $out);
    }

    // ========================================================================
    // getError
    // ========================================================================

    public function testGetErrorEmptyInitially(): void
    {
        $e = new Email();
        $this->assertEquals('', $e->getError());
    }
}
