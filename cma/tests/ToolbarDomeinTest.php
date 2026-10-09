<?php
/**
 * ToolbarDomeinTest — [domein] in extra formulierknoppen: scheme://[domein] wordt
 * protocol + host + poort van het huidige verzoek (zoals toolbar.inc via
 * lib_CurrentDomain), zodat knoppen ook op een niet-standaard poort werken.
 *
 *   php cma/tests/TestRunner.php ToolbarDomeinTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/ToolbarHelper.php';

use Cma\ToolbarHelper;

class ToolbarDomeinTest extends TestCase
{
    private array $saved = [];

    private function server(array $vars): void
    {
        foreach (['SERVER_NAME', 'SERVER_PORT', 'HTTPS', 'HTTP_X_FORWARDED_PROTO'] as $k) {
            $this->saved[$k] = $_SERVER[$k] ?? null;
            unset($_SERVER[$k]);
        }
        foreach ($vars as $k => $v) {
            $_SERVER[$k] = $v;
        }
    }

    private function restore(): void
    {
        foreach ($this->saved as $k => $v) {
            if ($v === null) { unset($_SERVER[$k]); } else { $_SERVER[$k] = $v; }
        }
    }

    public function testLokalePoortGaatMee(): void
    {
        $this->server(['SERVER_NAME' => 'localhost', 'SERVER_PORT' => '52779']);
        try {
            $this->assertSame(
                'http://localhost:52779/cma/details.asp?id=7',
                ToolbarHelper::makeLink('https://[domein]/cma/details.asp?id=[ID]', '7', '', '')
            );
            $this->assertSame(
                'http://localhost:52779/x',
                ToolbarHelper::makeLink('http://[domein]/x', '7', '', '')
            );
        } finally {
            $this->restore();
        }
    }

    public function testProductieZonderPoortEnGeenDubbelProtocol(): void
    {
        $this->server(['SERVER_NAME' => 'mijn.rino.nl', 'SERVER_PORT' => '443', 'HTTPS' => 'on']);
        try {
            $this->assertSame(
                'https://mijn.rino.nl/a?g=abc',
                ToolbarHelper::makeLink('https://[domein]/a?g=[guid]', '1', 'abc', '')
            );
        } finally {
            $this->restore();
        }
    }

    public function testKaalDomeinIsAlleenDeHost(): void
    {
        $this->server(['SERVER_NAME' => 'localhost', 'SERVER_PORT' => '8080']);
        try {
            $this->assertSame('host=localhost', ToolbarHelper::resolveDomain('host=[domein]'));
            $this->assertSame('geen placeholder', ToolbarHelper::resolveDomain('geen placeholder'));
        } finally {
            $this->restore();
        }
    }
}
