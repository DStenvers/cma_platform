<?php
/**
 * LinkURLs() (library/lib_url.inc): only a real e-mail address gets the
 * anti-spam script link; other text with an @ stays plain.
 *
 *   php cma/tests/TestRunner.php LinkUrlsEmailTest
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../../library/lib_url.inc';

class LinkUrlsEmailTest extends TestCase
{
    public function testARealAddressIsObfuscated(): void
    {
        $out = LinkURLs('Mail naar info@rino.nl voor meer.');
        $this->assertStringContainsString("lib_mail('info','rino.nl','')", $out);
        $this->assertStringContainsString('Mail naar ', $out);
        $this->assertStringContainsString(' voor meer.', $out);
    }

    public function testAnAtSignInOtherTextIsLeftAlone(): void
    {
        $this->assertSame('volg @rino op sociale media', LinkURLs('volg @rino op sociale media'));
        $this->assertSame('prijs 10 @ 2.50', LinkURLs('prijs 10 @ 2.50'), 'a dot near an @ is not an address');
    }

    public function testTrailingPunctuationStaysOutsideTheAddress(): void
    {
        $out = LinkURLs('Schrijf naar a.b@c.nl.');
        $this->assertStringContainsString("lib_mail('a.b','c.nl','')", $out);
        $this->assertTrue(str_ends_with($out, '.'));
    }
}
