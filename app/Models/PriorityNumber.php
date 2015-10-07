<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriorityNumber extends Model {

    protected $table = 'priority_number';
    protected $primaryKey = 'track_id';
    public $timestamps = false;

    public static function createPriorityNumber($service_id, $number_start, $number_limit, $last_number_given, $current_number, $date){
        $values = [
            'service_id' => $service_id,
            'number_start' => $number_start,
            'number_limit' => $number_limit,
            'last_number_given' => $last_number_given,
            'current_number' => $current_number,
            'date' => $date
        ];
        return PriorityNumber::insertGetId($values);
    }

    public static function serviceId($track_id){
        return PriorityNumber::find($track_id)->service_id;
    }

    public static function getTrackIdByServiceId($service_id) {
        return PriorityNumber::where('service_id', '=', $service_id)->select(array('track_id'))->get();
    }

}
