<?php

namespace OnCoreClient\Entity;

include_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

use OnCoreClient\ExternalModule\ExternalModule;
use REDCap;
use Records;
use REDCapEntity\Entity;
use REDCapEntity\StatusMessageQueue;

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

        if (empty($record)) {
            $record = $mappings['PrimaryIdentifier'] == $table_pk ? $this->data['data']['PrimaryIdentifier'] : getAutoId();
        }

        $remote_data_array = (json_decode(json_encode($remote_data), true)); // Converts nested objects to arrays

        // if a variable is mapped to the table primary key, reject the record if it is null
        if ( $pk = array_search($table_pk, $mappings['mappings']) ) {
            if ( empty(ExternalModule::digNestedData($remote_data_array, $pk)) ) {
                $errmsg = "Record " . $remote_data->PrimaryIdentifier . " could not be loaded because " . $pk . " is blank";
                StatusMessageQueue::enqueue($errmsg, $type = 'error');
                return false;
            }
        }

        // check version due to 9+ requring the $project_id parameter be set without a default
        if ( (explode('.', REDCAP_VERSION)[0]) >= 9 ) {
            Records::addNewRecordToCache($project_id = PROJECT_ID, $record = $record, $arm_id = $arm, $event_id = $event_id);
        } else {
            Records::addNewRecordToCache($record = $record, $arm_id = $arm, $event_id = $event_id);
        }


        foreach ($mappings['mappings'] as $key => $field) {
            $value = ExternalModule::digNestedData($remote_data_array, $key);
            $data[$field] = $value;
        }

        $data = [$record => [$event_id => $data]];

        $save_response = Records::saveData($this->data['project_id'], 'array', $data, 'overwrite');
        if (!$save_response === true) {
            return false;
        }

        if (!empty($save_response['errors'])) {
            $clear_error_msg = "";
            foreach ($save_response['errors'] as $key => $value) {
                $error_array = explode(",", $value); // [record, REDCap variable, OnCore value, error message]
                $clear_error_msg .= "</br>Error pulling to record $error_array[0]: " . substr_replace($error_array[3], " $error_array[2]", 10, 0);
            }

            StatusMessageQueue::enqueue($clear_error_msg, $type = 'error');
            return false;
        }

        if (!empty($save_response['warnings'])) {
            $clear_warning_msg = "";
            foreach ($save_response['warnings'] as $key => $value) {
                // TODO: find a warning to format properly
                $clear_warning_msg .= "</br>$value";
            }

            StatusMessageQueue::enqueue($clear_warning_msg, $type = 'warning');
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

        $remote_data_array = (json_decode(json_encode($remote_data), true)); // Converts nested objects to arrays

        foreach ($mappings['mappings'] as $key => $field) {
            $value = ExternalModule::digNestedData($remote_data_array, $key);
            if ($value !== $record_data[$field]) {
                $diff[$key] = [$record_data[$field], $value];
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
