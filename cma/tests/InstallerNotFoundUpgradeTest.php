<?php
/**
 * Tests for Installer::upgradeNotFoundHandler() — an untouched 404.php from an
 * earlier template is brought up to the current one (which calls
 * cma/hackguard.inc); anything a site changed is left alone.
 *
 * Run with: php cma/tests/TestRunner.php InstallerNotFoundUpgradeTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Installer;

class InstallerNotFoundUpgradeTest extends TestCase
{
    private function template(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/templates/404.php.template');
    }

    /** The previous template: the current one without the hack-attempts block. */
    private function previousTemplate(): string
    {
        $current = str_replace("\r\n", "\n", $this->template());
        $previous = preg_replace("#// ---- Hack attempts -.*?\n}\n\n#s", '', $current, 1, $count);
        $this->assertSame(1, $count, 'het hackguard-blok staat niet (meer) in het sjabloon');
        return $previous;
    }

    public function testUntouchedPreviousTemplateIsUpgraded(): void
    {
        $this->assertSame($this->template(), Installer::upgradeNotFoundHandler($this->previousTemplate(), $this->template()));
    }

    public function testCrlfCopyCountsAsUntouched(): void
    {
        $crlf = str_replace("\n", "\r\n", $this->previousTemplate());
        $this->assertSame($this->template(), Installer::upgradeNotFoundHandler($crlf, $this->template()));
    }

    public function testCustomisedHandlerIsLeftAlone(): void
    {
        $custom = $this->previousTemplate() . "\n// eigen regel\n";
        $this->assertNull(Installer::upgradeNotFoundHandler($custom, $this->template()));
    }

    public function testCurrentTemplateNeedsNothing(): void
    {
        $this->assertNull(Installer::upgradeNotFoundHandler($this->template(), $this->template()));
    }

    public function testTemplateCallsTheGuard(): void
    {
        $this->assertStringContainsString("cma/hackguard.inc", $this->template());
    }
}
