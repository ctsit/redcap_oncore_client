<?php

namespace OnCoreClient\Entity\View;

use OnCoreClient\ExternalModule\ExternalModule;
use RCView;
use REDCap;
use REDCapEntity\EntityView;
use REDCapEntity\StatusMessageQueue;

class SubjectsSyncView extends EntityView {

    protected function renderAddButton() {
        echo RCView::p([], 'For performance reasons, ' . RCView::b([], 'this list is cached') . ', so it might not reflect the current contrast between OnCore and REDCap subjects. Make sure to update the list before taking any actions.');
        echo RCView::p([], 'To rebuild/refresh cache, click on "Rebuild cache" button below.');

        $btn = RCView::i(['class' => 'fas fa-sync-alt']);
        $btn = RCView::button([
            'type' => 'submit',
            'name' => 'subjects_cache_rebuild',
            'class' => 'btn btn-primary',
        ], $btn . ' Rebuild cache');

        echo RCView::form(['method' => 'post', 'style' => 'margin-bottom: 20px;'], $btn);
    }

    protected function renderPageBody() {
        $this->module->setSubjectMappings();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['subjects_cache_rebuild'])) {
                $this->module->rebuildSubjectsSyncList();
            }
            elseif (isset($_POST['oncore_subject_link_record_id'])) {
                // TODO.
                $entity = $this->entityFactory->getInstance('oncore_sync_subject', $_POST['oncore_sync_subject_id']);
                $entity->setData(['record_id' => $_POST['oncore_subject_link_record_id'], 'type' => 'update']);
                $entity->sync();
                $this->module->rebuildSubjectsSyncList();
            }
        }

        parent::renderPageBody();

        if (empty($this->rows)) {
            return;
        }

        $mappings = ExternalModule::$subjectMappings['mappings'];
        $event_id = ExternalModule::$subjectMappings['event_id'];

        $fields = $mappings['PrimaryIdentifier'];
        $records = [];

        if (isset($mappings['FirstName']) && isset($mappings['LastName'])) {
            $fields = [$fields, $mappings['FirstName'], $mappings['LastName']];
        }

        $records_data = REDCap::getData(PROJECT_ID, 'array', null, $fields, $event_id);

        if (is_array($fields)) {
            foreach ($records_data as $id => $data) {
                $data = $data[$event_id];
                $records[$id] = '(' . $id . ') ' . $data[$mappings['FirstName']] . ' ' . $data[$mappings['LastName']];
            }
        }
        else {
            $records = array_keys($records_data);
            $records = array_combine($records, $records);
        }

        $this->jsFiles[] = $this->module->getUrl('js/subjects_sync.js');
        include $this->module->getModulePath() . 'templates/link_modal.php';
    }

    protected function getTableHeaderLabels() {
        $header = parent::getTableHeaderLabels();
        unset($header['id'], $header['protocol_no'], $header['created'], $header['updated']);

        global $table_pk;

        if ($table_pk == ExternalModule::$subjectMappings['mappings']['PrimaryIdentifier']) {
            $header['subject_id'] = 'Subject/Record ID';
            unset($header['record_id']);
        }

        $header['__operations'] = 'Operations';
        return $header;
    }

    protected function buildTableRow($entity, $bulk_operations = false) {
        $row = parent::buildTableRow($entity, $bulk_operations);
        $type = $entity->getType();
        $id = $entity->getId();

        $row['__operations'] = '';

        if ($type == 'create') {
            $row['__operations'] = RCView::button([
                'class' => 'btn btn-success btn-xs oncore-subject-link-btn',
                'data-toggle' => 'modal',
                'data-target' => '#oncore-subject-link',
                'data-oncore_sync_subject_id' => $id,
            ], 'Link to record');
        }
        else {
            $col = isset($row['record_id']) ? 'record_id' : 'subject_id';
            $row[$col] = RCView::a(['href' => APP_PATH_WEBROOT . 'DataEntry/record_home.php?pid=' . PROJECT_ID . '&id=' . $row[$col]], $row[$col]);

            if ($type == 'delete') {
                return $row;
            }
        }

        $labels = ExternalModule::$subjectMappings['labels'];
        $entity_data = $entity->getData();
        $data = [];

        foreach ($entity_data['data'] as $key => $value) {
            if (isset($labels[$key])) {
                $data[$labels[$key]] = $value;
            }
        }

        $op = $type == 'update' ? 'diff' : 'data';

        $row['__operations'] = RCView::button([
            'class' => 'btn btn-info btn-xs',
            'data-toggle' => 'modal',
            'data-target' => '#oncore-data-' . $id,
        ], 'View ' . $op) . $row['__operations'];

        include $this->module->getModulePath() . 'templates/' . $op . '_modal.php';
        return $row;
    }

    protected function getRowAttributes($entity) {
        $colors = [
            'create' => 'd4edda',
            'update' => 'fff3cd',
            'delete' => 'f8d7da',
        ];

        return ['style' => 'background-color: #' . $colors[$entity->getType()] . ';'];
    }

    protected function bulkOperationSubmit() {
        parent::bulkOperationSubmit();
        $this->module->rebuildSubjectsSyncList();
    }
}
