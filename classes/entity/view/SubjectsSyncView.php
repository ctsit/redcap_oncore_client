<?php

namespace OnCoreClient\Entity\View;

use ExternalModules\ExternalModules;
use OnCoreClient\ExternalModule\ExternalModule;
use RCView;
use Records;
use REDCap;
use REDCapEntity\EntityView;
use REDCapEntity\StatusMessageQueue;

class SubjectsSyncView extends EntityView {

    protected function renderAddButton() {
        echo RCView::p([], 'For performance reasons, ' . RCView::b([], 'this list is cached') . ', so it might not reflect the current OnCore data.');
        echo RCView::p([], 'Make sure OnCore data is updated before taking any actions. To rebuild/refresh cache, click on "Rebuild cache" button below.');

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

        if (empty(ExternalModule::$subjectMappings)) {
            // TODO.
            return;
        }

        if (!$this->listIsUpdated()) {
            $this->rebuildSubjectsSyncList();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['subjects_cache_rebuild'])) {
                $this->rebuildSubjectsSyncList();
            }
            elseif (isset($_POST['oncore_subject_link_record_id'])) {
                $entity = $this->entityFactory->getInstance('oncore_sync_subject', $_POST['oncore_subject_link_entity_id']);

                if ($entity && $entity->linkToRecord($_POST['oncore_subject_link_record_id'], !empty($_POST['oncore_subject_link_sync']))) {
                    StatusMessageQueue::enqueue('The subject has been linked to the record successfully.');
                }
                else {
                    // TODO: error msg.
                }
            }
        }

        parent::renderPageBody();

        if (empty($this->rows)) {
            return;
        }

        $records = $this->getLinkToRecordOptions();

        $this->jsFiles[] = $this->module->getUrl('js/subjects_sync.js');
        include $this->module->getModulePath() . 'templates/link_modal.php';

        ExternalModules::addResource(ExternalModules::getManagerCSSDirectory() . 'select2.css');
        ExternalModules::addResource(ExternalModules::getManagerJSDirectory() . 'select2.js');
    }

    protected function getTableHeaderLabels() {
        $header = parent::getTableHeaderLabels();
        $header = [
            'subject_id' => $header['subject_id'],
            'name' => 'Name',
            'type' => $header['type'],
            'record_id' => $header['record_id'],
            'status' => $header['status'],
            '__operations' => 'Operations',
        ];

        global $table_pk;
        $mappings = ExternalModule::$subjectMappings['mappings'];

        if ($table_pk == $mappings['PrimaryIdentifier']) {
            $header['subject_id'] = 'Subject/Record ID';
            unset($header['record_id']);
        }

        if (!isset($mappings['FirstName']) || !isset($mappings['LastName'])) {
            unset($header['name']);
        }

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
                'data-entity_id' => $id,
            ], 'Link to record');
        }
        else {
            $col = isset($row['record_id']) ? 'record_id' : 'subject_id';
            $row[$col] = RCView::a(['href' => APP_PATH_WEBROOT . 'DataEntry/record_home.php?pid=' . PROJECT_ID . '&id=' . $row[$col]], $row[$col]);

            if ($type == 'delete') {
                return $row;
            }
        }

        if (isset($this->header['name'])) {
            $data = $entity->getData();
            $row['name'] = $data['data']->FirstName . ' ' . $data['data']->LastName;
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
        $this->rebuildSubjectsSyncList();
    }

    protected function listIsUpdated() {
        $sql = '
            SELECT description FROM redcap_log_event
            WHERE
                description IN (
                    "Create record",
                    "Update record",
                    "Delete record",
                    "Modify configuration for external module \"' . $this->module->PREFIX . '_' . $this->module->VERSION . '\" for project",
                    "OnCore Subjects Sync cache clear"
                )
            ORDER BY log_event_id DESC LIMIT 1';

        $q = $this->module->query($sql);
        if (!db_num_rows($q)) {
            return true;
        }

        $last_event = db_fetch_assoc($q);
        return $last_event['description'] == 'OnCore Subjects Sync cache clear';
    }

    protected function rebuildSubjectsSyncList() {
        if (!$mappings = ExternalModule::$subjectMappings) {
            return;
        }

        $client = $this->module->getSoapClient();

        if (!$protocol_no = $this->module->getProjectSetting('protocol_no')) {
            return;
        }

        if (!$result = $client->request('getProtocolSubjects', array('ProtocolNo' => $protocol_no))) {
            return;
        }

        if (empty($result->ProtocolSubjects)) {
            return;
        }

        global $table_pk;

        if (!$this->module->query('DELETE FROM redcap_entity_oncore_sync_subject WHERE project_id = ' . intval(PROJECT_ID))) {
            return;
        }

        // TODO: include mapping fields to calculate diff.

        $subject_id_field = $mappings['mappings']['PrimaryIdentifier'];
        $records_data = REDCap::getData(PROJECT_ID, 'array', null, $subject_id_field, $mappings['event_id']);

        if ($subject_id_field == $table_pk) {
            $records = array_keys($records_data);
            $records = array_combine($records, $records);
        }
        else {
            $records = [];
            foreach ($records_data as $record => $data) {
                $data = $data[$mappings['event_id']];

                if (!empty($data[$subject_id_field])) {
                    $records[$data[$subject_id_field]] = $record;
                }
            }
        }

        $changed = [];

        // TODO: batch.
        foreach ($result->ProtocolSubjects as $subject) {
            $status = $subject->status;
            $subject = $subject->Subject;

            if (isset($records[$subject->PrimaryIdentifier])) {
                $changed[$records[$subject->PrimaryIdentifier]] = $subject;
                unset($records[$subject->PrimaryIdentifier]);

                continue;
            }

            $data = [
                'subject_id' => $subject->PrimaryIdentifier,
                'protocol_no' => $result->ProtocolNo,
                'type' => 'create',
                'status' => $status,
                'data' => json_encode($subject),
            ];

            $this->entityFactory->create('oncore_sync_subject', $data);
        }

        if (!empty($changed)) {
            $records_data = REDCap::getData(PROJECT_ID, 'array', array_keys($changed), $mappings['mappings'], $mappings['event_id']);

            foreach ($records_data as $record => $data) {
                $data = $data[$mappings['event_id']];
                $diff = [];

                foreach ($mappings['mappings'] as $key => $field) {
                    $value = $changed[$record]->{$key};
                    if ($value === null) {
                        $value = '';
                    }

                    if ($value !== $data[$field]) {
                        $diff[$key] = [$data[$field], $value];
                    }
                }

                if (!empty($diff)) {
                    $data = [
                        'subject_id' => (string) $changed[$record]->PrimaryIdentifier,
                        'record_id' => (string) $record,
                        'protocol_no' => $result->ProtocolNo,
                        'type' => 'update',
                        'data' => json_encode($diff),
                    ];

                    $this->entityFactory->create('oncore_sync_subject', $data);
                }
            }
        }

        foreach ($records as $subject_id => $record) {
            $data = [
                'subject_id' => (string) $subject_id,
                'record_id' => (string) $record,
                'protocol_no' => $result->ProtocolNo,
                'type' => 'delete',
            ];

            $this->entityFactory->create('oncore_sync_subject', $data);
        }

        REDCap::logEvent('OnCore Subjects Sync cache clear', '', '', null, null, PROJECT_ID);
        StatusMessageQueue::enqueue('The cache has been rebuilt successfully.');
    }

    protected function getLinkToRecordOptions() {
        global $table_pk;

        $project_id = intval(PROJECT_ID);
        $mappings = ExternalModule::$subjectMappings['mappings'];
        $event_id = intval(ExternalModule::$subjectMappings['event_id']);


        $sql_extra_fields = '';
        $sql_extra_conds = '';
        $sql_extra_joins = [];

        $callback = '_formatRecordIdLinkOption';

        if (isset($mappings['FirstName']) && isset($mappings['LastName'])) {
            $callback = '_formatFullNameLinkOption';

            $sql_extra_fields .= ', fn.value first_name, ln.value as last_name';
            $sql_extra_joins += [
                'fn' => $mappings['FirstName'],
                'ln' => $mappings['LastName'],
            ];
        }

        if ($table_pk != $mappings['PrimaryIdentifier']) {
            $sql_extra_conds .= ' OR s.value = "" OR s.value IS NULL';
            $sql_extra_joins['s'] = $mappings['PrimaryIdentifier'];
        }

        foreach ($sql_extra_joins as $alias => $col) {
            $sql_extra_joins[$alias] = '
                LEFT JOIN redcap_data ' . $alias . ' ON
                    ' . $alias . '.record = r.record AND
                    ' . $alias . '.project_id = ' . $project_id . ' AND
                    ' . $alias . '.event_id = ' . $event_id . ' AND
                    ' . $alias . '.field_name = "' . db_escape($col) . '" AND
                    ' . $alias . '.instance IS NULL';
        }

        $sql = '
            SELECT r.record record_id' . $sql_extra_fields . ' FROM redcap_data r
            LEFT JOIN redcap_entity_oncore_sync_subject e ON
                r.record = e.record_id AND
                e.project_id = "' . $project_id . '"' . implode('', $sql_extra_joins) . '
            WHERE
                r.field_name = "' . db_escape($table_pk) . '" AND
                r.project_id = ' . $project_id . ' AND
                r.event_id = ' . $event_id . ' AND
                r.instance IS NULL AND
                (e.type = "delete"' . $sql_extra_conds . ')
            ORDER BY r.record';

        if (!$q = $this->module->query($sql)) {
            return false;
        }

        if (!db_num_rows($q)) {
            return [];
        }

        $records = [];
        while ($result = db_fetch_assoc($q)) {
            $records[$result['record_id']] = $this->{$callback}($result);
        }

        return $records;
    }

    protected function _formatRecordIdLinkOption($data) {
        return $data['record_id'];
    }

    protected function _formatFullNameLinkOption($data) {
        $formatted = '(' . $data['record_id'] . ') ';

        if (empty($data['first_name']) || empty($data['last_name'])) {
            return $formatted . '----';
        }

        return $formatted . $data['first_name'] . ' ' . $data['last_name'];
    }
}
