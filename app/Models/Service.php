<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model {

    protected $table = 'service';
    protected $primaryKey = 'service_id';
    public $timestamps = false;

    public static function branchId($service_id){
        return Service::find($service_id)->branch_id;
    }

    /*
     * @author: CSD
     * @description: create branch on business creation/setup
     * @return service_id
     */
    public static function createBranchService($branch_id, $business_name){
        $service = new Service();
        $service->name = $business_name . " Service";
        $service->status = 1;
        $service->branch_id = $branch_id;
        $service->save();

        return $service->service_id;
    }

    /*
     * @author: CSD
     * @description: fetch services by branch id
     * @return services array of branch
     */
    public static function getServicesByBranchId($branch_id){
        return Service::where('branch_id', '=', $branch_id)->get();
    }

    public static function deleteServicesByBranchId($branch_id){
        return Service::where('branch_id', '=', $branch_id)->delete();
    }

    public static function getFirstServiceOfBusiness($business_id){
        $first_branch = Branch::getFirstBranchOfBusiness($business_id);
        return Service::getFirstServiceOfBranch($first_branch->branch_id);
    }

    public static function getFirstServiceOfBranch($branch_id){
        return Service::where('branch_id', '=', $branch_id)->first();
    }

}
