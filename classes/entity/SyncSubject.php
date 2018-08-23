<?php

namespace OnCoreClient\Entity;

include_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

use OnCoreClient\ExternalModule\ExternalModule;
use Records;
use REDCapEntity\Entity;

class SyncSubject extends Entity {
    protected $record_id;
    protected $subject_id;
    protected $protocol_no;
    protected $status;
    protected $type;
    protected $data;

    static protected $mappings;

    function getType() {
        return $this->type;
    }

    function sync($delete = false) {
        if (!$mappings = ExternalModule::$subjectMappings) {
            return false;
        }

        global $Proj, $table_pk;

        $event_id = $mappings['event_id'];
        $arm = $Proj->eventInfo[$event_id]['arm_num'];

        if ($this->type == 'delete') {
            global $multiple_arms, $randomization, $status;
            Records::deleteRecord(addDDEending($this->record_id), $table_pk, $multiple_arms, $randomization, $status, false, $arm);

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
            $record = Records::addNewRecordToCache($record, $Proj->eventInfo[$event_id]['arm_num'], $event_id);

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

        if (!Records::saveData($Proj->project_id, 'array', $data, 'overwrite') === true) {
            return false;
        }

        if ($delete) {
            return $this->delete();
        }

        return true;
    }
}