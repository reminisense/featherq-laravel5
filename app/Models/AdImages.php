<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdImages extends Model {

    protected $table = 'ad_images';
    protected $primaryKey = 'img_id';
    public $timestamps = false;

    public static function setWeight($weight, $img_id) {
        AdImages::where('img_id', '=', $img_id)->update(array('weight' => $weight));
    }

    public static function getAllImagesByBusinessId($business_id) {
        return AdImages::where('business_id', '=', $business_id)->orderBy('weight')->get();
    }

    public static function deleteImage($img_id) {
        AdImages::where('img_id', '=', $img_id)->delete();
    }

    public static function deleteImageByPath($path) {
        AdImages::where('path', '=', $path)->delete();
    }

    public static function saveImages($path, $business_id) {
        AdImages::insert(array(
            'business_id' => $business_id,
            'path' => $path,
            'weight' => AdImages::max('weight') + 1,
        ));
    }

}
