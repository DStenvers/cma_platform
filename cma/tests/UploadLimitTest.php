<?php
/**
 * App\Library\Upload — the one size rule for every upload sink: UPLOAD_MAX_MB,
 * never above php.ini.
 *
 *   php cma/tests/TestRunner.php UploadLimitTest
 */
require_once __DIR__ . '/TestRunner.php';

use App\Library\Settings;
use App\Library\Upload;

class UploadLimitTest extends TestCase
{
    public function tearDown(): void
    {
        unset($_ENV['UPLOAD_MAX_MB'], $_ENV['RESPONSIVE_IMAGE_SIZES'], $_ENV['EDITOR_ALLOW_BR']);
        putenv('UPLOAD_MAX_MB');
        Settings::reset();
    }

    public function testIniBytesReadsTheUsualSuffixes(): int
    {
        $this->assertSame(2 * 1024 * 1024, Upload::iniBytes('2M'));
        $this->assertSame(512 * 1024, Upload::iniBytes('512K'));
        $this->assertSame(1024 * 1024 * 1024, Upload::iniBytes('1G'));
        $this->assertSame(123, Upload::iniBytes('123'));
        $this->assertSame(0, Upload::iniBytes('-1'), 'unlimited reads as 0');
        return 0;
    }

    public function testUnsetSettingMeansThePhpIniLimit(): void
    {
        unset($_ENV['UPLOAD_MAX_MB']);
        $ini = min(Upload::iniBytes((string) ini_get('upload_max_filesize')), Upload::iniBytes((string) ini_get('post_max_size')));
        $this->assertSame($ini > 0 ? $ini : PHP_INT_MAX, Upload::maxBytes());
    }

    public function testTheSettingLowersButNeverRaisesTheLimit(): void
    {
        $_ENV['UPLOAD_MAX_MB'] = '1';
        $this->assertSame(1024 * 1024, Upload::maxBytes(), 'one megabyte is below any sane php.ini');
        $_ENV['UPLOAD_MAX_MB'] = '2048';
        $ini = min(Upload::iniBytes((string) ini_get('upload_max_filesize')), Upload::iniBytes((string) ini_get('post_max_size')));
        if ($ini > 0) {
            $this->assertSame($ini, Upload::maxBytes(), 'php.ini caps the setting');
        }
    }

    public function testSizeErrorNamesBothSizes(): void
    {
        $_ENV['UPLOAD_MAX_MB'] = '1';
        $this->assertNull(Upload::sizeError(['size' => 1024 * 1024]));
        $msg = Upload::sizeError(['size' => 3 * 1024 * 1024 + 512 * 1024]);
        $this->assertStringContainsString('3.5 MB', $msg);
        $this->assertStringContainsString('maximaal 1 MB', $msg);
    }

    public function testResponsiveSizesAndEditorAllowBrComeFromTheRegistry(): void
    {
        $this->assertSame([300, 400, 800, 1200], \App\Library\ResponsiveImage::sizes());
        $_ENV['RESPONSIVE_IMAGE_SIZES'] = '320, 640';
        $this->assertSame([320, 640], \App\Library\ResponsiveImage::sizes());
        $this->assertSame(85, \App\Library\ResponsiveImage::defaultQuality());

        $snapshot = $GLOBALS['Application'] ?? null;
        $GLOBALS['Application'] = ['cma_htmledit_allowbr' => '1']; // Application::get lowercases keys, as the bootstrap does for app.php
        $this->assertFalse(Settings::get('editor_allow_br'), 'the old key means the opposite');
        $GLOBALS['Application'] = [];
        $this->assertTrue(Settings::get('editor_allow_br'));
        $_ENV['EDITOR_ALLOW_BR'] = 'false';
        $this->assertFalse(Settings::get('editor_allow_br'));
        if ($snapshot === null) { unset($GLOBALS['Application']); } else { $GLOBALS['Application'] = $snapshot; }
    }
}
