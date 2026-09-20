<?php
/**
 * File-cache functions under their VBScript names, kept for the converted
 * sites that still call them (mijnRINO: the agenda and the header). They are
 * thin wrappers; the implementation is App\Library\Cache (loadFile, saveFile,
 * clearFile). New code calls Cache directly. The file goes when the last site
 * call site is gone; until then it stays, as deleting it would fatal those
 * pages on the next update.
 */

// Include-guard: dit bestand weigert zichzelf een tweede keer uit te voeren.
// require_once dekt het gewone geval al — die lost het pad eerst op — maar niet een
// kale require/include, en niet een pad dat via een junction anders uitkomt. De naam
// is uniek per bestand, zodat de guard niet van de aanroeper afhangt.
if (defined('INC_LIB_CACHE')) { return; }
define('INC_LIB_CACHE', 1);
use App\Library\Application;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Debug;
use App\Library\File;
use App\Library\Profiler;
use App\Library\Server;

define("CACHE_PRE_IDENTIFIER", 'cache_');
define("CACHE_XMLSTORE_IDENT", '__xmlstore');
define("SCHEMA_CACHE_ENABLED", false);

/**
 * Clear a cache file by identifier
 */
function lib_CacheFileClear($sIdentifier)
{
    $sFileName = Server::mapPath(Application::get('base_path', '') . CACHE_PATH . $sIdentifier);
    if (File::exists($sFileName)) {
        File::delete($sFileName);
    }
}

/**
 * Load content from a cache file
 */
function lib_CacheFileLoad($sIdentifier)
{
    $sFileName = lib_Cache_GetFilename($sIdentifier);
    $sFileContent = '';
    if (File::exists($sFileName)) {
        $sFileContent = File::readAsciiFile($sFileName);
        // Remove UTF-8 BOM if present
        if (substr($sFileContent, 0, 3) === "\xEF\xBB\xBF") {
            $sFileContent = substr($sFileContent, 3);
        }
    }
    return $sFileContent;
}

/**
 * Save content to a cache file
 */
function lib_CacheFileSave($sIdentifier, $sContent)
{
    $sFileName = lib_Cache_GetFilename($sIdentifier);
    File::createAsciiFile($sFileName, $sContent);
}

/**
 * Load from cache file (alias)
 */
function lib_Cache_FromFile($sFile)
{
    return Cache::loadFile($sFile);
}

/**
 * Save to cache file (alias)
 */
function lib_Cache_ToFile($sFile, $sContent)
{
    Cache::saveFile($sFile, $sContent);
}

/**
 * Get cache filename for an identifier
 */
function lib_Cache_GetFilename($sFile)
{
    return Server::mapPath(Application::get('base_path', '') . 'cache/' . Lib_FilenameSanitize(substr($sFile, 0, min(240, strlen($sFile)))));
}

/**
 * Clear a specific cache file
 */
function lib_Cache_ClearFile($sFile)
{
    $sFileName = lib_Cache_GetFilename($sFile);
    if (File::exists($sFileName)) {
        File::delete($sFileName);
    }
}
