<?php

// Include-guard: dit bestand weigert zichzelf een tweede keer uit te voeren.
// require_once dekt het gewone geval al — die lost het pad eerst op — maar niet een
// kale require/include, en niet een pad dat via een junction anders uitkomt. De naam
// is uniek per bestand, zodat de guard niet van de aanroeper afhangt.
if (defined('INC_CMA_CONNECT')) { return; }
define('INC_CMA_CONNECT', 1);
use Cma\CmaRepository;

$conn = null;

/**
 * Legacy wrapper functions - delegate to CmaRepository class
 * These functions are kept for backwards compatibility
 */

function openConnection($strConnect)
{
    // Deprecated - use CmaRepository methods instead
    $conn = \App\Library\Database::getConnection();
}

function openConnectionByDatabaseID($intDatabaseID)
{
    CmaRepository::openConnectionById($intDatabaseID);
}

function CMA_connect_GetStringByID($intDatabaseID)
{
    return CmaRepository::getConnectionStringById($intDatabaseID);
}

function CMA_connect_fulldbString($strConnect)
{
    return CmaRepository::resolveConnectionString($strConnect);
}
?>
