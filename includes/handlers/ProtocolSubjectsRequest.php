<?php

namespace OnCoreClient;
require_once dirname(dirname(__FILE__)) . '/OnCoreBaseNode.php';

use OnCoreClient\OnCoreBaseNode;

class ProtocolSubjectsRequest extends OnCoreBaseNode {
    protected $ProtocolNo;
    protected $ResearchCenterSubjectsOnly;
}
