<?php
/**
 * <lib-uploader> (library/webcomponents/lib-uploader.js): de standaard-uploader als webcomponent met een generieke naam. Controleert de bron en de geminificeerde versie: het element is
 * geregistreerd, de attributen en gebeurtenissen die in de kop staan bestaan echt, er zijn geen afhankelijkheden (jQuery, FineUploader) en geen afbeeldingen (pictogrammen komen uit het
 * lettertype LinearIcons).
 *
 *   php cma/tests/TestRunner.php LibUploaderTest
 */
require_once __DIR__ . '/TestRunner.php';

class LibUploaderTest extends TestCase
{
    private function bron(string $bestand = 'lib-uploader.js'): string
    {
        return (string) file_get_contents(__DIR__ . '/../../library/webcomponents/' . $bestand);
    }

    public function testElementIsGeregistreerd(): void
    {
        $this->assertTrue(strpos($this->bron(), "customElements.define('lib-uploader'") !== false, 'lib-uploader is geregistreerd');
        $this->assertTrue(strpos($this->bron(), "if (!customElements.get('lib-uploader'))") !== false, 'dubbel laden van het script geeft geen fout');
    }

    public function testDeAttributenUitDeKopBestaan(): void
    {
        $js = $this->bron();
        foreach (['field', 'endpoint', 'path', 'path-extra', 'extensions', 'max-size', 'button-text', 'drop-text', 'type-error', 'multiple', 'link-base', 'removable', 'disabled'] as $attr) {
            $this->assertTrue(strpos($js, "'" . $attr . "'") !== false, "attribuut $attr wordt gelezen");
        }
    }

    public function testDeGebeurtenissen(): void
    {
        $js = $this->bron();
        foreach (['upload-start', 'upload-complete', 'upload-error'] as $gebeurtenis) {
            $this->assertTrue(strpos($js, "'" . $gebeurtenis . "'") !== false, "gebeurtenis $gebeurtenis");
        }
        $this->assertTrue(strpos($js, "append('qqfile'") !== false, 'het bestand gaat als qqfile (het protocol van upload_handler.php)');
    }

    public function testGeenAfhankelijkhedenEnGeenAfbeeldingen(): void
    {
        $js = $this->bron();
        $code = preg_replace('~/\*.*?\*/~s', '', $js);   // de uitleg in de kop noemt jQuery en FineUploader; het gaat om de code
        $this->assertTrue(!preg_match('/\bjQuery\b|\$\(|fineUploader|\bqq\./', $code), 'geen jQuery of FineUploader');
        $css = substr($js, strpos($js, 'LibUploader.CSS'));
        $this->assertTrue(!preg_match('/url\(|\.svg|\.png|\.gif/i', $css), 'de opmaak verwijst naar geen afbeelding of svg');
        $this->assertTrue(strpos($css, 'font-family: "LinearIcons"') !== false && strpos($css, '\\\\e8f4') !== false, 'pictogrammen uit het lettertype LinearIcons');
    }

    public function testDeMeldingBijEenOnbekendTypeIsVriendelijk(): void
    {
        foreach (['lib-uploader.js', 'lib-uploader.min.js', 'lib-fileuploader.js', 'lib-fileuploader.min.js'] as $bestand) {
            $js = $this->bron($bestand);
            $this->assertTrue(strpos($js, 'De volgende bestandstypen worden ondersteund') !== false, "$bestand zegt \"worden ondersteund\"");
            $this->assertTrue(strpos($js, 'zijn toegestaan') === false, "$bestand zegt niet meer \"zijn toegestaan\"");
        }
    }

    public function testDeUploaderIsStandaardVolleBreedte(): void
    {
        foreach (['lib-uploader.js', 'lib-uploader.min.js'] as $bestand) {
            $js = $this->bron($bestand);
            $this->assertTrue(strpos($js, '--lib-uploader-max-width') !== false && strpos($js, '420px') === false, "$bestand: volle breedte, geen vaste 420px");
        }
    }

    public function testMinifiedVersieHeeftHetElement(): void
    {
        $min = $this->bron('lib-uploader.min.js');
        $this->assertTrue($min !== '' && strpos($min, 'lib-uploader') !== false && strlen($min) < strlen($this->bron()), 'lib-uploader.min.js bestaat, is kleiner en registreert het element');
    }
}
