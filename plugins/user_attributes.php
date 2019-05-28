<?php

use REDCapEntity\EntityList;

$list = new EntityList('user_attributes', $module);
$list->setOperations(['create', 'update', 'delete'])
    ->render('project');
