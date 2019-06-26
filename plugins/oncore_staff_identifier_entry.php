<?php

use REDCapEntity\EntityList;

$list = new EntityList('oncore_staff_identifier', $module);
$list->setOperations(['create', 'update', 'delete'])
    ->render('control_center');
