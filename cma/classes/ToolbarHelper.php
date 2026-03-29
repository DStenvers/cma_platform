<?php

namespace Cma;

use App\Library\Application;
use App\Library\Cookie;
use App\Library\Request;

/**
 * CMA Toolbar Helper
 *
 * Provides toolbar/button rendering functionality for the CMA interface.
 * Uses modern div-based toolbar layout (not table-based).
 */
class ToolbarHelper
{
    /**
     * Write the toolbar JavaScript include
     * @deprecated Use cma_js() instead, which already includes cma.js
     * This method now does nothing - kept for backward compatibility.
     * Pages using cma_html_header() already load cma.js via cma_js().
     */
    public static function writeJS(): void
    {
        // No longer outputs anything - cma_html_header() handles JS loading
        // The method is kept to avoid breaking existing code that calls it
    }

    /**
     * Write the standard CMA CSS link tag
     * Use this for pages that don't use cma_html_header()
     */
    public static function writeCSS(): void
    {
        cma_css();
    }

    /**
     * Write both CSS and JS includes
     * Convenience method for pages that build their own HTML structure
     * Note: cma_js() already includes cma.js, so no need to call writeJS()
     */
    public static function writeAssets(): void
    {
        self::writeCSS();
        cma_js();
    }

    /**
     * Render record action buttons (new, save, delete, etc.)
     */
    public static function recordButtons(string $formId, bool $newAllowed, bool $saveAllowed, bool $saveCloseAllowed, bool $deleteAllowed, bool $cancelAllowed, bool $copyAllowed): void
    {
        self::newButton($formId, $newAllowed);
        if ($saveCloseAllowed) {
            self::saveCloseButton($saveAllowed, Request::query('ID', '') === '');
        }
        if ($saveAllowed) {
            self::saveButton($saveAllowed, Request::query('ID', '') === '');
        }
        if ($copyAllowed) {
            self::copyButton($copyAllowed);
        }
        if ($deleteAllowed) {
            self::deleteButton($deleteAllowed);
        }
        self::cancelButton($cancelAllowed);
    }

    /**
     * Render tree view expand/collapse buttons
     */
    public static function treeButtons(): void
    {
        self::button('javascript:fExpandAll()', 'lnr-expandall', true, '', $lang_tb_expand ?? 'Uitklappen', 'btn_expand');
        self::button('javascript:fCollapseAll()', 'lnr-collapseall', true, '', $lang_tb_collapse ?? 'Inklappen', 'btn_collapse');
    }

    /**
     * Start toolbar container
     */
    public static function start(bool $forceTop = false): void
    {
        $class = 'toolbar';
        if ($forceTop) {
            $class .= ' toolbar-top';
        }
        echo PHP_EOL . '<div id="toolbar" class="' . $class . '">' . PHP_EOL;
        echo '<div class="toolbar-left">' . PHP_EOL;
    }

    /**
     * Render toolbar separator
     */
    public static function separator(): void
    {
        echo '<span class="tb-sep"></span>' . PHP_EOL;
    }

    /**
     * End toolbar left section and start right section
     */
    public static function startRight(): void
    {
        echo '</div>' . PHP_EOL;
        echo '<div class="toolbar-right">' . PHP_EOL;
    }

    /**
     * End toolbar container
     */
    public static function end(bool $scroll = false): void
    {
        echo '</div>' . PHP_EOL;
        echo '</div>' . PHP_EOL;
    }

