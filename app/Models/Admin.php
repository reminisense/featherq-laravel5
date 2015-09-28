<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model {

    public static function csvUrl(){
        return base_path('app/constants/FeatherqAdmins.csv');
    }

    public static function addAdmin($email){
        try{
            $emails = Admin::getAdmins();
            if(!in_array($email, $emails)){
                $emails[] = $email;
                $file = fopen(Admin::csvUrl(), 'w');
                fputcsv($file, $emails, ',');
                fclose($file);
            }
            return true;
        }catch(Exception $e){
            return false;
        }
    }

    public static function removeAdmin($email){
        try{
            $emails = Admin::getAdmins();
            if (in_array($email, $emails)){
                unset($emails[array_search($email,$emails)]);
                $file = fopen(Admin::csvUrl(), 'w');
                fputcsv($file, $emails, ',');
                fclose($file);
            }
            return true;
        }catch(Exception $e){
            return false;
        }
    }

    public static function getAdmins(){
        try{
            $file = fopen(Admin::csvUrl(), 'r');
            $emails = fgetcsv($file);
            fclose($file);
            return $emails;
        }catch(Exception $e){
            return [];
        }
    }

    public static function isAdmin($user_id = null){
        try{
            $user_id = $user_id ? $user_id : Helper::userId(); //$user_id = $user_id != NULL ? $user_id : Helper::userId(); // PAG changed because this will be true if $user_id = 0 which is supposed to be false too
            $emails = Admin::getAdmins();
            return in_array(User::email($user_id), $emails);
        }catch(Exception $e){
            return false;
        }
    }

}
