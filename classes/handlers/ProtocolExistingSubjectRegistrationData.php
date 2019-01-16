<?php

namespace OnCoreClient;
require_once dirname(__DIR__) . '/OnCoreBaseNode.php';

use OnCoreClient\OnCoreBaseNode;

class ProtocolExistingSubjectRegistrationData extends OnCoreBaseNode {
    const NODE_NAMESPACE = 'http://data.service.opas.percipenz.com/subject';
    protected $ProtocolSubjectRegistrationData;
}
