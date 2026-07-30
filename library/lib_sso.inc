<?php

// Include-guard: dit bestand weigert zichzelf een tweede keer uit te voeren.
// require_once dekt het gewone geval al — die lost het pad eerst op — maar niet een
// kale require/include, en niet een pad dat via een junction anders uitkomt. De naam
// is uniek per bestand, zodat de guard niet van de aanroeper afhangt.
if (defined('INC_LIB_SSO')) { return; }
define('INC_LIB_SSO', 1);

// SSO Specific fcunctions

// Check session

// Find user

// Log user in

// Log user out

// ..
?>
