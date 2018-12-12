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
            $this->module->rebuildSubjectsDiffList();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['oncore_subjects_cache_clear'])) {
                $this->module->clearOnCoreSubjectsCache();
            }
            elseif (isset($_POST['oncore_link_subject_id'])) {
                $entity = $this->entityFactory->getInstance('oncore_subject_diff', $_POST['oncore_link_subject_id']);

                if ($entity && $entity->linkToRecord($_POST['oncore_link_record_id'], !empty($_POST['oncore_link_override']))) {
                    StatusMessageQueue::enqueue('The subject has been linked to the record successfully.');
                }
                else {
                    // TODO: error msg.
                }

                $this->module->rebuildSubjectsDiffList();
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

    protected function buildTableRow($data, $entity) {
        $row = parent::buildTableRow($data, $entity);
        $row['__operations'] = '';

        $type = $entity->getType();
        $id = $entity->getId();
        $labels = ExternalModule::$subjectMappings['labels'];

        if ($subject = $entity->getSubject()) {
            $row['__operations'] .= RCView::button([
                'class' => 'btn btn-info btn-xs',
                'data-toggle' => 'modal',
                'data-target' => '#oncore-data-' . $id,
            ], 'View OnCore data');

            $subject = $subject->getData();
            $data = [];

            foreach ($subject['data'] as $key => $value) {
                if (isset($labels[$key])) {
                    $data[$labels[$key]] = $value;
                }
            }

            $inline = true;
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

    protected function getRowAttributes($data, $entity) {
        return ['class' => 'row-' . str_replace('_', '-', $entity->getType())];
    }

    protected function executeBulkOperation($op, $op_info, $entities) {
        parent::executeBulkOperation($op, $op_info, $entities);
        $this->module->rebuildSubjectsDiffList();
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
                    "OnCore Subjects Diff rebuild"
                )
            ORDER BY log_event_id DESC LIMIT 1';

        $q = $this->module->query($sql);
        if (!db_num_rows($q)) {
            return true;
        }

        $last_event = db_fetch_assoc($q);
        return $last_event['description'] == 'OnCore Subjects Diff rebuild';
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
