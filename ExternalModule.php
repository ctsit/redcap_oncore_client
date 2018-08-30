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
use RCView;
use REDCap;

/**
 * ExternalModule class for OnCore Client.
 */
class ExternalModule extends AbstractExternalModule {

    /**
     * @inheritdoc.
     */
    function redcap_every_page_top($project_id) {
        if (!$project_id || strpos(PAGE, 'ExternalModules/manager/project.php') === false) {
            return;
        }

        $settings = ['modulePrefix' => $this->PREFIX];

        $url = $this->getSystemSetting('sip');
        if ($url && ($xml = simplexml_load_file($url . '?hdn_function=SIP_PROTOCOL_LISTINGS&format=xml'))) {
            $protocols = [];
            foreach ($xml->protocol as $item) {
                $protocols[REDCap::escapeHtml($item->no)] = REDCap::escapeHtml($item->title);
            }

            $settings += ['protocols' => $protocols, 'protocolNo' => $this->getProjectSetting('protocol_no')];
        }
        else {
            if (SUPER_USER) {
                $attrs = [
                    'href' => APP_PATH_WEBROOT . 'ExternalModules/manager/control_center.php',
                    'style' => 'color: #000066; font-weight: bold;',
                ];

                $link = RCView::a($attrs, 'Contol Center > External Modules');
                $settings['msg'] = RCView::p([], 'The SIP URL has not been properly configured. To do that, go to ' . $link . ' and then configure OnCore Client.');
            }
            else {
                $settings['msg'] = RCView::p([], 'OnCore Client global configuration is incomplete or incorrect. Please contact site administrators.');
            }
        }

        $this->includeJs('js/config.js');
        $this->setJsSettings($settings);
    }

    /**
     * @inheritdoc.
     */
    function redcap_module_system_enable($version) {
        // Creates logs table.
        $sql = '
            CREATE TABLE IF NOT EXISTS `redcap_oncore_client_log` (
                id INT NOT NULL AUTO_INCREMENT,
                pid INT NOT NULL,
                operation VARCHAR(128) NOT NULL,
                success BOOL NOT NULL,
                timestamp INT NOT NULL,
                request TEXT,
                response TEXT,
                error_msg VARCHAR(512),
                PRIMARY KEY (id)
            )  COLLATE utf8_unicode_ci';

        $this->query($sql);
    }

    /**
     * @inheritdoc.
     */
    function redcap_module_system_disable($version) {
        $this->query('DROP TABLE IF EXISTS redcap_oncore_client_log');
    }

    /**
     * Gets SOAP client, parameterized with configuration data.
     */
    function getSoapClient() {
        $wsdl = $this->getSystemSetting('wsdl');
        $login = $this->getSystemSetting('login');
        $password = $this->getSystemSetting('password');
        $log_enabled = $this->getSystemSetting('log_enabled');

        return new OnCoreClient($wsdl, $login, $password, $log_enabled);
    }

    /**
     * Includes a local JS file.
     *
     * @param string $path
     *   The relative path to the js file.
     */
    protected function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '"></script>';
    }

    /**
     * Sets JS settings.
     *
     * @param array $settings
     *   A keyed array containing settings for the current page.
     */
    protected function setJsSettings($settings) {
        echo '<script>onCoreClient = ' . json_encode($settings) . ';</script>';
    }
}
