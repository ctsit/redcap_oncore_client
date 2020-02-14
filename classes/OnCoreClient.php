<?php
/**
 * @file
 * Provides OnCore client class.
 */

namespace OnCoreClient;

use OncoreClient\OnCoreSoapClient;
use REDCapEntity\EntityFactory;
use SoapFault;
use GuzzleHttp;

require_once 'OnCoreSoapClient.php';

/**
 * OnCore client class.
 */
class OnCoreClient {
    protected $client;
    protected $handlers = array();
    protected $logEnabled;
    protected $use_soap;
    protected $auth;
    protected $base_uri;

    /**
     * Constructor.
     */
    function __construct($wsdl, $login, $password, $log_enabled = false, $use_soap = true, $base_uri = null) {
        $this->use_soap = $use_soap;
        $this->base_uri = $base_uri;
        $this->setClient($wsdl, $login, $password);
        $this->logEnabled = $log_enabled;
    }

    /**
     * Includes handlers files.
     */
    function includeHandlers() {
        foreach (glob(__DIR__ . '/handlers/*.php') as $filename) {
            include_once $filename;
        }
    }

    /**
     * Sets up a new SOAP or HTTP client.
     */
    function setClient($wsdl, $login, $password) {

        if ($this->use_soap) {
            $this->setSoapClient($wsdl, $login, $password);
        }
        else {
            $this->setHttpClient($login, $password);
        }
    }

    private function setSoapClient($wsdl, $login, $password) {
        $this->includeHandlers();

        $options = array(
                'location' => str_replace('?wsdl','',$wsdl),
                'login' => $login,
                'password' => $password,
                'trace' => 1,
                'exceptions' => true,
                );
        $this->client = new OnCoreSoapClient($wsdl, $options);

        $this->setHandlers();
    }

    private function setHttpClient($login, $password, $base_uri = null) {
        // Add a trailing slash if the base_uri if it does not already have one
        if ( substr_compare('/', $this->base_uri, -strlen($this->base_uri)) !== 0 ) {
            $this->base_uri .= '/';
        }
        $this->client = new GuzzleHttp\Client([
                'base_uri' => $this->base_uri
        ]);
        $this->auth = [$login, $password];
    }

    /**
     * Sets up association between SOAP API operations and handler classes.
     */
    function setHandlers() {
        // Getting services available from WSDL and assigning each one of them
        // to a handler class.
        foreach ($this->client->__getFunctions() as $function) {
            preg_match('/\s(.*)\(/', $function, $matches);
            $op = $matches[1];

            preg_match('/' . $op . '\((.*)\s/', $function, $matches);
            $class = $matches[1];

            $class = '\OnCoreClient\\' . $class;
            if (class_exists($class)) {
                $this->handlers[$op] = $class;
            }
        }
    }

    /**
     * Performs a SOAP API request.
     */
    function request($op, $data) {
        if (!defined('PROJECT_ID')) {
            return false;
        }

        $log = [
            'operation' => $op,
            'success' => 1,
        ];

        if (!$class = $this->getOperationHandlerClass($op)) {
            $log['error_msg'] = 'Operation does not exist or not supported.';
            $log['success'] = 0;
            $this->log($log);

            return false;
        }

        $data = json_decode(json_encode($data));
        $data = new $class($data);

        try {
            $result = $this->client->{$op}($data);
        }
        catch (SoapFault $e) {
            $log['error_msg'] = $e->getMessage();
        }

        $log['success'] = empty($result) ? 0 : 1;

        if ($xml = $this->client->__getLastRequest()) {
            $log['request'] = $xml;
        }

        if ($xml = $this->client->__getLastResponse()) {
            $log['response'] = $xml;
        }

        $this->log($log);
        return $result;
    }

    /**
     * Logs API call.
     */
    function log($log) {
        if (!$this->logEnabled) {
            return;
        }

        $factory = new EntityFactory();
        $factory->create('oncore_api_log', $log);
    }

    /**
     * Gets the handler class for the given operation.
     */
    function getOperationHandlerClass($op) {
        return isset($this->handlers[$op]) ? $this->handlers[$op] : false;
    }

    function httpRequest($method, $endpoint, $args = [], $use_basic_auth = false) {
        try {
            if ($use_basic_auth) {
                $args['auth'] = $this->auth;
            }
            $result = $this->client->request($method, $endpoint, $args);
        } catch(GuzzleHttp\Exception\ClientException $e) {
            // TODO: handle specific errors
            //GuzzleHttp\Psr7\str($e->getResponse());
            return false;
        }
        return $result;
    }
}
