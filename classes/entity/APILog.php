<?php

namespace OnCoreClient\Entity;

use REDCapEntity\Entity;

class APILog extends Entity {
    protected $success;
    protected $operation;
    protected $user_id;
    protected $project_id;
    protected $request;
    protected $response;
    protected $status;
    protected $error_msg;
}
