<?php
/**
 * Tests for App\Library\EnvFile — the .env / ini-style KEY=VALUE reader that
 * replaced vlucas/phpdotenv.
 *
 * Run with: php cma/tests/TestRunner.php EnvFileTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\EnvFile;

class EnvFileTest extends TestCase
{
    public function testBasicKeyValue(): void
    {
        $v = EnvFile::parse("APP_ENVIRONMENT=P\nDEBUG=1");
        $this->assertSame('P', $v['APP_ENVIRONMENT']);
        $this->assertSame('1', $v['DEBUG']);
    }

    public function testCommentsAndBlankLinesIgnored(): void
    {
        $v = EnvFile::parse("# a comment\n; another\n\nKEY=val");
        $this->assertSame(['KEY' => 'val'], $v);
    }

    public function testQuotedValuesKeptVerbatim(): void
    {
        $v = EnvFile::parse("A=\"hello world\"\nB='it #2 & you'");
        $this->assertSame('hello world', $v['A']);
        // '#' inside quotes is NOT treated as a comment
        $this->assertSame('it #2 & you', $v['B']);
    }

    public function testSecretWithReservedCharsSurvives(): void
    {
        // parse_ini_file would choke on these; we take them literally.
        $v = EnvFile::parse('DEPLOY_SECRET=ab&c!d{e}f|g');
        $this->assertSame('ab&c!d{e}f|g', $v['DEPLOY_SECRET']);
    }

    public function testInlineCommentStrippedOnUnquoted(): void
    {
        $v = EnvFile::parse('KEY=value   # trailing note');
        $this->assertSame('value', $v['KEY']);
        // but a '#' with no preceding space is part of the value
        $v2 = EnvFile::parse('COLOR=#ffffff');
        $this->assertSame('#ffffff', $v2['COLOR']);
    }

    public function testExportPrefixAndDoubleQuoteEscapes(): void
    {
        $v = EnvFile::parse("export PATHY=/a/b\nMSG=\"line1\\nline2\"");
        $this->assertSame('/a/b', $v['PATHY']);
        $this->assertSame("line1\nline2", $v['MSG']);
    }

    public function testValueWithEqualsSign(): void
    {
        $v = EnvFile::parse('DSN=odbc:Driver={x};Dbq=C:/db/u.mdb');
        $this->assertSame('odbc:Driver={x};Dbq=C:/db/u.mdb', $v['DSN']);
    }

    public function testLoadIntoIsImmutable(): void
    {
        $_ENV['ENVFILE_TEST_KEEP'] = 'original';
        $tmp = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($tmp, "ENVFILE_TEST_KEEP=overwritten\nENVFILE_TEST_NEW=fresh");
        EnvFile::loadInto($tmp);
        unlink($tmp);
        $this->assertSame('original', $_ENV['ENVFILE_TEST_KEEP']); // not overwritten
        $this->assertSame('fresh', $_ENV['ENVFILE_TEST_NEW']);
        unset($_ENV['ENVFILE_TEST_KEEP'], $_ENV['ENVFILE_TEST_NEW']);
    }
}
