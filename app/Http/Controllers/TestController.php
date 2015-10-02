<?php
/**
 * Created by PhpStorm.
 * User: USER
 * Date: 7/29/15
 * Time: 4:48 PM
 */

class TestController extends Controller{

    public function getTwilio($number, $message){
        return Notifier::sendTwilio($number, $message);
    }
}