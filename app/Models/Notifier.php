<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifier extends Model {

    public $timestamps = false;

    public static function sendNumberCalledNotification($transaction_number, $terminal_id){

        $service_id = Terminal::serviceId($terminal_id);
        $queue_setting = QueueSettings::getServiceQueueSettings($service_id);

        if($queue_setting->sms_current_number) Notifier::sendNumberCalledToAllChannels($transaction_number);
        if($queue_setting->sms_1_ahead) Notifier::sendNumberCalledToNextNumber($transaction_number, 1);
        if($queue_setting->sms_5_ahead) Notifier::sendNumberCalledToNextNumber($transaction_number, 5);
        if($queue_setting->sms_10_ahead) Notifier::sendNumberCalledToNextNumber($transaction_number, 10);
        if($queue_setting->sms_blank_ahead) Notifier::sendNumberCalledToNextNumber($transaction_number, $queue_setting->input_sms_field);
    }

    public static function sendNumberCalledToAllChannels($transaction_number){
        Notifier::sendNumberCalledEmail($transaction_number);
        Notifier::sendNumberCalledSms($transaction_number);
        Notifier::sendNumberCalledAndroid($transaction_number);
    }

    public static function sendNumberNextToAllChannels($transaction_number, $diff){
        Notifier::sendNumberNextEmail($transaction_number, $diff);
        Notifier::sendNumberNextSms($transaction_number, $diff);
        Notifier::sendNumberNextAndroid($transaction_number, $diff);
    }

    public static function sendNumberCalledToNextNumber($transaction_number, $diff){
        $number = TerminalTransaction::where('transaction_number', '>=', $transaction_number)->skip($diff)->first();
        if($number){
            Notifier::sendNumberNextToAllChannels($number->transaction_number, $diff);
        }
    }

    /**
     * Email sending templates
     */

    public static function sendNumberCalledEmail($transaction_number){
        $email = PriorityQueue::email($transaction_number);
        $name = PriorityQueue::name($transaction_number);
        if($email){
            $terminal_id = TerminalTransaction::terminalId($transaction_number);
            $data = [
                'name' => $name == null ? null : ' ' . $name,
                'priority_number' => PriorityQueue::priorityNumber($transaction_number),
                'terminal_name' => $terminal_id != 0 ? Terminal::name($terminal_id) : '',
                'business_name' => $terminal_id != 0 ? Business::name(Business::getBusinessIdByTerminalId($terminal_id)) : '',
            ];
            Notifier::sendEmail($email, 'emails.process-queue.number-called', 'FeatherQ Message: Your number has been called.', $data);
        }
    }

    public static function sendNumberNextEmail($transaction_number, $diff = null){
        $email = PriorityQueue::email($transaction_number);
        $name = PriorityQueue::name($transaction_number);
        if($email){
            $terminal_id = TerminalTransaction::terminalId($transaction_number);
            $business_id = $terminal_id ? Business::getBusinessIdByTerminalId($terminal_id) : 0;
            $business_name = $business_id ? Business::name($business_id) : '';
            $waiting_time = Analytics::getWaitingTime($business_id);
            $estimated_time = Helper::millisecondsToHMSFormat($waiting_time);
            $data = [
                'name' => $name == null ? null : ' ' . $name,
                'priority_number' => PriorityQueue::priorityNumber($transaction_number),
                'numbers_ahead' => $diff,
                'business_name' => $business_name,
                'estimated_time' => $estimated_time,
            ];
            Notifier::sendEmail($email, 'emails.process-queue.number-next', 'FeatherQ Message: Your number will be called soon.', $data);
        }
    }

    public static function sendSignupEmail($email, $name){
        Notifier::sendEmail($email, 'emails.auth.signup', 'Welcome to FeatherQ', ['name' => $name]);
    }


    /**
     * Sms sending templates
     */

    public static function sendNumberCalledSms($transaction_number){
        $phone = PriorityQueue::phone($transaction_number);
        $name = PriorityQueue::name($transaction_number);
        if($phone){
            $terminal_id = TerminalTransaction::terminalId($transaction_number);
            $service_id = Terminal::serviceId($terminal_id);
            $priority_number = PriorityQueue::priorityNumber($transaction_number);
            $terminal_name = $terminal_id != 0 ? Terminal::name($terminal_id) : '';
            $business_name = $terminal_id != 0 ? Business::name(Business::getBusinessIdByTerminalId($terminal_id)) : '';
            $name = $name == null ? null : ' ' . $name;
            $message = "Hello$name, Your number ($priority_number) has been called! Please proceeed to the $terminal_name at $business_name.";
            Notifier::sendServiceSms($message, $phone, $service_id);
        }
    }

    public static function sendNumberNextSms($transaction_number, $diff){
        $phone = PriorityQueue::phone($transaction_number);
        $name = PriorityQueue::name($transaction_number);
        if($phone){
            $pq = Helper::firstFromTable('priority_queue', 'transaction_number', $transaction_number);
            $terminal_id = TerminalTransaction::terminalId($transaction_number);
            $service_id = PriorityNumber::serviceId($pq->track_id);
            $priority_number = PriorityQueue::priorityNumber($transaction_number);
            $current_number = ProcessQueue::currentNumber($service_id);
            $terminal_name = $terminal_id != 0 ? Terminal::name($terminal_id) : '';
            $business_name = $terminal_id != 0 ? Business::name(Business::getBusinessIdByTerminalId($terminal_id)) : '';
            $name = $name == null ? null : ' ' . $name;
            $message = "Hello$name, Your number, ($priority_number), will be called soon. ";
            $message .= "Number ($current_number) has been called by $terminal_name. ";
            if($diff >= 1){
                $message .= $diff == 1 ? "There is currently " . $diff . " person ahead of you " : "There are currently " . $diff . " people ahead of you ";
            }
            $message .= "at the $terminal_name at $business_name.";
            Notifier:: sendServiceSms($message, $phone, $service_id);
        }
    }

    public static function sendServiceSms($message, $phone, $service_id){
        Notifier::sendSMS($message, $phone, $service_id);
    }

    /**
     * Android sending functions
     */

    public static function sendNumberCalledAndroid($transaction_number){
        $priority_queue = PriorityQueue::find($transaction_number);
        if($priority_queue){
            $user_id = $priority_queue->user_id;
            $priority_number = $priority_queue->priority_number;
            $queue_platform = $priority_queue->queue_platform;
            $email = $priority_queue->email;

            $terminal_id = TerminalTransaction::terminalId($transaction_number);
            $terminal_name = $terminal_id != 0 ? Terminal::name($terminal_id) : '';
            $business_name = $terminal_id != 0 ? Business::name(Business::getBusinessIdByTerminalId($terminal_id)) : '';
            $message = "Please proceed to $terminal_name at $business_name. Your number ($priority_number) has been called.";

            if($queue_platform != 'web' && $queue_platform != 'specific'){
                $gcm_token = User::gcmToken($user_id);
                if($gcm_token) Notifier::sendAndroid($gcm_token, $message);
            }else if(($queue_platform == 'web' || $queue_platform == 'specific') && $email != null){
                $user = User::searchByEmail($email);
                $gcm_token = $user ? User::gcmToken($user['user_id']) : null;
                if($gcm_token) Notifier::sendAndroid($gcm_token, $message);
            }
        }
    }

    public static function sendNumberNextAndroid($transaction_number, $diff){
        $priority_queue = PriorityQueue::find($transaction_number);
        if($priority_queue){
            $user_id = $priority_queue->user_id;
            $priority_number = $priority_queue->priority_number;
            $queue_platform = $priority_queue->queue_platform;
            $email = $priority_queue->email;

            $terminal_id = TerminalTransaction::terminalId($transaction_number);
            $terminal_name = $terminal_id != 0 ? Terminal::name($terminal_id) : '';
            $business_id = $terminal_id != 0 ? Business::getBusinessIdByTerminalId($terminal_id) : 0;
            $business_name = $business_id != 0 ? Business::name($business_id) : '';
            $waiting_time = Analytics::getWaitingTime($business_id);
            $estimated_time = Helper::millisecondsToHMSFormat($waiting_time);
            $message = "Your number ($priority_number) will be called in approximately $estimated_time. ";
            if($diff == 1){
                $message .= "There is currently $diff person ahead of you at the $terminal_name at $business_name.";
            }else{
                $message .= "There are currently $diff people ahead of you at the $terminal_name at $business_name.";
            }

            if($queue_platform != 'web' && $queue_platform != 'specific'){
                $gcm_token = User::gcmToken($user_id);
                if($gcm_token) Notifier::sendAndroid($gcm_token, $message);
            }else if(($queue_platform == 'web' || $queue_platform == 'specific') && $email != null){
                $user = User::searchByEmail($email);
                $gcm_token = $user ? User::gcmToken($user['user_id']) : null;
                if($gcm_token) Notifier::sendAndroid($gcm_token, $message);
            }
        }
    }

    /**
     * Core sending functions
     */

    public static function sendEmail($email, $template, $subject, $data = array()){
        Mail::send($template, $data, function($message) use($email , $subject){
            $message->subject($subject);
            $message->to($email);
        });
    }

    public static function sendSMS($message, $phone, $service_id){
        //ARA Adding sms as business feature
        $business_features = Business::getBusinessFeatures(Branch::businessId(Service::branchId($service_id)));
        if($business_features['allow_sms'] == 'true'){
            $gateway = QueueSettings::smsGateway($service_id);
            $api_variables = unserialize(QueueSettings::smsGatewayApi($service_id));
            if($gateway == 'frontline_sms'){
                $url = QueueSettings::frontlineUrl($service_id);
                $secret = QueueSettings::frontlineSecret($service_id);
                $api_key = null;
                if($url == FRONTLINE_SMS_URL && $secret == FRONTLINE_SMS_SECRET){
                    $url = $api_variables['frontline_sms_url'];
                    $api_key = $api_variables['frontline_sms_api_key'];
                }

                Notifier::sendFrontlineSMS($message, $phone, $url, $secret, $api_key);
            }else if($gateway == 'twilio'){
                if($api_variables['twilio_phone_number'] == TWILIO_PHONE_NUMBER && $api_variables['twilio_account_sid'] == TWILIO_ACCOUNT_SID && $api_variables['twilio_auth_token'] == TWILIO_AUTH_TOKEN){
                    //@todo do something says that they are using featherq twilio service
                }
                $from = $api_variables['twilio_phone_number'];
                $account_sid = $api_variables['twilio_account_sid'];
                $auth_token = $api_variables['twilio_auth_token'];

                Notifier::sendTwilio($phone, $message, $from, $account_sid, $auth_token);
            }
        }
    }

    public static function sendFrontlineSMS($message, $phone, $url, $secret = null, $api_key = FRONTLINE_API_KEY){
//        $request = [
//            'secret' => $secret,
//            'message' => $message,
//            'recipients' => [
//                    ['type' => 'address', 'value' => "$phone"]
//            ]
//        ];

        //new request
        $request = [
            'apiKey' => $api_key,
            'payload' => [
                'message' => $message,
                'recipients' => [
                    ['type' => 'mobile', 'value' => "$phone"]
                ]
            ]
        ];

        //php_curl.dll must be enabled for the ff. code to work
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($request) );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    public static function sendTwilio($to, $message, $from = TWILIO_PHONE_NUMBER, $AccountSid = TWILIO_ACCOUNT_SID, $AuthToken = TWILIO_AUTH_TOKEN){
        // set your AccountSid and AuthToken from www.twilio.com/user/account
        $_http = new Services_Twilio_TinyHttp( "https://api.twilio.com", array("curlopts" => array( CURLOPT_SSL_VERIFYPEER => false,)));
        $client = new Services_Twilio($AccountSid, $AuthToken, null, $_http);

        $message = $client->account->messages->create(array(
            "From" => $from,
            "To" => $to,
            "Body" => $message,
        ));

        return $message;
    }

    public static function sendAndroid($device_token, $message, $title = "FeatherQ", $subtitle = null){
        $registrationIds = array($device_token);

        // prep the bundle
        $msg = array
        (
            'message'       => $message,
            'title'         => $title,
            'subtitle'      => $subtitle,
            'tickerText'    => $message,
            'vibrate'   => 1,
            'sound'     => 1
        );

        $fields = array
        (
            'registration_ids'  => $registrationIds,
            'data'              => $msg
        );

        $headers = array
        (
            'Authorization: key=' . API_ACCESS_KEY,
            'Content-Type: application/json'
        );

        $ch = curl_init();
        curl_setopt( $ch,CURLOPT_URL, 'https://android.googleapis.com/gcm/send' );
        curl_setopt( $ch,CURLOPT_POST, true );
        curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
        $result = curl_exec($ch );
        curl_close( $ch );

        return $result;
    }

}
