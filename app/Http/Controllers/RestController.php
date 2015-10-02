<?php
/**
 * Created by IntelliJ IDEA.
 * User: RUFFY
 * Date: 2/25/2015
 * Time: 10:29 AM
 */

class RestController extends BaseController {

    /**
     * @author Ruffy
     * @param null $quantity when User wants to specify how many to return
     * @return JSON response containing Business objects with: business_id, name, local_address
     */
    public function getPopularBusiness($quantity = null) {
        $businesses = DB::table('business')
            ->select(array('business_id', 'name', 'local_address'))
            ->take($quantity == null ? 5 : $quantity)
            ->get();

        $popular_business = array('popular-business' => $businesses);

        return Response::json($popular_business, 200, array(), JSON_PRETTY_PRINT);
    }

    /**
     * @param null $latitude Latitude
     * @param null $longitude Longitude
     * @return JSON response containing all businesses sorted by distance
     */
    public function getAllBusiness($latitude = null, $longitude = null, $quantity = null) {
        $search_results = DB::table('business')
            ->select(array('business_id', 'name', 'local_address', 'latitude', 'longitude'))
            ->get();

        // calculate near-ness of business
        foreach ( $search_results as $index => $result ) {
            // calculate distance
            $dist = $this->getDistanceFromLatLonInKm($latitude, $longitude, $result->latitude, $result->longitude);
            // assign gotten distance to
            $search_results[$index]->distance = $dist; //
        }

        // sort by distance
        usort($search_results, array('RestController', "compare"));

        // limit results to defined quantity
        $search_results = array_slice($search_results, 0, $quantity, true);

        $found_business = array('search-result' => $search_results);
        return Response::json($found_business, 200, array(), JSON_PRETTY_PRINT);
    }

    /**
     * @author Ruffy
     * @param $query Query string input for searching for a business
     * @return JSON response containing businesses that qualified with the search query
     */
    public function getSearchBusiness($query, $latitude = null, $longitude = null) {
        $search_results = DB::table('business')
            ->where('name', 'LIKE', '%' . $query . '%')
            ->select(array('business_id', 'name', 'local_address', 'latitude', 'longitude'))
            ->get();

        // calculate near-ness of business
        foreach ( $search_results as $index => $result ) {
            // calculate distance
            $dist = $this->getDistanceFromLatLonInKm($latitude, $longitude, $result->latitude, $result->longitude);
            // assign gotten distance to
            $search_results[$index]->distance = $dist; //
        }

        // sort by distance
        usort($search_results, array('RestController', "compare"));

        $found_business = array('search-result' => $search_results);
        return Response::json($found_business, 200, array(), JSON_PRETTY_PRINT);
    }

    public function getDistanceFromLatLonInKm($lat1,$lon1,$lat2,$lon2) {
        $R = 6371; // Radius of the earth in km
        $dLat = deg2rad($lat2-$lat1);  // deg2rad below
        $dLon = deg2rad($lon2-$lon1);
        $a =
            sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $d = $R * $c; // Distance in km
        return $d;
    }

    function deg2rad($deg) {
        return $deg * (Math.PI/180);
    }

    public static function compare($a, $b) {
        if ($a->distance == $b->distance) {
            return 0;
        }
        return ($a->distance < $b->distance) ? -1 : 1;
    }

    /**
     * @author Aunne
     * @param null $latitude
     * @param null $longitude
     * @return JSON response that gets the active businesses that are near the user
     */
    public function getActivebusinessDetails($latitude = null, $longitude = null){
        $businesses = DB::table('terminal_transaction')
            ->where('terminal_transaction.time_queued', '!=', 0)
            ->where('terminal_transaction.time_completed', '=', 0)
            ->where('terminal_transaction.time_removed', '=', 0)
            ->where('terminal_transaction.time_queued', '>', time() - 86400)
            ->join('priority_queue', 'priority_queue.transaction_number', '=', 'terminal_transaction.transaction_number')
            ->join('priority_number', 'priority_number.track_id', '=', 'priority_queue.track_id')
            ->join('service', 'service.service_id', '=', 'priority_number.service_id')
            ->join('branch', 'branch.branch_id', '=', 'service.branch_id')
            ->join('business', 'business.business_id', '=', 'branch.business_id')
            ->groupBy('business_id')
            ->select(array('business.business_id', 'business.name', 'business.local_address', 'business.latitude', 'business.longitude'))
            ->get();

        foreach($businesses as $index => $business){
            $businesses[$index]->distance = $this->getDistanceFromLatLonInKm($latitude, $longitude, $business->latitude, $business->longitude);
        }

        usort($businesses, array('RestController', "compare"));
        $actives = array('active-business' => $businesses);
        return Response::json($actives, 200, array(), JSON_PRETTY_PRINT);
    }

