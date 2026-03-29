<?php
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
