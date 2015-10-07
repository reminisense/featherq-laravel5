<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Helper extends Model {

    public static function VerifyFB($accessToken) {
        // Call Facebook and let them verify if the information sent by the user
        // is the same with the ones in their database.
        // This will save us from the exploit of a post request with bogus details
        $fb = new Facebook\Facebook(array(
            'app_id' => '1577295149183234',
            'app_secret' => '23a15a243f7ce66a648ec6c48fa6bee9',
            'default_graph_version' => 'v2.4',
        ));
        try {
            // Returns a `Facebook\FacebookResponse` object
            $response = $fb->get('/me', $accessToken); // Use the access token retrieved by JS login
            return $response;
        } catch(Facebook\Exceptions\FacebookResponseException $e) {
            //return json_encode(array('message' => $e->getMessage()));
            Auth::logout();
        } catch(Facebook\Exceptions\FacebookSDKException $e) {
            //return json_encode(array('message' => $e->getMessage()));
            Auth::logout();
        }
    }

    /**
     * gets the user id of the current user
     * @return mixed
     */
    public static function userId(){
        if(Auth::check()){
            return Auth::user()->user_id;
        }else{
            return 0;
        }
    }

    /**
     * gets the role id of the current session's user
     * @return mixed
     */
    public static function currentUserRoleId(){
        return DB::table('user_role')->where('user_id', '=', Helper::userId())->first()->role_id;
    }

    /**
     * checks if the role of the current user is in the given array
     * @return mixed
     */
    public static function currentUserIsEither($roles = array()){
        return in_array(Helper::currentUserRoleId(), $roles);
    }

    public static function parseTime($time){
        $arr = explode(' ', $time);
        $hourmin = explode(':', $arr[0]);

        return [
            'hour' => trim($hourmin[0]),
            'min'  => trim($hourmin[1]),
            'ampm' => trim($arr[1]),
        ];
    }

    public static function mergeTime($hour, $min, $ampm){
        return Helper::doubleZero($hour).':'.Helper::doubleZero($min).' '.$ampm;
    }

    public static function doubleZero($number){
        return $number == 0 ? '00' : $number;
    }

    public static function millisecondsToHMSFormat($ms){
        $second = $ms % 60;
        $ms = floor($ms / 60);

        $minute = $ms % 60;
        $ms = floor($ms / 60);

        $hour = $ms % 24;
        return Helper::formatTime($second, $minute, $hour);
    }

    public static function formatTime($second, $minute, $hour){
        $time_string = '';
        $time_string .= $hour > 0 ? $hour . ' hour(s) ' : '';
        $time_string .= $minute > 0 ? $minute . ' minute(s) ' : '';
        $time_string .= $second > 0 ? $second . ' second(s) ' : '';
        return $time_string;
    }

    public static function customSort($property, $var1, $var2){
        return $var1[$property] - $var2[$property];
    }

    public static function customSortRev($property, $var1, $var2){
        return $var2[$property] - $var1[$property];
    }

    public static function firstFromTable($table, $field, $value, $operator = '='){
        return DB::table($table)->where($field, $operator, $value)->first();
    }

    /**
     * requires an array of arrays
     * ex. 'field' => array('conditional_operator', 'value')
     * @param $conditions
     * @return mixed
     */
    public static function getMultipleQueries($table, $conditions){
        $query = DB::table($table);
        foreach($conditions as $field => $value){
            $field = strpos($field, '.') > 0 ? substr($field, 0, strpos($field, '.')) : $field;
            if(is_array($value)){
                $query->where($field, $value[0], $value[1]);
            }else{
                $query->where($field, '=', $value);
            }
        }
        return $query->get();
    }


    public static function getIP()
    {
        return $_SERVER["REMOTE_ADDR"];

        // populate a local variable to avoid extra function calls.
        // NOTE: use of getenv is not as common as use of $_SERVER.
        //       because of this use of $_SERVER is recommended, but
        //       for consistency, I'll use getenv below
        $tmp = getenv("HTTP_CLIENT_IP");
        // you DON'T want the HTTP_CLIENT_ID to equal unknown. That said, I don't
        // believe it ever will (same for all below)
        if ( $tmp && !strcasecmp($tmp, "unknown"))
            return $tmp;

        $tmp = getenv("HTTP_X_FORWARDED_FOR");
        if( $tmp && !strcasecmp($tmp, "unknown"))
            return $tmp;

        // no sense in testing SERVER after this.
        // $_SERVER[ 'REMOTE_ADDR' ] == gentenv( 'REMOTE_ADDR' );
        $tmp = getenv("REMOTE_ADDR");
        if($tmp && !strcasecmp($tmp, "unknown"))
            return $tmp;

        return("unknown");
    }

    /**
     * @param $birthdate must be int ex. strtotime(1/1/1990)
     */
    public static function getAge($birthdate){
        return floor( (time() - $birthdate) / 31556926);
    }

    public static function getTimezoneList(){
        $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        return $timezones;
    }

    /**
     * gets the date and timezone of business and converts it to user/browser timezone
     * @param $date
     * @param $business_timezone
     * @param $user_timezone
     * @return string
     */
    public static function changeBusinessTimeTimezone($date, $business_timezone, $browser_timezone){
        if(is_numeric($browser_timezone)) $browser_timezone = Helper::timezoneOffsetToName($browser_timezone);
        $datetime = new DateTime($date, new DateTimeZone($business_timezone));
        $datetime->setTimezone(new DateTimeZone($browser_timezone));
        return $datetime->format('g:i A');
    }

    /**
     * gets timezone offset and converts it to php timezone string
     * @param $offset
     * @return bool
     */
    public static function timezoneOffsetToName($offset){
        $abbrarray = timezone_abbreviations_list();
        foreach ($abbrarray as $abbr) {
            foreach ($abbr as $city) {
                if ($city['offset'] == $offset) {
                    return $city['timezone_id'];
                }
            }
        }
        return false;
    }

    public static function timezoneOffsetToNameArray($offset){
        $timezones = [];
        $abbrarray = timezone_abbreviations_list();
        foreach ($abbrarray as $abbr) {
            foreach ($abbr as $city) {
                if ($city['offset'] == $offset) {
                    $timezones[] = $city['timezone_id'];
                }
            }
        }
        return $timezones;
    }

    public static function isBusinessOwner($business_id, $user_id) {
        return $business_id == UserBusiness::getBusinessIdByOwner($user_id);
    }

    public static function isPartOfBusiness($business_id, $user_id) {
        $res = TerminalUser::getTerminalAssignement($user_id);
        if (isset($res)) {
            foreach ($res as $count => $data) {
                if ($business_id == Business::getBusinessIdByTerminalId($data['terminal_id'])) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    public static function isNotAnOwner($user_id) {
        return !UserBusiness::getBusinessIdByOwner($user_id);
    }

}
