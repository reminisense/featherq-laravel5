<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBusiness extends Model {

    protected $table = 'user_business';
    public $timestamps = false;

    public static function getBusinessIdByOwner($user_id) {
        $row = UserBusiness::where('user_id', '=', $user_id)->get()->first();
        if (count($row) > 0){
            return $row->business_id;
        } else {
            return 0;
        }
        /* return UserBusiness::where('user_id', '=', $user_id)->select(array('business_id'))->first()->business_id; */
    }

    public static function getAllBusinessIdByOwner($user_id){
        return UserBusiness::where('user_id', '=', $user_id)->get();
    }

    public static function getAllBusinessDetailsByOwner($user_id){
        $businesses = [];
        $user_businesses = UserBusiness::getAllBusinessIdByOwner($user_id);
        foreach($user_businesses as $user_business){
            $business = Business::find($user_business->business_id);
            array_push($businesses, $business);
        }

        return $businesses;
    }

    public static function getMyBusinesses(){
        return UserBusiness::getAllBusinessDetailsByOwner(Helper::userId());
    }

    public static function deleteUserByBusinessId($business_id) {
        UserBusiness::where('business_id', '=', $business_id)->delete();
    }
}
