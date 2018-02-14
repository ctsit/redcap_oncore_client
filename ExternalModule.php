<?php
/**
 * @file
 * Provides ExternalModule class for OnCore Client.
 */

namespace OnCoreClient\ExternalModule;

require 'includes/OnCoreClient.php';

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use OnCoreClient\OnCoreClient;

/**
 * ExternalModule class for OnCore Client.
 */
class ExternalModule extends AbstractExternalModule {

    function redcap_module_system_enable($version) {
        $q = $this->query('SHOW TABLES LIKE "redcap_oncore_client_log"');
        if (db_num_rows($q)) {
            return;
        }

        // Creates logs table.
        $sql = '
            CREATE TABLE redcap_oncore_client_log (
                id INT NOT NULL AUTO_INCREMENT,
                pid INT NOT NULL,
                operation VARCHAR(128) NOT NULL,
                success BOOL NOT NULL,
                timestamp INT NOT NULL,
                request TEXT,
                response TEXT,
                error_msg VARCHAR(512),
                PRIMARY KEY (id)
            )';

        $this->query($sql);
    }

    function redcap_module_system_disable($version) {
        $q = $this->query('SHOW TABLES LIKE "redcap_oncore_client_log"');
        if (!db_num_rows($q)) {
            return;
        }

        // Removes logs table.
        $this->query('DROP table redcap_oncore_client_log');
    }

    /**
     * Cleans up old logs based on an configured expiration time.
     */
    function logsCleanUp() {
        $projects = ExternalModules::getEnabledProjects($this->PREFIX);

        while ($project = db_fetch_assoc($projects)) {
            $pid = $project['project_id'];
            if (!$n_days = $this->getProjectSetting('log_lifetime', $pid)) {
                continue;
            }

            $timestamp = strtotime('-' . $n_days . ' days');
            $this->query('DELETE FROM redcap_oncore_client_log WHERE pid = ' . db_escape($pid) . ' AND timestamp < ' . $timestamp);
        }
    }

    /**
     * Gets SOAP client, parameterized with configuration data.
     */
    function getSoapClient() {
        $wsdl = $this->getProjectSetting('wsdl', $Proj->project_id);
        $login = $this->getProjectSetting('login', $Proj->project_id);
        $password = $this->getProjectSetting('password', $Proj->project_id);

        return new OnCoreClient($wsdl, $login, $password);
    }
}
