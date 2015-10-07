<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model {

    protected $table = 'message';
    protected $primaryKey = 'message_id';
    public $timestamps = false;

    public static function checkThreadByKey($thread_key) {
        return Message::where('thread_key', '=', $thread_key)->exists();
    }

    public static function createThread($val = array()) {
        Message::insert($val);
    }
    public static function updateThread($val = array(), $thread_key) {
        Message::where('thread_key', '=', $thread_key)->update($val);
    }

    public static function getPhoneByKey($thread_key) {
        return Message::where('thread_key', '=', $thread_key)->select(array('phone'))->first()->phone;
    }

    public static function getBusinessIdByMessageId($message_id) {
        return Message::where('message_id', '=', $message_id)->select(array('business_id'))->first()->business_id;
    }

    public static function getPhoneByMessageId($message_id) {
        return Message::where('message_id', '=', $message_id)->select(array('phone'))->first()->phone;
    }

    public static function getBusinessIdByThreadKey($thread_key) {
        return Message::where('thread_key', '=', $thread_key)->select(array('business_id'))->first()->business_id;
    }

    public static function getMessageIdByThreadKey($thread_key) {
        return Message::where('thread_key', '=', $thread_key)->select(array('message_id'))->first()->message_id;
    }

    public static function getThreadKeyByMessageId($message_id) {
        return Message::where('message_id', '=', $message_id)->select(array('thread_key'))->first()->thread_key;
    }

    public static function getMessagesByBusinessId($business_id) {
        return Message::where('business_id', '=', $business_id)->get();
    }

    public static function getMessagesByThreadKey($thread_key) {
        return Message::where('thread_key', '=', $thread_key)->get();
    }

    public static function getThreadKeyByBusinessIdAndEmail($business_id, $email) {
        return Message::where('business_id', '=', $business_id)->where('email', '=', $email)->select(array('thread_key'))->first()->thread_key;
    }

    public static function getMessagesByEmail($email){
        return Message::where('email', '=', $email)->get();
    }

    public static function threadKeyGenerator($business_id, $email) {
        return md5($business_id . 'fq' . $email);
    }

    public static function getThreadKeysByEmail($email) {
        return Message::where('email', '=', $email)->select(array('thread_key'))->get();
    }

}
