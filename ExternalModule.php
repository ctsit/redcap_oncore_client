<?php
/**
 * @file
 * Provides ExternalModule class for OnCore Client.
 */

namespace OnCoreClient\ExternalModule;

require_once 'classes/OnCoreClient.php';

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use OnCoreClient\OnCoreClient;
use RCView;
use REDCap;
use REDCapEntity\EntityDB;
use REDCapEntity\EntityFactory;

/**
 * ExternalModule class for OnCore Client.
 */
class ExternalModule extends AbstractExternalModule {

    static public $subjectMappings;

    /**
     * @inheritdoc.
     */
    function redcap_every_page_top($project_id) {
        if ($project_id && strpos(PAGE, 'ExternalModules/manager/project.php') !== false) {
            $this->setProtocolFormElement();
        }
    }

    /**
     * @inheritdoc.
     */
    function redcap_module_system_enable($version) {
        EntityDB::buildSchema($this);

        // Creates logs table.
        $sql .= '
            CREATE TABLE IF NOT EXISTS `redcap_oncore_client_log` (
                id INT NOT NULL AUTO_INCREMENT,
                pid INT NOT NULL,
                operation VARCHAR NOT NULL,
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
        EntityDB::dropSchema($this);
        $this->query('DROP TABLE IF EXISTS redcap_oncore_client_log');
    }

    function redcap_entity_types() {
        $types = [];

        $types['oncore_sync_subject'] = [
            'label' => 'OnCore Sync Subject',
            'label_plural' => 'OnCore Sync Subjects',
            'special_keys' => [
                'label' => 'subject_id',
                'project' => 'project_id',
            ],
            'class' => [
                'name' => 'OnCoreClient\Entity\SyncSubject',
                'path' => 'classes/entity/SyncSubject.php',
            ],
            'properties' => [
                'subject_id' => [
                    'name' => 'Subject ID',
                    'type' => 'text',
                    'required' => true,
                ],
                'protocol_no' => [
                    'name' => 'Protocol number',
                    'type' => 'text',
                    'required' => true,
                ],
                'project_id' => [
                    'name' => 'Project ID',
                    'type' => 'project',
                    'required' => true,
                ],
                'type' => [
                    'name' => 'Sync action',
                    'type' => 'text',
                    'required' => true,
                    'choices' => [
                        'create' => 'Create',
                        'update' => 'Update',
                        'delete' => 'Delete',
                    ],
                ],
                'record_id' => [
                    'name' => 'REDCap Record ID',
                    'type' => 'text',
                ],
                'status' => [
                    'name' => 'OnCore Status',
                    'type' => 'text',
                ],
                'data' => [
                    'name' => 'Data',
                    'type' => 'json',
                ],
            ],
            'operations' => [
                'create' => false,
                'update' => false,
                'delete' => false,
            ],
            'bulk_operations' => [
                'sync' => [
                    'label' => 'Sync',
                    'method' => 'sync',
                    'color' => 'green',
                    'success_message' => 'The subjects have been synced successfully.',
                    'confirmation_message' => 'Subjects can be created, updated or deleted if you sync the selected items. This action cannot be undone.',
                ],
            ],
        ];

        return $types;
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

    function setSubjectMappings() {
        global $Proj;

        $settings = $this->getFormattedSettings($Proj->project_id);
        $event_id = $settings['mappings_event'];

        if (!$event_id || !isset($Proj->eventInfo[$event_id])) {
            // Event ID is required.
            return;
        }

        $mappings = [];
        foreach ($settings['mappings'] + $settings['mappings']['ContactInfo'] as $key => $field) {
            if (
                empty($field) || !is_string($field) || !isset($Proj->metadata[$field]) ||
                !in_array($Proj->metadata[$field]['form_name'], $Proj->eventsForms[$event_id])
            ) {
                if ($key == 'PrimaryIdentifier') {
                    // Primary ID is required.
                    return;
                }

                continue;
            }

            // TODO: skip repeating forms.

            $mappings[$key] = $field;
        }

        $config = $this->getConfig();
        $config = $config['project-settings'][2]['sub_settings'];
        $labels = [];

        foreach (array_merge($config, $config[11]['sub_settings']) as $config_field) {
            if (isset($mappings[$config_field['key']])) {
                $labels[$config_field['key']] = $config_field['name'];
            }
        }

        // TODO: address lines.

        self::$subjectMappings = [
            'event_id' => $event_id,
            'mappings' => $mappings,
            'labels' => $labels,
        ];
    }

    protected function setProtocolFormElement() {
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
     * Formats settings into a hierarchical key-value pair array.
     *
     * @param int $project_id
     *   Enter a project ID to get project settings.
     *   Leave blank to get system settings.
     *
     * @return array
     *   The formatted settings.
     */
    function getFormattedSettings($project_id = null) {
        $settings = $this->getConfig();

        if ($project_id) {
            $settings = $settings['project-settings'];
            $values = ExternalModules::getProjectSettingsAsArray($this->PREFIX, $project_id);
        }
        else {
            $settings = $settings['system-settings'];
            $values = ExternalModules::getSystemSettingsAsArray($this->PREFIX);
        }

        return $this->_getFormattedSettings($settings, $values);
    }

    /**
     * Auxiliary function for getFormattedSettings().
     */
    protected function _getFormattedSettings($settings, $values, $inherited_deltas = array()) {
        $formatted = array();

        foreach ($settings as $setting) {
            $key = $setting['key'];
            $value = $values[$key]['value'];

            foreach ($inherited_deltas as $delta) {
                $value = $value[$delta];
            }

            if ($setting['type'] == 'sub_settings') {
                $deltas = array_keys($value);
                $value = array();

                foreach ($deltas as $delta) {
                    $sub_deltas = array_merge($inherited_deltas, array($delta));
                    $value[$delta] = $this->_getFormattedSettings($setting['sub_settings'], $values, $sub_deltas);
                }

                if (empty($setting['repeatable'])) {
                    $value = $value[0];
                }
            }

            $formatted[$key] = $value;
        }

        return $formatted;
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
