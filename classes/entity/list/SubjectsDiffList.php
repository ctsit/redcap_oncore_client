<?php

namespace OnCoreClient\Entity;

use OnCoreClient\ExternalModule\ExternalModule;
use RCView;
use Records;
use REDCap;
use REDCapEntity\EntityList;
use REDCapEntity\StatusMessageQueue;

class SubjectsDiffList extends EntityList {

    protected $linkToRecordEnabled = false;

    protected function renderPageBody() {
        $this->module->initSubjectsMetadata();

        if (empty(ExternalModule::$subjectMappings)) {
            // TODO.
            return;
        }

        if (!$this->isListUpdated()) {
            $this->rebuildSubjectsDiffList();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['oncore_subjects_cache_clear'])) {
                $this->clearOnCoreSubjectsCache();
                $this->rebuildSubjectsDiffList();
            }
            elseif (isset($_POST['oncore_link_subject_id'])) {
                $entity = $this->entityFactory->getInstance('oncore_subject_diff', $_POST['oncore_link_subject_id']);

                if ($entity && $entity->linkToRecord($_POST['oncore_link_record_id'], !empty($_POST['oncore_link_override']))) {
                    StatusMessageQueue::enqueue('The subject has been linked to the record successfully.');
                }
                else {
                    // TODO: error msg.
                }

                $this->rebuildSubjectsDiffList();
            }
        }

        $subjects = $this->getLinkToSubjectOptions();
        $this->linkToRecordEnabled = !empty($subjects);

        include $this->module->getModulePath() . 'templates/link_modal.php';

        $this->jsFiles[] = $this->module->getUrl('js/subjects_pull.js');
        $this->cssFiles[] = $this->module->getUrl('css/subjects_pull.css');

        parent::renderPageBody();
    }

    protected function renderAddButton() {
        echo RCView::p([], 'For performance reasons, ' . RCView::b([], 'OnCore data is cached') . ' on this system, so the list below might not include the latest OnCore updates.');
        echo RCView::p([], 'Make sure OnCore data is updated before taking any actions. To refresh cache, click on "Refresh OnCore data" button below.');

        $btn = RCView::i(['class' => 'fas fa-sync-alt']);
        $btn = RCView::button([
            'type' => 'submit',
            'name' => 'oncore_subjects_cache_clear',
            'class' => 'btn btn-secondary btn-sm',
        ], $btn . ' Refresh OnCore data');

        echo RCView::form(['id' => 'oncore-cache-clear', 'method' => 'post'], $btn);
    }

    protected function renderTable() {
        parent::renderTable();

        if ($this->rows) {
            include $this->module->getModulePath() . 'templates/table_legend.php';
        }
    }

    protected function getTableHeaderLabels() {
        $header = parent::getTableHeaderLabels() + ['__operations' => 'Operations'];
        unset($header['id'], $header['updated'], $header['created']);

        $mappings = ExternalModule::$subjectMappings['mappings'];

        if (!isset($mappings['FirstName']) || !isset($mappings['LastName'])) {
            unset($header['subject_name']);
        }

        if (!isset($mappings['BirthDate'])) {
            unset($header['subject_dob']);
        }

        return $header;
    }

    protected function buildTableRow($entity, $bulk_operations = false) {
        $row = parent::buildTableRow($entity, $bulk_operations);
        $row['__operations'] = '';

        $type = $entity->getType();
        $id = $entity->getId();
        $labels = ExternalModule::$subjectMappings['labels'];

        if ($subject = $entity->getSubject()) {
            $row['__operations'] .= RCView::button([
                'class' => 'btn btn-info btn-xs',
                'data-toggle' => 'modal',
                'data-target' => '#oncore-subject-data-' . $id,
            ], 'View OnCore data');

            $subject = $subject->getData();
            $data = [];

            foreach ($subject['data'] as $key => $value) {
                if (isset($labels[$key])) {
                    $data[$labels[$key]] = $value;
                }
            }

            include $this->module->getModulePath() . 'templates/data_modal.php';
        }

        if ($type != 'oncore_only') {
            $row['record_id'] = RCView::a([
                'href' => APP_PATH_WEBROOT . 'DataEntry/record_home.php?pid=' . PROJECT_ID . '&id=' . $row['record_id'] . '&arm=' . getArm(),
                'target' => '_blank',
            ], $row['record_id']);

            $data = $entity->getData();

            if ($type == 'data_diff') {
                $row['__operations'] .= ' ' . RCView::button([
                    'class' => 'btn btn-info btn-xs',
                    'data-toggle' => 'modal',
                    'data-target' => '#oncore-subject-diff-' . $id,
                ], 'View diff');

                $diff = [];

                foreach ($data['diff'] as $key => $values) {
                    if (isset($labels[$key])) {
                        $diff[$labels[$key]] = $values;
                    }
                }

                include $this->module->getModulePath() . 'templates/diff_modal.php';
            }
            else {
                $row['__bulk_op'] = '';
                $opts = [
                    'class' => 'btn btn-success btn-xs oncore-subject-link-btn',
                    'data-toggle' => 'modal',
                    'data-target' => '#oncore-subject-link',
                    'data-record_id' => $data['record_id'],
                ];

                if (!$this->linkToRecordEnabled) {
                    $opts['disabled'] = true;
                    $opts['title'] = 'There are no available subjects to link';
                }

                $row['__operations'] .= ' ' . RCView::button($opts, 'Link to subject');
            }
        }

        return $row;
    }

    protected function getExposedFilters() {
        $filters = parent::getExposedFilters();
        unset($filters['subject_dob']);

        return $filters;
    }

    protected function getRowAttributes($entity) {
        return ['class' => 'row-' . str_replace('_', '-', $entity->getType())];
    }

    protected function executeBulkOperation($op, $op_info, $entities) {
        parent::executeBulkOperation($op, $op_info, $entities);
        $this->rebuildSubjectsDiffList();
    }

    protected function isListUpdated() {
        $sql = '
            SELECT description FROM redcap_log_event
            WHERE
                description IN (
                    "Create record",
                    "Update record",
                    "Delete record",
                    "Modify configuration for external module \"' . $this->module->PREFIX . '_' . $this->module->VERSION . '\" for project",
                    "Erase all data",
                    "OnCore-REDCap Diff rebuild"
                )
            ORDER BY log_event_id DESC LIMIT 1';

        $q = $this->module->query($sql);
        if (!db_num_rows($q)) {
            return true;
        }

        $last_event = db_fetch_assoc($q);
        return $last_event['description'] == 'OnCore-REDCap Diff rebuild';
    }

    protected function clearOnCoreSubjectsCache() {
        if (!$mappings = ExternalModule::$subjectMappings) {
            return;
        }

        if (!$protocol_no = $this->module->getProjectSetting('protocol_no')) {
            return;
        }

        $client = $this->module->getSoapClient();

        if (!$result = $client->request('getProtocolSubjects', array('ProtocolNo' => $protocol_no))) {
            return;
        }

        if (empty($result->ProtocolSubjects)) {
            return;
        }

        if (!$this->module->query('DELETE FROM redcap_entity_oncore_subject WHERE project_id = ' . intval(PROJECT_ID))) {
            return;
        }

        foreach ($result->ProtocolSubjects as $subject) {
            $status = str_replace(' ', '_', strtolower($subject->status));

            if (!isset(ExternalModule::$subjectStatuses[$status])) {
                continue;
            }

            $data = [
                'subject_id' => $subject->Subject->PrimaryIdentifier,
                'protocol_no' => $result->ProtocolNo,
                'status' => $status,
                'data' => json_encode($subject->Subject),
            ];

            $this->entityFactory->create('oncore_subject', $data);
        }

        REDCap::logEvent('OnCore Subjects cache clear', '', '', null, null, PROJECT_ID);
        StatusMessageQueue::enqueue('The OnCore data cache has been refreshed successfully.');
    }

    protected function rebuildSubjectsDiffList() {
        if (!$mappings = ExternalModule::$subjectMappings) {
            return;
        }

        if (!$this->module->query('DELETE FROM redcap_entity_oncore_subject_diff WHERE project_id = ' . intval(PROJECT_ID))) {
            return;
        }

        global $table_pk;

        $subject_id_field = $mappings['mappings']['PrimaryIdentifier'];
        $records_data = REDCap::getData(PROJECT_ID, 'array', null, $subject_id_field, $mappings['event_id']);

        $not_linked = array_keys($records_data);
        $not_linked = array_combine($not_linked, $not_linked);

        if ($subject_id_field == $table_pk) {
            $records = $not_linked;
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

        if (!$subjects = $this->entityFactory->query('oncore_subject')->execute()) {
            return;
        }

        $linked = [];

        foreach ($subjects as $id => $subject) {
            $data = $subject->getData();
            $subject_id = $data['subject_id'];

            if (isset($records[$subject_id])) {
                $record = $records[$subject_id];
                $linked[$record] = $data;

                unset($not_linked[$record]);
                continue;
            }

            $data = [
                'subject_id' => $data['id'],
                'subject_name' => trim($data['data']->FirstName . ' ' . $data['data']->LastName),
                'subject_dob' => strtotime($data['data']->BirthDate),
                'status' => $data['status'],
                'type' => 'oncore_only',
            ];

            $this->entityFactory->create('oncore_subject_diff', $data);
        }

        if (!empty($linked)) {
            $records_data = REDCap::getData(PROJECT_ID, 'array', array_keys($linked), $mappings['mappings'], $mappings['event_id']);

            foreach ($records_data as $record => $data) {
                $subject_data = $linked[$record];
                $data = $data[$mappings['event_id']];
                $diff = [];

                foreach ($mappings['mappings'] as $key => $field) {
                    $value = $subject_data['data']->{$key};
                    if ($value === null) {
                        $value = '';
                    }

                    if ($value !== $data[$field]) {
                        $diff[$key] = [$data[$field], $value];
                    }
                }

                if (!empty($diff)) {
                    $data = [
                        'subject_id' => $subject_data['id'],
                        'record_id' => (string) $record,
                        'subject_name' => trim($subject_data['data']->FirstName . ' ' . $subject_data['data']->LastName),
                        'subject_dob' => $subject_data['data']->BirthDate ? strtotime($subject_data['data']->BirthDate) : null,
                        'status' => $subject_data['status'],
                        'type' => 'data_diff',
                        'diff' => json_encode($diff),
                    ];

                    $this->entityFactory->create('oncore_subject_diff', $data);
                }
            }
        }

        if (!empty($not_linked)) {
            $full_name_enabled = isset($mappings['mappings']['FirstName']) && isset($mappings['mappings']['LastName']);
            $dob_enabled = isset($mappings['mappings']['BirthDate']);

            foreach ($not_linked as $record) {
                $data = [
                    'record_id' => (string) $record,
                    'type' => 'redcap_only',
                ];

                $record_data = $records_data[$record][$mappings['event_id']];
                if ($full_name_enabled) {
                    $data['subject_name'] = trim($record_data[$mappings['mappings']['FirstName']] . ' ' . $record_data[$mappings['mappings']['LastName']]);
                }

                if ($dob_enabled) {
                    $dob = $record_data[$mappings['mappings']['BirthDate']];
                    $data['subject_dob'] = $dob ? strtotime($dob) : null;
                }

                $this->entityFactory->create('oncore_subject_diff', $data);
            }
        }

        REDCap::logEvent('OnCore-REDCap Diff rebuild', '', '', null, null, PROJECT_ID);
    }

    protected function getLinkToSubjectOptions() {
        $entities = $this->entityFactory->query('oncore_subject_diff')
            ->condition('type', 'oncore_only')
            ->orderBy('subject_name')
            ->execute();

        if (empty($entities)) {
            return [];
        }

        $subjects = [];

        foreach ($entities as $entity_id => $entity) {
            $subject = $entity->getSubject();
            $subjects[$entity_id] = '(' . $subject->getLabel() . ') ' . $entity->getLabel();
        }

        return $subjects;
    }
}
