<?php
/**
 * Content Blocks Maintenance Tool
 *
 * Developer-only tool for managing content block templates.
 * Content blocks are reusable UI components for the front-end CMS.
 *
 * This follows the generic form layout pattern used by users/groups.
 */

use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

// Developer-only tool
if (!SecurityHelper::isDeveloper()) {
    Error::page('Toegang geweigerd', 'Deze functie is alleen beschikbaar voor developers.', false);
    exit;
}

Response::noCache();

// File path for content blocks (in site root, not CMA)
$contentBlocksFile = dirname(__DIR__, 2) . '/assets/contentblocks/contentblocks.json';

// Load current content blocks
$contentBlocks = [];
if (file_exists($contentBlocksFile)) {
    $json = file_get_contents($contentBlocksFile);
    $data = json_decode($json, true);
    $contentBlocks = $data['templates'] ?? [];
}

// Handle API requests
if (Request::hasQuery('api') || Request::hasPost('api')) {
    header('Content-Type: application/json');
    $action = Request::query('action', Request::post('action', ''));

    if ($action === 'list') {
        $listData = [];
        foreach ($contentBlocks as $block) {
            $listData[] = [
                'id' => $block['id'] ?? '',
                'title' => $block['title'] ?? '',
                'description' => $block['description'] ?? ''
            ];
        }
        echo json_encode(['success' => true, 'data' => $listData, 'count' => count($listData)]);
        exit;
    }

    if ($action === 'record') {
        $id = Request::query('id', '');
        foreach ($contentBlocks as $block) {
            if ($block['id'] === $id) {
                $block['variables'] = !empty($block['variables'])
                    ? json_encode($block['variables'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : '';
                echo json_encode(['success' => true, 'data' => $block]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Blok niet gevonden']);
        exit;
    }

    if ($action === 'save') {
        $id = Request::post('id', '');
        $title = Request::post('title', '');
        $description = Request::post('description', '');
        $html = Request::post('html', '');
        $variablesJson = Request::post('variables', '');

        if (empty($id)) {
            echo json_encode(['success' => false, 'error' => 'ID is verplicht']);
            exit;
        }

        // Parse variables JSON
        $variables = [];
        if (!empty($variablesJson)) {
            $variables = json_decode($variablesJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['success' => false, 'error' => 'Ongeldige JSON: ' . json_last_error_msg()]);
                exit;
            }
        }

        // Find and update or add block
        $found = false;
        foreach ($contentBlocks as &$block) {
            if ($block['id'] === $id) {
                $block['title'] = $title;
                $block['description'] = $description;
                $block['html'] = $html;
                $block['variables'] = $variables;
                $found = true;
                break;
            }
        }
        unset($block);

        if (!$found) {
            $contentBlocks[] = [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'html' => $html,
                'variables' => $variables
            ];
        }

        $data = ['templates' => $contentBlocks];
        if (file_put_contents($contentBlocksFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true, 'message' => $found ? 'Blok bijgewerkt' : 'Blok toegevoegd', 'id' => $id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Kon bestand niet opslaan']);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = Request::post('id', Request::query('id', ''));
        $originalCount = count($contentBlocks);
        $contentBlocks = array_filter($contentBlocks, fn($b) => ($b['id'] ?? '') !== $id);

        if (count($contentBlocks) === $originalCount) {
            echo json_encode(['success' => false, 'error' => 'Blok niet gevonden']);
            exit;
        }

        $data = ['templates' => array_values($contentBlocks)];
        if (file_put_contents($contentBlocksFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true, 'message' => 'Blok verwijderd']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Kon bestand niet opslaan']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Onbekende actie']);
    exit;
}

// Output HTML page using form layout
cma_html_header('CMA - Content blocks');
?>
<body class="contentbody tools">
<div class="cma-form">
    <!-- Toolbar -->
    <div class="toolbar">
        <span class="form-title">Content Blokken</span>
        <span class="toolbar-subtitle">Beheer herbruikbare content block templates</span>
        <div class="toolbar-buttons">
            <button type="button" class="toolbar-btn" id="btn_add" data-tooltip="Nieuw blok">
                <span class="lnr lnr-plus-circle"></span>
            </button>
            <button type="button" class="toolbar-btn" id="btn_save" data-tooltip="Opslaan" disabled>
                <span class="lnr lnr-checkmark-circle"></span>
            </button>
            <button type="button" class="toolbar-btn" id="btn_delete" data-tooltip="Verwijderen" disabled>
                <span class="lnr lnr-trash"></span>
            </button>
            <div class="toolbar-separator"></div>
            <div class="view-toggle">
                <button type="button" class="view-btn active" data-view="tree" data-tooltip="Boomstructuur">
                    <span class="lnr lnr-list"></span>
                </button>
                <button type="button" class="view-btn" data-view="table" data-tooltip="Tabelweergave">
                    <span class="lnr lnr-frame-expand"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Form Layout -->
    <div class="form-layout" id="formLayout">
        <!-- List Panel -->
        <div class="list-panel" id="listPanel">
            <div class="list-header">
                <lib-search-input id="searchInput" placeholder="Zoeken..."></lib-search-input>
            </div>
            <div class="list-content" id="listContent">
                <!-- Populated by JS -->
            </div>
        </div>

        <!-- Resize Handle -->
        <div class="resize-handle" id="resizeHandle"></div>

        <!-- Detail Panel -->
        <div class="detail-panel" id="detailPanel">
            <div class="detail-content">
                <div class="no-selection" id="noSelection">
                    <p>Selecteer een blok om te bewerken, of klik op + om een nieuw blok toe te voegen.</p>
                </div>
                <form id="blockForm" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <td class="label-cell"><label for="block_id">ID</label></td>
                            <td class="field-cell">
                                <input type="text" id="block_id" name="id" class="form-input" style="width:200px;" required>
                                <span class="field-hint">Unieke identifier (bijv: hero_banner)</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-cell"><label for="block_title">Titel</label></td>
                            <td class="field-cell">
                                <input type="text" id="block_title" name="title" class="form-input" style="width:300px;" required>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-cell"><label for="block_description">Beschrijving</label></td>
                            <td class="field-cell">
                                <textarea id="block_description" name="description" class="form-input" rows="3" style="width:100%;"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="section-header">Template</td>
                        </tr>
                        <tr>
                            <td class="label-cell"><label for="block_html">HTML</label></td>
                            <td class="field-cell">
                                <textarea id="block_html" name="html" class="form-input code-editor" rows="8" style="width:100%;font-family:monospace;"></textarea>
                                <span class="field-hint">Gebruik {{variabele}} voor placeholders</span>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="section-header">Variabelen</td>
                        </tr>
                        <tr>
                            <td class="label-cell"><label for="block_variables">JSON</label></td>
                            <td class="field-cell">
                                <textarea id="block_variables" name="variables" class="form-input code-editor" rows="10" style="width:100%;font-family:monospace;"></textarea>
                                <span class="field-hint">{"var_naam": {"description": "...", "type": "text|longtext|url|array", "required": true/false}}</span>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let currentBlockId = null;
    let isNewBlock = false;
    let isDirty = false;

    const listContent = document.getElementById('listContent');
    const blockForm = document.getElementById('blockForm');
    const noSelection = document.getElementById('noSelection');
    const btnAdd = document.getElementById('btn_add');
    const btnSave = document.getElementById('btn_save');
    const btnDelete = document.getElementById('btn_delete');
    const searchInput = document.getElementById('searchInput');

    // Load list
    function loadList() {
        fetch('?api&action=list')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderList(data.data);
                }
            });
    }

    // Render list
    function renderList(blocks) {
        if (blocks.length === 0) {
            listContent.innerHTML = '<div class="list-empty">Geen blokken gevonden</div>';
            return;
        }

        let html = '<div id="simpletree">';
        blocks.forEach(block => {
            const isActive = block.id === currentBlockId;
            html += `<a href="#" class="tree-item${isActive ? ' selected' : ''}" data-id="${escapeHtml(block.id)}">
                <span class="item-title">${escapeHtml(block.title)}</span>
                <span class="item-subtitle">${escapeHtml(block.id)}</span>
            </a>`;
        });
        html += '</div>';
        listContent.innerHTML = html;

        // Attach click handlers
        listContent.querySelectorAll('.tree-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                selectBlock(item.dataset.id);
            });
        });
    }

    // Select block
    function selectBlock(id) {
        if (isDirty && !await libConfirm('Wijzigingen niet opgeslagen. Doorgaan?')) {
            return;
        }

        currentBlockId = id;
        isNewBlock = false;

        // Update selection
        listContent.querySelectorAll('.tree-item').forEach(item => {
            item.classList.toggle('selected', item.dataset.id === id);
        });

        // Load record
        fetch(`?api&action=record&id=${encodeURIComponent(id)}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showForm(data.data);
                }
            });
    }

    // Show form
    function showForm(block) {
        noSelection.style.display = 'none';
        blockForm.style.display = 'block';

        document.getElementById('block_id').value = block.id || '';
        document.getElementById('block_id').readOnly = !isNewBlock;
        document.getElementById('block_title').value = block.title || '';
        document.getElementById('block_description').value = block.description || '';
        document.getElementById('block_html').value = block.html || '';
        document.getElementById('block_variables').value = block.variables || '';

        btnSave.disabled = false;
        btnDelete.disabled = isNewBlock;
        isDirty = false;
    }

    // Add new block
    btnAdd.addEventListener('click', async () => {
        if (isDirty && ! await libConfirm('Wijzigingen niet opgeslagen. Doorgaan?')) {
            return;
        }

        currentBlockId = null;
        isNewBlock = true;

        // Clear selection
        listContent.querySelectorAll('.tree-item').forEach(item => {
            item.classList.remove('selected');
        });

        showForm({});
        document.getElementById('block_id').focus();
    });

    // Save block
    btnSave.addEventListener('click', () => {
        const formData = new FormData(blockForm);
        formData.append('action', 'save');

        fetch('?api', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                libNotify(data.message, 'success');
                currentBlockId = data.id;
                isNewBlock = false;
                isDirty = false;
                loadList();
                btnDelete.disabled = false;
                document.getElementById('block_id').readOnly = true;
            } else {
                libNotify(data.error, 'error');
            }
        });
    });

    // Delete block
    btnDelete.addEventListener('click', () => {
        if (!currentBlockId) return;

        libConfirm('Weet je zeker dat je dit blok wilt verwijderen?', {
            type: 'danger',
            confirmText: 'Verwijderen',
            onConfirm: () => {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', currentBlockId);

                fetch('?api', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        libNotify(data.message, 'success');
                        currentBlockId = null;
                        noSelection.style.display = 'block';
                        blockForm.style.display = 'none';
                        btnSave.disabled = true;
                        btnDelete.disabled = true;
                        isDirty = false;
                        loadList();
                    } else {
                        libNotify(data.error, 'error');
                    }
                });
            }
        });
    });

    // Track dirty state
    blockForm.addEventListener('input', () => {
        isDirty = true;
    });

    // Search
    searchInput.addEventListener('input', (e) => {
        const term = (e.detail?.value ?? e.target.value ?? '').toLowerCase();
        listContent.querySelectorAll('.tree-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(term) ? '' : 'none';
        });
    });

    // View toggle
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const view = btn.dataset.view;
            document.getElementById('formLayout').className = 'form-layout ' + (view === 'table' ? 'table-view' : '');
        });
    });

    // escapeHtml() provided by cma-utils.js

    // Initialize
    loadList();
})();
</script>
</body>
</html>
<?php
