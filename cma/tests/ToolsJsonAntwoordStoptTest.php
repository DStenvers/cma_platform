<?php
/**
 * Een tool die met Response::json() antwoordt moet daarna stoppen: Response::json() doet dat zelf niet. Zonder exit volgt de hele
 * paginaweergave op de JSON en kan de browser het antwoord niet lezen (Deployment forceren meldde "Starten mislukt" terwijl de deploy
 * al was gestart).
 */
require_once __DIR__ . '/TestRunner.php';

class ToolsJsonAntwoordStoptTest extends TestCase
{
    public function testElkeJsonAntwoordInEenToolEindigtMetExitOfReturn(): void
    {
        $fout = [];
        foreach (glob(__DIR__ . '/../tools/*.php') as $bestand) {
            $s = (string) file_get_contents($bestand);
            $n = preg_match_all('/Response::json\(/', $s, $m, PREG_OFFSET_CAPTURE);
            for ($k = 0; $k < $n; $k++) {
                $i = $m[0][$k][1] + strlen('Response::json(');
                for ($d = 1; $d > 0 && $i < strlen($s); $i++) {
                    $d += ($s[$i] === '(') - ($s[$i] === ')');
                }
                $rest = substr($s, $i, 120);
                if (!preg_match('/^\s*;\s*(\}\s*)?(exit|return|die)\b/', $rest) && !preg_match('/^\s*;\s*\n\s*(exit|return|die)\b/', $rest)) {
                    $fout[] = basename($bestand) . ':' . (substr_count(substr($s, 0, $m[0][$k][1]), "\n") + 1);
                }
            }
        }
        $this->assertSame([], $fout, 'Response::json() zonder exit/return erna: ' . implode(', ', $fout));
    }
}
