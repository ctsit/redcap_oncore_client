<?php

namespace OnCoreClient\Entity;

include_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

use OnCoreClient\ExternalModule\ExternalModule;
use REDCap;
use Records;
use REDCapEntity\Entity;

class SubjectDiff extends Entity {
    protected $internal_subject_id;
    protected $subject_id;
    protected $record_id;
    protected $project_id;
    protected $subject_name;
    protected $subject_dob;
    protected $type;
    protected $diff;

    function getType() {
        return $this->type;
    }

    function getSubject() {
        if (empty($this->internal_subject_id)) {
            return false;
        }

        return $this->__factory->getInstance('oncore_subject', $this->internal_subject_id);
    }

    function pull($delete = false) {
        if (!($mappings = ExternalModule::$subjectMappings) || $this->project_id != PROJECT_ID || $this->type == 'redcap_only') {
            return false;
        }

        global $Proj, $table_pk;

        $event_id = $mappings['event_id'];
        $arm = $Proj->eventInfo[$event_id]['arm_num'];
        $subject = $this->getSubject();

        $remote_data = $subject->getData();
        $remote_data = $remote_data['data'];

        $record = $this->record_id;
        $data = [];

        $record = $mappings['PrimaryIdentifier'] == $table_pk ? $this->data['PrimaryIdentifier'] : getAutoId();
        $record = Records::addNewRecordToCache($record, $arm, $event_id);

        foreach ($mappings['mappings'] as $key => $field) {
            $data[$field] = $remote_data->{$key};
        }

        $data = [$record => [$event_id => $data]];

        if (!Records::saveData($this->project_id, 'array', $data, 'overwrite') === true) {
            return false;
        }

        if ($delete) {
            return $this->delete();
        }

        return true;
    }

    function linkToRecord($record, $sync = true, $delete = false) {
        if ($this->type != 'oncore_only' || !($mappings = ExternalModule::$subjectMappings) || $this->project_id != PROJECT_ID) {
            return false;
        }

        global $Proj, $table_pk;
        $event_id = $mappings['event_id'];

        if ($mappings['mappings']['PrimaryIdentifier'] == $table_pk) {
            $arm = $Proj->eventInfo[$event_id]['arm_num'];

            if (Records::recordExists($this->project_id, $new_record, $arm)) {
                return false;
            }

            changeRecordId($record, $this->subject_id);
            $record = $this->subject_id;
        }
        else {
            Records::saveData($this->project_id, 'array', [$record => [$event_id => [$mappings['mappings']['PrimaryIdentifier'] => $this->subject_id]]]);
        }

        $subject = $this->getSubject();

        $remote_data = $subject->getData();
        $remote_data = $remote_data['data'];

        $record_data = REDCap::getData($this->project_id, 'array', $record, $mappings['mappings'], $event_id);
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
