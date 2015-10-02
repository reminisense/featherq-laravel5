<?php
/**
 * Created by PhpStorm.
 * User: USER
 * Date: 1/28/15
 * Time: 6:55 PM
 */
class QueueSettingsController extends BaseController{
    public function getUpdate($business_id, $field, $value){
        $first_branch = Branch::where('business_id', '=', $business_id)->first();
        $first_service = Service::where('branch_id', '=', $first_branch->branch_id)->first();

        if(QueueSettings::serviceExists($first_service->service_id)){
            QueueSettings::updateQueueSetting($first_service->service_id, $field, $value);
        }else{
            QueueSettings::createQueueSetting([
                'service_id' => $first_service->service_id,
                'date' => mktime(0, 0, 0, date('m'), date('d'), date('Y')),
                $field => $value
            ]);
        }

        return json_encode(['success' => 1]);
    }

    public function getAllvalues($service_id){
        $values = QueueSettings::getServiceQueueSettings($service_id);
        $queue_settings = [
            'number_start' => $values->number_start,
            'number_limit' => $values->number_limit,
            'auto_issue' => $values->auto_issue,
            'allow_sms' => $values->allow_sms,
            'allow_remote' => $values->allow_remote,
        ];

        return json_encode(['success' => 1, 'queue_settings' => $queue_settings]);
    }

    public function getAssignterminal($terminal_id, $user_id){
        TerminalManager::addToTerminal($user_id, $terminal_id);
        return json_encode(['success' => 1]);
    }
}