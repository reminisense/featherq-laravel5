<?php
/**
 *
 * Should contain business related functions
 *
 * Created by PhpStorm.
 * User: USER
 * Date: 1/22/15
 * Time: 5:10 PM
 */


class BusinessController extends BaseController{


    public function getMyBusiness(){
        if(Auth::check()){
            $businesses = UserBusiness::getAllBusinessIdByOwner(Helper::userId());
            $my_terminals = TerminalUser::getTerminalAssignement(Helper::userId());
            $assigned_businesses = [];
            if (count($my_terminals) > 0){
                foreach($my_terminals as $terminal){
                    $bid = Business::getBusinessIdByTerminalId($terminal['terminal_id']);
                    if(!isset($assigned_businesses[$bid])){
                        $assigned_businesses[$bid] = [
                            'business_id' => $bid,
                            'name' => Business::name($bid),
                            'terminals' => [
                                [
                                    'terminal_id' => $terminal['terminal_id'],
                                    'name' => Terminal::name($terminal['terminal_id'])
                                ]
                            ]
                        ];
                    }else{
                        array_push($assigned_businesses[$bid]['terminals'], [
                            'terminal_id' => $terminal['terminal_id'],
                            'name' => Terminal::name($terminal['terminal_id'])
                        ]);
                    }
                }
            }

            if (count($businesses) > 0){
                $business = $businesses[0];
                $business_id = $business->business_id;
                unset($assigned_businesses[$business->business_id]);
                $first_service = Service::getFirstServiceOfBusiness($business_id);
                $terminals = Terminal::getTerminalsByServiceId($first_service->service_id);
                $first_terminal = count($terminals) > 0 ? $terminals[0]['terminal_id'] : null;
                return View::make('business.my-business')
                    //->with('user_id', Helper::userId()) //ARA - moved assignment to filters.php
                    ->with('business_id', $business_id)
                    ->with('assigned_businesses', $assigned_businesses)
                    ->with('first_terminal', $first_terminal);
            } else {
                return View::make('business.my-business')
                    ->with('assigned_businesses', $assigned_businesses);
            }
        } else {
            return Redirect::to('/');
        }
    }

