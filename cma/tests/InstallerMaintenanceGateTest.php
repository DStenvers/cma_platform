<?php
/**
 * De Installer werkt de onderhoudspoort in een bestaand _bootstrap.php bij —
 * maar uitsluitend het ongewijzigde platformblok van een vorige generatie.
 *
 * _bootstrap.php is copy-if-absent, dus een verbeterde poort bereikte geen
 * enkele bestaande site: precies de reparatie "tijdens een deploy hoort ook
 * /cma/ het onderhoudsscherm te tonen" bleef overal uit. De middenweg die dit
 * test: het blok gaat mee wanneer het daar byte-voor-byte van het platform is,
 * en van álles wat de site zelf heeft aangepast blijven we af.
 */

use App\Library\Installer;

class InstallerMaintenanceGateTest extends TestCase
{
    /** Het blok zoals de vorige template-generatie het neerzette. */
    private function oudBlok(): string
    {
        return "    \$uri = (string) (\$_SERVER['REQUEST_URI'] ?? '');\n"
             . "    if (strpos(\$uri, '/cma/') === 0\n"
             . "        || preg_match('#(^|/)(deploy|deploy_status)\\\\.php(\\\\?|\$)#', \$uri)) {\n"
             . "        return;\n"
             . "    }\n";
    }

    public function testOngewijzigdOudBlokWordtBijgewerkt(): void
    {
        $bron = "<?php\n// aanhef\n" . $this->oudBlok() . "// rest van de site\n";
        $nieuw = Installer::upgradeMaintenanceGate($bron);
        $this->assertNotNull($nieuw);
        // De nieuwe poort staat erin: /cma/ alleen nog achter een handmatige vlag.
        $this->assertTrue(strpos($nieuw, "\$manual && strpos(\$uri, '/cma/') === 0") !== false);
        // En de onvoorwaardelijke doorlaat is weg.
        $this->assertTrue(strpos($nieuw, "if (strpos(\$uri, '/cma/') === 0\n") === false);
        // Wat om het blok heen stond is onaangeraakt.
        $this->assertTrue(strpos($nieuw, '// aanhef') !== false);
        $this->assertTrue(strpos($nieuw, '// rest van de site') !== false);
    }

    public function testHetEchteOudeTemplateWordtHerkend(): void
    {
        // Niet een nagebouwd fixture maar de template zoals hij werkelijk
        // verscheept is — als deze test breekt, is de aanname "het oude blok
        // stond zó op de sites" zelf onwaar geworden.
        $oud = shell_exec('git -C ' . escapeshellarg(dirname(__DIR__, 2))
            . ' show v1.29.254:templates/_bootstrap.php.template 2>/dev/null');
        if (!is_string($oud) || $oud === '') {
            $this->markTestSkipped('geen git-historie beschikbaar');
            return;
        }
        $this->assertNotNull(Installer::upgradeMaintenanceGate($oud));
    }

    public function testAangepastBlokBlijftMetRust(): void
    {
        // Eén site-eigen aanpassing — een extra uitzondering — en we blijven eraf.
        $aangepast = str_replace("'/cma/'", "'/cma/', '/intranet/'", $this->oudBlok());
        $this->assertNull(Installer::upgradeMaintenanceGate("<?php\n" . $aangepast));
    }

    public function testNieuweTemplateIsAlGoedEnBlijftZo(): void
    {
        // Idempotent: op de huidige template valt niets te vervangen, dus een
        // tweede composer-run schrijft niet opnieuw.
        $huidig = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/_bootstrap.php.template');
        $this->assertNull(Installer::upgradeMaintenanceGate($huidig));
    }

    public function testLegeBronDoetNiets(): void
    {
        $this->assertNull(Installer::upgradeMaintenanceGate(''));
    }
}
