<?php

require_once dirname(__DIR__) . '/classes/entity/list/SubjectsDiffList.php';

use OnCoreClient\Entity\SubjectsDiffList;

$view = new SubjectsDiffList('oncore_subject_diff', $module);
$view->render('project', 'Pull OnCore Subjects', 'arrow_rotate_clockwise');
