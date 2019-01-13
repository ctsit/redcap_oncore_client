<?php

namespace OnCoreClient\Entity;

include_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

use OnCoreClient\ExternalModule\ExternalModule;
use REDCap;
use Records;
use REDCapEntity\Entity;

class SubjectDiff extends Entity {

    static function getStatuses() {
        return ExternalModule::$subjectStatuses;
    }

    function getType() {
        return $this->data['type'];
    }

    function getSubject() {
        if (empty($this->data['subject_id'])) {
            return false;
        }

        return $this->factory->getInstance('oncore_subject', $this->data['subject_id']);
    }

    function pull($delete = false) {
        if (!($mappings = ExternalModule::$subjectMappings) || $this->data['project_id'] != PROJECT_ID || $this->data['type'] == 'redcap_only') {
            return false;
        }

        global $Proj, $table_pk;

        $event_id = $mappings['event_id'];
        $arm = $Proj->eventInfo[$event_id]['arm_num'];
        $subject = $this->getSubject();

        $remote_data = $subject->getData();
        $remote_data = $remote_data['data'];

        $record = $this->data['record_id'];
        $data = [];

        $record = $mappings['PrimaryIdentifier'] == $table_pk ? $this->data['data']['PrimaryIdentifier'] : getAutoId();
        $record = Records::addNewRecordToCache($record, $arm, $event_id);

        foreach ($mappings['mappings'] as $key => $field) {
            $data[$field] = $remote_data->{$key};
        }

        $data = [$record => [$event_id => $data]];

        if (!Records::saveData($this->data['project_id'], 'array', $data, 'overwrite') === true) {
            return false;
        }

        if ($delete) {
            return $this->delete();
        }

        return true;
    }

    function linkToRecord($record, $sync = true, $delete = false) {
        if ($this->data['type'] != 'oncore_only' || !($mappings = ExternalModule::$subjectMappings) || $this->data['project_id'] != PROJECT_ID) {
            return false;
        }

        global $Proj, $table_pk;
        $event_id = $mappings['event_id'];

        $subject = $this->getSubject();
        $subject_id = $subject->getLabel();

        if ($mappings['mappings']['PrimaryIdentifier'] == $table_pk) {
            $arm = $Proj->eventInfo[$event_id]['arm_num'];

            if (Records::recordExists($this->data['project_id'], $new_record, $arm)) {
                return false;
            }

            changeRecordId($record, $subject_id);
            $record = $subject_id;
        }
        else {
            Records::saveData($this->data['project_id'], 'array', [$record => [$event_id => [$mappings['mappings']['PrimaryIdentifier'] => $subject_id]]]);
        }

        $subject = $this->getSubject();

        $remote_data = $subject->getData();
        $remote_data = $remote_data['data'];

        $record_data = REDCap::getData($this->data['project_id'], 'array', $record, $mappings['mappings'], $event_id);
        $diff = [];

        foreach ($mappings['mappings'] as $key => $field) {
            if ($remote_data->{$key} !== $record_data[$field]) {
                $diff[$key] = [$record_data[$field], $remote_data->{$key}];
            }
        }

        if (empty($diff) && $delete) {
            return $this->delete();
        }

        $this->setData(['type' => 'data_diff', 'record_id' => $record, 'diff' => $diff]);
        if ($sync) {
            return $this->pull($delete);
        }

        return true;
    }
}