    /**
     * @author Aunne
     * @param null $latitude
     * @param null $longitude
     * @return JSON response of a random business
     */
    public function getRandomBusiness($latitude = null, $longitude = null){
        $businesses = DB::table('business')
            ->select(array('business.business_id', 'business.name', 'business.local_address', 'business.latitude', 'business.longitude'))
            ->get();

        foreach($businesses as $index => $business){
            $businesses[$index]->distance = $this->getDistanceFromLatLonInKm($latitude, $longitude, $business->latitude, $business->longitude);
        }

        usort($businesses, array('RestController', "compare"));
        array_splice($businesses, 9);
        $lucky_number = rand(0, count($businesses) - 1);
        $random_business = array('random_business' => $businesses[$lucky_number]);
        return Response::json($random_business, 200, array(), JSON_PRETTY_PRINT);
    }

    /**
     * @author Ruffy
     * @param $id BusinessI ID of the selected business
     * @return JSON object generated through businessId according to the database
     */
    public function getBusinessDetail($id) {
        $search = Business::find($id);
        return Response::json($search, 200, array(), JSON_PRETTY_PRINT);
    }

    /**
     * @author Ruffy
     * @return JSON response containing list of active businesses
     */
    public function getActiveBusiness() {
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
                                    && $grace_period < 86400 ) { // 1 day; 60secs * 60 min * 24 hours
                                    $active_businesses[] = array(
                                        'business_id' => $business->business_id,
                                        'local_address' => $business->local_address,
                                        'name' => $business->name,
                                    );
                                    $actives = array('active-business' => $active_businesses);
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
        return Response::json($actives, 200, array(), JSON_PRETTY_PRINT);
    }

    /**
     * @author Ruffy
     * @param int $business_id
     * @return Formatted single JSON Object that contains all attributes related to the Broadcast Page numbers
     */
    public function getShowNumber($business_id = 0) {
        $numbers = json_decode(file_get_contents(public_path() . '/json/' . $business_id . '.json'), true);
        $output = array();

        $get_num = $numbers['get_num'];
        $display = $numbers['display'];
        $date = $numbers['date'];

        unset($numbers['get_num']);
        unset($numbers['display']);
        unset($numbers['date']);
        unset($numbers['show_issued']);
        unset($numbers['turn_on_tv']);
        unset($numbers['ad_video']);
        unset($numbers['tv_channel']);
        unset($numbers['ad_type']);
        unset($numbers['ad_image']);
        unset($numbers['ticker_message']);
        unset($numbers['ticker_message2']);
        unset($numbers['ticker_message3']);
        unset($numbers['ticker_message4']);
        unset($numbers['ticker_message5']);
        unset($numbers['carousel_delay']);

        foreach($numbers as $key => $box_data) {
            // generate object attribute
            $title = $key;
            foreach($box_data as $key2 => $box_details) {
                $title = $title . $key2;
                $output[$title] = $numbers[$key][$key2];
            }
        }
        $output['get_num'] = $get_num;
        $output['display'] = $display;
        $output['date'] = $date;

        return Response::json($output, 200, array(), JSON_PRETTY_PRINT);
    }

