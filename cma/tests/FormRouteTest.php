<?php
/**
 * Tests for Cma\FormRoute — the canonical inbound URL contract.
 *
 * Run with: php tests/TestRunner.php FormRouteTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/FormRoute.php';

use Cma\FormRoute;

class FormRouteTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    /** The bug this fixes: /cma/form/<form>/<id> arrives as formID=<id>. */
    public function testTopLevelRecordFromFormIDSlug(): void
    {
        $_GET = ['form' => 'steensoorten_producten', 'formID' => '4984'];
        $route = FormRoute::fromRequest();

        $this->assertEquals('steensoorten_producten', $route->form);
        $this->assertEquals('4984', $route->recordId);
        $this->assertFalse($route->isNew);
        $this->assertNull($route->parentId);
    }

    public function testNewRecordFromNewSlug(): void
    {
        $_GET = ['form' => 'opleidingen', 'formID' => 'new'];
        $route = FormRoute::fromRequest();

        $this->assertEquals('opleidingen', $route->form);
        $this->assertNull($route->recordId);
        $this->assertTrue($route->isNew);
    }

    public function testNewRecordFromNewFlag(): void
    {
        $_GET = ['form' => 'opleidingen', 'New' => 'Y'];
        $route = FormRoute::fromRequest();

        $this->assertTrue($route->isNew);
        $this->assertNull($route->recordId);
    }

    public function testExplicitIdWinsOverFormIDSlug(): void
    {
        $_GET = ['form' => 'x', 'id' => '7', 'formID' => '4984'];
        $route = FormRoute::fromRequest();

        $this->assertEquals('7', $route->recordId);
    }

    public function testCapitalIDIsRead(): void
    {
        $_GET = ['form' => 'x', 'ID' => '12'];
        $route = FormRoute::fromRequest();

        $this->assertEquals('12', $route->recordId);
    }

    /** ID=0 is the "add without subforms" mode — must survive as '0', not null. */
    public function testZeroRecordIdSurvives(): void
    {
        $_GET = ['form' => 'x', 'formID' => '0'];
        $route = FormRoute::fromRequest();

        $this->assertEquals('0', $route->recordId);
        $this->assertFalse($route->isNew);
    }

    public function testAlphanumericRecordId(): void
    {
        $_GET = ['form' => 'x', 'formID' => 'C47'];
        $route = FormRoute::fromRequest();

        $this->assertEquals('C47', $route->recordId);
    }

    public function testCopyMode(): void
    {
        $_GET = ['form' => 'x', 'id' => '5', 'copy' => 'Y'];
        $route = FormRoute::fromRequest();

        $this->assertEquals('5', $route->recordId);
        $this->assertTrue($route->isCopy);
    }

    public function testPopupSubformRecord(): void
    {
        $_GET = [
            'form' => 'steensoorten',
            'formID' => '123',
            'popup' => 'producten',
            'popupID' => '456',
        ];
        $route = FormRoute::fromRequest();

        $this->assertEquals('producten', $route->form);
        $this->assertEquals('456', $route->recordId);
        $this->assertEquals('123', $route->parentId);
        $this->assertFalse($route->isNew);
    }

    public function testPopupSubformNew(): void
    {
        $_GET = [
            'form' => 'steensoorten',
            'formID' => '123',
            'popup' => 'producten',
            'popupID' => 'new',
        ];
        $route = FormRoute::fromRequest();

        $this->assertEquals('producten', $route->form);
        $this->assertNull($route->recordId);
        $this->assertEquals('123', $route->parentId);
        $this->assertTrue($route->isNew);
    }

    public function testExplicitParentIdOverridesPopupParent(): void
    {
        $_GET = [
            'form' => 'p',
            'formID' => '1',
            'popup' => 's',
            'popupID' => '2',
            'parentID' => '9',
            'parentField' => 'parentId',
        ];
        $route = FormRoute::fromRequest();

        $this->assertEquals('9', $route->parentId);
        $this->assertEquals('parentId', $route->parentField);
    }

    public function testViewPassthrough(): void
    {
        $_GET = ['form' => 'x', 'view' => 'table'];
        $route = FormRoute::fromRequest();

        $this->assertEquals('table', $route->view);
    }

    public function testEmptyFormWhenNoParams(): void
    {
        $_GET = [];
        $route = FormRoute::fromRequest();

        $this->assertEquals('', $route->form);
        $this->assertNull($route->recordId);
        $this->assertFalse($route->isNew);
    }
}