    /**
     * Render a button with linearicon
     *
     * @param string $href Link URL or javascript:
     * @param string $icon Linearicon class (e.g., 'lnr-save')
     * @param bool $enabled Whether the button is enabled
     * @param string $text Optional text label
     * @param string $title Tooltip title
     * @param string $id Optional element ID
     * @param string $dataAction Optional data-action attribute
     */
    public static function button(string $href, string $icon, bool $enabled, string $text = '', string $title = '', string $id = '', string $dataAction = ''): void
    {
        // Legacy compatibility: detect old-style HTML parameters
        if (strpos($href, '<a ') !== false || strpos($href, '<a>') !== false) {
            // Old-style call with HTML in href - delegate to linearButton
            self::linearButton($href, $icon, $enabled, $text);
            return;
        }
        if (strpos($icon, '<img') !== false) {
            // Old-style call with img tag in icon - try to extract src and use imageButton
            $imageSrc = '';
            if (preg_match('/src=[\'"]?([^\s\'"]+)[\'"]?/', $icon, $matches)) {
                $imageSrc = $matches[1];
            }
            $extractedTitle = '';
            if (preg_match('/(?:alt|title)=[\'"]([^"\']+)[\'"]/', $icon, $matches)) {
                $extractedTitle = $matches[1];
            }
            // Extract URL from href parameter (old format: '<a href=...>')
            $url = '';
            if (preg_match('/href=[\'"]([^"\']+)[\'"]/', $href, $matches)) {
                $url = $matches[1];
            } elseif (preg_match('/href=([^\s>]+)/', $href, $matches)) {
                $url = $matches[1];
            }
            if ($imageSrc !== '' && $url !== '') {
                self::imageButton($url, $imageSrc, $enabled, $text, $extractedTitle ?: $title);
                return;
            }
        }
        // Replace environment placeholder
        if (stripos($href, '[omgeving]') !== false) {
            switch (strtoupper(Application::get('omgeving', ''))) {
                case 'T':
                    $href = str_replace('[omgeving]', 'test', $href);
                    break;
                case 'A':
                    $href = str_replace('[omgeving]', 'acceptatie', $href);
                    break;
                case 'P':
                    $href = str_replace('[omgeving]', 'www', $href);
                    break;
            }
            $href = str_ireplace('www.onderwijsportaal.', 'onderwijsportaal.', $href);
        }
        // Replace domain placeholder
        if (stripos($href, '[domein]') !== false) {
            $href = str_ireplace('[domein]', Request::server('SERVER_NAME', ''), $href);
        }
        // Match protocol to current request (avoid https on localhost/IP)
        $isHttps = (Request::server('HTTPS', '') === 'on') || (Request::server('HTTP_X_FORWARDED_PROTO', '') === 'https');
        if (!$isHttps && stripos($href, 'https:') !== false) {
            $href = str_ireplace('https://', 'http://', $href);
        }

        $btnClass = 'tb-btn';
        if (!$enabled) {
            $btnClass .= ' disabled';
        }

        $idAttr = $id !== '' ? ' id="' . htmlspecialchars($id) . '"' : '';
        $dataAttr = $dataAction !== '' ? ' data-action="' . htmlspecialchars($dataAction) . '"' : '';
        $target = '';
        if (stripos($href, 'http:') !== false || stripos($href, 'https:') !== false) {
            $target = ' target="_blank"';
        }

        if ($title !== '' && $text !== '' && strcasecmp($title, $text) === 0) {
            // Tooltip duplicates visible text: use data-tooltip (CSS-only, hidden when text visible)
            $titleAttr = ' data-tooltip="' . htmlspecialchars($title) . '"';
        } else {
            $titleAttr = $title !== '' ? ' title="' . htmlspecialchars($title) . '"' : '';
        }
        echo '<span class="' . $btnClass . '"' . $idAttr . $titleAttr . '>';
        if ($enabled) {
            echo '<a href="' . htmlspecialchars($href) . '"' . $target . $dataAttr . '>';
        }
        echo '<span class="lnr ' . htmlspecialchars($icon) . '"></span>';
        if ($text !== '') {
            echo '<span class="tb-btn-text">' . htmlspecialchars($text) . '</span>';
        }
        if ($enabled) {
            echo '</a>';
        }
        echo '</span>' . PHP_EOL;
    }

    /**
     * Render a button with custom image
     */
    public static function imageButton(string $href, string $imageSrc, bool $enabled, string $text = '', string $title = ''): void
    {
        // Replace environment placeholder
        if (stripos($href, '[omgeving]') !== false) {
            switch (strtoupper(Application::get('omgeving', ''))) {
                case 'T':
                    $href = str_replace('[omgeving]', 'test', $href);
                    break;
                case 'A':
                    $href = str_replace('[omgeving]', 'acceptatie', $href);
                    break;
                case 'P':
                    $href = str_replace('[omgeving]', 'www', $href);
                    break;
            }
            $href = str_ireplace('www.onderwijsportaal.', 'onderwijsportaal.', $href);
        }
        // Replace domain placeholder
        if (stripos($href, '[domein]') !== false) {
            $href = str_ireplace('[domein]', Request::server('SERVER_NAME', ''), $href);
        }
        // Match protocol to current request (avoid https on localhost/IP)
        $isHttps = (Request::server('HTTPS', '') === 'on') || (Request::server('HTTP_X_FORWARDED_PROTO', '') === 'https');
        if (!$isHttps && stripos($href, 'https:') !== false) {
            $href = str_ireplace('https://', 'http://', $href);
        }

        $btnClass = 'tb-btn';
        if (!$enabled) {
            $btnClass .= ' disabled';
        }

        $target = '';
        if (stripos($href, 'http:') !== false || stripos($href, 'https:') !== false) {
            $target = ' target="_blank"';
        }

        // Check if this is an icon from the icons folder - use filter inversion for dark mode
        $isThemeIcon = (strpos($imageSrc, 'images/icons/') !== false && str_ends_with($imageSrc, '.png'));
        $imgClass = 'tb-img';
        if ($isThemeIcon) {
            $imgClass .= ' theme-icon-png';  // CSS filter invert in dark mode
        }

        echo '<span class="' . $btnClass . '">';
        if ($enabled) {
            if (stripos($href, 'javascript:') === 0) {
                $jsCode = substr($href, 11);
                echo '<a href="#" onclick="' . htmlspecialchars($jsCode) . '; return false;">';
            } else {
                echo '<a href="' . htmlspecialchars($href) . '"' . $target . '>';
            }
        }
        echo '<img src="' . htmlspecialchars($imageSrc) . '" class="' . $imgClass . '"';
        if ($title !== '') {
            echo ' title="' . htmlspecialchars($title) . '"';
        }
        echo '>';
        if ($text !== '') {
            echo '<span class="tb-btn-text">' . htmlspecialchars($text) . '</span>';
        }
        if ($enabled) {
            echo '</a>';
        }
        echo '</span>' . PHP_EOL;
    }

