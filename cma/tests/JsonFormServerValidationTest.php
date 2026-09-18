<?php
/**
 * Server-side validation on the JSON save path (FormDataProvider::validateJsonFormData) and
 * the read-only write-skip in saveJsonFormRecord. The client validates too, but the API is
 * callable without it; these are the rules the classic detailsRep_post.asp enforced.
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';
require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/FormDataProvider.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';
require_once dirname(__DIR__) . '/classes/FormDefinition.php';
require_once dirname(__DIR__) . '/classes/SecurityHelper.php';
require_once dirname(__DIR__) . '/classes/Services/Logger.php';
require_once dirname(__DIR__) . '/classes/Services/BaseFormService.php';
require_once dirname(__DIR__) . '/classes/Services/RecordService.php';

use App\Library\Application;
use Cma\FormDataProvider;

class JsonFormServerValidationTest extends TestCase
{
    private StubConnection $conn;

    private const FIELDS = [
        ['name' => 'naam',     'type' => 'textbox',  'caption' => 'Naam', 'required' => true, 'maxLength' => 5],
        ['name' => 'email',    'type' => 'email',    'caption' => 'E-mail'],
        ['name' => 'site',     'type' => 'url',      'caption' => 'Website'],
        ['name' => 'tijd',     'type' => 'time',     'caption' => 'Tijd'],
        ['name' => 'datum',    'type' => 'date',     'caption' => 'Datum'],
        ['name' => 'aantal',   'type' => 'textbox',  'caption' => 'Aantal', 'dataType' => 'number'],
        ['name' => 'dir',      'type' => 'directory','caption' => 'Directory'],
        ['name' => 'stamp',    'type' => 'textbox',  'caption' => 'Stamp', 'readOnly' => true],
        ['name' => 'code',     'type' => 'textbox',  'caption' => 'Code', 'readOnly' => true, 'defaultValue' => 'NIEUW'],
        ['name' => 'actief',   'type' => 'checkbox', 'caption' => 'Actief', 'required' => true],
    ];

    public function setUp(): void
    {
        TestHarness::loginAsAdmin();
        TestHarness::silenceLogger();
        TestHarness::injectFormDef('val_form', ['_json' => [
            'name' => 'val_form', 'title' => 'Val', 'table' => 'tblVal', 'idField' => 'ID', 'database' => 'data',
            'fields' => self::FIELDS,
        ]]);
        $this->conn = StubConnection::create();
        TestHarness::injectConnection('data', $this->conn);
        Application::set('cma_monitoring', '');
    }

    public function tearDown(): void
    {
        TestHarness::reset();
    }

    private function validate(array $data, bool $isNew = true): array
    {
        return FormDataProvider::validateJsonFormData(self::FIELDS, $data, $isNew, null, 'tblVal', 'ID', $isNew ? null : '1', false);
    }

    public function testVerplichtVeldLeegGeeftFout(): void
    {
        $r = $this->validate(['naam' => '']);
        $this->assertEquals('Naam is verplicht', $r['errors']['naam'] ?? '');
        // niet gepost op een NIEUW record is ook een fout, op een bestaand record niet
        $this->assertTrue(isset($this->validate([])['errors']['naam']));
        $this->assertFalse(isset($this->validate([], false)['errors']['naam']));
    }

    public function testCheckboxEnReadonlyWordenNietGevalideerd(): void
    {
        $r = $this->validate(['naam' => 'ok', 'actief' => '', 'stamp' => 'onzin']);
        $this->assertEquals([], $r['errors']);
    }

    public function testEmailMeerdereAdressenGenormaliseerd(): void
    {
        $r = $this->validate(['naam' => 'ok', 'email' => 'a@b.nl;  c@d.nl , e@f.nl']);
        $this->assertEquals([], $r['errors']);
        $this->assertEquals('a@b.nl; c@d.nl; e@f.nl', $r['data']['email']);
        $r = $this->validate(['naam' => 'ok', 'email' => 'a@b.nl; geen-adres']);
        $this->assertStringContainsString('ongeldig e-mailadres (geen-adres)', $r['errors']['email'] ?? '');
    }

    public function testUrlKrijgtHttps(): void
    {
        $r = $this->validate(['naam' => 'ok', 'site' => ' www.rino.nl ']);
        $this->assertEquals('https://www.rino.nl', $r['data']['site']);
        $r = $this->validate(['naam' => 'ok', 'site' => 'http://oud.nl']);
        $this->assertEquals('http://oud.nl', $r['data']['site']);
    }

    public function testTijdFormaat(): void
    {
        $this->assertEquals([], $this->validate(['naam' => 'ok', 'tijd' => '9:15'])['errors']);
        $this->assertTrue(isset($this->validate(['naam' => 'ok', 'tijd' => '915'])['errors']['tijd']));
        $this->assertTrue(isset($this->validate(['naam' => 'ok', 'tijd' => '25:00'])['errors']['tijd']));
    }

    public function testDatumEnGetal(): void
    {
        $this->assertEquals([], $this->validate(['naam' => 'ok', 'datum' => '07-06-2026', 'aantal' => '12,5'])['errors']);
        $this->assertTrue(isset($this->validate(['naam' => 'ok', 'datum' => '31-02-2026'])['errors']['datum']));
        $this->assertTrue(isset($this->validate(['naam' => 'ok', 'aantal' => 'twaalf'])['errors']['aantal']));
    }

    public function testDirectoryGenormaliseerd(): void
    {
        $r = $this->validate(['naam' => 'ok', 'dir' => 'mijn map/2026.']);
        $this->assertEquals('MIJNMAP2026', $r['data']['dir']);
    }

    public function testMaxLengte(): void
    {
        $r = $this->validate(['naam' => 'zeslang']);
        $this->assertEquals('Naam is te lang (maximaal 5 tekens)', $r['errors']['naam'] ?? '');
    }

    public function testSaveWeigertOngeldigeInvoerZonderTeSchrijven(): void
    {
        $result = FormDataProvider::saveJsonFormRecord('val_form', null, ['naam' => '', 'tijd' => 'x']);
        $this->assertFalse($result['success']);
        $this->assertTrue(isset($result['validation']['naam']) && isset($result['validation']['tijd']));
        $writes = array_filter($this->conn->getCalls(), fn($c) => preg_match('/^(INSERT|UPDATE)/i', $c['sql']));
        $this->assertEquals(0, count($writes), 'geen INSERT/UPDATE bij validatiefouten');
    }

    public function testLegeWaardeNietInInsertZodatKolomDefaultGeldt(): void
    {
        $this->conn->enqueueResult([]); $this->conn->enqueueResult([['ID' => 8]]); $this->conn->enqueueResult([['cnt' => 1]]);
        FormDataProvider::saveJsonFormRecord('val_form', null, ['naam' => 'ok', 'email' => '', 'site' => '']);
        $insert = array_values(array_filter($this->conn->getCalls(), fn($c) => str_starts_with($c['sql'], 'INSERT')));
        $this->assertTrue(count($insert) > 0, 'INSERT verwacht');
        $sql = $insert[0]['sql'];
        $this->assertFalse(str_contains($sql, '[email]') || str_contains($sql, '[site]'), 'lege velden niet in INSERT (kolom-default): ' . $sql);
        $this->assertTrue(str_contains($sql, '[naam]'), $sql);

        // UPDATE: een geleegd veld wordt wél NULL
        $this->conn = StubConnection::create(); TestHarness::injectConnection('data', $this->conn);
        $this->conn->enqueueResult([['ID' => '1', 'naam' => 'a', 'email' => 'x@y.nl']]); $this->conn->enqueueResult([]); $this->conn->enqueueResult([['cnt' => 1]]);
        FormDataProvider::saveJsonFormRecord('val_form', '1', ['naam' => 'ok', 'email' => '']);
        $update = array_values(array_filter($this->conn->getCalls(), fn($c) => str_starts_with($c['sql'], 'UPDATE')));
        $this->assertTrue(str_contains($update[0]['sql'], '[email] = NULL'), 'geleegd veld -> NULL bij UPDATE: ' . $update[0]['sql']);
    }

    public function testReadonlyNietGeschrevenBehalveDefaultOpNieuw(): void
    {
        // INSERT: 'stamp' (readonly zonder default) niet, 'code' met definitie-default 'NIEUW'
        $this->conn->enqueueResult([]);                 // INSERT
        $this->conn->enqueueResult([['ID' => 7]]);      // @@IDENTITY / lookup
        $this->conn->enqueueResult([['cnt' => 1]]);
        FormDataProvider::saveJsonFormRecord('val_form', null, ['naam' => 'ok', 'stamp' => 'gepost', 'code' => 'gepost']);
        $insert = array_values(array_filter($this->conn->getCalls(), fn($c) => str_starts_with($c['sql'], 'INSERT')));
        $this->assertTrue(count($insert) > 0, 'INSERT verwacht');
        $sql = $insert[0]['sql'];
        $this->assertFalse(str_contains($sql, '[stamp]'), 'readonly zonder default niet in INSERT: ' . $sql);
        $this->assertTrue(str_contains($sql, '[code]') && str_contains($sql, 'NIEUW') && !str_contains($sql, 'gepost'), 'readonly default uit definitie: ' . $sql);

        // UPDATE: beide readonly-velden niet
        $this->conn = StubConnection::create();
        TestHarness::injectConnection('data', $this->conn);
        $this->conn->enqueueResult([['ID' => '1', 'naam' => 'a', 'stamp' => 'x', 'code' => 'y']]);
        $this->conn->enqueueResult([]);
        $this->conn->enqueueResult([['cnt' => 1]]);
        FormDataProvider::saveJsonFormRecord('val_form', '1', ['naam' => 'ok', 'stamp' => 'gepost', 'code' => 'gepost']);
        $update = array_values(array_filter($this->conn->getCalls(), fn($c) => str_starts_with($c['sql'], 'UPDATE')));
        $this->assertTrue(count($update) > 0, 'UPDATE verwacht');
        $this->assertFalse(str_contains($update[0]['sql'], '[stamp]') || str_contains($update[0]['sql'], '[code]'), 'readonly niet in UPDATE: ' . $update[0]['sql']);
    }
}