    public function getRegisterUser()
    {
        $params = func_get_args();

        $data = array(
            'fb_id' => $params[0],
            'fb_url' => 'https://www.facebook.com/app_scoped_user_id/' . $params[0] . '/', // https://www.facebook.com/app_scoped_user_id/1438888283100110/
            'first_name' => $params[1],
            'last_name' => $params[2],
            'email' => $params[3],
            'gender' => $params[4],
            'phone' => $params[5],
            'country' => $params[6],
        );
        User::saveFBDetails($data);
//        Auth::loginUsingId(User::getUserIdByFbId($data['fb_id']));
        return json_encode(array('success' => $data['fb_id']));
    }

    /**
     * @author Ruffy Heredia
     * @desc Quick turnaround fix for instant registration and update later
     * @return string
     */
    public function getUpdateContactCountry()
    {
        $params = func_get_args();
        User::updateContactCountry($params[0], $params[1], $params[2]);
        return json_encode(array('success' => $params[0]));
    }

    /**
     * @author Aunne
     * @param $facebook_id
     * @param int $limit
     * @return JSON containing the industries view/searched by user
     */
    public function getUserIndustryInfo($facebook_id, $limit = 10){
        try{
            $user_id = User::getUserIdByFbId($facebook_id);
        }catch(Exception $e){
            $user_id = null;
        }

        if($user_id){
            $industry_data = Watchdog::queryUserInfo('industry', $user_id);
            unset($industry_data['Industry']); //remove industry index since this is useless data caused by searching without industry parameter
            arsort($industry_data);
            $industry_data = array_slice($industry_data, 0, $limit);
            return json_encode(['industries' => $industry_data]);
        }else{
            return json_encode(['error' => 'You are not registered to FeatherQ.']);
        }

    }

    /**
     * @author Aunne Rouie Arzadon
     * @param $facebook_id
     * @return string
     */
    public function getQueueInfo($facebook_id, $business_id = null){
        try{
            $user_id = User::getUserIdByFbId($facebook_id);
        }catch(Exception $e){
            $user_id = null;
        }

        if($business_id){
            $service = Service::getFirstServiceOfBusiness($business_id);
            $allow_remote = QueueSettings::allowRemote($service->service_id);
        }

        if($user_id){
            try{
            $transaction_number = PriorityQueue::getLatestTransactionNumberOfUser($user_id);
            $priority_number = PriorityQueue::priorityNumber($transaction_number);
            $track_id = PriorityQueue::trackId($transaction_number);
            $service_id = PriorityNumber::serviceId($track_id);
            $business_id = Branch::businessId(Service::branchId($service_id));

            $details = [
                'number_assigned' => $priority_number,
                'business_id' => $business_id,
                'business_name' => Business::name($business_id),
                'current_number_called' => ProcessQueue::currentNumber($service_id),
                'estimated_time_until_called' => Analytics::getWaitingTime($business_id),
                'status' => TerminalTransaction::queueStatus($transaction_number),
                'allow_remote' => isset($allow_remote) ? $allow_remote : null,
            ];
            }catch(Exception $e){
                $details = [
                    'number_assigned' => 0,
                    'business_id' => 0,
                    'business_name' => '',
                    'current_number_called' => 0,
                    'estimated_time_until_called' => 0,
                    'status' => 'Error',
                    'allow_remote' => isset($allow_remote) ? $allow_remote : null,
                ];
            }

            return json_encode($details);
        }else{
            return json_encode(['error' => 'You are not registered to FeatherQ.']);
        }

    }

    /**
     * @author Ruffy
     * @param $query Query string input for searching for a businesses under an industry
     * @return JSON response containing businesses that qualified with the search query
     */
    public function getSearchIndustry($query) {
        $search_results = DB::table('business')
            ->where('industry', '=', $query)
            ->select(array('business_id', 'name', 'local_address', 'latitude', 'longitude'))
            ->get();

            //ARA added info for businesses
            foreach($search_results as $index => $business){
                $first_service = Service::getFirstServiceOfBusiness($business->business_id);
                $all_numbers = ProcessQueue::allNumbers($first_service->service_id);

                $search_results[$index]->last_number_called = count($all_numbers->called_numbers) > 0 ? $all_numbers->called_numbers[0]['priority_number'] : 'none';
                $search_results[$index]->next_available_number = $all_numbers->next_number;
                $search_results[$index]->active = count($all_numbers->called_numbers) + count($all_numbers->uncalled_numbers) + count($all_numbers->timebound_numbers) > 0 ? 1: 0;
            }

        $found_business = array('search-result' => $search_results);
        return Response::json($found_business, 200, array(), JSON_PRETTY_PRINT);
    }

