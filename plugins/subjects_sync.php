<?php

require_once dirname(__DIR__) . '/classes/entity/view/SubjectsSyncView.php';

use OnCoreClient\Entity\View\SubjectsSyncView;
use REDCapEntity\StatusMessageQueue;
use ExternalModules\ExternalModules;

$view = new SubjectsSyncView('oncore_sync_subject', $module);
$view->render('project', 'OnCore Subjects Sync', 'arrow_rotate_clockwise');

ExternalModules::addResource(ExternalModules::getManagerCSSDirectory() . 'select2.css');
ExternalModules::addResource(ExternalModules::getManagerJSDirectory() . 'select2.js');
