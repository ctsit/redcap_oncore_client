<?php

require_once dirname(__DIR__) . '/classes/entity/list/SubjectsDiffList.php';

use OnCoreClient\Entity\SubjectsDiffList;

$view = new SubjectsDiffList('oncore_subject_diff', $module);
$view->setBulkOperation('pull', 'Pull OnCore Subjects', 'The subjects have been pulled', 'green')
    ->render('project', 'Pull OnCore Subjects');
