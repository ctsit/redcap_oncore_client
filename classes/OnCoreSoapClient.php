<?php

namespace OnCoreClient;
use SoapClient;

/**
 * Extends SoapClient in order to handle BOM (Byte-Order-Mark) on responses.
 *
 * Avoids "looks like we got no XML document" errors due to BOM.
 *
 */
class OnCoreSoapClient extends SoapClient {
    public function __doRequest($req, $location, $action, $version = SOAP_1_1, $one_way=0) {
        $response = parent::__doRequest($req, $location, $action, $version, $one_way);
        if (!$response) {
            return $response;
        }
        $soap_result = array();
        preg_match('/<soap:Envelope.*<\/soap:Envelope>/s', $response, $soap_result);
        return $soap_result[0];
    }
}
