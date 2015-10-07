<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model {

    protected $table = 'branch';
    protected $primaryKey = 'branch_id';
    public $timestamps = false;

    public static function businessId($branch_id){
        return Branch::where('branch_id', '=', $branch_id)->select(array('business_id'))->first()->business_id;
    }

    public static function name($branch_id){
        return Branch::where('branch_id', '=', $branch_id)->select(array('name'))->first()->name;
    }

    /*
     * @author: CSD
     * @description: create new branch on business setup
     * @return branch_id
     */
    public static function createBusinessBranch($business_id, $business_name){
        $branch = new Branch();
        $branch->name = $business_name . " Branch";
        $branch->business_id = $business_id;
        $branch->save();

        return $branch->branch_id;
    }

    /*
     * @author: CSD
     * @description: fetch all branches by business id
     * @return branches array by business id
     */
    public static function getBranchesByBusinessId($business_id){
        return Branch::where('business_id', '=', $business_id)->get();
    }

    public static function deleteBranchesByBusinessId($business_id){
        return Branch::where('business_id', '=', $business_id)->delete();
    }

    public static function getFirstBranchOfBusiness($business_id){
        return Branch::where('business_id', '=', $business_id)->first();
    }

}
