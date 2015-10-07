<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model {

    protected $table = 'business';
    protected $primaryKey = 'business_id';
    public $timestamps = false;

    public static function name($business_id)
    {
        return Business::where('business_id', '=', $business_id)->select(array('name'))->first()->name;
    }

    public static function localAddress($business_id)
    {
        return Business::where('business_id', '=', $business_id)->select(array('local_address'))->first()->local_address;
    }

    public static function openHour($business_id)
    {
        return Business::where('business_id', '=', $business_id)->select(array('open_hour'))->first()->open_hour;
    }

    public static function openMinute($business_id)
    {
        return Business::where('business_id', '=', $business_id)->select(array('open_minute'))->first()->open_minute;
    }

    public static function openAMPM($business_id)
    {
        return Business::where('business_id', '=', $business_id)->select(array('open_ampm'))->first()->open_ampm;
    }

    public static function closeHour($business_id)
    {
        return Business::where('business_id', '=', $business_id)->select(array('close_hour'))->first()->close_hour;
    }

    public static function closeMinute($business_id)
    {
        return Business::where('business_id', '=', $business_id)->select(array('close_minute'))->first()->close_minute;
    }

    public static function closeAMPM($business_id)
    {
        return Business::where('business_id', '=', $business_id)->select(array('close_ampm'))->first()->close_ampm;
    }

    public static function industry($business_id)
    {
        return Business::where('business_id', '=', $business_id)->select(array('industry'))->first()->industry;
    }

    /** functions to get the Business name **/
    public static function getBusinessNameByTerminalId($terminal_id)
    {
        return Business::getBusinessNameByServiceId(Terminal::serviceId($terminal_id));
    }

    public static function getBusinessNameByServiceId($service_id)
    {
        return Business::getBusinessNameByBranchId(Service::branchId($service_id));
    }

    public static function getBusinessNameByBranchId($branch_id)
    {
        return Business::name(Branch::businessId($branch_id));
    }

    public static function getBusinessIdByTerminalId($terminal_id)
    {
        return Business::getBusinessIdByServiceId(Terminal::serviceId($terminal_id));
    }

    public static function getBusinessIdByServiceId($service_id)
    {
        return Branch::businessId(Service::branchId($service_id));
    }

    public static function getBusinessDetails($business_id)
    {
        $business = Business::where('business_id', '=', $business_id)->get()->first();
        $terminals = Terminal::getTerminalsByBusinessId($business_id);
        $terminals = Terminal::getAssignedTerminalWithUsers($terminals);
        $analytics = Analytics::getBusinessAnalytics($business_id);
        $first_service = Service::getFirstServiceOfBusiness($business_id);
        $business_details = [
            'business_id' => $business_id,
            'business_name' => $business->name,
            'business_address' => $business->local_address,
            'facebook_url' => $business->fb_url,
            'industry' => $business->industry,
            'time_open' => Helper::mergeTime($business->open_hour, $business->open_minute, $business->open_ampm),
            'time_closed' => Helper::mergeTime($business->close_hour, $business->close_minute, $business->close_ampm),
            'timezone' => $business->timezone, //ARA Added timezone
            'queue_limit' => $business->queue_limit, /* RDH Added queue_limit to Edit Business Page */
            'terminal_specific_issue' => QueueSettings::terminalSpecificIssue($first_service->service_id),
            'sms_current_number' => QueueSettings::smsCurrentNumber($first_service->service_id),
            'sms_1_ahead' => QueueSettings::smsOneAhead($first_service->service_id),
            'sms_5_ahead' => QueueSettings::smsFiveAhead($first_service->service_id),
            'sms_10_ahead' => QueueSettings::smsTenAhead($first_service->service_id),
            'sms_blank_ahead' => QueueSettings::smsBlankAhead($first_service->service_id),
            'input_sms_field' => QueueSettings::inputSmsField($first_service->service_id),
            'allow_remote' => QueueSettings::allowRemote($first_service->service_id),
            'remote_limit' => QueueSettings::remoteLimit($first_service->service_id),
            'terminals' => $terminals,
            'analytics' => $analytics,
            'features' => Business::getBusinessFeatures($business_id),
            'sms_gateway' => QueueSettings::smsGateway($first_service->service_id),
            'allowed_businesses' => Business::getForwardingAllowedBusinesses($business_id),
        ];


        $sms_gateway_api = unserialize(QueueSettings::smsGatewayApi($first_service->service_id));
        if($business_details['sms_gateway'] == 'frontline_sms' && $sms_gateway_api){
            $business_details['frontline_sms_url'] = $sms_gateway_api['frontline_sms_url'];
            $business_details['frontline_sms_api_key'] = $sms_gateway_api['frontline_sms_api_key'];
        }elseif($business_details['sms_gateway'] == 'twilio' && $sms_gateway_api){
            if($sms_gateway_api['twilio_account_sid'] == TWILIO_ACCOUNT_SID &&
                $sms_gateway_api['twilio_auth_token'] == TWILIO_AUTH_TOKEN &&
                $sms_gateway_api['twilio_phone_number'] == TWILIO_PHONE_NUMBER){
                $business_details['sms_gateway'] = NULL;
                $business_details['twilio_account_sid'] = NULL;
                $business_details['twilio_auth_token'] = NULL;
                $business_details['twilio_phone_number'] = NULL;
            }else{
                $business_details['twilio_account_sid'] = $sms_gateway_api['twilio_account_sid'];
                $business_details['twilio_auth_token'] = $sms_gateway_api['twilio_auth_token'];
                $business_details['twilio_phone_number'] = $sms_gateway_api['twilio_phone_number'];
            }
        }else{
            $business_details['sms_gateway'] = NULL;
            $business_details['twilio_account_sid'] = NULL;
            $business_details['twilio_auth_token'] = NULL;
            $business_details['twilio_phone_number'] = NULL;
        }


        return $business_details;
    }

    /*
     * @author: CSD
     * @description: fetch business row by business id
     * @return business row with all branches, services and terminals
     */
    public static function getBusinessArray($business_id)
    {
        $business = Business::where('business_id', '=', $business_id)->get()->first();
        $branches = [];
        $services = [];
        $terminals = [];
        $rawBranches = Branch::getBranchesByBusinessId($business->business_id);

        foreach ($rawBranches as $branch) {
            array_push($branches, $branch);
            $rawServices = Service::getServicesByBranchId($branch->branch_id);

            foreach ($rawServices as $service) {
                array_push($services, $service);

                $rawTerminals = Terminal::getTerminalsByServiceId($service->service_id);

                /* get terminal id's of assigned terminals */
                $user_id = isset(Auth::user()->user_id) ? Auth::user()->user_id : 0; // ARA Checks if user has been logged in
                $terminalAssignments = TerminalUser::getTerminalAssignement($user_id);
                $terminalIds = [];
                foreach ($terminalAssignments as $assignment) {
                    array_push($terminalIds, $assignment['terminal_id']);
                }
                /* end */

                foreach ($rawTerminals as $terminal) {
                    if (in_array($terminal['terminal_id'], $terminalIds)) {
                        $terminal['assigned'] = 1;
                    } else {
                        $terminal['assigned'] = 0;
                    }
                    array_push($terminals, $terminal);
                }
            }
        }

        $business->branches = $branches;
        $business->services = $services;
        $business->terminals = $terminals;

        return $business;
    }

    public static function getBusinessByNameCountryIndustryTimeopen($name, $country, $industry, $time_open = null, $timezone = null)
    {
        if ($time_open) {
            $time_open_arr = Helper::parseTime($time_open);
        } else {
            $time_open_arr['hour'] = '';
            $time_open_arr['min'] = '';
            $time_open_arr['ampm'] = '';
        }

        if ($industry == 'Industry') {
            $industry = '';
        }

        if(is_numeric($timezone)){
            $timezones = Helper::timezoneOffsetToNameArray($timezone);
        }else{
            $timezones = [$timezone];
        }

        //ARA this makes editing queries easier
        $query = Business::where('name', 'LIKE', '%' . $name . '%')
            //->where('local_address', 'LIKE', '%' . $country . '%')
            ->where('latitude', '<=', $country['ne_lat'])
            ->where('latitude', '>=', $country['sw_lat'])
            ->where('longitude', '<=', $country['ne_lng'])
            ->where('longitude', '>=', $country['sw_lng'])
            ->where('industry', 'LIKE', '%' . $industry . '%');

        if($name == ''){
            $query->whereIn('timezone', $timezones);
        }

        if ($time_open_arr['ampm'] == 'PM' && $time_open_arr['min'] == '00') {
            $query->where('open_ampm', '=', 'PM')
                ->where('open_hour', '>=', $time_open_arr['hour']);
        } elseif ($time_open_arr['ampm'] == 'PM' && $time_open_arr['min'] == '30') {
            $query->where('open_ampm', '=', 'PM')
                ->whereRaw('open_hour > ? OR (open_hour = ? AND open_minute = ?)',
                    array($time_open_arr['hour'], $time_open_arr['hour'], '30'));
        } elseif ($time_open_arr['ampm'] == 'AM' && $time_open_arr['min'] == '00') {
            $query->whereRaw('(open_hour >= ? AND open_ampm = ?) OR (open_hour < ? AND open_ampm = ?)',
                array($time_open_arr['hour'], 'AM', $time_open_arr['hour'], 'PM'));
        } elseif ($time_open_arr['ampm'] == 'AM' && $time_open_arr['min'] == '30') {
            $query->whereRaw('(open_hour > ? AND open_ampm = ?) OR (open_hour < ? AND open_ampm = ?) OR (open_hour = ? AND open_minute = ? AND open_ampm = ?)',
                array($time_open_arr['hour'], 'AM', $time_open_arr['hour'], 'PM', $time_open_arr['hour'], '30', 'AM'));
        }
        return $query->get();
    }

    public static function businessExistsByNameByAddress($business_name, $business_address)
    {
        return Business::where('name', '=', $business_name)
            ->where('local_address', '=', $business_address)
            ->get();
    }

    public static function deleteBusinessByBusinessId($business_id)
    {
        Business::where('business_id', '=', $business_id)->delete();

        // PAG delete also the json file
        unlink(public_path() . '/json/' . $business_id . '.json');
    }

    /*
     * @author: CSD
     * @description get called numbers of all services under each business
     */
    public static function getPopularBusinesses()
    {
        $business_ids = Business::select('business_id')->get();
        $business_arr = [];
        foreach ($business_ids as $business) {
            array_push($business_arr, Business::getBusinessArray($business->business_id));
        }

        $date = new DateTime();
        $newDate = $date->modify('-7 days');

        $enddate = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $startdate = mktime(0, 0, 0, $newDate->format('m'), $newDate->format('d'), $newDate->format('Y'));

        $currMax = 0;
        $new_bizz_array = [];
        foreach ($business_arr as $b_id => $business) {
            $count = Analytics::getTotalNumbersCalledByBusinessIdWithDate($business->business_id, $startdate, $enddate);
            $business_arr[$b_id]->count = $count;
            if ($count > $currMax) {
                array_unshift($new_bizz_array, $business_arr[$b_id]);
                $currMax = $count;
            } else {
                array_push($new_bizz_array, $business_arr[$b_id]);
            }
        }

        return $new_bizz_array;
    }

    public static function getActiveBusinesses()
    {
        /*
        return DB::table('business')->join('branch', 'business.business_id', '=', 'branch.business_id')
          ->join('service', 'branch.branch_id', '=', 'service.branch_id')
          ->join('priority_number', 'service.service_id', '=', 'priority_number.service_id')
          ->join('priority_queue', 'priority_number.track_id', '=', 'priority_queue.track_id')
          ->join('terminal_transaction', 'priority_queue.transaction_number', '=', 'terminal_transaction.transaction_number')
          ->where('terminal_transaction.time_queued', '!=', 0)
          ->select(array('business.business_id', 'business.name', 'business.local_address'))
          ->get();
        */
        $active_businesses = array();
        $businesses = Business::all();
        foreach ($businesses as $count => $business) {
            $branches = Branch::getBranchesByBusinessId($business->business_id);
            foreach ($branches as $count2 => $branch) {
                $services = Service::getServicesByBranchId($branch->branch_id);
                foreach ($services as $count3 => $service) {
                    $priority_numbers = PriorityNumber::getTrackIdByServiceId($service->service_id);
                    foreach ($priority_numbers as $count4 => $priority_number) {
                        $priority_queues = PriorityQueue::getTransactionNumberByTrackId($priority_number->track_id);
                        foreach ($priority_queues as $count5 => $priority_queue) {
                            $terminal_transactions = TerminalTransaction::getTimesByTransactionNumber($priority_queue->transaction_number);
                            foreach ($terminal_transactions as $count6 => $terminal_transaction) {
                                $grace_period = time() - $terminal_transaction->time_queued; // issued time must be on the current day to count as active
                                if ($terminal_transaction->time_queued != 0
                                    && $terminal_transaction->time_completed == 0
                                    && $terminal_transaction->time_removed == 0
                                    && $grace_period < 86400
                                ) { // 1 day; 60secs * 60 min * 24 hours
                                    $active_businesses[$business->business_id] = array(
                                        'local_address' => $business->local_address,
                                        'name' => $business->name,
                                    );
                                    break;
                                }
                            }
                            if (array_key_exists($business->business_id, $active_businesses)) {
                                break;
                            }
                        }
                        if (array_key_exists($business->business_id, $active_businesses)) {
                            break;
                        }
                    }
                    if (array_key_exists($business->business_id, $active_businesses)) {
                        break;
                    }
                }
                if (array_key_exists($business->business_id, $active_businesses)) {
                    break;
                }
            }
        }

        return $active_businesses;
    }

    public static function getNewBusinesses()
    {
        return Business::orderBy('business_id', 'desc')->limit(5)->get(); // RDH Changed implementation to only include newest 4 businesses
    }

    public static function getProcessingBusinesses()
    {
        $pool = array();
        //$new_pool = array();
        $active_businesses = array();
        $json_path = public_path() . '/json';
        $iter = new DirectoryIterator($json_path);
        foreach ($iter as $item) {
            if ($item != '.' && $item != '..' && $item->getFilename() != '.DS_Store' /* Mac Prob */) {
                if ($item->isDir()) {
                    Business::getProcessingBusinesses("$json_path/$item");
                } else {
                    $filepath = $json_path . '/' . $item->getFilename();
                    $data = json_decode(file_get_contents($filepath));
                    if ($data->box1->number != '') {
                        $pool[] = basename($item->getFilename(), '.json');
                    } elseif (isset($data->box2)) {
                        if ($data->box2->number != '') {
                            $pool[] = basename($item->getFilename(), '.json');
                        }
                    } elseif (isset($data->box3)) {
                        if ($data->box3->number != '') {
                            $pool[] = basename($item->getFilename(), '.json');
                        }
                    } elseif (isset($data->box4)) {
                        if ($data->box4->number != '') {
                            $pool[] = basename($item->getFilename(), '.json');
                        }
                    } elseif (isset($data->box5)) {
                        if ($data->box5->number != '') {
                            $pool[] = basename($item->getFilename(), '.json');
                        }
                    } elseif (isset($data->box6)) {
                        if ($data->box6->number != '') {
                            $pool[] = basename($item->getFilename(), '.json');
                        }
                    } elseif ($data->get_num != '') {
                        $pool[] = basename($item->getFilename(), '.json');
                    }
                }
            }
        }

        // if there are more than 5 currently processing businesses, then return
        // a randomized result set
//      if (sizeof($pool) > 5) {
//        $business_count = 0;
//        shuffle($pool);
//        foreach ($pool as $key => $val) {
//          if ($business_count == 5) break; // only show 5 random businesses
//          if (Business::where('business_id', '=', $val)->exists()) {
//            $active_businesses[$val]['business_id'] = $val;
//            $active_businesses[$val]['name'] = Business::name($val);
//            $active_businesses[$val]['local_address'] = Business::localAddress($val);
//            $business_count++;
//          }
//        }
//      }
//      else {
//        foreach ($pool as $key => $val) {
//            $active_businesses[$val]['business_id'] = $val;
//            $active_businesses[$val]['name'] = Business::name($val);
//            $active_businesses[$val]['local_address'] = Business::localAddress($val);
//        }
//      }

        //ARA no need to randomize active businesses since all businesses will now be shown
        foreach ($pool as $key => $val) {
//          if ($business_count == 7) break; // only show 7 random businesses as homepage businesses limit
            if (Business::where('business_id', '=', $val)->exists()) {
                $active_businesses[$val]['business_id'] = $val;
            }
        }

        return $active_businesses;
    }

    /**
     * ARA merges active businesses and other businesses
     * @return array
     */
    public static function getDashboardBusinesses()
    {
        $businesses = array();
        $active_businesses = Business::getProcessingBusinesses();
        $all_businesses = Business::where('status', '=', 1)->get()->toArray();
        foreach ($all_businesses as $index => $business) {
            $open_time_string = $business['open_hour'] . ':' . Helper::doubleZero($business['open_minute']) . ' ' . $business['open_ampm'];
            $closing_time_string = $business['close_hour'] . ':' . Helper::doubleZero($business['close_minute']) . ' ' . $business['close_ampm'];
            $waiting_time = Analytics::getWaitingTimeString($business['business_id']); //get time before the next available number is called. should be in minutes

            //ARA more info for business cards
            $first_service = Service::getFirstServiceOfBusiness($business['business_id']);
            $all_numbers = ProcessQueue::allNumbers($first_service->service_id);
            $last_number_called = count($all_numbers->called_numbers) > 0 ? $all_numbers->called_numbers[0]['priority_number'] : 'none';
            $next_number = $all_numbers->next_number;
            $is_calling = count($all_numbers->called_numbers) > 0 ? true : false;
            $is_issuing = count($all_numbers->uncalled_numbers) + count($all_numbers->timebound_numbers) > 0 ? true : false;
            $last_active = Analytics::getLastActive($business['business_id']);

            $business_details = array(
                'business_id' => $business['business_id'],
                'name' => $business['name'],
                'local_address' => $business['local_address'],
                'open_time' => $open_time_string,
                'close_time' => $closing_time_string,
                'waiting_time' => $waiting_time,

                //ARA more info for business cards
                'last_number_called' => $last_number_called, //ok
                'next_available_number' => $next_number, //ok
                'is_calling' => $is_calling, //ok
                'is_issuing' => $is_issuing, //ok
                'last_active' => $last_active
            );

            //Add active business to top of list
            if (isset($active_businesses[$business['business_id']])) {
                array_unshift($businesses, $business_details);
            } else {
                array_push($businesses, $business_details);
            }
        }
        return $businesses;
    }

    public static function getBusinessByLatitudeLongitude($latitude, $longitude, $timezone)
    {
        $max_lat = $latitude + 0.06;
        $max_long = $longitude + 0.06;
        $min_lat = $latitude - 0.06;
        $min_long = $longitude - 0.06;
        $timezones = Helper::timezoneOffsetToNameArray($timezone);
        return Business::where('latitude', '>=', $min_lat)
            ->where('latitude', '<=', $max_lat)
            ->where('longitude', '>=', $min_long)
            ->where('longitude', '<=', $max_long)
            ->whereIn('timezone', $timezones)
            ->get();
    }

    public static function processingBusinessBool($business_id)
    {
        /*
          $filepath = public_path() . '/json/' . $business_id . '.json';
          $data = json_decode(file_get_contents($filepath));
          if ($data->box1->number != '') {
              return TRUE;
          } elseif (isset($data->box2)) {
              if ($data->box2->number != '') {
                  return TRUE;
              }
          } elseif (isset($data->box3)) {
              if ($data->box3->number != '') {
                  return TRUE;
              }
          } elseif (isset($data->box4)) {
              if ($data->box4->number != '') {
                  return TRUE;
              }
          } elseif (isset($data->box5)) {
              if ($data->box5->number != '') {
                  return TRUE;
              }
          } elseif (isset($data->box6)) {
              if ($data->box6->number != '') {
                  return TRUE;
              }
          } elseif ($data->get_num != '') {
              return TRUE;
          }
          return FALSE;
        */

        // will be using Aunne's data from process queue to determine if the business is active or inactive
        $first_service = Service::getFirstServiceOfBusiness($business_id);
        $all_numbers = ProcessQueue::allNumbers($first_service->service_id);
        $is_calling = count($all_numbers->called_numbers) > 0 ? true : false;
        $is_issuing = count($all_numbers->uncalled_numbers) + count($all_numbers->timebound_numbers) > 0 ? true : false;
        return $is_calling || $is_issuing;
    }

    public static function getBusinessIdByName($business_name){
        return Business::where('name', $business_name)->get();
    }

    public static function countBusinessByRange($start_date, $end_date){
        $temp_start_date = date("Y/m/d", $start_date);
        $temp_end_date = date("Y/m/d", $end_date);
        return Business::where('registration_date', '>=', $temp_start_date)->where('registration_date','<', $temp_end_date)->count();
    }

    public static function getAllBusinessNames(){
        return Business::select('business_id', 'name')->get();
    }

    public static function getBusinessIdsByIndustry($industry){
        return Business::select('business_id')->where('industry',"=", $industry)->get();
    }

    public static function getBusinessIdsByCountry($country){
        return Business::select('business_id')->where('local_address', 'LIKE', '%' . $country . '%')->get();
    }

    public static function getAvailableIndustries(){
        return Business::select('industry')->groupBy('industry')->get();
    }

    public static function saveBusinessFeatures($business_id, $features = array()){
        Business::where('business_id', '=', $business_id)->update(['business_features' => serialize($features)]);
    }

    public static function getBusinessFeatures($business_id){
        $serialized = Business::where('business_id', '=', $business_id)->select('business_features')->first()->business_features;
        return unserialize($serialized);
    }

    public static function getBusinessAccessKey($business_id){
        return Crypt::encrypt($business_id);
    }

    /**
     * Gets the businesses that you allow to forward
     * @param $business_id
     * @return mixed
     */
    public static function getForwardingAllowedBusinesses($business_id){
        return DB::table('queue_forward_permissions')
            ->where('queue_forward_permissions.business_id', '=', $business_id)
            ->join('business', 'business.business_id', '=', 'queue_forward_permissions.forwarder_id')
            ->select('business.business_id', 'business.name')
            ->get();
    }

    public static function getForwarderAllowedBusinesses($business_id){
        return DB::table('queue_forward_permissions')
            ->where('queue_forward_permissions.forwarder_id', '=', $business_id)
            ->join('business', 'business.business_id', '=', 'queue_forward_permissions.business_id')
            ->select('business.business_id', 'business.name')
            ->get();
    }

    public static function getForwarderAllowedInBusiness($business_id, $forwarder_id){
        return DB::table('queue_forward_permissions')->where('business_id', '=', $business_id)->where('forwarder_id', '=', $forwarder_id)->first();
    }

}
