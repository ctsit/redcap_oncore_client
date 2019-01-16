<?php

require_once dirname(__DIR__) . '/classes/entity/list/APILogsList.php';

use OnCoreClient\Entity\APILogsList;

$view = new APILogsList('oncore_api_log', $module);
$view->setBulkDelete()->render('control_center');
