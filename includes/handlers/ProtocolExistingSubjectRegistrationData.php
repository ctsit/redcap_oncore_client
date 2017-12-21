<?php

namespace OnCoreClient;
require_once dirname(dirname(__FILE__)) . '/OnCoreBaseNode.php';

use OnCoreClient\OnCoreBaseNode;

class ProtocolExistingSubjectRegistrationData extends OnCoreBaseNode {
    const NODE_NAMESPACE = 'http://data.service.opas.percipenz.com/subject';
    protected $ProtocolSubjectRegistrationData;
}
