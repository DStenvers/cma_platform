<?php
/**
 * Subform Display - Modernized with lib-table web component
 *
 * Displays subforms for a parent form/record using tabs and the lib-table component.
 */
use App\Library\Application;
use App\Library\Arr;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Profiler;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;

require_once __DIR__ . '/bootstrap.inc';

function main()
{
    Response::noCache();

    Database::getConnection();
    Profiler::start();

    $formId = Request::queryInt('FormID');
    $parentId = Request::queryIntAndGuid('ID');
    $userId = (int)Cookie::get(SecurityHelper::COOKIE_USERID, '0');

    // Get subform definitions
    $subforms = SubFormGetArray($formId);
    $tabs = [];

    if (Arr::isArray($subforms) || $subforms instanceof \ArrayAccess) {
        $count = count($subforms[SUBFORM_ID] ?? []);
        // Get parent form rights for inheritance
        $parentFormRights = SecurityHelper::checkFormRights($userId, $formId);

        for ($i = 0; $i < $count; $i++) {
            $subformId = (int)$subforms[SUBFORM_ID][$i];
            $rights = SecurityHelper::checkFormRights($userId, $subformId);

            // Subforms often don't have their own menu entry, so inherit from parent form
            if ($rights < SecurityHelper::ACCESS_FULL && $parentFormRights >= SecurityHelper::ACCESS_FULL) {
                $rights = $parentFormRights;
            }

            // Skip if no access or if beheer-only and not beheer
            if ($rights <= SecurityHelper::ACCESS_NONE) continue;
            if ($subforms[SUBFORM_BEHEER][$i] && $rights != SecurityHelper::ACCESS_FULL_BEHEER) continue;

            $tabs[] = [
                'index' => $i,
                'id' => $subformId,
                'name' => Server::htmlEncode($subforms[SUBFORM_NAME][$i] ?? ''),
                'parentField' => $subforms[SUBFORM_PARENT][$i] ?? '',
                'fullWidth' => (bool)($subforms[SUBFORM_FULLWIDTH][$i] ?? false),
                'canAdd' => $rights >= SecurityHelper::ACCESS_FULL,
                'actie' => $subforms[SUBFORM_ACTIE][$i] ?? '',
                'beheer' => (bool)$subforms[SUBFORM_BEHEER][$i],
            ];
        }
    }

    // Output HTML
    cma_html_header('', '', false);
    cma_script('../library/webcomponents/lib-table.js');
    echo '</head>';
    echo '<body class="subformbody">';

    if (empty($tabs)) {
        echo '<div class="subform-empty">Geen subformulieren beschikbaar</div>';
    } else {
        // Render tab strip
        echo '<div class="subform-tabs" id="subformTabs">';
        foreach ($tabs as $index => $tab) {
            $activeClass = $index === 0 ? ' active' : '';
            $actieSpan = $tab['actie'] ? '<span class="actie" title="' . htmlspecialchars($tab['beheer'] ? 'beheer' : $tab['actie']) . '"></span>' : '';
            echo '<button class="subform-tab' . $activeClass . '" data-index="' . $tab['index'] . '">';
            echo $tab['name'] . $actieSpan;
            echo '<span class="tab-count" id="count-' . $tab['index'] . '"></span>';
            echo '</button>';
        }
        echo '</div>';

        // Render tab panels with lib-table
        echo '<div class="subform-panels">';
        foreach ($tabs as $index => $tab) {
            $activeClass = $index === 0 ? ' active' : '';
            echo '<div class="subform-panel' . $activeClass . '" data-index="' . $tab['index'] . '">';

            // Toolbar
            if ($tab['canAdd']) {
                echo '<div class="toolbar">';
                echo '<span class="tb-btn">';
                echo '<a href="#" onclick="subformAdd(' . $tab['id'] . ', \'' . $tab['parentField'] . '\', \'' . $parentId . '\', \'' . addslashes($tab['name']) . '\', ' . ($tab['fullWidth'] ? 'true' : 'false') . '); return false;" title="' . htmlspecialchars($lang_tb_add ?? 'Toevoegen') . '">';
                echo '<span class="lnr lnr-file-add"></span>';
                echo '<span class="btn-text">Toevoegen</span>';
                echo '</a>';
                echo '</span>';
                echo '</div>';
            }

            // lib-data-table component - JSON-based table with virtual scrolling
            echo '<lib-data-table';
            echo ' data-url="api/form_subform.php?formId=' . $formId . '&parentId=' . $parentId . '&subform=' . $tab['index'] . '"';
            echo ' data-form-id="subform-' . $formId . '-' . $tab['index'] . '"';
            echo ' data-subform-id="' . $tab['id'] . '"';
            echo ' data-parent-field="' . htmlspecialchars($tab['parentField']) . '"';
            echo ' data-parent-id="' . htmlspecialchars($parentId) . '"';
            echo ' data-full-width="' . ($tab['fullWidth'] ? 'true' : 'false') . '"';
            echo ' data-subform-name="' . htmlspecialchars($tab['name']) . '"';
            echo ' page-size="50"';
            echo ' density="compact"';
            echo ' sortable filterable resizable reorderable>';
            echo '</lib-data-table>';

            echo '</div>';
        }
        echo '</div>';
    }

    // JavaScript for tab switching and row clicks
    ?>
    <script>
    (function() {
        const formId = <?= json_encode($formId) ?>;
        const parentId = <?= json_encode($parentId) ?>;
        const cookieKey = 'subform_' + formId;

        // Tab switching
        document.querySelectorAll('.subform-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const index = this.dataset.index;

                // Update tab active states
                document.querySelectorAll('.subform-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // Update panel visibility
                document.querySelectorAll('.subform-panel').forEach(p => p.classList.remove('active'));
                document.querySelector('.subform-panel[data-index="' + index + '"]').classList.add('active');

                // Save to cookie
                lib_createCookie(cookieKey, index);
            });
        });

        // Restore active tab from cookie
        const savedTab = lib_readCookie(cookieKey);
        if (savedTab !== null) {
            const tab = document.querySelector('.subform-tab[data-index="' + savedTab + '"]');
            if (tab) tab.click();
        }

        // Handle row clicks on lib-table
        document.querySelectorAll('lib-table').forEach(table => {
            table.addEventListener('row-click', function(e) {
                const row = e.detail.row;
                const recordId = row._id || row.id || row.ID;
                const subformId = this.dataset.subformId;
                const parentField = this.dataset.parentField;
                const fullWidth = this.dataset.fullWidth === 'true';
                const subformName = this.dataset.subformName;

                // Get first column value for title
                const firstColValue = Object.values(row).find(v => v && typeof v === 'string') || '';
                const title = subformName + ' | ' + firstColValue;

                w(subformId, recordId, parentField, parentId, title, fullWidth);
            });

            // Update tab counts when data loads
            table.addEventListener('data-loaded', function(e) {
                const index = this.closest('.subform-panel').dataset.index;
                const countEl = document.getElementById('count-' + index);
                if (countEl && e.detail.totalCount > 0) {
                    countEl.textContent = e.detail.totalCount;
                    countEl.classList.add('have_data');
                }
            });
        });
    })();

    // Add new record
    function subformAdd(subformId, parentField, parentId, name, fullWidth) {
        w(subformId, '', parentField, parentId, 'Toevoegen record bij ' + name, fullWidth);
    }
    </script>

    <style>
    .subformbody {
        margin: 0;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: var(--font-size);
        background: #fff;
    }

    .subform-empty {
        padding: 20px;
        text-align: center;
        color: #666;
    }

    .subform-tabs {
        display: flex;
        gap: 2px;
        padding: 4px 8px;
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
        overflow-x: auto;
    }

    .subform-tab {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border: 1px solid #ddd;
        border-bottom: none;
        border-radius: 4px 4px 0 0;
        background: #fff;
        color: #666;
        font-size: var(--font-size-sm);
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.15s ease;
    }

    .subform-tab:hover {
        color: #007bff;
        border-color: #007bff;
    }

    .subform-tab.active {
        background: #007bff;
        color: #fff;
        border-color: #007bff;
    }

    .subform-panels {
        padding: 8px;
    }

    .subform-panel {
        display: none;
    }

    .subform-panel.active {
        display: block;
    }

    .actie {
        display: inline-block;
        width: 8px;
        height: 8px;
        background: #f0ad4e;
        border-radius: 50%;
        margin-left: 4px;
    }

    lib-table {
        height: calc(100vh - 120px);
        min-height: 200px;
    }
    </style>
    <?php

    echo '</body></html>';
    Profiler::end();
}

main();
