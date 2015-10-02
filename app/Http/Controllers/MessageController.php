<?php

class MessageController extends BaseController {

    public function getDisplay() {
        if (Auth::check()) {
            return View::make('messages.inbox');
        }
        else {
            return Redirect::to('/');
        }
    }

    public function postAssignedBusinesses() {
        if (Auth::check()) {
            $businesses = array();
            $res = TerminalUser::getTerminalAssignement(Auth::user()->user_id);
            if (isset($res)) {
                foreach ($res as $count => $data) {
                    $business_id = Business::getBusinessIdByTerminalId($data['terminal_id']);
                    $businesses[] = array(
                        'business_id' => $business_id,
                        'business_name' => Business::name($business_id),
                    );
                }
            }
            return json_encode(array('businesses' => $businesses));
        }
        else {
            return json_encode(array('messages' => 'You are not allowed to access this function.'));
        }
    }

    public function postBusinessInbox() {
        if (Auth::check()) {
            $messages = array();
            $res = TerminalUser::getTerminalAssignement(Auth::user()->user_id);
            if (isset($res)) {
                foreach ($res as $count => $data) {
                    $list = Message::getMessagesByBusinessId(Business::getBusinessIdByTerminalId($data['terminal_id']));
                    foreach ($list as $count => $thread) {
                        $messages[] = array(
                            'contactname' => $thread->contactname,
                            'message_id' => $thread->message_id,
                            'business_id' => $thread->business_id,
                        );
                    }
                }
            }
            return json_encode(array('messages' => $messages));
        }
        else {
            return json_encode(array('messages' => 'You are not allowed to access this function.'));
        }
    }

    public function postOtherInbox() {
        if (Auth::check()) {
            $messages = array();
            $res = Message::getThreadKeysByEmail(User::email(Auth::user()->user_id));
            foreach ($res as $count => $data) {
                $list = Message::getMessagesByThreadKey($data->thread_key);
                foreach ($list as $count => $thread) {
                    $messages[] = array(
                        'contactname' => Business::name(Message::getBusinessIdByMessageId($thread->message_id)),
                        'message_id' => $thread->message_id,
                        'business_id' => $thread->business_id,
                    );
                }
            }
            return json_encode(array('messages' => $messages));
        }
        else {
            return json_encode(array('messages' => 'You are not allowed to access this function.'));
        }
    }

    public function postSendtoBusiness() {
      if (Auth::check()) {
        $business_id = Input::get('business_id');
        $attachment = Input::get('contfile');
        $email = User::email(Auth::user()->user_id);
        $timestamp = time();
        $thread_key = $this->threadKeyGenerator($business_id, $email);
        $custom_fields_bool = Input::get('custom_fields_bool');

        // save if there are custom fields available
        $custom_fields_data = '';
        if ($custom_fields_bool) {
          $custom_fields = Input::get('custom_fields');
          $res = Forms::getFieldsByBusinessId($business_id);
          foreach ($res as $count => $data) {
            $custom_fields_data .= '<strong>' . Forms::getLabelByFormId($data->form_id) . ':</strong> ' . $custom_fields[$data->form_id] . "\n";
          }
        }

        if (!Message::checkThreadByKey($thread_key)) {
          $phones[] = Input::get('contmobile');
          Message::createThread(array(
            'contactname' => User::first_name(Auth::user()->user_id) . ' ' . User::last_name(Auth::user()->user_id),
            'business_id' => $business_id,
            'email' => $email,
            'phone' => serialize($phones),
            'thread_key' => $thread_key,
          ));
          $data = json_encode(array(
            array(
              'timestamp' => $timestamp,
              'contmessage' => Input::get('contmessage') . "\n\n" . $custom_fields_data,
              'attachment' => $attachment,
              'sender' => 'user',
            )
          ));
          file_put_contents(public_path() . '/json/messages/' . $thread_key . '.json', $data);
        }
        else {
          $data = json_decode(file_get_contents(public_path() . '/json/messages/' . $thread_key . '.json'));
          $data[] = array(
            'timestamp' => $timestamp,
            'contmessage' => Input::get('contmessage') . "\n\n" . $custom_fields_data,
            'attachment' => $attachment,
            'sender' => 'user',
          );
          $data = json_encode($data);
          file_put_contents(public_path() . '/json/messages/' . $thread_key . '.json', $data);
        }

        /*
        Mail::send('emails.contact', array(
          'name' => $name,
          'email' => $email,
          'messageContent' => Input::get('contmessage') . "\n\nAttachment: " . $attachment . "\n\n" . $custom_fields_data,
        ), function($message, $email, $name)
        {
          $message->subject('Message from '. $name . ' ' . $email);
          $message->to('paul@reminisense.com');
        });
        */

        return json_encode(array('status' => 1));
      }
      else {
        return json_encode(array('messages' => 'You are not allowed to access this function.'));
      }
    }

