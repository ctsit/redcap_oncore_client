<?php

require_once dirname(__DIR__) . '/classes/entity/list/SummaryAccrualList.php';

use OnCoreClient\Entity\SummaryAccrualList;

$list = new SummaryAccrualList('oncore_summary_accrual', $module);
$list->setCols(['configuration', 'updated'])
    ->render('project', 'Summary Accruals');