    /**
     * Queue To Business
     * @param $facebook_id
     * @param $business_id
     * @return string
     */
    public function getQueueBusiness($facebook_id, $business_id){
        try{
            $user_id = User::getUserIdByFbId($facebook_id);
        }catch(Exception $e){
            $user_id = null;
        }

        if ($user_id) {
            $service = Service::getFirstServiceOfBusiness($business_id);
            $service_id = $service->service_id;

            $name = User::full_name($user_id);
            $phone = User::phone($user_id);
            $email = User::email($user_id);

            $next_number = ProcessQueue::nextNumber(ProcessQueue::lastNumberGiven($service_id), QueueSettings::numberStart($service_id), QueueSettings::numberLimit($service_id));
            $priority_number = $next_number;
            $queue_platform = 'android';

            $number = ProcessQueue::issueNumber($service_id, $priority_number, null, $queue_platform, 0, $user_id);
            PriorityQueue::updatePriorityQueueUser($number['transaction_number'], $name, $phone, $email);

            $details = [
                'number_assigned' => $priority_number,
            ];

            return json_encode($details);
        } else {
            return json_encode(['error' => 'You are not registered to FeatherQ.']);
        }

    }

    public function getSendMessage($device_token = null, $message = null) {
        PushNotification::app('featherqAndroid')
            ->to($device_token)
            ->send($message);
    }

    public function getSendManual($device_token, $message, $title = "FeatherQ", $subtitle = null) {
        // API access key from Google API's Console
//        define( 'API_ACCESS_KEY', 'AIzaSyCj0EfjXkZe-USRLOlTXxywayUXSIYg1wA' );

        $registrationIds = array($device_token);

        // prep the bundle
        $msg = array
        (
            'message'       => $message,
            'title'         => $title,
            'subtitle'      => $subtitle,
            'tickerText'    => $message,
            'vibrate'   => 1,
            'sound'     => 1
        );

        $fields = array
        (
            'registration_ids'  => $registrationIds,
            'data'              => $msg
        );

        $headers = array
        (
            'Authorization: key=' . API_ACCESS_KEY,
            'Content-Type: application/json'
        );

        $ch = curl_init();
        curl_setopt( $ch,CURLOPT_URL, 'https://android.googleapis.com/gcm/send' );
        curl_setopt( $ch,CURLOPT_POST, true );
        curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
        $result = curl_exec($ch );
        curl_close( $ch );

        echo $result;
    }

    public function getMyHistory($facebook_id, $limit = 5, $offset = 0){
        $user = User::searchByFacebookId($facebook_id);
        $user_queues = User::getUserHistory($user['user_id'], $limit, $offset);
        foreach($user_queues as $index => $data){
            $action = 'issued';
            if($data['status'] == 1 ) { $action = 'called'; }
            else if($data['status'] == 2 ) { $action = 'served'; }
            else if($data['status'] == 3 ) { $action = 'dropped'; }

            $user_queues[$index]['status'] = $action;
            $user_queues[$index]['date'] = date('Y-m-d', $data['date']);
        }

        return json_encode(['history' => $user_queues]);
    }

    /**
     * @author Ruffy Heredia
     * @desc Webservice to rate a business from the user perspective
     * @param $rating
     * @param $facebookId
     * @param $business_id
     * @param int $action
     * @return string
     */
    public function getRateBusiness($rating, $facebookId, $business_id, $transaction_number, $action = 4) {
        $date = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $user = User::searchByFacebookId($facebookId);
        $user_id = $user["user_id"];

        UserRating::rateBusiness($date, $business_id, $rating, $user_id, '0', $action, $transaction_number);

        return json_encode(['success' => 1]);
    }

