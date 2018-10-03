<?php

namespace OnCoreClient\Entity;

use REDCapEntity\Entity;
use OnCoreClient\ExternalModule\ExternalModule;

class OnCoreSubject extends Entity {
    protected $subject_id;
    protected $project_id;
    protected $protocol_no;
    protected $status;
    protected $data;

    function getStatuses() {
        return ExternalModule::$subjectStatuses;
    }
}
