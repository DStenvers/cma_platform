<?php
/**
 * Tests for App\Library\Request::currentDomain().
 *
 * Run with: php tests/TestRunner.php RequestCurrentDomainTest
 *
 * Focus: the non-standard-port handling used when building custom-button URLs —
 * the port must be appended for anything other than 80 (http) / 443 (https),
 * and never doubled up if SERVER_NAME already carries one.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Request;

class RequestCurrentDomainTest extends TestCase
{
    private array $savedServer = [];

    public function setUp(): void
    {
        $this->savedServer = $_SERVER;
    }

    public function tearDown(): void
    {
        $_SERVER = $this->savedServer;
    }

    private function domain(string $https, string $name, $port): string
    {
        $_SERVER['HTTPS'] = $https;
        $_SERVER['SERVER_NAME'] = $name;
        $_SERVER['SERVER_PORT'] = $port;
        return Request::currentDomain();
    }

    public function testStandardHttpPortIsOmitted(): void
    {
        $this->assertEquals('http://example.com', $this->domain('', 'example.com', '80'));
    }

    public function testStandardHttpsPortIsOmitted(): void
    {
        $this->assertEquals('https://example.com', $this->domain('on', 'example.com', '443'));
    }

    public function testNonStandardHttpPortIsAppended(): void
    {
        $this->assertEquals('http://example.com:8080', $this->domain('', 'example.com', '8080'));
    }

    public function testNonStandardHttpsPortIsAppended(): void
    {
        $this->assertEquals('https://example.com:8443', $this->domain('on', 'example.com', '8443'));
    }

    public function testPortIsNotDoubledWhenServerNameHasOne(): void
    {
        $this->assertEquals('http://example.com:8080', $this->domain('', 'example.com:8080', '8080'));
    }

    public function testMissingPortFallsBackToBareHost(): void
    {
        $this->assertEquals('http://example.com', $this->domain('', 'example.com', ''));
    }
}