    /*
     * @author: CSD
     * @description: post business data from initial setup modal form
     * @return success on successful business create
     */
    public function postSetupBusiness()
    {
      if (Auth::check() && Helper::isNotAnOwner(Helper::userId())) { // PAG added permission checking
        $business_data = $_POST;
        $business = new Business();
        $business_check = Business::businessExistsByNameByAddress($business_data['business_name'], $business_data['business_address']);

        if (count($business_check) != 1) {
          $business->name = $business_data['business_name'];
          $business->local_address = $business_data['business_address'];
          $business->industry = $business_data['industry'];
          $business->longitude = $business_data['longitude'];
          $business->latitude = $business_data['latitude'];

          $time_open_arr = Helper::parseTime($business_data['time_open']);
          $business->open_hour = $time_open_arr['hour'];
          $business->open_minute = $time_open_arr['min'];
          $business->open_ampm = $time_open_arr['ampm'];

          $time_close_arr = Helper::parseTime($business_data['time_close']);
          $business->close_hour = $time_close_arr['hour'];
          $business->close_minute = $time_close_arr['min'];
          $business->close_ampm = $time_close_arr['ampm'];

          /*
           * @author CSD
           * @description:
           * set default num terminals to 3 when creating a business
           * set default queue limit to 9999 max
           */
          $business->queue_limit = 9999;
          $business->num_terminals = 1;
          $business->save();

          $business_user = new UserBusiness();
          $business_user->user_id = $business_data['user_id'];
          $business_user->business_id = $business->business_id;

          $business->timezone = $business_data['timezone'];

          /* Timezone is already set in config/app.php
          date_default_timezone_set("Asia/Manila"); // Manila Timezone for now but this depends on business location
          */

          $contents = '
                {
                  "box1": {
                    "number": "1",
                    "terminal": "",
                    "rank": ""
                  },
                  "box2": {
                    "number": "2",
                    "terminal": "",
                    "rank": ""
                  },
                  "box3": {
                    "number": "3",
                    "terminal": "",
                    "rank": ""
                  },
                  "box4": {
                    "number": "4",
                    "terminal": "",
                    "rank": ""
                  },
                  "box5": {
                    "number": "5",
                    "terminal": "",
                    "rank": ""
                  },
                  "box6": {
                    "number": "6",
                    "terminal": "",
                    "rank": ""
                  },
                  "get_num": " ",
                  "display": "1-6",
                  "show_issued": true,
                  "ad_image": "",
                  "ad_video": "\/\/www.youtube.com\/embed\/EMnDdH8fdEc",
                  "ad_type": "image",
                  "turn_on_tv": false,
                  "tv_channel": "",
                  "date": "' . date("mdy") . '",
                  "ticker_message" : "",
                  "ticker_message2" : "",
                  "ticker_message3" : "",
                  "ticker_message4" : "",
                  "ticker_message5" : ""
                }
            ';

          File::put(public_path() . '/json/' . $business->business_id . '.json', $contents);
          $business_user->save();

          $branch_id = Branch::createBusinessBranch($business->business_id, $business->name);
          $service_id = Service::createBranchService($branch_id, $business->name);

          /* @CSD Auto issue on business create */
          $issueController = new IssueNumberController();
          $issueController->getMultiple($service_id, 10);
          /* Auto issue end */

          $terminals = Terminal::createBranchServiceTerminal(Auth::user()->user_id, $service_id, $business->num_terminals);
          if ($business->save()) {
            return json_encode([
              'success' => 1,
              'terminals' => $terminals
            ]);
          }
          else {
            return json_encode([
              'success' => 0,
              'error' => 'Something went wrong while saving your business.'
            ]);
          }
        }
        else {
          $error = "Business name already exists with the same business address.";
          return json_encode([
            'success' => 0,
            'error' => $error
          ]);
        }
      }
      else {
        return json_encode(array('success' => 0, 'error' => 'You are not allowed to access this function or you already have a business account.'));
      }

    }

