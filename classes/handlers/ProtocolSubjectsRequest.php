<?php

namespace OnCoreClient;
require_once dirname(__DIR__) . '/OnCoreBaseNode.php';

use OnCoreClient\OnCoreBaseNode;

class ProtocolSubjectsRequest extends OnCoreBaseNode {
    protected $ProtocolNo;
    protected $ResearchCenterSubjectsOnly;
}
