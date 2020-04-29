<?php

namespace OnCoreClient\Entity;

use OnCoreClient\ExternalModule\ExternalModule;
use REDCapEntity\StatusMessageQueue;
use REDCapEntity\EntityList;

// TODO: Consider decoupling from EntityList to allow EntityForm use
class PermissionChecker extends EntityList {

    public static function userOnStaff($EntityList) {

        if (!$protocol_no = $EntityList->module->getProjectSetting('protocol_no')) {
            StatusMessageQueue::enqueue("Please choose a protocol number.", 'error');
            return;
        }

        if ( ($EntityList->module->getSystemSetting('autopopulate_staff_id')) && ($server_var = $EntityList->module->getSystemSetting('staff_id_server_variable_name')) ) {
            if ($server_val = $_SERVER[$server_var]) {
                // Create or update user credentials

                if ($EntityList->module->getSystemSetting('use_custom_database') == "1") {
                    // hack to make user_id a pseudo primary key
                    if ($id = $EntityList->entityFactory->query('oncore_staff_identifier')->condition('user_id', USERID)->execute()) {
                        $id = array_values($id)[0]->getId();
                    }
                    $entity = $EntityList->entityFactory->getInstance('oncore_staff_identifier', $id); // null id defaults to a new entry

                    if ($entity->setData(['staff_id' => $server_val,
                                'user_id' => USERID])) {
                        $entity->save();
                    } else {
                        //TODO: handle errors with entity setData if they arise
                    }
                } else {
                    // do server stuff
                    $sql = "UPDATE redcap_user_information SET user_inst_id='" . $server_val . "' WHERE username='" . USERID . "'";
                    $EntityList->module->query($sql);
                }
            }
        }

        $query = $EntityList->entityFactory->query('oncore_protocol_staff');

        $query
            ->addField('e.stop_date', 'stop_date')
            ->addField('e.staff_id', 'staff_id')
            ->addField('e.protocol_no', 'protocol_no')
            ->condition('protocol_no', $protocol_no);

        if ($EntityList->module->getSystemSetting('use_custom_database') == "1") {
            $query
                ->join('redcap_entity_oncore_staff_identifier', 'a', 'staff_id = a.staff_id')
                ->condition('a.user_id', USERID);
        } else {
            $sql = "SELECT user_inst_id FROM redcap_user_information WHERE username='" . USERID . "'";
            $query->condition('staff_id', ($EntityList->module->query($sql)->fetch_assoc()['user_inst_id']));
        }

        $query->execute();
        $sql_result = array_values($query->getRawResults())[0];

        if (!$sql_result && (SUPER_USER != 1)) {
            StatusMessageQueue::enqueue("You are not authorized to access this protocol.", 'error');
            return;
        }

        if (!empty($sql_result['stop_date']) && $sql_result['stop_date'] <= date('Y-m-d')) {
            StatusMessageQueue::enqueue("You are no longer authorized to access this protocol.", 'error');
            return;
        }

        return true;
    }
}
