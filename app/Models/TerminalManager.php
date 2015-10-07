<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminalManager extends Model {

    protected $table = 'terminal_manager';
    protected $primaryKey = 'login_id';
    public $timestamps = false;

    public static function hookedTerminal($terminal_id) {
        if (TerminalManager::where('terminal_id', '=', $terminal_id)->first()) {
            return TerminalManager::orderBy('login_id', 'desc')->select('in_out')->where('terminal_id', '=', $terminal_id)->first()->in_out;
        }
    }

    public static function getAssignedTerminal($user_id){
        return TerminalManager::orderBy('login_id', 'desc')->select('terminal_id')->where('user_id', '=', $user_id)->first()->terminal_id;
    }

    public static function getLatestLoginIdOfTerminal($terminal_id) {
        return TerminalManager::orderBy('login_id', 'desc')->select('login_id')->where('terminal_id', '=', $terminal_id)->first()->login_id;
    }

    public static function getTerminalManagerLoginId($user_id){
        $row = TerminalManager::orderBy('login_id', 'desc')->where('user_id', '=', $user_id)->first();
        return $row ? $row->login_id : null;
    }

    public static function checkLoginIdIsUser($user_id, $login_id){
        $row = TerminalManager::where('user_id', '=', $user_id)->where('login_id', '=', $login_id)->get();
        return $row ? true: false;
    }

    public static function getAssignedUsers($terminal_id, $date = null ){
        //@todo implement function that gets the users that have hooked in but have not hooked out
        return TerminalManager::where('terminal_id', '=', $terminal_id)
            ->where('in_out', '=', 1)
            ->join('user', 'user.user_id', '=', 'terminal_manager.user_id')
            ->select('terminal_manager.*', 'user.first_name' , 'user.last_name')
            ->get()
            ->toArray();
    }

    public static function addToTerminal($user_id, $terminal_id){
        try{
            $hooked_terminal = TerminalManager::getAssignedTerminal($user_id);
            if($hooked_terminal){
                TerminalManager::unassignFromTerminal($user_id, $hooked_terminal);
            }
            TerminalManager::assignToTerminal($user_id, $terminal_id);
        }catch(Exception $e){
            TerminalManager::assignToTerminal($user_id, $terminal_id);
        }
    }

    public static function assignToTerminal($user_id, $terminal_id){
        return TerminalManager::addTerminalManager($user_id, $terminal_id, 1);
    }

    public static function unassignFromTerminal($user_id, $terminal_id){
        TerminalManager::where('user_id', '=', $user_id)
            ->where('terminal_id', '=', $terminal_id)
            ->where('in_out', '=', 1)
            ->update(['in_out' => 0]);
    }

    public static function addTerminalManager($user_id, $terminal_id, $in_out = 1){
        $values = [
            'user_id' =>$user_id,
            'terminal_id' => $terminal_id,
            'in_out' => $in_out,
        ];
        return TerminalManager::insertGetId($values);
    }

}