    /*
     * @author: CSD
     * @description: post edit business details
     * @return updated business row
     */
    public function postEditBusiness(){
        $business_data = Input::all();
      if (Helper::isBusinessOwner($business_data['business_id'], Helper::userId())) { // PAG added permission checking
        $business = Business::find($business_data['business_id']);

        if ($this->validateBusinessNameBusinessAddress($business, $business_data)) {
          $business->name = $business_data['business_name'];
          $business->local_address = $business_data['business_address'];
          $business->industry = $business_data['industry'];
          $business->fb_url = $business_data['facebook_url'];
          $business->timezone = $business_data['timezone']; //ARA Added timezone property

          $time_open_arr = Helper::parseTime($business_data['time_open']);
          $business->open_hour = $time_open_arr['hour'];
          $business->open_minute = $time_open_arr['min'];
          $business->open_ampm = $time_open_arr['ampm'];

          $time_close_arr = Helper::parseTime($business_data['time_close']);
          $business->close_hour = $time_close_arr['hour'];
          $business->close_minute = $time_close_arr['min'];
          $business->close_ampm = $time_close_arr['ampm'];

          $business->queue_limit = $business_data['queue_limit']; /* RDH Added queue_limit to Edit Business Page */

          $business->save();

            //ARA For queue settings terminal-specific numbers
            $queue_settings = new QueueSettingsController();
            $queue_settings->getUpdate($business['business_id'], 'number_limit', $business_data['queue_limit']);
            $queue_settings->getUpdate($business['business_id'], 'terminal_specific_issue', $business_data['terminal_specific_issue']);
            $queue_settings->getUpdate($business['business_id'], 'sms_current_number', $business_data['sms_current_number']);
            $queue_settings->getUpdate($business['business_id'], 'sms_1_ahead', $business_data['sms_1_ahead']);
            $queue_settings->getUpdate($business['business_id'], 'sms_5_ahead', $business_data['sms_5_ahead']);
            $queue_settings->getUpdate($business['business_id'], 'sms_10_ahead', $business_data['sms_10_ahead']);
            $queue_settings->getUpdate($business['business_id'], 'sms_blank_ahead', $business_data['sms_blank_ahead']);
            $queue_settings->getUpdate($business['business_id'], 'input_sms_field', $business_data['input_sms_field']);
            $queue_settings->getUpdate($business['business_id'], 'allow_remote', $business_data['allow_remote']);
            $queue_settings->getUpdate($business['business_id'], 'remote_limit', $business_data['remote_limit']);

            //sms settings
            $sms_api_data = [];
            $sms_gateway_api = NULL;
            if($business_data['sms_gateway'] == 'frontline_sms'){
                $sms_api_data = [
                    'frontline_sms_url' => $business_data['frontline_sms_url'],
                    'frontline_sms_api_key' => $business_data['frontline_sms_api_key'],
                ];
                $sms_gateway_api = serialize($sms_api_data);
            }elseif($business_data['sms_gateway'] == 'twilio'){
                if($business_data['twilio_account_sid'] == TWILIO_ACCOUNT_SID &&
                    $business_data['twilio_auth_token'] == TWILIO_AUTH_TOKEN &&
                    $business_data['twilio_phone_number'] == TWILIO_PHONE_NUMBER){
                        $business_data['sms_gateway'] = NULL;
                        $sms_gateway_api = NULL;
                }else{
                    $sms_api_data = [
                        'twilio_account_sid' => $business_data['twilio_account_sid'],
                        'twilio_auth_token' => $business_data['twilio_auth_token'],
                        'twilio_phone_number' => $business_data['twilio_phone_number'],
                    ];
                    $sms_gateway_api = serialize($sms_api_data);
                }
            }
            $queue_settings->getUpdate($business['business_id'], 'sms_gateway', $business_data['sms_gateway']);
            $queue_settings->getUpdate($business['business_id'], 'sms_gateway_api', $sms_gateway_api);
            $business = Business::getBusinessDetails($business->business_id);
            return json_encode([
                'success' => 1,
                'business' => $business
            ]);
        } else {
            return json_encode([
                'success' => 0,
                'error' => 'Business name already exists with the same business address.'
            ]);
        }
      }
      else {
        return json_encode([
          'success' => 0,
          'error' => 'You are not allowed to access this function.',
        ]);
      }
    }

    public function getPdfDownload($business_id){
      if (Helper::isBusinessOwner($business_id, Helper::userId())) { // PAG added permission checking
        $business_name = Business::name($business_id);
        $business_address = Business::localAddress($business_id);

        $businesslink = $this->make_bitly_url(url('/broadcast/business/' . $business_id), 'reminisense', 'R_553289e06aaf4ca684392d2dbadec0a8', 'json');
        $qr_link = "https://api.qrserver.com/v1/create-qr-code/?data=" . url('/broadcast/business/' . $business_id) . "&size=302x302"; // CSD Updated QR Link

        $data = [
          'business_name' => $business_name,
          'business_address' => $business_address,
          'qr_code' => $qr_link,
          'shortlink' => $businesslink
        ];

        $pdf = PDF::loadView('pdf.pdftemplate', $data);
        return $pdf->stream($business_name . '.pdf');
      }
      else {
        return json_encode(array('status' => 0, 'message', 'You are not allowed to access this function.'));
      }
    }

    /* make a URL small */
    private function make_bitly_url($url, $login, $appkey, $format = 'xml', $version = '2.0.1') {
        //create the URL
        $bitly = 'http://api.bit.ly/shorten?version='.$version.'&longUrl='.urlencode($url).'&login='.$login.'&apiKey='.$appkey.'&format='.$format;

        //get the url
        //could also use cURL here
        $response = file_get_contents($bitly);

        //parse depending on desired format
        if(strtolower($format) == 'json')
        {
            $json = @json_decode($response,true);
            return $json['results'][$url]['shortUrl'];
        }
        else //xml
        {
            $xml = simplexml_load_string($response);
            return 'http://bit.ly/'.$xml->results->nodeKeyVal->hash;
        }
    }


