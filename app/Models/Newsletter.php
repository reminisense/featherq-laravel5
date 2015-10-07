<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model {

    protected $table = 'newsletter_subscription';
    protected $primaryKey = 'email_id';
    public $timestamps = false;

    public static function searchSubscribedEmail($email)
    {

        $subscriber = Newsletter::where('email', '=', $email)->get();

        return $subscriber ? $subscriber->toArray() : null;
    }

    public static function saveEmail($email)
    {
        $data = ['email' => $email];
        Newsletter::insert($data);
    }

    public static function sendEmail($email)
    {
        Mail::send('emails.newsletter_subscription', $data = array(), function($message) use ($email)
        {
            $message->to($email)->subject("Thank you for subscribing to FeatherQ's Newsletters");
        });
    }

}
