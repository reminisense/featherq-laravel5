<?php
/**
 * Created by PhpStorm.
 * User: JONAS
 * Date: 5/12/2015
 * Time: 1:25 PM
 */

class RatingController extends BaseController{

    public function getUserratings($rating, $email, $terminal_id , $action){

        $date = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $business_id = Business::getBusinessIdByTerminalId($terminal_id);
        $user = User::searchByEmail($email);
        $user_id = $user["user_id"];
        $terminal_user_id = Auth::id();

        UserRating::rateUser($date, $business_id, $rating, $user_id, $terminal_user_id, $action);

        return json_encode(['success' => 1]);
    }

    public function getVerifyemail($email){

        $user = User::searchByEmail($email);

        if(is_null($user)){
           return json_encode(['success' => 1, 'result' => false]);
        }else{
           return json_encode(['success' => 1, 'result' => true]);
        }


    }
}