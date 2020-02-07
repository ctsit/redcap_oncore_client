<?php

namespace OnCoreClient\Entity;

use OnCoreClient\ExternalModule\ExternalModule;
use RCView;
use REDCapEntity\EntityList;

class SummaryAccrualList extends EntityList {

    protected $linkToRecordEnabled = false;

    protected function renderPageBody() {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['oncore_sa_send'])) {
                $this->module->sendSummaryAccrualData();
            }
        }

        $this->cssFiles[] = $this->module->getUrl('css/summary_accrual.css');

        // TODO: store responses and integrate with Entity
        parent::renderPageBody();
    }

    protected function renderAddButton() {
        $btn = RCView::i(['class' => 'fas fa-upload']);
        $btn = RCView::button([
            'type' => 'submit',
            'name' => 'oncore_sa_send',
            'class' => 'btn btn-primary btn-sm',
        ], $btn . ' Send Summary Accrual Data');

        echo RCView::form(['id' => 'oncore-sa-send', 'method' => 'post'], $btn);
    }

}
