<?php
use App\Library\Application;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use App\Library\SQL;
use App\Library\Server;
use App\Library\Str;
use Cma\ToolbarHelper;

require_once __DIR__ . '/bootstrap.inc';
Response::noCache();

$parSearchFor = trim(Request::query('SearchFor', '') ?: Request::post('SearchFor', ''));

// Get tree data as JSON for cma-tree component
$treeJson = getTreeData($parSearchFor);

$extraHead = '<script>
document.addEventListener("DOMContentLoaded", function() {
    var tree = document.getElementById("templates-tree");
    if (tree) {
        tree.expandAll();
        window.fExpandAll = function() { tree.expandAll(); };
        window.fCollapseAll = function() { tree.collapseAll(); };
        // Auto-click if single result
        var items = tree.shadowRoot ? tree.shadowRoot.querySelectorAll("a.t[href]") : [];
        if (items.length === 1) items[0].click();
    }
    var searchField = document.getElementById("searchfor");
    if (searchField) searchField.focus();
});
</script>';

cma_html_header('Wijzigbare pagina\'s', $extraHead, false);
cma_script('webcomponents/cma-tree.js');
ToolbarHelper::writeJS();
?>
</head>
<body class="listbody tools-layout">

<div id="leftlist" class="tools-sidebar">
    <?php ToolbarHelper::start(false); ?>
    <?php if ($parSearchFor != ''): ?>
    <?php ToolbarHelper::linearButton('<a href=' . Request::currentDomain() . Request::getOriginalScript() . '>', '<span class="lnr lnr-back" data-tooltip="' . ($lang_tb_search_ret ?? 'Terug') . '"></span>', true, ''); ?>
    <?php endif; ?>
    <?php ToolbarHelper::treeButtons(); ?>
    <td>
    <?php ToolbarHelper::linearButton('<a href="template_fillrep.php" target="R">', '<span class="lnr lnr-refresh" data-tooltip="Vernieuw lijst met wijzigbare pagina\'s (kan even duren)"></span>', true, 'Vernieuwen'); ?>
    </td>
    <td width="99%">
        <form name="Snel" id="Snel" method="get" style="margin:0px;width:100%">
            <lib-search-input id="searchfor" name="searchfor" autofocus value="<?= Server::htmlEncode($parSearchFor) ?>" placeholder="Zoek naar ..."></lib-search-input>
        </form>
        <script>
            document.getElementById('searchfor').addEventListener('input', function() { searchasyoutype(); });
            document.getElementById('searchfor').addEventListener('search', function() { document.getElementById('Snel').submit(); });
        </script>
    </td>
    <?php ToolbarHelper::end(false); ?>
    <div id="c" class="listcontent blockselect" onselectstart="return false">
        <cma-tree
            id="templates-tree"
            storage-key="tree_templates"
            item-icon="file"
            data='<?= htmlspecialchars($treeJson, ENT_QUOTES, 'UTF-8') ?>'>
        </cma-tree>
    </div>
</div>

<iframe name="R" id="details_iframe" src="about:blank" frameborder="0"></iframe>

<style>
/* Templates layout */
body.tools-layout {
    display: flex;
    flex-direction: row;
    height: 100vh;
    overflow: hidden;
}
body.tools-layout #leftlist {
    width: 300px;
    min-width: 150px;
    max-width: 500px;
    height: 100%;
    overflow: hidden;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--border-color);
}
body.tools-layout #leftlist #c {
    flex: 1;
    overflow: auto;
}
body.tools-layout #details_iframe {
    flex: 1;
    height: 100%;
    border: none;
}
</style>

<script>
(function() {
    'use strict';
    var tree = document.getElementById('templates-tree');
    if (!tree) return;

    // Handle tree item clicks
    tree.addEventListener('item-click', function(e) {
        var href = e.detail.href;
        if (href && href !== '#' && href !== 'about:blank') {
            var iframe = document.getElementById('details_iframe');
            if (iframe) {
                iframe.src = href;
            }
        }
    });
})();
</script>

</body>
</html>
<?php

/**
 * Generate the templates tree data as JSON for cma-tree component
 * @param string $searchFor Optional search filter
 * @return string JSON string for cma-tree data attribute
 */
function getTreeData($searchFor = '')
{
    $sql = 'SELECT ID, FilePath, FileTitle FROM tblSiteFiles WHERE FileEditable ORDER BY FilePath, FileTitle';
    if ($searchFor != '') {
        $sql = SQL::complicatedWhere($sql, $searchFor, 'FilePath,FileTitle');
    }

    $rs = Database::openRS($sql, 'data');
    if ($rs === null) {
        return json_encode([['type' => 'folder', 'label' => 'Wijzigbare pagina\'s', 'children' => []]], JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    // Group items by FilePath
    $groups = [];
    while (!$rs->EOF) {
        $row = $rs->fields;
        $filePath = $row['FilePath'] ?? '';
        $groupKey = $filePath ?: '[Hoofdmap]';

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [];
        }

        $groups[$groupKey][] = [
            'type' => 'item',
            'label' => $row['FileTitle'] ?? '',
            'href' => 'template_edit.php?ID=' . ($row['ID'] ?? 0),
            'target' => 'R'
        ];

        $rs->MoveNext();
    }

    // Build tree structure
    $rootNode = [
        'type' => 'folder',
        'label' => 'Wijzigbare pagina\'s',
        'children' => []
    ];

    foreach ($groups as $groupName => $items) {
        $folderNode = [
            'type' => 'folder',
            'label' => $groupName,
            'children' => $items
        ];
        $rootNode['children'][] = $folderNode;
    }

    return json_encode([$rootNode], JSON_HEX_APOS | JSON_HEX_QUOT);
}
?>
