<?php

require_once dirname(__DIR__) . '/classes/entity/list/APILogsList.php';

use OnCoreClient\Entity\APILogsList;

$view = new APILogsList('oncore_api_log', $module);
$view->render('control_center', 'OnCore API Logs', 'report');
