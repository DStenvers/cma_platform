<?php
/**
 * De logreader toont de NIEUWSTE regels. Vroeger las hij vanaf het begin van het laatste stuk van het bestand en stopte na drie keer het
 * aantal gevraagde regels: een log met meer regels toonde oude regels (het deploy-log liet alleen 25 november zien).
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../tools/logreader_bronnen.inc';

class LogreaderLaatsteRegelsTest extends TestCase
{
    private string $pad;

    public function setUp(): void
    {
        $this->pad = sys_get_temp_dir() . '/logtail-' . bin2hex(random_bytes(6)) . '.log';
    }

    public function tearDown(): void
    {
        @unlink($this->pad);
    }

    private function schrijf(int $n): void
    {
        $s = '';
        for ($i = 1; $i <= $n; $i++) {
            $s .= sprintf("[2026-11-%02d 10:00:00] regel %d%s\n", 1 + intdiv($i, 100), $i, $i % 7 === 0 ? ' composer' : '');
        }
        file_put_contents($this->pad, $s);
    }

    public function testGeeftDeLaatsteRegelsOudsteEerst(): void
    {
        $this->schrijf(1000);
        $r = LogreaderLaatsteRegels($this->pad, 100);
        $this->assertCount(100, $r);
        $this->assertStringContainsString('regel 901', $r[0]);
        $this->assertStringContainsString('regel 1000', $r[99], 'de nieuwste regel hoort erbij (vroeger: regel 201-300)');
    }

    public function testKleinBestandGeeftAlleRegels(): void
    {
        $this->schrijf(5);
        $this->assertCount(5, LogreaderLaatsteRegels($this->pad, 100));
    }

    public function testFilterZoektInHetHeleGelezenStukEnGeeftDeLaatsteTreffers(): void
    {
        $this->schrijf(1000);
        $r = LogreaderLaatsteRegels($this->pad, 3, 'composer');
        $this->assertCount(3, $r);
        $this->assertStringContainsString('regel 980', $r[0]);
        $this->assertStringContainsString('regel 994', $r[2]);
        $this->assertCount(0, LogreaderLaatsteRegels($this->pad, 10, 'bestaatniet'));
    }

    public function testBeperktTotDeLaatsteBytesEnLaatEenAangesnedenRegelWeg(): void
    {
        $this->schrijf(1000);
        $r = LogreaderLaatsteRegels($this->pad, 500, '', 2000);
        $this->assertLessThan(500, count($r));
        foreach ($r as $regel) {
            $this->assertTrue(strpos($regel, '[2026-11-') === 0, 'geen halve regel: ' . $regel);
        }
        $this->assertStringContainsString('regel 1000', $r[count($r) - 1]);
    }

    public function testLeegOfOntbrekendBestand(): void
    {
        $this->assertSame([], LogreaderLaatsteRegels($this->pad, 10));
        file_put_contents($this->pad, '');
        $this->assertSame([], LogreaderLaatsteRegels($this->pad, 10));
        file_put_contents($this->pad, "\n\n  \n");
        $this->assertSame([], LogreaderLaatsteRegels($this->pad, 10));
    }
}