    /**
     * Render back button
     */
    public static function backButton(): void
    {
        self::button('javascript:history.go(-1)', 'lnr-back', true, $lang_back ?? 'Terug', $lang_tb_back ?? 'Terug', 'toolbar_back');
    }

    /**
     * Render new record button
     */
    public static function newButton(string $formId, bool $enabled): void
    {
        $script = Request::scriptName();
        if (stripos(strtolower($script), 'detailsrepnew.php') !== false) {
            $script = 'form.php?FormID=' . $formId . '&New=Y';
        } elseif (stripos(strtolower($script), 'details.php') !== false) {
            $script = 'form.php?FormID=' . $formId . '&New=Y';
        } else {
            $script = Request::addToURL($script, 'FormID', $formId);
            $script = Request::addToURL($script, 'New', 'Y');
        }
        $script = Request::addToURL($script, 'parentID', Request::query('parentID', ''));
        $script = Request::addToURL($script, 'parentField', Request::query('parentField', ''));
        self::button($script, 'lnr-file-add', $enabled, $lang_add ?? 'Nieuw', $lang_tb_add ?? 'Nieuw record', 'toolbar_new');
    }

    /**
     * Render cancel button
     */
    public static function cancelButton(bool $enabled): void
    {
        $onclick = Request::query('parentField', '') !== '' ? 'javascript:window.parent.lib_OpenWindowCenteredClose()' : 'javascript:window.location=window.location';
        self::button($onclick, 'lnr-cancel', $enabled, $lang_wizard_cancel ?? 'Annuleer', $lang_tb_undo ?? 'Annuleren', 'toolbar_cancel');
    }

    /**
     * Render delete button
     */
    public static function deleteButton(bool $enabled): void
    {
        self::button('javascript:tb_AskDelete(event)', 'lnr-delete', $enabled, $lang_delete ?? 'Verwijder', $lang_tb_delete ?? 'Verwijderen', 'toolbar_delete');
    }

    /**
     * Render copy button
     */
    public static function copyButton(bool $enabled): void
    {
        $queryString = str_replace('&action=restore', '', Request::server('QUERY_STRING', ''));
        self::button('form.php?' . $queryString . '&copy=Y', 'lnr-kopieer', $enabled, $lang_copy ?? 'Kopieer', $lang_tb_copy ?? 'Kopiëren', 'toolbar_copy');
    }

    /**
     * Render save button
     */
    public static function saveButton(bool $enabled, bool $isNew = false): void
    {
        self::button('javascript:tb_DoSave(false)', 'lnr-save', $enabled, $lang_save ?? 'Opslaan', $lang_tb_save ?? 'Opslaan', 'toolbar_save');
    }

    /**
     * Render save and close button
     */
    public static function saveCloseButton(bool $enabled, bool $isNew = false): void
    {
        self::button('javascript:tb_DoSave(true)', 'lnr-save', $enabled, $lang_save_and_close ?? 'Opslaan & Sluiten', $lang_tb_save ?? 'Opslaan en sluiten', 'toolbar_saveclose');
    }

    /**
     * Render print button
     */
    public static function printButton(bool $enabled): void
    {
        self::button('javascript:window.print()', 'lnr-print', $enabled, 'Print', $lang_tb_print ?? 'Afdrukken');
    }

