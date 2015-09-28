<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Analytics extends Model {

    protected $table = 'queue_analytics';
    public $timestamps = false;

    public static function getBusinessRemainingCount($business_id){
        $uncalled_numbers = 0;
        $branches = Branch::getBranchesByBusinessId($business_id);
        foreach($branches as $branch){
            $uncalled_numbers = Analytics::getBranchRemainingCount($branch->branch_id);
        }
        return $uncalled_numbers;
    }

    public static function getBranchRemainingCount($branch_id){
        $uncalled_numbers = 0;
        $services = Service::getServicesByBranchId($branch_id);
        foreach($services as $service){
            $uncalled_numbers += Analytics::getServiceRemainingCount($service->service_id);
        }
        return $uncalled_numbers;
    }

    public static function getServiceRemainingCount($service_id){
        $all_numbers = ProcessQueue::allNumbers($service_id);
        return isset($all_numbers->uncalled_numbers) ? count($all_numbers->uncalled_numbers) : 0;
    }

    /**
     * Saving To Queue Analytics table
     */

    public static function saveQueueAnalytics($values){
        DB::table('queue_analytics')->insert($values);
    }

    public static function insertAnalyticsQueueNumber($action, $transaction_number, $service_id, $date, $time, $terminal_id, $queue_platform){
        $values = [
            'transaction_number' => $transaction_number,
            'date' => $date,
            'business_id' => Business::getBusinessIdByServiceId($service_id),
            'branch_id' => Service::branchId($service_id),
            'service_id' => $service_id,
            'terminal_id' => $terminal_id,
            'queue_platform' => $queue_platform,
            'user_id' => Helper::userId(),
            'action' => $action,
            'action_time' => $time
        ];

        Analytics::saveQueueAnalytics($values);

    }

    public static function insertAnalyticsQueueNumberIssued($transaction_number, $service_id, $date, $time, $terminal_id, $queue_platform){
        Analytics::insertAnalyticsQueueNumber(0, $transaction_number, $service_id, $date, $time, $terminal_id, $queue_platform);
    }

    public static function insertAnalyticsQueueNumberCalled($transaction_number, $service_id, $date, $time, $terminal_id, $queue_platform){
        Analytics::insertAnalyticsQueueNumber(1, $transaction_number, $service_id, $date, $time, $terminal_id, $queue_platform);
    }

    public static function insertAnalyticsQueueNumberServed($transaction_number, $service_id, $date, $time, $terminal_id, $queue_platform){
        Analytics::insertAnalyticsQueueNumber(2, $transaction_number, $service_id, $date, $time, $terminal_id, $queue_platform);
    }

    public static function insertAnalyticsQueueNumberRemoved($transaction_number, $service_id, $date, $time, $terminal_id, $queue_platform){
        Analytics::insertAnalyticsQueueNumber(3, $transaction_number, $service_id, $date, $time, $terminal_id, $queue_platform);
    }

    /**
     * requires an array of arrays
     * ex. 'field' => array('conditional_operator', 'value')
     * @param $conditions
     * @return mixed
     */
    public static function getQueueAnalyticsRows($conditions){
        return Helper::getMultipleQueries('queue_analytics', $conditions);
    }

    public static function getBusinessAnalytics($business_id, $startdate = null, $enddate = null){
        $startdate = $startdate == null ? mktime(0, 0, 0, date('m'), date('d'), date('Y')) : $startdate;
        $enddate = $enddate == null ? mktime(0, 0, 0, date('m'), date('d'), date('Y')) : $enddate;

        $analytics = [
            'remaining_count' => Analytics::getBusinessRemainingCount($business_id),
            'total_numbers_issued' => Analytics::getTotalNumbersIssuedByBusinessId($business_id, $startdate, $enddate),
            'total_numbers_called' => Analytics::getTotalNumbersCalledByBusinessId($business_id, $startdate, $enddate),
            'total_numbers_served' => Analytics::getTotalNumbersServedByBusinessId($business_id, $startdate, $enddate),
            'total_numbers_dropped' => Analytics::getTotalNumbersDroppedByBusinessId($business_id, $startdate, $enddate),
            'average_time_called' => Analytics::getAverageTimeCalledByBusinessId($business_id, 'string', $startdate, $enddate),
            'average_time_served' => Analytics::getAverageTimeServedByBusinessId($business_id, 'string', $startdate, $enddate)
        ];

        return $analytics;
    }


    /**
     * individual queries
     */

    /*time served*/

    public static function getTotalNumbersIssuedByBusinessId($business_id, $startdate, $enddate){
        return count(Analytics::getQueueAnalyticsRows(['action' => ['=', 0], 'business_id' => ['=', $business_id ], 'date' => ['>=', $startdate], 'date.' => ['<=', $enddate]]));
    }

    public static function getTotalNumbersCalledByBusinessId($business_id, $startdate, $enddate){
        return count(Analytics::getQueueAnalyticsRows(['action' => ['=', 1], 'business_id' => ['=', $business_id ], 'date' => ['>=', $startdate], 'date.' => ['<=', $enddate]]));
    }

    public static function getTotalNumbersServedByBusinessId($business_id, $startdate, $enddate){
        return count(Analytics::getQueueAnalyticsRows(['action' => ['=', 2], 'business_id' => ['=', $business_id ], 'date' => ['>=', $startdate], 'date.' => ['<=', $enddate]]));
    }

    public static function getTotalNumbersDroppedByBusinessId($business_id, $startdate, $enddate){
        return count(Analytics::getQueueAnalyticsRows(['action' => ['=', 3], 'business_id' => ['=', $business_id ], 'date' => ['>=', $startdate], 'date.' => ['<=', $enddate]]));
    }

    public static function getTotalNumbersProcessedByBusinessId($business_id, $startdate, $enddate){
        return count(Analytics::getQueueAnalyticsRows(['action' => ['>', 1], 'business_id' => ['=', $business_id ], 'date' => ['>=', $startdate], 'date.' => ['<=', $enddate]]));
    }

    public static function getAverageTimeCalledByBusinessId($business_id, $format = 'string', $startdate, $enddate){
        if($format === 'string'){
            return Analytics::getAverageTimeFromActionByBusinessId(0, 1, $business_id, $startdate, $enddate);
        }else{
            return Analytics::getAverageTimeValueFromActionByBusinessId(0, 1, $business_id, $startdate, $enddate);
        }
    }

    public static function getAverageTimeServedByBusinessId($business_id, $format = 'string', $startdate, $enddate){
        if($format === 'string'){
            return Analytics::getAverageTimeFromActionByBusinessId(1, 2, $business_id, $startdate, $enddate);
        }else{
            return Analytics::getAverageTimeValueFromActionByBusinessId(1, 2, $business_id, $startdate, $enddate);
        }
    }

    //gets the string representation of the average time
    public static function getAverageTimeFromActionByBusinessId($action1, $action2, $business_id, $startdate, $enddate){
        return Helper::millisecondsToHMSFormat(Analytics::getAverageTimeValueFromActionByBusinessId($action1, $action2, $business_id, $startdate, $enddate));
    }

    //gets the numeric representation of the average time
    public static function getAverageTimeValueFromActionByBusinessId($action1, $action2, $business_id, $startdate, $enddate){
        $action1_numbers = Analytics::getQueueAnalyticsRows(['action' => ['=', $action1], 'business_id' => ['=', $business_id ], 'date' => ['>=', $startdate], 'date.' => ['<=', $enddate]]);
        $action2_numbers = Analytics::getQueueAnalyticsRows(['action' => ['=', $action2], 'business_id' => ['=', $business_id ], 'date' => ['>=', $startdate], 'date.' => ['<=', $enddate]]);
        return Analytics::getAverageTimeFromActionArray($action1_numbers, $action2_numbers);
    }

    public static function getAverageTimeFromActionArray($action1_numbers, $action2_numbers){
        $counter = 0;
        $time_sum = 0;
        foreach($action1_numbers as $action1_number){
            foreach($action2_numbers as $action2_number){
                if($action1_number->transaction_number == $action2_number->transaction_number){
                    $counter++;
                    $time_sum += ($action2_number->action_time - $action1_number->action_time);
                    break 1;
                }
            }
        }
        $average = $counter == 0 ? 0 : round($time_sum/$counter);
        return $average;
    }

    public static function getTotalNumbersCalledByBusinessIdWithDate($business_id, $startdate, $enddate){
        return count(Analytics::getQueueAnalyticsRows(['action' => ['=', 1], 'business_id' => ['=', $business_id ], 'date' => ['>=', $startdate], 'date' => ['<=', $enddate]]));
    }

    /**
     * ARA Computes for the time the next available number has to wait in order to be called
     * equation : time_to_be_called = average_calling_time x numbers_remaining_in_queue
     */
    public static function getWaitingTime($business_id){
        $date = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $numbers_in_queue = Analytics::getBusinessRemainingCount($business_id);
        $average_waiting_time = Analytics::getAverageTimeCalledByBusinessId($business_id, 'numeric', $date, $date);
        return $average_waiting_time * $numbers_in_queue;
    }

    public static function getWaitingTimeString($business_id){
        $waiting_time = Analytics::getWaitingTime($business_id);
        $waiting_time = floor($waiting_time / 60);

        //Reduced to 3 different line statuses
        if($waiting_time > 30){
            $waiting_time_string = 'heavy';
        }else if($waiting_time <= 30 && $waiting_time > 15){
            $waiting_time_string = 'moderate';
        }else{
            $waiting_time_string = 'light';
        }

        return $waiting_time_string;
    }

    public static function getLastActive($business_id){
        $last = Analytics::orderBy('transaction_number', 'desc')->where('business_id', '=', $business_id)->first();
        if($last){
            $last_active = mktime(0, 0, 0, date('m'), date('d'), date('Y')) - $last->date;
            $last_active = $last_active / 86400; //convert seconds to days
        }else{
            $last_active = null;
        }
        return $last_active;
    }

    public static function getUserQueues($user_id = null){
        if($user_id){
            $results = Analytics::where('user_id', '=', $user_id)->get();
        }else{
            $results = Analytics::all();
        }

        foreach($results as $index => $data){
            $action = 'issued';
            if($data->action == 1 ) { $action = 'called'; }
            else if($data->action == 2 ) { $action = 'served'; }
            else if($data->action == 3 ) { $action = 'dropped'; }

            try{
                $user_data[$index][$action] = Business::name($data->business_id);
                $user_data[$index]['user_id'] = $data->user_id;
            }catch(Exception $e){
                $user_data[$index][$action] = 'Deleted Businesses';
                $user_data[$index]['user_id'] = $data->user_id;
            }
        }
        return $user_data;
    }

    public static function countBusinessNumbers( $start_date, $end_date, $action){

        $temp_start_date = mktime(0, 0, 0, date('m', $start_date), date('d', $start_date), date('Y', $start_date));
        $temp_end_date = mktime(0, 0, 0, date('m', $end_date), date('d', $end_date), date('Y', $end_date));

        return Analytics::where('date', '>=', $temp_start_date)->where('date','<=', $temp_end_date)->where('action','=',$action)->count();
    }

    public static function countNumbersByBusiness($business_id, $temp_start_date, $action){
        return Analytics::where('business_id','=',$business_id)->where('date', '=', $temp_start_date)->where('action','=',$action)->count();
    }

    public static function countIndustryNumbersWithData($business_id, $temp_start_date)
    {
        $count = [];

        for ($i = 0; $i < count($business_id); $i++) {
            $transaction_number_array = Analytics::where('business_id', '=', $business_id[$i]->business_id)->where('date', '=', $temp_start_date)->where('action', '=', 0)->lists('transaction_number');
            for ($i = 0; $i < count($transaction_number_array); $i++) {
                $temp_data = DB::table('priority_queue')->where('transaction_number', '=', $transaction_number_array[$i])->get();
                if ($temp_data[0]->name || $temp_data[0]->email || $temp_data[0]->phone) {
                    array_push($count, 1);
                }
            }
        }
        return array_sum($count);
    }

    public static function countCountryNumbersWithData($business_id, $temp_start_date)
    {

        $count = [];

        for ($i = 0; $i < count($business_id); $i++) {
            $transaction_number_array = Analytics::where('business_id', '=', $business_id[$i]->business_id)->where('date', '=', $temp_start_date)->where('action', '=', 0)->lists('transaction_number');
            for ($i = 0; $i < count($transaction_number_array); $i++) {
                $temp_data = DB::table('priority_queue')->where('transaction_number', '=', $transaction_number_array[$i])->get();
                if ($temp_data[0]->name || $temp_data[0]->email || $temp_data[0]->phone) {
                    array_push($count, 1);
                }
            }
        }
        return array_sum($count);
    }

    public static function countNumbersWithData($business_id, $temp_start_date){

        $transaction_number_array = Analytics::where('business_id','=',$business_id)->where('date', '=', $temp_start_date)->where('action','=',0)->lists('transaction_number');
        $count= [];

        for($i=0; $i < count($transaction_number_array); $i++) {
            $temp_data= DB::table('priority_queue')->where('transaction_number','=',$transaction_number_array[$i])->get();
            if($temp_data[0]->name || $temp_data[0]->email || $temp_data[0]->phone){
                array_push($count, 1);
            }
        }

        return array_sum($count);
    }

    public static function countNumbersByIndustry($business_id, $temp_start_date, $action){

        $count= [];

        for($i=0; $i < count($business_id); $i++){
            $temp_count = Analytics::where('business_id','=',$business_id[$i]->business_id)->where('date', '=', $temp_start_date)->where('action','=',$action)->count();
            array_push($count, $temp_count);
        }

        return array_sum($count);
    }

    public static function countNumbersByCountry($business_id, $temp_start_date, $action){

        $count = [];

        for($i=0; $i < count($business_id); $i++){

            $temp_count = Analytics::where('business_id','=',$business_id[$i]->business_id)->where('date', '=', $temp_start_date)->where('action','=',$action)->count();
            array_push($count, $temp_count);
        }

        return array_sum($count);
    }

}
