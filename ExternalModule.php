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
use REDCapEntity\StatusMessageQueue;

/**
 * ExternalModule class for OnCore Client.
 */
class ExternalModule extends AbstractExternalModule {

    static public $subjectMappings;
    static public $subjectStatuses;

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
        EntityDB::buildSchema($this->PREFIX);
    }

    /**
     * @inheritdoc.
     */
    function redcap_module_system_disable($version) {
        EntityDB::dropSchema($this->PREFIX);
    }

    function redcap_entity_types() {
        $types = [];

        $types['oncore_subject_diff'] = [
            'label' => 'OnCore Subject Diff',
            'label_plural' => 'OnCore Subject Diffs',
            'icon' => 'arrow_rotate_clockwise',
            'special_keys' => [
                'project' => 'project_id',
                'label' => 'subject_name',
            ],
            'class' => [
                'name' => 'OnCoreClient\Entity\SubjectDiff',
                'path' => 'classes/entity/SubjectDiff.php',
            ],
            'properties' => [
                'subject_id' => [
                    'name' => 'OnCore Subject',
                    'type' => 'entity_reference',
                    'entity_type' => 'oncore_subject',
                ],
                'record_id' => [
                    'name' => 'REDCap Record',
                    'type' => 'record',
                ],
                'project_id' => [
                    'name' => 'Project ID',
                    'type' => 'project',
                    'required' => true,
                ],
                'subject_name' => [
                    'name' => 'Name',
                    'type' => 'text',
                ],
                'subject_dob' => [
                    'name' => 'DOB',
                    'type' => 'date',
                ],
                'type' => [
                    'name' => 'Type',
                    'type' => 'text',
                    'required' => true,
                    'choices' => [
                        'oncore_only' => 'New subject',
                        'redcap_only' => 'Record not linked',
                        'data_diff' => 'Needs update',
                    ],
                ],
                'status' => [
                    'name' => 'OnCore Status',
                    'type' => 'text',
                    'choices_callback' => '\OnCoreClient\Entity\SubjectDiff::getStatuses',
                ],
                'diff' => [
                    'name' => 'Data',
                    'type' => 'json',
                ],
            ],
        ];

        $types['oncore_subject'] = [
            'label' => 'OnCore Subject',
            'label_plural' => 'OnCore Subjects',
            'special_keys' => [
                'label' => 'subject_id',
                'project' => 'project_id',
            ],
            'properties' => [
                'subject_id' => [
                    'name' => 'OnCore Primary Identifier',
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
                'status' => [
                    'name' => 'OnCore Status',
                    'type' => 'text',
                    'choices_callback' => '\OnCoreClient\Entity\SubjectDiff::getStatuses',
                ],
                'data' => [
                    'name' => 'Data',
                    'type' => 'json',
                ],
            ],
        ];

        $types['oncore_api_log'] = [
            'label' => 'OnCore API Log',
            'label_plural' => 'OnCore API Logs',
            'icon' => 'report',
            'properties' => [
                'success' => [
                    'name' => 'Success',
                    'type' => 'boolean',
                ],
                'operation' => [
                    'name' => 'Operation',
                    'type' => 'text',
                    'choices' => [
                        'getProtocol' => 'getProtocol',
                        'getProtocolSubjects' => 'getProtocolSubjects',
                        'createProtocol' => 'createProtocol',
                        'registerNewSubjectToProtocol' => 'registerNewSubjectToProtocol',
                        'registerExistingSubjectToProtocol' => 'registerExistingSubjectToProtocol',
                    ],
                ],
                'user_id' => [
                    'name' => 'User',
                    'type' => 'user',
                    'required' => true,
                ],
                'project_id' => [
                    'name' => 'Project ID',
                    'type' => 'project',
                    'required' => true,
                ],
                'request' => [
                    'name' => 'Request',
                    'type' => 'data',
                ],
                'response' => [
                    'name' => 'Response',
                    'type' => 'data',
                ],
                'error_msg' => [
                    'name' => 'Error message',
                    'type' => 'text',
                ],
            ],
            'special_keys' => [
                'project' => 'project_id',
                'author' => 'user_id',
            ],
        ];

        $types['user_attributes'] = [
            'label' => 'Local User Information',
            'label_plural' => 'Local Users Information',
            'properties' => [
                'staff_id' => [
                    'name' => 'Staff ID',
                    'type' => 'text',
                    'required' => true,
                ],
                'user_id' => [
                    'name' => 'User',
                    'type' => 'user',
                ],
            ],
        ];

        $types['protocol_staff'] = [
            'label' => 'OnCore Protocol Staff Information',
            'label_plural' => 'Local Users Information',
            'properties' => [
                'protocol_no' => [
                    'name' => 'Protocol Number',
                    'type' => 'text',
                    'required' => true,
                ],
                'staff_id' => [
                    'name' => 'Staff ID',
                    'type' => 'text',
                ],
                'stop_date' => [
                    'name' => 'Stop Date',
                    'type' => 'date',
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

    function initSubjectsMetadata() {
        global $Proj;

        $settings = $this->getFormattedSettings($Proj->project_id);
        if (!$statuses = array_filter($settings['valid_statuses'])) {
            return;
        }

        $event_id = $settings['mappings_event'];

        if (!$event_id || !isset($Proj->eventInfo[$event_id])) {
            // Event ID is required.
            return;
        }

        $mappings = [];
        foreach ($settings['mappings'] + $settings['mappings']['contact_info'] as $key => $field) {
            if (
                empty($field) || !is_string($field) || !isset($Proj->metadata[$field]) ||
                !in_array($Proj->metadata[$field]['form_name'], $Proj->eventsForms[$event_id])
            ) {
                if ($key == 'primary_identifier') {
                    // Primary ID is required.
                    return;
                }

                continue;
            }

            // TODO: skip repeating forms.

            $mappings[$this->_toCamelCase($key)] = $field;
        }

        $config = $this->getConfig();

        foreach ($config['project-settings'][1]['sub_settings'] as $config_field) {
            if (isset($statuses[$config_field['key']])) {
                $statuses[$config_field['key']] = $config_field['name'];
            }
        }

        if (isset($statuses['_expired'])) {
            // Workaround for a bug on EM that does not allow fields keyed as
            // "expired" to exist.
            $statuses['expired'] = $statuses['_expired'];
            unset($statuses['_expired']);
        }

        self::$subjectStatuses = $statuses;

        $config = $config['project-settings'][3]['sub_settings'];
        $labels = [];

        foreach (array_merge($config, $config[11]['sub_settings']) as $config_field) {
            $key = $this->_toCamelCase($config_field['key']);

            if (isset($mappings[$key])) {
                $labels[$key] = $config_field['name'];
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
                $protocols[REDCap::escapeHtml($item->no)] = REDCap::escapeHtml('(' . $item->no . ') ' . $item->title);
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

// TODO: roll this and fillProtocolStaff into another container function to avoid allocating redunandant variables
    function clearOnCoreSubjectsCache() {
        if (!$mappings = ExternalModule::$subjectMappings) {
            return;
        }

        if (!$protocol_no = $this->getProjectSetting('protocol_no')) {
            return;
        }

        $this->fillProtocolStaff();

        $client = $this->getSoapClient();

        if (!$result = $client->request('getProtocolSubjects', array('ProtocolNo' => $protocol_no))) {
            return;
        }

        if (empty($result->ProtocolSubjects)) {
            return;
        }

        if (!$this->query('DELETE FROM redcap_entity_oncore_subject WHERE project_id = ' . intval(PROJECT_ID))) {
            return;
        }

        $factory = new EntityFactory();

        foreach ($result->ProtocolSubjects as $subject) {
            $status = str_replace(' ', '_', strtolower($subject->status));

            if (!isset(ExternalModule::$subjectStatuses[$status])) {
                continue;
            }

            $data = [
                'subject_id' => $subject->Subject->PrimaryIdentifier,
                'protocol_no' => $result->ProtocolNo,
                'status' => $status,
                'data' => json_encode($subject->Subject),
            ];

            $factory->create('oncore_subject', $data);
        }

        $this->rebuildSubjectsDiffList();

        REDCap::logEvent('OnCore Subjects cache clear', '', '', null, null, PROJECT_ID);
        StatusMessageQueue::enqueue('The OnCore data cache has been refreshed.');
    }

    function fillProtocolStaff() {
        if (!$protocol_no = $this->getProjectSetting('protocol_no')) {
            return;
        }

        $client = $this->getSoapClient();

        if(!$result = $client->request('getProtocolStaff', array('ProtocolNo' => $protocol_no,
                                                                 'LastName' => ''))) {
            return;
        }

        $factory = new EntityFactory();

        $staffList = $result->ProtocolStaff;

        // Workaround for EntityDB not having a unique columns option
        if (!$this->query('DELETE FROM redcap_entity_protocol_staff WHERE protocol_no = \'' . $protocol_no . '\'')) {
            return;
        }

        foreach($staffList as $key => $value) {
            $staff_id = $value->Staff->InstitutionStaffId;

            $epoch = strtotime($value->StopDate);

            $epoch = ($epoch) ? ($epoch) : null; // strtotime drops null values

            $data = [
                'protocol_no' => $protocol_no,
                'staff_id' => $staff_id,
                'stop_date' => $epoch,
            ];

            $factory->create('protocol_staff', $data);
        }
    }

    function rebuildSubjectsDiffList() {
        if (!$mappings = ExternalModule::$subjectMappings) {
            return;
        }

        if (!$this->query('DELETE FROM redcap_entity_oncore_subject_diff WHERE project_id = ' . intval(PROJECT_ID))) {
            return;
        }

        global $table_pk;

        $subject_id_field = $mappings['mappings']['PrimaryIdentifier'];
        $records_data = REDCap::getData(PROJECT_ID, 'array', null, $subject_id_field, $mappings['event_id']);

        $not_linked = array_keys($records_data);
        $not_linked = array_combine($not_linked, $not_linked);

        if ($subject_id_field == $table_pk) {
            $records = $not_linked;
        }
        else {
            $records = [];

            foreach ($records_data as $record => $data) {
                $data = $data[$mappings['event_id']];

                if (!empty($data[$subject_id_field])) {
                    $records[$data[$subject_id_field]] = $record;
                }
            }
        }

        $factory = new EntityFactory();
        if (!$subjects = $factory->query('oncore_subject')->execute()) {
            return;
        }

        $linked = [];

        foreach ($subjects as $id => $subject) {
            $data = $subject->getData() + ['id' => $subject->getId()];
            $subject_id = $data['subject_id'];

            if (isset($records[$subject_id])) {
                $record = $records[$subject_id];
                $linked[$record] = $data;

                unset($not_linked[$record]);
                continue;
            }

            $data = [
                'subject_id' => $data['id'],
                'subject_name' => trim($data['data']->FirstName . ' ' . $data['data']->LastName),
                'subject_dob' => strtotime($data['data']->BirthDate),
                'status' => $data['status'],
                'type' => 'oncore_only',
            ];

            $factory->create('oncore_subject_diff', $data);
        }

        if (!empty($linked)) {
            $records_data = REDCap::getData(PROJECT_ID, 'array', array_keys($linked), $mappings['mappings'], $mappings['event_id']);

            foreach ($records_data as $record => $data) {
                $subject_data = $linked[$record];
                $data = $data[$mappings['event_id']];
                $diff = [];

                foreach ($mappings['mappings'] as $key => $field) {
                    $value = $subject_data['data']->{$key};
                    if ($value === null) {
                        $value = '';
                    }

                    if ($value !== $data[$field]) {
                        $diff[$key] = [$data[$field], $value];
                    }
                }

                if (!empty($diff)) {
                    $data = [
                        'subject_id' => $subject_data['id'],
                        'record_id' => (string) $record,
                        'subject_name' => trim($subject_data['data']->FirstName . ' ' . $subject_data['data']->LastName),
                        'subject_dob' => $subject_data['data']->BirthDate ? strtotime($subject_data['data']->BirthDate) : null,
                        'status' => $subject_data['status'],
                        'type' => 'data_diff',
                        'diff' => json_encode($diff),
                    ];

                    $factory->create('oncore_subject_diff', $data);
                }
            }
        }

        if (!empty($not_linked)) {
            $full_name_enabled = isset($mappings['mappings']['FirstName']) && isset($mappings['mappings']['LastName']);
            $dob_enabled = isset($mappings['mappings']['BirthDate']);

            foreach ($not_linked as $record) {
                $data = [
                    'record_id' => (string) $record,
                    'type' => 'redcap_only',
                ];

                $record_data = $records_data[$record][$mappings['event_id']];
                if ($full_name_enabled) {
                    $data['subject_name'] = trim($record_data[$mappings['mappings']['FirstName']] . ' ' . $record_data[$mappings['mappings']['LastName']]);
                }

                if ($dob_enabled) {
                    $dob = $record_data[$mappings['mappings']['BirthDate']];
                    $data['subject_dob'] = $dob ? strtotime($dob) : null;
                }

                $factory->create('oncore_subject_diff', $data);
            }
        }

        REDCap::logEvent('OnCore Subjects Diff rebuild', '', '', null, null, PROJECT_ID);
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

    /**
     * Aux function that converts a snake case string into camel case.
     */
    protected function _toCamelCase($string) {
        $output = '';

        foreach (explode('_', $string) as $part) {
            $output .= ucfirst($part);
        }

        return $output;
    }
}