    /**
     * @author Ruffy Heredia
     * @desc
     * @param $transaction_number
     * @param int $timestamp
     * @return mixed
     */
    public function getTransactionRatingInfo($transaction_number, $timestamp = 0) {
        $current_date = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        try {
            $rating = UserRating::getUserRating($transaction_number);
            $rating->is_rated = '1';
            $rating->can_be_rated = '0';
        } catch (Exception $e) {
            $rating = null;
        }
        return Response::json($rating ? $rating : ['can_be_rated' => $current_date == $timestamp ? '1' : '0']
                            , 200
                            , array()
                            , JSON_PRETTY_PRINT);
    }

    public function getIndustries(){
        return json_encode(['industries' => Business::getAvailableIndustries()]);
    }

    /**
     * @param $facebook_id
     * @return JSON-formatted data of user
     */
    public function getUserInfo($facebook_id){
        try{
            $user_id = User::getUserIdByFbId($facebook_id);
        }catch(Exception $e){
            $user_id = null;
        }

        if( $user_id ){
            $full_name = User::full_name($user_id);
            $email = User::email($user_id);
            $phone = User::phone($user_id);
            $local_address = User::local_address($user_id);
            $details = [
                'name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'address' => $local_address,
            ];
            return Response::json($details, 200, array(), JSON_PRETTY_PRINT);
        } else {
            return json_encode(['error' => 'You are not registered to FeatherQ.']);
        }
    }
    /**
     * @param $facebook_id
     * @return JSON-formatted response of the business name, estimated time, people-in-queue and next number available .
     */

    public function getBusinessServiceDetails($facebook_id) {
        try{
            $user_id = User::getUserIdByFbId($facebook_id);
        }catch (Exception $e) {
            $user_id = null;
        }

        if($user_id){

            $business_id = UserBusiness::getBusinessIdByOwner($user_id);
            $business_name = Business::name($business_id);
            $estimated_time = Analytics::getWaitingTime($business_id);
            $service= Service::getFirstServiceOfBusiness($business_id);
            $remaining_queue_count = Analytics::getServiceRemainingCount($service->service_id);
            $next_available_number = ProcessQueue::nextNumber(ProcessQueue::lastNumberGiven($service->service_id), QueueSettings::numberStart($service->service_id), QueueSettings::numberLimit($service->service_id));

            $details = [
                'business_name' => $business_name,
                'estimated_time' => $estimated_time,
                'people_in_queue' => $remaining_queue_count,
                'next_available_number' => $next_available_number
            ];

            return Response::json($details, 200, array(), JSON_PRETTY_PRINT);
        }else{
            return json_encode(['error' => 'Something went wrong!']);
        }
    }

    /**
     * @param $service_id
     * @param $name
     * @param $phone
     * @param $email
     * @return JSON-formatted queued number
     */

    public function getQueueNumber($service_id, $name, $phone, $email) {

        try{
            $next_number = ProcessQueue::nextNumber(ProcessQueue::lastNumberGiven($service_id), QueueSettings::numberStart($service_id), QueueSettings::numberLimit($service_id));
            $priority_number = $next_number;
            $queue_platform = 'kiosk';

            $number = ProcessQueue::issueNumber($service_id, $priority_number, null, $queue_platform);
            PriorityQueue::updatePriorityQueueUser($number['transaction_number'], $name, $phone, $email);

            $details = [
                'number_assigned' => $priority_number,
            ];

            return Response::json($details, 200, array(), JSON_PRETTY_PRINT);

        }catch(Exception $e){
            return json_encode(['error' => 'Something went wrong!']);
        }
    }


    //Messaging to business

