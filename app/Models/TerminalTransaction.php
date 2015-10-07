<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminalTransaction extends Model {

    protected $table = 'terminal_transaction';
    protected $primaryKey = 'transaction_number';
    public $timestamps = false;

    /*========================
     * retrieve methods
     =======================*/

    public static function terminalId($transaction_number){
        return TerminalTransaction::where('transaction_number', '=', $transaction_number)->first()->terminal_id;
    }


    /*===========================
     * update and create methods
     ============================*/

    /**
     * creates a new terminal transaction
     * @param unknown $transaction_number
     * @param string $time_queued
     */
    public static function createTerminalTransaction($transaction_number, $time_queued, $terminal_id = null){
        $values = [
            'transaction_number' => $transaction_number,
            'time_queued' => $time_queued,
        ];
        if($terminal_id) $values['terminal_id'] = $terminal_id;
        TerminalTransaction::insert($values);
    }


    /**
     * updates the time called of a particular transaction
     * @param unknown $transaction_number
     * @param string $time_called
     */
    public static function updateTransactionTimeCalled($transaction_number, $login_id, $time_called = null, $terminal_id = null){
        $values['login_id'] = $login_id;
        $values['time_called'] = $time_called == null ? time() : $time_called;
        if(isset($terminal_id))$values['terminal_id'] =  $terminal_id;  //Adds terminal id to terminal transaction to bypass hooking of terminals
        TerminalTransaction::where('transaction_number', '=', $transaction_number)->update($values);
    }

    /**
     * updates the time completed of a particular transaction
     * @param unknown $transaction_number
     * @param string $time_completed
     */
    public static function updateTransactionTimeCompleted($transaction_number, $time_completed = null){
        $values['time_completed'] = $time_completed == null ? time() : $time_completed;
        TerminalTransaction::where('transaction_number', '=', $transaction_number)->update($values);
    }

    /**
     * updates the time removed of a particular transaction
     * @param unknown $transaction_number
     * @param string $time_removed
     */
    public static function updateTransactionTimeRemoved($transaction_number, $time_removed = null){
        $values['time_removed'] = $time_removed == null ? time() : $time_removed;
        TerminalTransaction::where('transaction_number', '=', $transaction_number)->update($values);
    }

    public static function getTimesByTransactionNumber($transaction_number) {
        return TerminalTransaction::where('transaction_number', '=', $transaction_number)->select(array('time_queued', 'time_completed', 'time_removed'))->get();
    }

    public static function terminalActiveNumbers($terminal_id, $date = null){
        $date = $date == null ? mktime(0, 0, 0, date('m'), date('d'), date('Y')) : $date;
        $results = TerminalTransaction::where('terminal_id', '=', $terminal_id)
            ->where('time_queued', '!=', 0)
            ->where('time_completed', '=', 0)
            ->where('time_removed', '=', 0)
            ->where('priority_number.date', '=', $date)
            ->leftJoin('priority_queue', 'terminal_transaction.transaction_number', '=', 'priority_queue.transaction_number')
            ->leftJoin('priority_number', 'priority_queue.track_id', '=', 'priority_number.track_id')
            ->get()
            ->toArray();
        return $results ? count($results) : 0;
    }

    public static function queueStatus($transaction_number){
        $number = TerminalTransaction::where('transaction_number', '=', $transaction_number)->first();

        $called = $number->time_called != 0 ? TRUE : FALSE;
        $served = $number->time_completed != 0 ? TRUE : FALSE;
        $removed = $number->time_removed != 0 ? TRUE : FALSE;

        if(!$called && !$removed){
            return 'Queueing';
        }else if($called && !$served && !$removed){
            return 'Called';
        }else if($called && !$served && $removed){
            return 'Dropped';
        }else if(!$called && $removed){
            return 'Removed';
        }else if($called && $served){
            return 'Served';
        }else{
            return 'Error';
        }
    }

}
