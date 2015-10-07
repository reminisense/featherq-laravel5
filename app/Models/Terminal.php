<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Terminal extends Model {

    protected $table = 'terminal';
    protected $primaryKey = 'terminal_id';
    public $timestamps = false;

    /*
     * @author: CSD
     * @description: create terminal on business creation/setup
     * @return none
     */
    public static function createBranchServiceTerminal($user_id, $service_id, $num){
        $terminals = [];
        for($i = 1; $i <= $num; $i++){
            $terminal = new Terminal();
            $terminal->name = "Terminal " . $i;
            $terminal->service_id = $service_id;
            $terminal->status = 1;
            $terminal->box_rank = Terminal::generateBoxRank($service_id); // Added by PAG

            $terminal->save();

            $terminaluser = new TerminalUser();
            $terminaluser->user_id = $user_id;
            $terminaluser->terminal_id = $terminal->terminal_id;
            $terminaluser->status = 1;
            $terminaluser->date = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
            $terminaluser->save();

            array_push($terminals, $terminal);
        }

        return $terminals;
    }

    /*
     * @author: CSD
     * @description: fetch terminals by service id
     * @return: terminal array by service id
     */
    public static function getTerminalsByServiceId($service_id){
        return Terminal::where('service_id', '=', $service_id)->get()->toArray();
    }

    public static function name($terminal_id){
        return Terminal::find($terminal_id)->name;
    }

    public static function serviceId($terminal_id){
        return Terminal::find($terminal_id)->service_id;
    }

    public static function getTerminalsByBranchId($branch_id){
        $services = Service::where('branch_id', '=', $branch_id)->get();
        $terminals = [];
        foreach($services as $service){
            $service_terminals = Terminal::getTerminalsByServiceId($service->service_id);
            foreach($service_terminals as $terminal){
                array_push($terminals, $terminal);
            }
        }
        return $terminals;
    }

    public static function getTerminalsByBusinessId($business_id){
        $branches = Branch::where('business_id', '=', $business_id)->get();
        $terminals = [];
        foreach($branches as $branch){
            $branch_terminals = Terminal::getTerminalsByBranchId($branch->branch_id);
            foreach($branch_terminals as $terminal){
                array_push($terminals, $terminal);
            }
        }
        return $terminals;
    }

    public static function getAssignedTerminalWithUsers($terminals){
        foreach($terminals as $index => $terminal){
            $terminals[$index]['users'] = TerminalUser::getAssignedUsers($terminal['terminal_id']);
        }
        return $terminals;
    }

    public static function deleteTerminal($terminal_id){
        TerminalUser::where('terminal_id', '=', $terminal_id)->delete();
        Terminal::where('terminal_id', '=', $terminal_id)->delete();
    }

    public static function createBusinessNewTerminal($business_id, $name){
        $first_branch = Branch::where('business_id', '=', $business_id)->first();
        $first_service = Service::where('branch_id', '=', $first_branch->branch_id)->first();

        $terminal = new Terminal();
        $terminal->name = $name;
        $terminal->service_id = $first_service->service_id;
        $terminal->status = 1;
        $terminal->box_rank = Terminal::generateBoxRank($first_service->service_id); // Added by PAG

        $terminal->save();
    }

    // Added by PAG
    private static function generateBoxRank($service_id) {
        $box_rank = array();
        $res = Terminal::where('service_id', '=', $service_id)->select(array('box_rank'))->get();
        foreach ($res as $count => $data) {
            $box_rank[] = $data->box_rank;
        }
        if (!in_array('1', $box_rank)) return '1';
        elseif (!in_array('2', $box_rank)) return '2';
        elseif (!in_array('3', $box_rank)) return '3';
        //return Terminal::where('service_id', '=', $service_id)->select(DB::raw('COUNT(*) AS rankcount'))->first()->rankcount + 1;
    }

    public static function boxRank($terminal_id) {
        return $terminal_id ? Terminal::where('terminal_id', '=', $terminal_id)->select(array('box_rank'))->first()->box_rank : 0;
    }

    public static function setName($terminal_id, $name) {
        Terminal::where('terminal_id', '=', $terminal_id)->update(array('name' => $name));
    }

    public static function deleteTerminalsByServiceId($service_id) {
        Terminal::where('service_id', '=', $service_id)->delete();
    }

}