    /**
     * Render search box
     */
    public static function searchBox(string $formName = '', string $formId = ''): void
    {
        $placeholder = 'Zoeken';
        if ($formName !== '') {
            $placeholder .= ' in \'' . htmlspecialchars($formName) . '\'';
        }
        $placeholder .= '...';

        echo '<lib-search-input id="searchfor" name="searchfor" placeholder="' . htmlspecialchars($placeholder) . '"></lib-search-input>' . PHP_EOL;
    }

    /**
     * Render status text in toolbar
     */
    public static function status(string $text): void
    {
        echo '<span class="toolbar-status">' . htmlspecialchars($text) . '</span>' . PHP_EOL;
    }

    /**
     * Render title in toolbar
     */
    public static function title(string $title): void
    {
        echo '<span class="toolbar-title">' . htmlspecialchars($title) . '</span>' . PHP_EOL;
    }

    /**
     * Render a help button that opens a lib-dialog
     *
     * @param string $dialogId ID of the lib-dialog element to open
     */
    public static function helpButton(string $dialogId = 'helpDialog'): void
    {
        self::button(
            'javascript:document.getElementById(\'' . $dialogId . '\').open()',
            'lnr-question-circle', true, '', 'Help', 'toolbar_help'
        );
    }

    /**
     * Render report toolbar
     *
     * @param string $title Report title
     * @param bool $tableButtons Show table view toggle buttons
     * @param bool $tableGrouped Current grouped state
     * @param bool $excel Show Excel/Word export buttons
     * @param bool $showTimestamp Show current timestamp in status area (default: false)
     * @param string $subtitle Optional subtitle/description shown after title
     * @param string $extraHtml Optional extra HTML to render in the right section (e.g., database selector)
     * @param string $helpDialogId Optional ID of a lib-dialog to show a help button for
     */
    public static function report(string $title, bool $tableButtons, bool $tableGrouped, bool $excel, bool $showTimestamp = false, string $subtitle = '', string $extraHtml = '', string $helpDialogId = ''): void
    {
        self::start(true);

        // Title on the left
        self::title($title);

        // Subtitle if provided
        if ($subtitle) {
            self::separator();
            self::status($subtitle);
        }

        if ($tableButtons) {
            self::separator();
            $prevParam = '';
            foreach (Request::queryAll() as $key => $param) {
                if (strtolower($key) !== strtolower(CONST_STRSORTPARAM ?? '')) {
                    $prevParam .= $key . '=' . Request::query($key, '') . '&';
                }
            }
            $url = Request::scriptName() . '?' . substr($prevParam, 0, max(0, strlen($prevParam) - 1));
            self::button($url, 'lnr-grouped', !$tableGrouped, 'Groepeer', 'Groepeer');
            self::button($url . '&Sort=1', 'lnr-table', $tableGrouped, 'Tabel', 'Tabel weergave');
        }

        if ($excel) {
            self::separator();
            self::button('javascript:export_to_excel()', 'filetype_xls', true, 'Excel', 'Excel export');
            self::separator();
            self::button(Request::addToURL('', 'export', 'word'), 'filetype_doc', true, 'Word', 'Word export');
        }

        self::startRight();
        if ($extraHtml !== '') {
            echo $extraHtml . PHP_EOL;
        }
        if ($showTimestamp) {
            self::status(date("Y-m-d H:i:s"));
        }
        if ($helpDialogId !== '') {
            self::helpButton($helpDialogId);
        }
        self::end(true);
    }

