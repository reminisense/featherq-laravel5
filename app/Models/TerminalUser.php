<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class TerminalUser extends Model {

    protected $table = 'terminal_user';
    protected $primaryKey = 'terminal_user_id';
    public $timestamps = false;

    public static function assignTerminalUser($user_id, $terminal_id){
        if(TerminalUser::terminalUserExists($user_id, $terminal_id)){
            TerminalUser::updateTerminalUserStatus($user_id, $terminal_id, 1);
        }else{
            TerminalUser::createTerminalUser($user_id, $terminal_id);
        }
    }

    public static function unassignTerminalUser($user_id, $terminal_id){
        TerminalUser::updateTerminalUserStatus($user_id, $terminal_id, 0);
    }

    public static function updateTerminalUserStatus($user_id, $terminal_id, $status = 0){
        TerminalUser::where('user_id', '=', $user_id)->where('terminal_id', '=', $terminal_id)->update(['status' => $status]);
    }

    public static function terminalUserExists($user_id, $terminal_id){
        return TerminalUser::where('user_id', '=', $user_id)->where('terminal_id', '=', $terminal_id)->first();
    }

    public static function createTerminalUser($user_id, $terminal_id){
        $date = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $values = [
            'user_id' => $user_id,
            'terminal_id' => $terminal_id,
            'status' => 1,
            'date' => $date
        ];
        return TerminalUser::insertGetId($values);
    }

    public static function getTerminalAssignement($user_id = 0){
        return TerminalUser::where('status', '=', 1)->where('user_id', '=', $user_id)->get()->toArray();
    }

    public static function getAssignedUsers($terminal_id){
        return TerminalUser::where('terminal_user.status', '=', 1)
            ->where('terminal_user.terminal_id', '=', $terminal_id)
            ->join('user', 'user.user_id', '=', 'terminal_user.user_id')
            ->select('terminal_user.*', 'user.first_name' , 'user.last_name')
            ->get()
            ->toArray();
    }

    public static function isUserAssignedToTerminal($user_id, $terminal_id){
        return TerminalUser::where('status', '=', 1)->where('user_id', '=', $user_id)->where('terminal_id', '=', $terminal_id)->first();
    }

    public static function isCurrentUserAssignedToTerminal($terminal_id){
        return TerminalUser::isUserAssignedToTerminal(Helper::userId(), $terminal_id);
    }

    public static function deleteUserByTerminalId($terminal_id) {
        TerminalUser::where('terminal_id', '=', $terminal_id)->delete();
    }

}
