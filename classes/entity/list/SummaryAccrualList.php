<?php

namespace OnCoreClient\Entity;

use OnCoreClient\ExternalModule\ExternalModule;
use RCView;
use REDCap;
use REDCapEntity\EntityList;
use REDCapEntity\StatusMessageQueue;

require_once dirname(__DIR__) . '/PermissionChecker.php';

class SummaryAccrualList extends EntityList {

    protected $linkToRecordEnabled = false;

    protected function getColsLabels() {
        $header = parent::getColsLabels() + ['__view_data' => 'Rejections'];
        return $header;
    }

    protected function buildTableRow($data, $entity) {
        $row = parent::buildTableRow($data, $entity);
        $id = $entity->getId();

        $data = json_decode(json_encode($data), True);
        $data = $data['data'];

        if (empty($data['error_records'])) {
            $row['__view_data'] = "No rejects";
            return $row;
        }

        $row['__view_data'] = RCView::button([
            'class' => 'btn btn-info btn-xs',
            'data-toggle' => 'modal',
            'data-target' => '#oncore-data-' . $id,
        ], 'View rejects');

        $error_records= [];

        foreach($data['error_records'] as $err) {
            $cause = $err['error_cause'];
            foreach($err['id'] as $err_id) {
                $record_link = APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . DS . "DataEntry/record_home.php?pid=" . $entity->getData()['project_id'] . '&id=' . $err_id;
                $error_records[] = ['ID' => "<a href='$record_link'>$err_id</a>", 'Error Cause' => implode(", ", $cause)];
            }
        }

        // construct table
        $rows = $error_records;
        $tbody = array_reduce($rows, function($a, $b) { return $a.="<tr><td>".implode("</td><td>",$b)."</td></tr>"; });
        $thead = "<thead class='thead-light'><tr><th>" . implode("</th><th>", array_keys($rows[0])) . "</th></tr></thead>";
        $table = "<table class='table table-responsive-md table-striped'>$thead\n$tbody</table>";

        $data = [
            '' => $table
        ];

        include $this->module->getModulePath() . 'templates/data_modal.php';

        return $row;
    }

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
