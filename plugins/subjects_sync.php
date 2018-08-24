<?php

require_once dirname(__DIR__) . '/classes/entity/view/SubjectsSyncView.php';

use OnCoreClient\Entity\View\SubjectsSyncView;

$view = new SubjectsSyncView('oncore_sync_subject', $module);
$view->render('project', 'OnCore Subjects Sync', 'arrow_rotate_clockwise');
