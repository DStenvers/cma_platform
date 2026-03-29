<?php
use App\Library\Application;
use App\Library\Response;

require_once __DIR__ . '/header.inc';
require_once __DIR__ . '/footer.inc';

Response::redirect(Application::get('base_path', '') . '?PageAction=404');
?>
