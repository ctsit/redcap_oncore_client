<?php

namespace OnCoreClient;
use SoapClient;

/**
 * Extends SoapClientin order to handle BOM (Byte-Order-Mark) on responses.
 *
 * Avoids "looks like we got no XML document" errors due to BOM.
 *
 * @see https://www.mikemackintosh.com/fix-soap-exception-looks-like-we-got-no-xml-document/
 */
class OnCoreSoapClient extends SoapClient {
    public function __doRequest($req, $location, $action, $version = SOAP_1_1) {
        $response = explode("\r\n", parent::__doRequest($req, $location, $action, $version));
        $response = preg_replace('/^(\x00\x00\xFE\xFF|\xFF\xFE\x00\x00|\xFE\xFF|\xFF\xFE|\xEF\xBB\xBF)/', '', $response[5]);
        return $response;
    }
}
