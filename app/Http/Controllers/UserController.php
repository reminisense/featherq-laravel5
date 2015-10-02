<?php
/**
 * Created by PhpStorm.
 * User: USER
 * Date: 1/22/15
 * Time: 5:12 PM
 */

class UserController extends BaseController{

    /*
     * @author: CSD
     * @description: check if user data is validated through verified user table row
     */
    public function getUserStatus()
    {
      /* PAG code re-written due to trivial values
        $user = Auth::user();

        return json_encode([
            'success'   => 1,
            'user'      => $user,
        ]);
      */
      return json_encode(array('success'   => 1, 'user' => Auth::user()));
    }

    /*
     * @author: CSD
     * @description: update user profile details
     */
    public function postUpdateUser(){
        $userData = $_POST;
      if (Auth::check() && Helper::userId() == $userData['user_id']) { // PAG added permission checking
        $user = User::find($userData['user_id']);
        $user->first_name = $userData['edit_first_name'];
        $user->last_name = $userData['edit_last_name'];
        $user->phone = $userData['edit_mobile'];
        $user->local_address = $userData['edit_user_location'];

        if ($user->save()) {
          return json_encode([
            'success' => 1,
          ]);
        }
        else {
          return json_encode([
            'success' => 0,
            'error' => 'Something went wrong while trying to save your profile.'
          ]);
        }
      }
      else {
        return json_encode(array('message' => 'You are not allowed to access this function.'));
      }
    }

    /*
     * @author: CSD
     * @description: verify data and update user details
     */
    public function postVerifyUser(){
        $userData = $_POST;
      if (Auth::check() && Helper::userId() == $userData['user_id']) { // PAG added permission checking
        $user = User::find($userData['user_id']);
        $user->first_name = $userData['first_name'];
        $user->last_name = $userData['last_name'];
        $user->email = $userData['email'];
        $user->phone = $userData['mobile'];
        $user->local_address = $userData['location'];
        $user->verified = 1;

        if ($user->save()) {
          return json_encode([
            'success' => 1,
          ]);
        }
        else {
          return json_encode([
            'success' => 0,
          ]);
        }
      }
      else {
        return json_encode(array('message' => 'You are not allowed to access this function.'));
      }
    }

    /*
     * @author: CSD
     * @description: render dashboard, fetch all businesses for default search view, and businesses created by logged in user
     */
    public function getUserDashboard(){
        $response = Helper::VerifyFB(Session::get('FBaccessToken'));
        //$active_businesses = Business::getDashboardBusinesses();
        if (Auth::check())
        {
            /*
            //$search_businesses = Business::getPopularBusinesses(); ARA No more popular businesses. only active businesses
            $business_ids = UserBusiness::getAllBusinessIdByOwner(Helper::userId());
            $my_businesses = [];
            if (count($business_ids) > 0){
                foreach($business_ids as $b_id)
                {
                    $temp_array = Business::getBusinessArray($b_id->business_id);
                    $temp_array->owner = 1;
                    array_push($my_businesses, $temp_array);
                }
            }
            /* @author: CSD
             * @desc: check if user already has own business

            if(count($my_businesses) > 0){
                $has_business = true;
            } else {
                $has_business = false;
            }

            $my_terminals = TerminalUser::getTerminalAssignement(Auth::user()->user_id);
            if (count($my_terminals) > 0){
                foreach($my_terminals as $terminal){
                    $b_id = Business::getBusinessIdByTerminalId($terminal['terminal_id']);
                    $business = Business::getBusinessArray($b_id);
                    if (!$this->inArrayBusiness($my_businesses, $business)){
                        $business->owner = 0;
                        array_push($my_businesses, $business);
                    }
                }
            }
            */

            return View::make('user.dashboardnew');
            //->with('search_businesses', $search_businesses) ARA No more popular businesses. only active businesses
            //->with('active_businesses', $active_businesses)
            //->with('my_businesses', $my_businesses)
            //->with('has_business', $has_business);
        }
        else
        {
            return View::make('homepage');
            //->with('active_businesses', $active_businesses)
            //->with('search_businesses', Business::getNewBusinesses()); // RDH Active and New Businesses on Front Use Different Results
        }
    }

    public function processContactForm(){
        $data = [
            'name' => Input::get('name'),
            'email' => Input::get('email'),
            'messageContent' => Input::get('message')
        ];
        Mail::send('emails.contact', $data, function($message)
        {
            $message->subject('Inquiry at FeatherQ'); // RDH Changed to correct spelling "Inquiry"
            $message->to('admin@reminisense.com');
        });

        Mail::send('emails.contact_confirmation', $data, function($message)
        {
            $message->subject('Confirmation Message from FeatherQ');
            $message->to(Input::get('email'));
        });

        return Redirect::to('/#contact')
            ->with('message', 'Email successfully sent!');
    }

    private function inArrayBusiness($businesses, $business){
        foreach($businesses as $haystack){
            if ($haystack->business_id == $business->business_id){
                return true;
            }
        }

        return false;
    }

//    removed due to new implementation of assigning users
//    public function getUserlist(){
//        $users = User::getAllUsers();
//        return json_encode(['success' => 1 , 'users' => $users]);
//    }

    public function getEmailsearch($email){
        $user = User::searchByEmail($email);
        return json_encode(['success' => 1, 'user' => $user]);
    }

    /**
     * @author Ruffy Heredia
     * @description Get User by Facebook ID
     */
    public function getFacebookIdSearch($fb_id) {
        $user = User::searchByFacebookId($fb_id);
        return json_encode(['success' => 1, 'user' => $user]);
    }

    /**
     * @author Ruffy Heredia
     * @description Get GCM token of user based on Facebook ID
     */
    public function getGcmToken($fb_id) {
        $user = User::getGcmByFacebookId($fb_id);
        return json_encode(['success' => 1, 'user' => $user]);
    }

    /**
     * @author Carl Dalid
     * @description Check if FB ID exist, if true update GCM Token
     */
    public function getUpdateGcmToken($fb_id, $gcm){
        $user = User::checkFBUser($fb_id);
        if($user){
            User::updateGCMToken($fb_id, $gcm);
            return json_encode(['success' => 1, 'user' => $user]);
        } else {
            return json_encode(['success' => 0]);
        }
    }

    /**
     * @author: Carl Dalid
     * @description: Get User by User ID for remote queue
     */
    public function getRemoteuser($user_id){
        if($user_id != 0){
            return json_encode(array('status' => '1', 'first_name' => User::first_name($user_id), 'last_name' => User::last_name($user_id), 'phone' => User::phone($user_id), 'email' => User::email($user_id)));
        } else {
            return json_encode(array('status' => '0'));
        }

    }

    public function getSearchUser($keyword){
        $users = User::searchByKeyword($keyword);
        return json_encode(['success' => 1, 'users' => $users]);
    }
}