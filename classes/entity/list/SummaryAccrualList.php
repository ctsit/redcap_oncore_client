<?php

namespace OnCoreClient\Entity;

use OnCoreClient\ExternalModule\ExternalModule;
use RCView;
use REDCapEntity\EntityList;
use REDCapEntity\StatusMessageQueue;

require_once 'PermissionChecker.php';

class SummaryAccrualList extends EntityList {

    protected $linkToRecordEnabled = false;

    protected function renderPageBody() {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['oncore_sa_send'])) {
                $this->module->sendSummaryAccrualData();
            }
            elseif (isset($_POST['oncore_staff_refresh'])) {
                $this->module->fillProtocolStaff();
            }
        }

        $this->cssFiles[] = $this->module->getUrl('css/summary_accrual.css');

        if ( !PermissionChecker::userOnStaff($this) ) {
            // If the user is not on the staff list, alert them
            // but still allow them to refresh the staff list
            StatusMessageQueue::clear();
            $this->renderAddButton(false);
            return;
        }

        // TODO: store responses and integrate with Entity
        parent::renderPageBody();
    }

    protected function renderAddButton($allow_upload = true) {

        $btn = RCView::i(['class' => 'fas fa-sync']);
        $btn = RCView::button([
            'type' => 'submit',
            'name' => 'oncore_staff_refresh',
            'class' => 'btn btn-primary btn-sm',
        ], $btn . ' Refresh OnCore Staff List');

        echo RCView::form(['id' => 'oncore-staff-refresh', 'method' => 'post'], $btn);

        if ( !$allow_upload ) {
            return;
        }

        $btn = RCView::i(['class' => 'fas fa-upload']);
        $btn = RCView::button([
            'type' => 'submit',
            'name' => 'oncore_sa_send',
            'class' => 'btn btn-primary btn-sm',
        ], $btn . ' Send Summary Accrual Data');

        echo RCView::form(['id' => 'oncore-sa-send', 'method' => 'post'], $btn);
    }

    protected function renderTable() {

        if ( !PermissionChecker::userOnStaff($this) ) {
            return;
        }
        parent::renderTable();
    }

}