    public function getBusinessdetails($business_id){
      if (Helper::isBusinessOwner($business_id, Helper::userId())) { // PAG added permission checking
        $business = Business::getBusinessDetails($business_id);
        return json_encode(['success' => 1, 'business' => $business]);
      }
      else {
        return json_encode(array('status' => 0, 'message', 'You are not allowed to access this function.'));
      }
    }

    public function postFilterSearch() {
        $post = json_decode(file_get_contents("php://input"));
        $geolocation = json_decode(file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.$post->country));
        $post->country = array(
            'ne_lat' => $geolocation->results[0]->geometry->bounds->northeast->lat,
            'ne_lng' => $geolocation->results[0]->geometry->bounds->northeast->lng,
            'sw_lat' => $geolocation->results[0]->geometry->bounds->southwest->lat,
            'sw_lng' => $geolocation->results[0]->geometry->bounds->southwest->lng,
        );
        $user_timezone = isset($post->user_timezone) ? $post->user_timezone : 'Asia/Manila'; //ARA set user timezone if any
        $res = Business::getBusinessByNameCountryIndustryTimeopen($post->keyword, $post->country, $post->industry, $post->time_open, $user_timezone);

        $arr = array();
        foreach ($res as $count => $data) {
            $first_service = Service::getFirstServiceOfBusiness($data->business_id);
            $all_numbers = ProcessQueue::allNumbers($first_service->service_id);
            $time_open = $data->open_hour . ':' . Helper::doubleZero($data->open_minute) . ' ' . strtoupper($data->open_ampm);
            $time_close = $data->close_hour . ':' . Helper::doubleZero($data->close_minute) . ' ' . strtoupper($data->close_ampm);
            $arr[] = array(
                'business_id' => $data->business_id,
                'business_name' => $data->name,
                'local_address' => $data->local_address,
                'time_open' => Helper::changeBusinessTimeTimezone($time_open, $data->timezone, $user_timezone),
                'time_close' => Helper::changeBusinessTimeTimezone($time_close, $data->timezone, $user_timezone),
                'waiting_time' => Analytics::getWaitingTimeString($data->business_id),

                //ARA more info for business cards
                'last_number_called' => count($all_numbers->called_numbers) > 0 ? $all_numbers->called_numbers[0]['priority_number'] : 'none', //ok
                'next_available_number' => $all_numbers->next_number, //ok
                //'is_calling' => count($all_numbers->called_numbers) > 0 ? true : false, //ok
                //'is_issuing' => count($all_numbers->uncalled_numbers) + count($all_numbers->timebound_numbers) > 0 ? true : false, //ok
                'last_active' => Analytics::getLastActive($data->business_id),
                'card_bool' => Business::processingBusinessBool($data->business_id), // for info cards marker
            );
        }
        if (Auth::check()) { // dashboard business boxes should be 8; known users will be redirected to dashboard
          $arr = array_slice($arr, 0, 8);
        }
        else { // homepage business boxes should be only 7 to give space for (more business) box; anon users will be redirected to homepage
          $arr = array_slice($arr, 0, 7);
        }
        return json_encode($arr);
    }

    //ARA Added name search while user is typing in searchbar
    public function getNameSearch($keyword){
        $businesses = Business::where('name', 'LIKE', '%' . $keyword . '%')
            ->orWhere('local_address', 'LIKE', '%' . $keyword . '%')
            ->select(array('name', 'local_address'))
            ->get()
            ->toArray();
        return json_encode(array('keywords' => $businesses));
    }

    public function postRemove() {
      $post = json_decode(file_get_contents("php://input"));
      if (Helper::isBusinessOwner($post->business_id, Helper::userId())) { // PAG added permission checking
        Business::deleteBusinessByBusinessId($post->business_id);
        $branches = Branch::getBranchesByBusinessId($post->business_id);
        foreach ($branches as $count => $data) {
          $services = Service::getServicesByBranchId($data->branch_id);
          foreach ($services as $count2 => $data2) {
            $terminals = Terminal::getTerminalsByServiceId($data2->service_id);
            foreach ($terminals as $count3 => $data3) {
              TerminalUser::deleteUserByTerminalId($data3['terminal_id']);
            }
            Terminal::deleteTerminalsByServiceId($data2->service_id);
          }
          Service::deleteServicesByBranchId($data->branch_id);
        }

        Branch::deleteBranchesByBusinessId($post->business_id);
        UserBusiness::deleteUserByBusinessId($post->business_id);

        return json_encode(array('status' => 1));
      }
      else {
        return json_encode(array('status' => 0, 'message' => 'You are not allowed to access this function.'));
      }
    }

    /*
     * @author: CSD
     * @description:
     * checks if business name or business address input is different from db data,
     * if different validate if it exists or not
     *
     */
    private function validateBusinessNameBusinessAddress($dbBusiness, $inputBusiness) {
        if ($dbBusiness->name != $inputBusiness['business_name'] || $dbBusiness->local_address != $inputBusiness['business_address']){
            $row = Business::businessExistsByNameByAddress($inputBusiness['business_name'], $inputBusiness['business_address']);
            if(count($row) == 0){
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    public function postPersonalizedBusinesses() {
        $processing = array();
        $not_processing = array();
        $post = json_decode(file_get_contents("php://input"));
        if ($post) {
            $user_timezone = isset($post->user_timezone) ? $post->user_timezone : 'Asia/Manila'; //ARA set user timezone if any
            if ($post->latitude && $post->longitude) {
                $res = Business::getBusinessByLatitudeLongitude($post->latitude, $post->longitude, $user_timezone); // get location first
                if (!count($res)) $res = Business::all();
            }
            else $res = Business::all();

            foreach ($res as $count => $data) {
                $first_service = Service::getFirstServiceOfBusiness($data->business_id);
                $all_numbers = ProcessQueue::allNumbers($first_service->service_id);

                $time_open = $data->open_hour . ':' . Helper::doubleZero($data->open_minute) . ' ' . strtoupper($data->open_ampm);
                $time_close = $data->close_hour . ':' . Helper::doubleZero($data->close_minute) . ' ' . strtoupper($data->close_ampm);

                // check if business is currently processing numbers
                if (Business::processingBusinessBool($data->business_id)) {
                    if (Auth::check()) {
                        $processing[] = array(
                            'business_id' => $data->business_id,
                            'business_name' => $data->name,
                            'local_address' => $data->local_address,
                            'time_open' => Helper::changeBusinessTimeTimezone($time_open, $data->timezone, $user_timezone),
                            'time_close' => Helper::changeBusinessTimeTimezone($time_close, $data->timezone, $user_timezone),
                            'waiting_time' => Analytics::getWaitingTimeString($data->business_id),

                            //ARA more info for business cards
                            'last_number_called' => count($all_numbers->called_numbers) > 0 ? $all_numbers->called_numbers[0]['priority_number'] : 'none', //ok
                            'next_available_number' => $all_numbers->next_number, //ok
                            //'is_calling' => count($all_numbers->called_numbers) > 0 ? true : false, //ok
                            //'is_issuing' => count($all_numbers->uncalled_numbers) + count($all_numbers->timebound_numbers) > 0 ? true : false, //ok
                            'last_active' => Analytics::getLastActive($data->business_id),
                            'card_bool' => true, // for info cards marker
                        );
                    }
                    else {
                        $processing[] = array(
                            'business_id' => $data->business_id,
                            'business_name' => $data->name,
                            'local_address' => $data->local_address,
                        );
                    }

                }
                else {
                    if (Auth::check()) {
                        $not_processing[] = array(
                            'business_id' => $data->business_id,
                            'business_name' => $data->name,
                            'local_address' => $data->local_address,
                            'time_open' => Helper::changeBusinessTimeTimezone($time_open, $data->timezone, $user_timezone),
                            'time_close' => Helper::changeBusinessTimeTimezone($time_close, $data->timezone, $user_timezone),
                            'waiting_time' => Analytics::getWaitingTimeString($data->business_id),

                            //ARA more info for business cards
                            'last_number_called' => count($all_numbers->called_numbers) > 0 ? $all_numbers->called_numbers[0]['priority_number'] : 'none', //ok
                            'next_available_number' => $all_numbers->next_number, //ok
                            //'is_calling' => count($all_numbers->called_numbers) > 0 ? true : false, //ok
                            //'is_issuing' => count($all_numbers->uncalled_numbers) + count($all_numbers->timebound_numbers) > 0 ? true : false, //ok
                            'last_active' => Analytics::getLastActive($data->business_id),
                            'card_bool' => false, // for info cards marker
                        );
                    }
                    else {
                        $not_processing[] = array(
                            'business_id' => $data->business_id,
                            'business_name' => $data->name,
                            'local_address' => $data->local_address,
                        );
                    }
                }

            }
            $merged_businesses = array_merge($processing, $not_processing);
            if (Auth::check()) { // dashboard business boxes should be 8; known users will be redirected to dashboard
              $merged_businesses = array_slice($merged_businesses, 0, 8);
            }
            else { // homepage business boxes should be only 7 to give space for (more business) box; anon users will be redirected to homepage
              $merged_businesses = array_slice($merged_businesses, 0, 7);
            }
            return json_encode($merged_businesses);
        }
    }

    public function getName($business_id) {
      return json_encode(array('business_name' => Business::name($business_id)));
    }

    public function getGeolocationFixer($business_id) {
        $parsed_location = str_replace(" ", "+", Business::localAddress($business_id));
        $data = json_decode(file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.$parsed_location));
        Business::where('business_id', '=', $business_id)->update(array('longitude' => $data->results[0]->geometry->location->lng, 'latitude' => $data->results[0]->geometry->location->lat));
        echo 'Coordinates set.';
    }

    public function postBusinessAnalytics(){
        $startdate = Input::get('startdate');
        $enddate = Input::get('enddate');
        $business_id = Input::get('business_id');

        $analytics = Analytics::getBusinessAnalytics($business_id, strtotime($startdate), strtotime($enddate));
        return json_encode(array('analytics' => $analytics));
    }

    public function getAccessKey($business_id){
        if(Helper::isBusinessOwner($business_id, Helper::userId())){
            return json_encode(['access_key' => Crypt::encrypt($business_id)]);
        }else{
            return json_encode(['error' => 'Access Denied.']);
        }
    }

    public function postForwardingPermission(){
        $business_id = Input::get('business_id');
        $access_key = Input::get('access_key');
        if(Helper::isBusinessOwner($business_id, Helper::userId())){
            $forwarder_id = Crypt::decrypt($access_key);
            $existing = DB::table('queue_forward_permissions')->where('business_id', '=', $business_id)->where('forwarder_id', '=', $forwarder_id)->first();
            if(!$existing){
                DB::table('queue_forward_permissions')->insert(['business_id' => $business_id, 'forwarder_id' => $forwarder_id]);
            }
            $allowed_businesses = Business::getForwardingAllowedBusinesses($business_id);
            return json_encode(['success' => 1, 'allowed_businesses' => $allowed_businesses]);
        }else{
            return json_encode(['error' => 'Access Denied.']);
        }
    }

    public function postDeletePermission(){
        $business_id = Input::get('business_id');
        $forwarder_id = Input::get('forwarder_id');
        if(Helper::isBusinessOwner($business_id, Helper::userId())) {
            DB::table('queue_forward_permissions')->where('business_id', '=', $business_id)->where('forwarder_id', '=', $forwarder_id)->delete();
            $allowed_businesses = Business::getForwardingAllowedBusinesses($business_id);
            return json_encode(['success' => 1, 'allowed_businesses' => $allowed_businesses]);
        }else{
            return json_encode(['error' => 'Access Denied.']);
        }
    }

    public function getAllowedBusinesses($business_id){
        $allowed_businesses = Business::getForwarderAllowedBusinesses($business_id);
        return json_encode(['success' => 1, 'allowed_businesses' => $allowed_businesses]);
    }
}