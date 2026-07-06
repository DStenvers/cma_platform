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

    // ------------------------------------------------------------------
    // Additional coverage: missing file, duplicate keys, key validation,
    // single-quote literalness, unbalanced quotes, loadInto side effects.
    // ------------------------------------------------------------------

    public function testLoadOfMissingFileReturnsEmpty(): void
    {
        $this->assertSame([], EnvFile::load(sys_get_temp_dir() . '/definitely-not-here-' . uniqid()));
    }

    public function testLoadReadsRealFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($tmp, "FOO=bar\n# note\nBAZ=qux");
        $v = EnvFile::load($tmp);
        unlink($tmp);
        $this->assertSame('bar', $v['FOO']);
        $this->assertSame('qux', $v['BAZ']);
    }

    public function testDuplicateKeyLastWins(): void
    {
        $v = EnvFile::parse("K=first\nK=second");
        $this->assertSame('second', $v['K']);
    }

    public function testLineWithoutEqualsIsSkipped(): void
    {
        $v = EnvFile::parse("JUSTAWORD\nK=v");
        $this->assertSame(['K' => 'v'], $v);
    }

    public function testInvalidKeyIsSkipped(): void
    {
        // A key starting with a digit doesn't match [A-Za-z_]… and is dropped;
        // a valid dotted key on the same file is kept.
        $v = EnvFile::parse("1BAD=nope\nAPP.SUB=ok");
        $this->assertSame(['APP.SUB' => 'ok'], $v);
    }

    public function testEmptyValueIsEmptyString(): void
    {
        $v = EnvFile::parse('EMPTY=');
        $this->assertSame('', $v['EMPTY']);
    }

    public function testSingleQuotesDoNotUnescape(): void
    {
        // Only double-quoted values unescape \n; single-quoted are verbatim.
        $v = EnvFile::parse("A='line1\\nline2'");
        $this->assertSame('line1\\nline2', $v['A']);
    }

    public function testUnbalancedQuoteKeptLiteral(): void
    {
        // A leading quote with no matching closer is not treated as a quoted
        // value — the raw text (including the quote) is preserved.
        $v = EnvFile::parse('A="foo');
        $this->assertSame('"foo', $v['A']);
    }

    public function testLoadIntoPopulatesServerAndPutenv(): void
    {
        $key = 'ENVFILE_SRV_' . strtoupper(bin2hex(random_bytes(3)));
        unset($_ENV[$key], $_SERVER[$key]);
        $tmp = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($tmp, "$key=sideeffect");
        EnvFile::loadInto($tmp);
        unlink($tmp);
        $this->assertSame('sideeffect', $_ENV[$key]);
        $this->assertSame('sideeffect', $_SERVER[$key]);
        $this->assertSame('sideeffect', getenv($key));
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
}