    public function postSendtoUser(){
      $business_id = Message::getBusinessIdByMessageId(Input::get('message_id'));
      if (Helper::isPartOfBusiness($business_id, Helper::userId())) {
        $timestamp = time();
        if (Input::get('preview_type') == 'other') {
          $thread_key = Message::getThreadKeyByMessageId(Input::get('message_id'));
          $data = json_decode(file_get_contents(public_path() . '/json/messages/' . $thread_key . '.json'));
          $data[] = array(
            'timestamp' => $timestamp,
            'contmessage' => Input::get('messageContent'),
            'attachment' => Input::get('attachment'),
            'sender' => 'user',
          );
          $data = json_encode($data);
          file_put_contents(public_path() . '/json/messages/' . $thread_key . '.json', $data);
          return json_encode(array('timestamp' => date("Y-m-d h:i A", $timestamp)));
        }
        else {
          /*
           * ARA removed sms sending
          if (Input::get('sendbyphone')) {
            $business_name = Business::name(Input::get('business_id'));
            $text_message = 'From: ' . $business_name  .  "\n" . 'To: ' . Input::get('phonenumber') . "\n" . Input::get('messageContent') . "\n\nThanks for using FeatherQ";
            Notifier::sendFrontlineSMS($text_message, Input::get('phonenumber'), FRONTLINE_SMS_URL, FRONTLINE_SMS_SECRET);
          }
          */
          if (!Input::get('message_id')) {
            if (Input::get('business_id') && Input::get('email')) {
              $business_id = Input::get('business_id');
              $email = Input::get('email');
              $thread_key = $this->threadKeyGenerator($business_id, $email);
              Message::createThread(array(
                'contactname' => User::first_name(Auth::user()->user_id) . ' ' . User::last_name(Auth::user()->user_id),
                'business_id' => $business_id,
                'email' => $email,
                'thread_key' => $thread_key,
              ));
            }
          }
          else {
            $thread_key = Message::getThreadKeyByMessageId(Input::get('message_id'));
            $data = json_decode(file_get_contents(public_path() . '/json/messages/' . $thread_key . '.json'));
          }
          $attachment = Input::get('attachment');
          $data[] = array(
            'timestamp' => $timestamp,
            'contmessage' => Input::get('messageContent'),
            'attachment' => $attachment,
            'sender' => 'business',
          );
          $data = json_encode($data);
          file_put_contents(public_path() . '/json/messages/' . $thread_key . '.json', $data);
          /*
          $business_name = Business::name(Message::getBusinessIdByMessageId(Input::get('message_id')));
          $subject = 'Message From ' . $business_name;
          if (Input::get('attachment')) {
            $attachment = '<br><br><a href="' . Input::get('attachment') . '" download>Download Attachment</a>';
          }
          Notifier::sendEmail(Input::get('contactemail'), 'emails.messaging', $subject, array(
            'messageContent' => Input::get('messageContent') . $attachment,
            'businessName' => $business_name,
          ));
          */
          return json_encode(array('timestamp' => date("Y-m-d h:i A", $timestamp)));
        }
      }
      else {
        return json_encode(array('messages' => 'You are not allowed to access this function.'));
      }
    }

    public function postMessageList() {
      if (Auth::check()) {
        $messages = array();
        $list = Message::getMessagesByBusinessId(Input::get('business_id'));
        foreach ($list as $count => $thread) {
          $messages[] = array(
            'email' => $thread->email,
            'phone' => unserialize($thread->phone),
            'contactname' => $thread->contactname,
            'message_id' => $thread->message_id,
          );
        }
        return json_encode(array('messages' => $messages));
      }
      else {
        return json_encode(array('messages' => 'You are not allowed to access this function.'));
      }
    }

    public function postMessageThread() {
        return $this->getMessageThread(Input::get('preview_type'), Message::getThreadKeyByMessageId(Input::get('message_id')));
    }

    public function postPhoneList() {
        return json_encode(array('numbers' => unserialize(Message::getPhoneByMessageId(Input::get('message_id')))));
    }

    public function postBusinessUserThread(){
        $business_id = Input::get('business_id');
        $email = Input::get('email');
        $thread_key = Message::getThreadKeyByBusinessIdAndEmail($business_id, $email);
        $message_id = Message::getMessageIdByThreadKey($thread_key);
        $message_thread = $this->getMessageThread(null, $thread_key);
        $message_thread = json_decode($message_thread);
        $message_thread->message_id = $message_id;
        return json_encode($message_thread);
    }

    private function threadKeyGenerator($business_id, $email) {
        return md5($business_id . 'fq' . $email);
    }

    private function getMessageThread($preview_type = 'business', $thread_key){
        $message_content = array();
        $data = json_decode(file_get_contents(public_path() . '/json/messages/' . $thread_key . '.json'));
        foreach ($data as $count => $content) {
            if ($preview_type == 'other' && $content->sender == 'user') {
                $content->sender = 'business';
            }
            elseif ($preview_type == 'other' && $content->sender == 'business') {
                $content->sender = 'user';
            }
            $message_content[] = array(
                'timestamp' => date("Y-m-d h:i A", $content->timestamp),
                'content' => $content->contmessage,
                'attachment' => isset($content->attachment) ? $content->attachment : '',
                'sender' => $content->sender,
            );
        }
        return json_encode(array('contactmessage' => $message_content));
    }

}