    /**
     * Render extra toolbar buttons defined in form definition
     *
     * @deprecated Use FormTemplate::generateExtraButtons() instead. This method is kept for
     *             backwards compatibility with legacy details.php code.
     */
    public static function extraButtons(array|\ArrayAccess $arrRep, string $formId, string $recordId, string $guid, string $guid2, bool $alwaysDisabled): void
    {
        $formDef = FormDefinition::fromArray($arrRep);

        // DEBUG: Add ?extrabuttondebug=1 to URL to see icon data
        if (\App\Library\Request::query('extrabuttondebug', '') !== '') {
            echo '<!-- DEBUG ExtraButtons START -->';
            echo '<pre style="background:#fff;color:#000;padding:10px;font-size:var(--font-size-xs);overflow:auto;max-height:300px;border:2px solid red;">';
            echo "FormID: {$formId}, RecordID: {$recordId}, GUID: {$guid}, GUID2: {$guid2}\n\n";
            echo "Raw arrRep extraIcon indices:\n";
            echo "Q_EXTRAICONURL (37): " . print_r($arrRep[37] ?? 'NOT SET', true) . "\n";
            echo "Q_EXTRAICONRES (38): " . print_r($arrRep[38] ?? 'NOT SET', true) . "\n";
            echo "Q_EXTRAICONTITLE (39): " . print_r($arrRep[39] ?? 'NOT SET', true) . "\n";
            echo "Q_EXTRAICON2URL (45): " . print_r($arrRep[45] ?? 'NOT SET', true) . "\n";
            echo "Q_EXTRAICON2RES (46): " . print_r($arrRep[46] ?? 'NOT SET', true) . "\n";
            echo "Q_EXTRAICON2TITLE (47): " . print_r($arrRep[47] ?? 'NOT SET', true) . "\n";
            echo "\nFormDefinition getExtraIcon results:\n";
            for ($i = 1; $i <= 5; $i++) {
                $icon = $formDef->getExtraIcon($i);
                echo "Icon {$i}: " . json_encode($icon) . "\n";
            }
            echo '</pre>';
            echo '<!-- DEBUG ExtraButtons END -->';
        }

        // Render extra toolbar buttons (1-5) using FormDefinition
        for ($iconNum = 1; $iconNum <= 5; $iconNum++) {
            $icon = $formDef->getExtraIcon($iconNum);
            if (!empty($icon['url'])) {
                if (SecurityHelper::checkFormButtonRights((int) Cookie::get(SecurityHelper::COOKIE_USERID, ''), (int) $formId, $iconNum)) {
                    $enabled = !$alwaysDisabled && self::checkButtonEnabled($icon['url'], $recordId, $guid, $guid2);
                    self::separator();
                    // Convert .png to .svg and ensure correct path (absolute to prevent wrong resolution with clean URLs)
                    $iconPath = str_replace('.png', '.svg', $icon['resource'] ?? '');
                    if (strpos($iconPath, 'assets/icons/') !== 0) {
                        $iconPath = 'assets/icons/' . basename($iconPath);
                    }
                    self::imageButton(
                        self::makeLink($icon['url'], $recordId, $guid, $guid2),
                        '/cma/' . $iconPath,
                        $enabled,
                        $icon['title'],
                        $icon['title']
                    );
                }
            }
        }
    }

    /**
     * Check if a button should be enabled based on required parameters
     */
    public static function checkButtonEnabled(string $url, string $recordId, string $guid, string $guid2): bool
    {
        if (stripos($url, '[ID]') !== false && $recordId === '') {
            return false;
        }
        if (stripos($url, '[GUID]') !== false && $guid === '') {
            return false;
        }
        if (stripos($url, '[GUID2]') !== false && $guid2 === '') {
            return false;
        }
        return true;
    }

    /**
     * Generate a link URL with parameter substitutions
     */
    public static function makeLink(string $url, string $recordId, string $guid, string $guid2): string
    {
        // OPTIMIZATION: Single str_ireplace call with arrays instead of nested calls
        $result = str_ireplace(
            ['[ID]', '[guid]', '[guid2]'],
            [$recordId, $guid, $guid2],
            $url
        );
        $serverName = Request::server('SERVER_NAME', '');
        $result = str_ireplace('[domein]', $serverName, $result);

        // Match protocol to current request (avoid https on localhost/IP)
        $isHttps = (Request::server('HTTPS', '') === 'on') || (Request::server('HTTP_X_FORWARDED_PROTO', '') === 'https');
        if (!$isHttps) {
            $result = str_ireplace('https://', 'http://', $result);
        }
        return $result;
    }

    // ===== LEGACY COMPATIBILITY METHODS =====
    // These maintain backwards compatibility with old table-based toolbar calls

    /**
     * @deprecated Use button() instead
     */
    public static function linearButton(string $href, string $buttonHtml, bool $enabled, string $text): void
    {
        // Extract icon class from the button HTML (e.g., '<span class="lnr lnr-save"...')
        $icon = 'lnr-question';
        if (preg_match('/lnr-([\w-]+)/', $buttonHtml, $matches)) {
            $icon = 'lnr-' . $matches[1];
        }

        // Extract title from the button HTML
        $title = '';
        if (preg_match('/title=[\'"]([^"\']+)[\'"]/', $buttonHtml, $matches)) {
            $title = $matches[1];
        }

        // Extract ID from href
        $id = '';
        if (preg_match('/id=[\'"]?(\w+)[\'"]?/', $href, $matches)) {
            $id = $matches[1];
        }

        // Extract href URL
        $url = '';
        if (preg_match('/href=[\'"]([^"\']+)[\'"]/', $href, $matches)) {
            $url = $matches[1];
        } elseif (preg_match('/href=([^\s>]+)/', $href, $matches)) {
            $url = $matches[1];
        }

        self::button($url, $icon, $enabled, $text, $title, $id);
    }
}
