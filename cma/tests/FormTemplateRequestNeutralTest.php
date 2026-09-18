<?php
/**
 * FormTemplateRequestNeutralTest — het formuliersjabloon wordt per formulier +
 * toegangsniveau gecachet (APCu + bestand) en aan iedere aanvraag geserveerd.
 * Alles wat van de aanvraag afhangt (popup, is-creating/has-record, parentField)
 * hoort dus NIET in het sjabloon maar wordt door form.php / de controller per
 * aanvraag toegevoegd. Dit was mis: wie een formulier het eerst in een popup
 * opende, bakte body.popup + mode-detail + __ParentField voor iedereen in.
 *
 *   php cma/tests/TestRunner.php FormTemplateRequestNeutralTest
 */

require_once __DIR__ . '/TestRunner.php';

class FormTemplateRequestNeutralTest extends TestCase
{
    private function bron(): string
    {
        return file_get_contents(dirname(__DIR__) . '/classes/FormTemplate.php');
    }

    private function functie(string $naam): string
    {
        $bron = $this->bron();
        $start = strpos($bron, 'function ' . $naam . '(');
        $this->assertTrue($start !== false, "$naam niet gevonden");
        $eind = strpos($bron, "\n    }\n", $start);
        return substr($bron, $start, $eind - $start);
    }

    public function testGenerateBodyLeestNietsUitDeAanvraag(): void
    {
        $body = $this->functie('generateBody');
        $this->assertStringNotContainsString('Request::', $body);
        $this->assertStringNotContainsString('Cookie::', $body);
    }

    public function testParentVeldenStaanLeegInHetSjabloon(): void
    {
        $bron = $this->bron();
        $this->assertStringContainsString('name="__ParentField" value=""', $bron);
        $this->assertStringContainsString('name="__ParentValue" value=""', $bron);
        $this->assertStringNotContainsString("Request::query('parentField'", $bron);
    }

    public function testFormPhpZetPopupEnDetailPerAanvraag(): void
    {
        $form = file_get_contents(dirname(__DIR__) . '/form.php');
        $this->assertStringContainsString("\$isPopupRequest = \$parentID !== '' || \$parentField !== ''", $form);
        $this->assertStringContainsString("\$bodyClasses[] = 'popup';", $form);
    }

    public function testControllerVultParentVeldenUitDataAttributen(): void
    {
        $js = file_get_contents(dirname(__DIR__) . '/assets/js/form-controller.js');
        $this->assertStringContainsString('input[name="__ParentField"]', $js);
        $this->assertStringContainsString('pf.value = this.parentField', $js);
    }
}
