<?php
/**
 * Created by PhpStorm.
 * User: JONAS
 * Date: 6/10/2015
 * Time: 2:41 PM
 */


class NewsletterController extends BaseController {

    public function getSubscribe($email){
        $user = Newsletter::searchSubscribedEmail($email);
        if(!$user){
            Newsletter::saveEmail($email);
            Newsletter::sendEmail($email);
            return json_encode(['success' => 1]);
        }else{
            return json_encode(['success' => 0]);
        }
    }

}