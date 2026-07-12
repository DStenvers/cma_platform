<?php
/**
 * InfiniteScrollLoopTest — end-to-end simulation of the CMA list infinite
 * scroll pager, composing the REAL server pagination (Cma\Services\JsonFormService::
 * getTableHtml) with a faithful PHP replica of the client loop
 * (CmaInfiniteScroll.load + FormController._autoPrefetchRows in
 * cma/assets/js/table-preferences.js / form-controller.js).
 *
 * WHY: every previous "fix" for the recurring "records 1-1500 van 1827
 * (laden...)" stall was verified against a SINGLE batch (ListTableRenderTest)
 * or by eye on a live site. The stall is a MULTI-BATCH keyset convergence
 * property: does the loop, driven exactly as the browser drives it, reach
 * currentCount == totalCount and terminate — for every dataset shape
 * (JOIN one-to-many, group straddling the 500 boundary, an id-group larger
 * than a page, DESC order, string ids, an active filter)?
 *
 * This test PROVES convergence (or finds the divergence) without a browser
 * or a live DB. The synthetic dataset stands in for the Access table; the
 * keyset page is computed here exactly as the DB would (id >/< lastId,
 * ORDER BY id, TOP pageSize+1) and fed to the real getTableHtml.
 *
 * Run: cd cma && php tests/TestRunner.php InfiniteScrollLoopTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';
require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/classes/form_constants.php';
require_once dirname(__DIR__) . '/classes/SecurityHelper.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';
require_once dirname(__DIR__) . '/classes/FormDefinition.php';
require_once dirname(__DIR__) . '/classes/Services/Logger.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/Services/BaseFormService.php';
require_once dirname(__DIR__) . '/classes/Services/TableService.php';
require_once dirname(__DIR__) . '/classes/Services/JsonFormService.php';
require_once dirname(__DIR__) . '/classes/FormDataProvider.php';

use Cma\Services\JsonFormService;

class InfiniteScrollLoopTest extends TestCase
{
    private StubConnection $conn;
    private const PAGE = 500;
    private bool $countIsDistinct = false;

    public function setUp(): void
    {
        TestHarness::loginAsAdmin();
        TestHarness::silenceLogger();
        $this->conn = StubConnection::create();
        TestHarness::injectConnection('data', $this->conn);
    }

    public function tearDown(): void
    {
        TestHarness::reset();
        $_COOKIE = [];
    }

    // -----------------------------------------------------------------
    // Synthetic-dataset helpers
    // -----------------------------------------------------------------

    /**
     * Build raw rows in id order. $groups maps distinct-id => join-multiplicity
     * (how many physical rows the one-to-many JOIN emits for that id). A plain
     * table is multiplicity 1 for every id.
     * @return array<int,array{ID:int|string,naam:string}>
     */
    private function buildRows(array $groups): array
    {
        $rows = [];
        foreach ($groups as $id => $mult) {
            for ($i = 0; $i < $mult; $i++) {
                $rows[] = ['ID' => $id, 'naam' => 'rec' . $id];
            }
        }
        return $rows;
    }

    /** Distinct id count = the true total the pager must reach. */
    private function distinctCount(array $rows): int
    {
        $seen = [];
        foreach ($rows as $r) { $seen[(string)$r['ID']] = true; }
        return count($seen);
    }

    /**
     * The page the real DB would return for a keyset request: rows with
     * ID >/< lastId (per direction), already in id order, take PAGE+1.
     */
    private function keysetPage(array $rows, $lastId, string $dir): array
    {
        $ordered = $rows;
        if ($dir === 'DESC') {
            $ordered = array_reverse($ordered);
        }
        $out = [];
        foreach ($ordered as $r) {
            if ($lastId === null) {
                $out[] = $r;
            } else {
                $cmp = $this->cmp($r['ID'], $lastId);
                if (($dir === 'DESC' && $cmp < 0) || ($dir !== 'DESC' && $cmp > 0)) {
                    $out[] = $r;
                }
            }
            if (count($out) >= self::PAGE + 1) break;
        }
        return $out;
    }

    /** Mirror the SQL comparison: numeric when both numeric, else string. */
    private function cmp($a, $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return ($a <=> $b);
        }
        return strcmp((string)$a, (string)$b);
    }

    /** Parse rendered data-id values from a batch of <tr>. */
    private function parseIds(string $html): array
    {
        preg_match_all('/<tr class="listrow[^"]*" data-id="([^"]*)"/', $html, $m);
        return $m[1];
    }

    // -----------------------------------------------------------------
    // The core simulation: run the REAL server + a replica of the client
    // loop until it converges or a safety cap trips. Returns the observed
    // final state so the caller can assert.
    // -----------------------------------------------------------------

    private function runPager(string $form, array $rows, string $dir, array $baseOptions = [], bool $usesListQuery = false): array
    {
        // A custom listQuery may emit duplicate ids (JOIN, comma-join, UNION,
        // view). The render pass dedupes rows by id UNCONDITIONALLY, so the
        // denominator must be the DISTINCT-id count too — else numerator and
        // denominator use different units and the counter sticks forever.
        // Model the DB faithfully: for a listQuery form the (fixed) server
        // builds a DISTINCT count query, so the DB returns the distinct count;
        // a plain single-table form has a unique PK, so COUNT(*) == distinct.
        $this->countIsDistinct = $usesListQuery;

        // ---- Initial load (lastId null): COUNT query, then data query ----
        $page0 = $this->keysetPage($rows, null, $dir);
        $this->enqueueCountAndData($rows, $page0, $dir, true);
        $r = JsonFormService::getTableHtml($form, null, $baseOptions);
        $this->assertTrue($r['success'] ?? false, 'initial load failed: ' . ($r['error'] ?? ''));

        // GUARD independent of the stubbed count value: verify the SERVER itself
        // chose a DISTINCT count query for a custom listQuery. The stub returns
        // whatever count we enqueue regardless of SQL, so only inspecting the
        // emitted SQL actually catches the "inflated denominator" root cause.
        if ($usesListQuery) {
            $countSql = $this->conn->getCalls()[0]['sql'] ?? '';
            $this->assertTrue(stripos($countSql, 'DISTINCT') !== false,
                "a custom listQuery may repeat ids; totalCount must COUNT DISTINCT — got: $countSql");
        }

        // ---- Client state (mirrors CmaInfiniteScroll) ----
        $seen = [];                                   // tbody._cmaSeenIds
        foreach ($this->parseIds($r['html']) as $id) { $seen[$id] = true; }
        $currentCount = (int)$r['count'];             // updateFromResponse: currentCount = data.count
        $totalCount   = $r['totalCount'];
        $lastId       = $r['lastId'];
        $hasMore      = (bool)$r['hasMore'];

        // Sanity: client seed count must equal server's rendered count.
        $this->assertEquals(count($seen), $currentCount,
            'seed divergence: DOM rows != data.count on initial load');

        $iterations = 0;
        $maxIterations = (int)ceil(count($rows) / 1) + 20; // generous cap; a stall/loop blows past a sane bound
        while ($hasMore) {
            if (++$iterations > $maxIterations) {
                return ['converged' => false, 'reason' => 'INFINITE_LOOP',
                        'currentCount' => $currentCount, 'totalCount' => $totalCount,
                        'iterations' => $iterations, 'lastId' => $lastId];
            }

            $requestedLastId = $lastId;
            $page = $this->keysetPage($rows, $lastId, $dir);
            $this->enqueueCountAndData($rows, $page, $dir, false);
            $r = JsonFormService::getTableHtml($form, null, array_merge($baseOptions, ['lastId' => $lastId]));
            if (!($r['success'] ?? false)) {
                return ['converged' => false, 'reason' => 'SERVER_ERROR:' . ($r['error'] ?? '?'),
                        'currentCount' => $currentCount, 'totalCount' => $totalCount,
                        'iterations' => $iterations, 'lastId' => $lastId];
            }

            $lastId  = $r['lastId'];
            $hasMore = (bool)$r['hasMore'];

            // --- client cursor-did-not-advance net (load() ~line 705) ---
            if ($hasMore && ($lastId === null || $lastId === '' ||
                             (string)$lastId === (string)$requestedLastId)) {
                $hasMore = false;
            }

            // --- append + dedup (load() ~line 759) ---
            $ids = $this->parseIds($r['html']);
            $unique = 0;
            foreach ($ids as $id) {
                if ($id !== '' && isset($seen[$id])) continue;
                if ($id !== '') $seen[$id] = true;
                $unique++;
            }

            // --- all-duplicate stop (load() ~line 780) ---
            if (count($ids) > 0 && $unique === 0) {
                $hasMore = false;
            }

            $currentCount += $unique;

            // --- reached-total net (load() ~line 810) ---
            if ($totalCount !== null && $currentCount >= $totalCount) {
                $hasMore = false;
            }
        }

        return ['converged' => true, 'currentCount' => $currentCount,
                'totalCount' => $totalCount, 'iterations' => $iterations,
                'distinctSeen' => count($seen)];
    }

    /**
     * Enqueue results in the order getTableHtml consumes them.
     * Initial load: COUNT(*) result first, then data page.
     * loadMore: data page only (no COUNT).
     */
    private function enqueueCountAndData(array $rows, array $page, string $dir, bool $initial): void
    {
        if ($initial) {
            // Faithful DB answer: DISTINCT branch => distinct-id count;
            // plain COUNT(*) => raw row count (inflated by duplicates).
            $cnt = $this->countIsDistinct ? $this->distinctCount($rows) : count($rows);
            $this->conn->enqueueResult([['cnt' => $cnt]]);
        }
        $this->conn->enqueueResult($page);
    }

    private function injectPlainForm(string $name, string $order = 'ASC'): void
    {
        TestHarness::injectFormDef($name, ['_json' => [
            'name' => $name, 'title' => 'T', 'table' => 'tblTest',
            'idField' => 'ID', 'database' => 'data', 'orderDirection' => $order,
            'fields' => [['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam']],
        ]]);
    }

    private function injectJoinForm(string $name, string $order = 'ASC'): void
    {
        TestHarness::injectFormDef($name, ['_json' => [
            'name' => $name, 'title' => 'T', 'table' => '',
            'idField' => 'ID', 'database' => 'data', 'orderDirection' => $order,
            'listQuery' => 'SELECT tblTest.ID, tblTest.naam FROM tblTest INNER JOIN tblChild ON tblChild.parent = tblTest.ID',
            'fields' => [['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam']],
        ]]);
    }

    /** A one-to-many list whose duplicates come from a COMMA join — no ` JOIN ` keyword. */
    private function injectCommaJoinForm(string $name, string $order = 'ASC'): void
    {
        TestHarness::injectFormDef($name, ['_json' => [
            'name' => $name, 'title' => 'T', 'table' => '',
            'idField' => 'ID', 'database' => 'data', 'orderDirection' => $order,
            'listQuery' => 'SELECT tblTest.ID, tblTest.naam FROM tblTest, tblChild WHERE tblChild.parent = tblTest.ID',
            'fields' => [['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam']],
        ]]);
    }

    // -----------------------------------------------------------------
    // Cases
    // -----------------------------------------------------------------

    /** Baseline: plain table, 1827 rows, ASC. The exact numbers the user reported. */
    public function testPlain1827ConvergesAsc(): void
    {
        $this->injectPlainForm('p1827', 'ASC');
        $groups = [];
        for ($i = 1; $i <= 1827; $i++) { $groups[$i] = 1; }
        $rows = $this->buildRows($groups);

        $res = $this->runPager('p1827', $rows, 'ASC');
        $this->assertTrue($res['converged'], 'pager did not converge: ' . json_encode($res));
        $this->assertEquals(1827, $res['totalCount']);
        $this->assertEquals(1827, $res['currentCount'], 'numerator must reach the total');
    }

    /** DESC order, 1827 rows — keyset uses `< lastId`. */
    public function testPlain1827ConvergesDesc(): void
    {
        $this->injectPlainForm('p1827d', 'DESC');
        $groups = [];
        for ($i = 1; $i <= 1827; $i++) { $groups[$i] = 1; }
        $rows = $this->buildRows($groups);

        $res = $this->runPager('p1827d', $rows, 'DESC');
        $this->assertTrue($res['converged'], 'DESC pager did not converge: ' . json_encode($res));
        $this->assertEquals(1827, $res['currentCount'], 'DESC numerator must reach the total');
    }

    /**
     * One-to-many JOIN: 1827 distinct records, ~1/3 of them emit 2 join rows.
     * Raw rows exceed 1827; distinct total is 1827. This is the v1.28.119 case
     * — but exercised across the FULL loop, not one batch.
     */
    public function testJoinOneToManyConverges(): void
    {
        $this->injectJoinForm('j1827', 'ASC');
        $groups = [];
        for ($i = 1; $i <= 1827; $i++) { $groups[$i] = ($i % 3 === 0) ? 2 : 1; }
        $rows = $this->buildRows($groups);

        $res = $this->runPager('j1827', $rows, 'ASC', [], true);
        $this->assertTrue($res['converged'], 'JOIN pager did not converge: ' . json_encode($res));
        $this->assertEquals(1827, $res['totalCount'], 'totalCount must be the distinct-id count');
        $this->assertEquals(1827, $res['currentCount'], 'numerator must reach the distinct total');
    }

    /**
     * BOUNDARY: a record whose join-group straddles the pageSize boundary
     * (its physical rows span raw positions 499..503). Proves the keyset
     * cursor doesn't skip or double-count the straddling id.
     */
    public function testGroupStraddlingPageBoundaryConverges(): void
    {
        $this->injectJoinForm('jstraddle', 'ASC');
        $groups = [];
        for ($i = 1; $i <= 700; $i++) {
            // id 499 gets 5 join rows so its group crosses the 500 boundary.
            $groups[$i] = ($i === 499) ? 5 : 1;
        }
        $rows = $this->buildRows($groups);

        $res = $this->runPager('jstraddle', $rows, 'ASC', [], true);
        $this->assertTrue($res['converged'], 'straddle pager did not converge: ' . json_encode($res));
        $this->assertEquals(700, $res['currentCount'], 'straddling group must be counted exactly once');
    }

    /**
     * BOUNDARY: a single id whose join-group is LARGER than a whole page
     * (600 physical rows). A full batch can be entirely that one id — the
     * all-duplicate client stop must NOT fire prematurely and end the pager.
     */
    public function testIdGroupLargerThanPageConverges(): void
    {
        $this->injectJoinForm('jbig', 'ASC');
        $groups = [];
        for ($i = 1; $i <= 400; $i++) { $groups[$i] = 1; }
        $groups[401] = 600;                 // one monster group > pageSize
        for ($i = 402; $i <= 800; $i++) { $groups[$i] = 1; }
        $rows = $this->buildRows($groups);

        $res = $this->runPager('jbig', $rows, 'ASC', [], true);
        $this->assertTrue($res['converged'], 'big-group pager did not converge: ' . json_encode($res));
        $this->assertEquals(800, $res['currentCount'], 'all distinct ids must be reached past a >page group');
    }

    /** Exactly pageSize*k rows (1500) — boundary where the last full batch has no extra row. */
    public function testExactMultipleOfPageConverges(): void
    {
        $this->injectPlainForm('pexact', 'ASC');
        $groups = [];
        for ($i = 1; $i <= 1500; $i++) { $groups[$i] = 1; }
        $rows = $this->buildRows($groups);

        $res = $this->runPager('pexact', $rows, 'ASC');
        $this->assertTrue($res['converged'], 'exact-multiple pager did not converge: ' . json_encode($res));
        $this->assertEquals(1500, $res['currentCount']);
    }

    /**
     * REPRODUCTION of the persistent "records 1-1500 van 1827" stall.
     * The duplicates come from a COMMA join, so `stripos($sql, ' JOIN ')` is
     * FALSE: totalCount is a plain COUNT(*) over the INFLATED join (1827 raw)
     * while the render pass STILL dedupes rows by id (1500 distinct). The
     * numerator can never reach the inflated denominator — the counter sticks
     * at "1-1500 van 1827". The denominator and numerator must use the same
     * unit; here they don't.
     */
    public function testCommaJoinInflatedTotalStallsBelowDenominator(): void
    {
        $this->injectCommaJoinForm('jcomma', 'ASC');
        // 1500 distinct records; every 5th record emits an extra join row →
        // 1500 + 327 = 1827 raw rows. Distinct total the user should see: 1500.
        $groups = [];
        for ($i = 1; $i <= 1500; $i++) {
            // 327 records emit a duplicate join row → 1500 + 327 = 1827 raw.
            $groups[$i] = ($i <= 327) ? 2 : 1;
        }
        $rows = $this->buildRows($groups);
        $this->assertEquals(1827, count($rows), 'fixture must have 1827 raw rows');
        $this->assertEquals(1500, $this->distinctCount($rows), 'fixture must have 1500 distinct records');

        $res = $this->runPager('jcomma', $rows, 'ASC', [], true);
        $this->assertTrue($res['converged'], 'pager must terminate: ' . json_encode($res));
        // The counter must settle at the distinct total, not an inflated join count.
        $this->assertEquals(1500, $res['totalCount'],
            'totalCount must be the distinct-id count even for a comma-join (no JOIN keyword)');
        $this->assertEquals($res['totalCount'], $res['currentCount'],
            'numerator and denominator must match — else "records 1-1500 van 1827" sticks forever');
    }

    /** String (non-numeric) ids — keyset comparison falls to string ordering. */
    public function testStringIdsConverge(): void
    {
        $this->injectPlainForm('pstr', 'ASC');
        // Zero-padded so lexical order is stable and total > 2 pages.
        $groups = [];
        for ($i = 1; $i <= 1200; $i++) { $groups[sprintf('A%05d', $i)] = 1; }
        $rows = $this->buildRows($groups);

        $res = $this->runPager('pstr', $rows, 'ASC');
        $this->assertTrue($res['converged'], 'string-id pager did not converge: ' . json_encode($res));
        $this->assertEquals(1200, $res['currentCount']);
    }

    // -----------------------------------------------------------------
    // Count-query shape: correctness-safe DISTINCT vs cheap COUNT(*).
    // These inspect the SQL the server actually builds — the stub returns
    // whatever count we enqueue, so only the emitted SQL proves the choice.
    // -----------------------------------------------------------------

    /** Return the COUNT query SQL getTableHtml builds for an initial load. */
    private function countSqlFor(string $form): string
    {
        $this->conn->enqueueResult([['cnt' => 1]]); // COUNT(*) result
        $this->conn->enqueueResult([['ID' => 1, 'naam' => 'x']]); // data page
        JsonFormService::getTableHtml($form, null, []);
        return $this->conn->getCalls()[0]['sql'] ?? '';
    }

    /**
     * A SINGLE-TABLE listQuery cannot repeat ids, so it must use the cheap
     * plain COUNT(*) — no DISTINCT subquery scan on every list open. (Large
     * log lists like audit_log take this path.)
     */
    public function testSingleTableListQueryUsesPlainCount(): void
    {
        TestHarness::injectFormDef('lsingle', ['_json' => [
            'name' => 'lsingle', 'title' => 'T', 'table' => '',
            'idField' => 'ID', 'database' => 'data', 'orderDirection' => 'DESC',
            'listQuery' => 'SELECT ID, naam FROM tblTest ORDER BY ID DESC',
            'fields' => [['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam']],
        ]]);

        $sql = $this->countSqlFor('lsingle');
        $this->assertTrue(stripos($sql, 'DISTINCT') === false,
            "single-table listQuery must use a plain COUNT(*), not a DISTINCT scan — got: $sql");
    }

    /** A UNION listQuery can repeat ids across the arms → must COUNT DISTINCT. */
    public function testUnionListQueryUsesDistinctCount(): void
    {
        TestHarness::injectFormDef('lunion', ['_json' => [
            'name' => 'lunion', 'title' => 'T', 'table' => '',
            'idField' => 'ID', 'database' => 'data', 'orderDirection' => 'ASC',
            'listQuery' => 'SELECT ID, naam FROM tblA UNION ALL SELECT ID, naam FROM tblB',
            'fields' => [['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam']],
        ]]);

        $sql = $this->countSqlFor('lunion');
        $this->assertTrue(stripos($sql, 'DISTINCT') !== false,
            "a UNION listQuery can repeat ids; totalCount must COUNT DISTINCT — got: $sql");
    }

    /** A WHERE ... IN (1,2,3) list must NOT be misread as a comma-join. */
    public function testInListIsNotMistakenForCommaJoin(): void
    {
        TestHarness::injectFormDef('linlist', ['_json' => [
            'name' => 'linlist', 'title' => 'T', 'table' => '',
            'idField' => 'ID', 'database' => 'data', 'orderDirection' => 'ASC',
            'listQuery' => 'SELECT ID, naam FROM tblTest WHERE status IN (1, 2, 3)',
            'fields' => [['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam']],
        ]]);

        $sql = $this->countSqlFor('linlist');
        $this->assertTrue(stripos($sql, 'DISTINCT') === false,
            "an IN(...) list is single-source; must not trigger a DISTINCT scan — got: $sql");
    }
}