    /**
     * @author ARA
     * @param $facebook_id
     * @return mixed
     * get last Message of user to/from each business
     */
    public function getAllMessages($facebook_id){
        $messages = [];
        $email = User::where('fb_id', '=', $facebook_id)->select('email')->first();
        if($email){
            $threadKeys = Message::getMessagesByEmail($email->email);
            foreach($threadKeys as $threadKey){
                $data = json_decode(file_get_contents(public_path() . '/json/messages/' . $threadKey->thread_key . '.json'));
                $message = $data[count($data) - 1];
                $message->business_id = $threadKey->business_id;
                $message->business_name = Business::name($threadKey->business_id);
                $messages[] = $message;
            }
        }else{
            return Response::json(['error' => 'User is not registered'], 200, array(), JSON_PRETTY_PRINT);
        }

        return Response::json(['messages' => $messages], 200, array(), JSON_PRETTY_PRINT);

    }

    /**
     * @author ARA
     * @param $facebook_id
     * @param $business_id
     * @return mixed
     * get Messages between user and business
     */
    public function getBusinessMessages($facebook_id, $business_id){
        $email = User::where('fb_id', '=', $facebook_id)->select('email')->first();
        if($email){
            try{
                $threadKey = Message::getThreadKeyByBusinessIdAndEmail($business_id, $email->email);
                $messages = json_decode(file_get_contents(public_path() . '/json/messages/' . $threadKey . '.json'));
            }catch(Exception $e){
                $messages = [];
            }
        }else{
            return Response::json(['error' => 'User is not registered'], 200, array(), JSON_PRETTY_PRINT);
        }

        return Response::json(['messages' => $messages], 200, array(), JSON_PRETTY_PRINT);
    }

    /**
     * @author ARA
     * @param $facebook_id
     * @param $business_id
     * @param $message
     * @param null $phone
     * @return mixed
     * send message to business
     */
    public function getSendtoBusiness($facebook_id, $business_id, $message, $phone = null){
        $user = User::where('fb_id', '=', $facebook_id)->select('email', 'first_name', 'last_name')->first();
        if($user){
            $name = $user->first_name . ' ' . $user->last_name;
            $email = $user->email;
            $timestamp = time();
            $attachment = '';
            $business = Business::where('business_id', '=', $business_id)->first();
            if($business){
                $thread_key = Message::threadKeyGenerator($business_id, $email);
                if (!Message::checkThreadByKey($thread_key)) {
                    $phones[] = $phone;
                    Message::createThread(array(
                        'contactname' => $name,
                        'business_id' => $business_id,
                        'email' => $email,
                        'phone' => serialize($phones),
                        'thread_key' => $thread_key,
                    ));
                    $data = json_encode(array(array(
                        'timestamp' => $timestamp,
                        'contmessage' => $message,
                        'attachment' => $attachment,
                        'sender' => 'user',
                    )));
                }
                else {
                    $phones = unserialize(Message::getPhoneByKey($thread_key));
                    if (!is_array($phones)) $phones = array($phones);
                    if (!in_array($phone, $phones)) $phones[] = $phone;
                    Message::updateThread(array(
                        'phone' => serialize($phones),
                    ), $thread_key);
                    $data = json_decode(file_get_contents(public_path() . '/json/messages/' . $thread_key . '.json'));
                    $data[] = array(
                        'timestamp' => $timestamp,
                        'contmessage' =>  $message,
                        'attachment' => $attachment,
                        'sender' => 'user',
                    );
                    $data = json_encode($data);
                }

                file_put_contents(public_path() . '/json/messages/' . $thread_key . '.json', $data);
            }else{
                return Response::json(['error' => 'Business does not exist'], 200, array(), JSON_PRETTY_PRINT);
            }
        }else{
            return Response::json(['error' => 'User is not registered'], 200, array(), JSON_PRETTY_PRINT);
        }
        return Response::json(['success' => 1], 200, array(), JSON_PRETTY_PRINT);
    }

    public function getTransactionStats($transaction_number){
        $transaction_stats = [];
        $transaction_array = DB::table('terminal_transaction')
            ->where('transaction_number', '=', $transaction_number)
            ->select('transaction_number', 'time_queued', 'time_called', 'time_completed', 'time_removed')
            ->first();
        foreach($transaction_array as $title => $value){
            $transaction_stats[] = [
                'title' => $title,
                'value' => $value
            ];
        }
        return Response::json(['transaction_stats' => $transaction_stats], 200, array(), JSON_PRETTY_PRINT);
    }
}