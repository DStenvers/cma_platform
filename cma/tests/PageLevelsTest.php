<?php
/**
 * PageLevelsTest.php — CMA per-page minimum-level guard (page_levels.inc), standalone.
 *   php cma/tests/PageLevelsTest.php
 */
namespace Cma {
    class SecurityHelper {
        const LEVEL_USER = 0; const LEVEL_ADMIN = 1; const LEVEL_DEVELOPER = 2;
        public static function getUserLevel(): int { return $GLOBALS['__lvl'] ?? 0; }
        public static function getUserLevelName(int $l): string {
            return [0=>'Gebruiker',1=>'Administrator',2=>'Developer'][$l] ?? '?';
        }
    }
}
namespace {
    // Stub the catalog BEFORE requiring page_levels.inc so its require_once is skipped.
    function buildToolsTreeData(bool $isDeveloper): array {
        return [
            ['type'=>'folder','label'=>'Standaard','children'=>[
                ['type'=>'item','label'=>'Serverinfo','href'=>'tools/tools_serverinfo.php'],
                ['type'=>'item','label'=>'Cache','badge'=>'A','href'=>'tools/tools_clearcache.php'],
            ]],
            ['type'=>'folder','label'=>'Gebruikers','badge'=>'A','children'=>[
                ['type'=>'item','label'=>'Gebruikers','badge'=>'A','href'=>'form.php?form=users'],
                ['type'=>'item','label'=>'Inherit','href'=>'form.php?form=inherit'],
            ]],
            ['type'=>'folder','label'=>'Dev','children'=>[
                ['type'=>'item','label'=>'SQL','badge'=>'D','href'=>'tools/tools_query.php'],
            ]],
        ];
    }
    require __DIR__ . '/../page_levels.inc';
    $pass=0;$fail=0; function ck($c,$m){global $pass,$fail; if($c){$pass++;}else{$fail++;echo "  FAIL: $m\n";}}

    ck(cma_required_level('tools/tools_query.php')==='developer','D badge -> developer');
    ck(cma_required_level('tools/tools_clearcache.php')==='admin','A badge -> admin');
    ck(cma_required_level('tools/tools_serverinfo.php')==='user','no badge, folder user -> user');
    ck(cma_required_level('form.php?form=users')==='admin','form users -> admin');
    ck(cma_required_level('form.php?form=inherit')==='admin','folder-inherited A -> admin');
    ck(cma_required_level('form.php?form=random')==='user','uncatalogued form -> user (default)');
    ck(cma_required_level('dashboard.php')==='user','plain page -> user');
    ck(cma_required_level('tools/opcache_reset.php')==='developer','uncatalogued tools/ -> developer (fail-safe)');
    ck(cma_required_level('../../etc/passwd')==='user','traversal stripped');
    ck(cma_required_level('')==='user','empty -> user');

    echo "\nPageLevelsTest: $pass passed, $fail failed\n";
    exit($fail?1:0);
}
