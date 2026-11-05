<?php
/**
 * Tests for App\Library\NotFoundAnalyzer — candidate paths from the 404 log,
 * validation of the model's answer, and the three-month reminder.
 *
 * Run with: php cma/tests/TestRunner.php NotFoundAnalyzerTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\NotFoundAnalyzer;
use App\Library\NotFoundLists;

class NotFoundAnalyzerTest extends TestCase
{
    private string $root;

    public function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nfanalyzer_' . uniqid('', true);
    }

    public function tearDown(): void
    {
        @unlink($this->root . '/data/notfound_analysis.json');
        @unlink($this->root . '/data/notfound_lists.json');
        @rmdir($this->root . '/data');
        @rmdir($this->root);
    }

    private function lists(): NotFoundLists
    {
        return NotFoundLists::forSite($this->root);
    }

    public function testCandidatesCountUnlistedPathsWithoutQuery(): void
    {
        $this->lists()->add(NotFoundLists::HACK, '/wp-login.php', 'test');
        $lines = [
            '{"url":"http://x/wp-login.php"}',
            '{"url":"http://x/boaform/admin?a=1"}',
            '{"url":"http://x/boaform/admin?a=2"}',
            '{"url":"http://x/oud"}',
            '{"type":"icon_redirect","url":"http://x/favicon.ico"}',
            'geen json',
        ];
        $this->assertSame(['/boaform/admin' => 2, '/oud' => 1], NotFoundAnalyzer::candidates($lines, $this->lists()));
    }

    public function testParseKeepsOnlySoundSuggestionsThatMatchTheLog(): void
    {
        $candidates = ['/boaform/admin' => 5, '/boaform/x' => 2, '/oud' => 1];
        $answer = '```json' . "\n" . json_encode([
            ['patroon' => '/boaform/*', 'reden' => 'router-exploit'],
            ['patroon' => '/BOAFORM/*', 'reden' => 'dubbel'],
            ['patroon' => '*', 'reden' => 'te breed'],
            ['patroon' => '/*', 'reden' => 'te breed'],
            ['patroon' => '/niet-in-log.php', 'reden' => 'verzonnen'],
            ['patroon' => 'geenpad', 'reden' => 'geen pad'],
            ['reden' => 'geen patroon'],
        ]) . "\n```";
        $result = NotFoundAnalyzer::parse($answer, $candidates, $this->lists());
        $this->assertSame(1, count($result));
        $this->assertSame('/boaform/*', $result[0]['patroon']);
        $this->assertSame(7, $result[0]['aantal']);
        $this->assertSame(['/boaform/admin', '/boaform/x'], $result[0]['voorbeelden']);
    }

    public function testParseSkipsPatternsAlreadyOnTheList(): void
    {
        $this->lists()->add(NotFoundLists::HACK, '/boaform/*', 'test');
        $answer = json_encode([['patroon' => '/boaform/*', 'reden' => 'x']]);
        $this->assertSame([], NotFoundAnalyzer::parse($answer, ['/boaform/a' => 1], $this->lists()));
    }

    public function testParseToleratesGarbage(): void
    {
        $this->assertSame([], NotFoundAnalyzer::parse('Sorry, dat kan ik niet.', ['/a' => 1], $this->lists()));
    }

    public function testReminderIsDueUntilAnalysedAndAgainAfterThreeMonths(): void
    {
        $now = 1_800_000_000;
        $this->assertTrue(NotFoundAnalyzer::reminderDue($this->root, $now));
        NotFoundAnalyzer::markRun($this->root, $now);
        $this->assertFalse(NotFoundAnalyzer::reminderDue($this->root, $now + 80 * 86400));
        $this->assertTrue(NotFoundAnalyzer::reminderDue($this->root, $now + 100 * 86400));
    }
}
