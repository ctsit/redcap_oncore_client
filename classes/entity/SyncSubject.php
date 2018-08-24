<?php

namespace OnCoreClient\Entity;

include_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

use OnCoreClient\ExternalModule\ExternalModule;
use REDCap;
use Records;
use REDCapEntity\Entity;

class SyncSubject extends Entity {
    protected $record_id;
    protected $subject_id;
    protected $project_id;
    protected $protocol_no;
    protected $status;
    protected $type;
    protected $data;

    static protected $mappings;

    function getSubjectId() {
        return $this->subject_id;
    }

    function getType() {
        return $this->type;
    }

    function sync($delete = false) {
        if (!($mappings = ExternalModule::$subjectMappings) || $this->project_id != PROJECT_ID) {
            return false;
        }

        global $Proj, $table_pk;

        $event_id = $mappings['event_id'];
        $arm = $Proj->eventInfo[$event_id]['arm_num'];

        if ($this->type == 'delete') {
            global $multiple_arms, $randomization, $status;
            Records::deleteRecord($this->record_id, $table_pk, $multiple_arms, $randomization, $status, false, $arm);

            if ($delete) {
                return $this->delete();
            }

            return true;
        }

        $remote_data = json_decode($this->data, true);

        $record = $this->record_id;
        $data = [];

        if ($this->type == 'create') {
            $record = $mappings['PrimaryIdentifier'] == $table_pk ? $this->data['PrimaryIdentifier'] : getAutoId();
            $record = Records::addNewRecordToCache($record, $arm, $event_id);

            foreach ($mappings['mappings'] as $key => $field) {
                $data[$field] = $remote_data[$key];
            }
        }
        else {
            foreach ($remote_data as $key => $diff) {
                $data[$mappings['mappings'][$key]] = $diff[1];
            }
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
        if ($this->type != 'create' || !($mappings = ExternalModule::$subjectMappings) || $this->project_id != PROJECT_ID) {
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

        $remote_data = json_decode($this->data, true);
        $record_data = REDCap::getData($this->project_id, 'array', $record, $mappings['mappings'], $event_id);
        $diff = [];

        foreach ($mappings['mappings'] as $key => $field) {
            if ($remote_data[$key] !== $record_data[$field]) {
                $diff[$key] = [$record_data[$field], $remote_data[$key]];
            }
        }

        if (empty($diff) && $delete) {
            return $this->delete();
        }

        $this->setData(['type' => 'update', 'record_id' => $record, 'data' => $diff]);
        if ($sync) {
            return $this->sync($delete);
        }

        return true;
    }
}
