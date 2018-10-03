<?php

namespace OnCoreClient;

use Exception;
use SoapVar;

class OnCoreBaseNode {
    const NODE_NAMESPACE = null;

    function __construct($values) {
        foreach ($values as $key => $value) {
            if (property_exists($this, $key)) {
                $enc_type = is_object($value) ? SOAP_ENC_OBJECT : XSD_STRING;
                $this->$key = new SoapVar($value, $enc_type, null, null, $key, static::NODE_NAMESPACE);
            }
        }
    }
}
