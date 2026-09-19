<?php
/**
 * FormTemplate::headScriptsForShell — the sidebar shell gets the head's
 * config scripts once, and never the body's controller init (that one is
 * already inside the body content the shell inserts).
 *
 *   php cma/tests/TestRunner.php HeadScriptsForShellTest
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/FormTemplate.php';

class HeadScriptsForShellTest extends TestCase
{
    private function template(): string
    {
        return '<!DOCTYPE html><html><head><title>x</title>'
            . '<script>window.CMA = window.CMA || {}; CMA.formConfig = {"sourceFormId":68};</script>'
            . '<script src="/cma/ckeditor/ckeditor.js"></script>'
            . '<script>(function(){ var unrelated = 1; })();</script>'
            . '</head><body class="cma-form">'
            . '<div class="form-layout"></div>'
            . '<script>(function() { function initForm() { formLayout._cmaController = new CMA.FormController(68, CMA.formConfig); } initForm(); })();</script>'
            . '</body></html>';
    }

    public function testOnlyTheHeadConfigScriptComesOut(): void
    {
        $scripts = \Cma\FormTemplate::headScriptsForShell($this->template());
        $this->assertCount(1, $scripts);
        $this->assertStringContainsString('CMA.formConfig = ', $scripts[0]);
    }

    public function testTheBodyInitScriptIsNotRepeated(): void
    {
        foreach (\Cma\FormTemplate::headScriptsForShell($this->template()) as $script) {
            $this->assertFalse(strpos($script, 'initForm') !== false, 'the body init script belongs to the body content');
        }
    }

    public function testATemplateWithoutBodyIsSearchedWhole(): void
    {
        $scripts = \Cma\FormTemplate::headScriptsForShell('<script>window.CMA = {};</script><script>var x;</script>');
        $this->assertCount(1, $scripts);
    }
}
