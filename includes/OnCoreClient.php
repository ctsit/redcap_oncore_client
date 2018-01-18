<?php
/**
 * @file
 * Provides OnCore client class.
 */

namespace OnCoreClient;

use OncoreClient\OnCoreSoapClient;
use SoapFault;

require_once dirname(__FILE__) . '/OnCoreSoapClient.php';

/**
 * OnCore client class.
 */
class OnCoreClient {
    protected $client;
    protected $handlers = array();

    /**
     * Constructor.
     */
    function __construct($wsdl, $login, $password) {
        $this->includeHandlers();
        $this->setClient($wsdl, $login, $password);
        $this->setHandlers();
    }

    /**
     * Includes handlers files.
     */
    function includeHandlers() {
        foreach (glob(dirname(__FILE__) . '/handlers/*.php') as $filename) {
            include_once $filename;
        }
    }

    /**
     * Sets up a new SOAP client.
     */
    function setClient($wsdl, $login, $password) {
        $options = array(
            'location' => $wsdl,
            'login' => $login,
            'password' => $password,
            'trace' => 1,
            'exceptions' => true,
        );

        $this->client = new OnCoreSoapClient($wsdl, $options);
    }

    /**
     * Sets up association between API operations and handler classes.
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
     * Performs an API request.
     */
    function request($op, $data) {
        if (!defined('PROJECT_ID')) {
            return false;
        }

        $log = array(
            'pid' => (int) PROJECT_ID,
            'operation' => $op,
            'timestamp' => time(),
            'success' => 1,
            'request' => '',
            'response' => '',
            'error_msg' => '',
        );

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
        foreach ($log as $key => $value) {
            if (is_string($value)) {
                $log[$key] = '"' . db_escape($value) . '"';
            }
        }

        $sql = 'INSERT INTO redcap_oncore_client_log (' . implode(', ', array_keys($log)) . ') VALUES (' . implode(', ', $log) . ')';
        db_query($sql);
    }

    /**
     * Gets the handler class for the given operation.
     */
    function getOperationHandlerClass($op) {
        return isset($this->handlers[$op]) ? $this->handlers[$op] : false;
    }
}
