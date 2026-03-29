<?php
/**
 * Legacy cache file functions - NOT YET MIGRATED
 *
 * These functions handle file-based caching and are not yet
 * migrated to the Cache helper class.
 *
 * TODO: Migrate these to Cache helper and remove this file
 */
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
