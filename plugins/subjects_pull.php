<?php

require_once dirname(__DIR__) . '/classes/entity/view/SubjectsDiffView.php';

use OnCoreClient\Entity\View\SubjectsDiffView;

$view = new SubjectsDiffView('oncore_subject_diff', $module);
$view->render('project', 'Pull OnCore Subjects', 'arrow_rotate_clockwise');
