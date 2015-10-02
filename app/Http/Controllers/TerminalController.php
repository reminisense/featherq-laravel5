<?php
/**
 * Created by PhpStorm.
 * User: USER
 * Date: 2/3/15
 * Time: 6:53 PM
 */

class TerminalController extends BaseController{

    public function postAssign(){
      $business_id = Business::getBusinessIdByTerminalId(Input::get('terminal_id'));
      if (Helper::isBusinessOwner($business_id, Helper::userId())) { // PAG added permission checking
        TerminalUser::assignTerminalUser(Input::get('user_id'), Input::get('terminal_id'));
        $business = Business::getBusinessDetails(Business::getBusinessIdByTerminalId(Input::get('terminal_id')));
        return json_encode(['success' => 1, 'business' => $business]);
      }
      else {
        return json_encode(array('message' => 'You are not allowed to access this function.'));
      }
    }

    public function postUnassign(){
      $business_id = Business::getBusinessIdByTerminalId(Input::get('terminal_id'));
      if (Helper::isBusinessOwner($business_id, Helper::userId())) { // PAG added permission checking
        TerminalUser::unassignTerminalUser(Input::get('user_id'), Input::get('terminal_id'));
        $business = Business::getBusinessDetails(Business::getBusinessIdByTerminalId(Input::get('terminal_id')));
        return json_encode(['success' => 1, 'business' => $business]);
      }
      else {
        return json_encode(array('message' => 'You are not allowed to access this function.'));
      }
    }

    public function postDelete(){
      $business_id = Business::getBusinessIdByTerminalId(Input::get('terminal_id'));
      if (Helper::isBusinessOwner($business_id, Helper::userId())) { // PAG added permission checking
        $error = 'There are still pending numbers for this terminal.';
        if (TerminalTransaction::terminalActiveNumbers(Input::get('terminal_id')) == 0) {
          Terminal::deleteTerminal(Input::get('terminal_id'));
          $error = NULL;
        }
        $business = Business::getBusinessDetails($business_id);
        $business['error'] = $error;
        return json_encode(['success' => 1, 'business' => $business]);
      }
      else {
        return json_encode(array('message' => 'You are not allowed to access this function.'));
      }
    }

    public function postCreate(){
      if (Helper::isBusinessOwner(Input::get('business_id'), Helper::userId())) { // PAG added permission checking
        $terminal_id = count(Terminal::getTerminalsByBusinessId(Input::get('business_id')));
        if ($this->validateTerminalName(Input::get('business_id'), Input::get('name'), $terminal_id)) {
          Terminal::createBusinessNewTerminal(Input::get('business_id'), Input::get('name'));
          $business = Business::getBusinessDetails(Input::get('business_id'));
          return json_encode(['success' => 1, 'business' => $business]);
        }
        else {
          return json_encode(['status' => 0]);
        }
      }
      else {
        return json_encode(array('message' => 'You are not allowed to access this function.'));
      }
    }

    public function postEdit() {
      $post = json_decode(file_get_contents("php://input"));
      $business_id = Business::getBusinessIdByTerminalId($post->terminal_id);
      if (Helper::isBusinessOwner($business_id, Helper::userId())) { // PAG added permission checking
        if ($this->validateTerminalName($business_id, $post->name, $post->terminal_id)) {
          Terminal::setName($post->terminal_id, $post->name);
          return json_encode(array('status' => 1));
        }
        else {
          return json_encode(array('status' => 0));
        }
      }
      else {
        return json_encode(array('message' => 'You are not allowed to access this function.'));
      }
    }

    public function validateTerminalName($business_id, $input_terminal_name, $terminal_id){
        $terminals = Terminal::getTerminalsByBusinessId($business_id);

        foreach($terminals as $terminal){

            /* JCA - string to lower case, remove spaces before and after, and removes whitepaces in between*/
            $trimmed_input_terminal_name_lower = preg_replace('/\s+/', ' ', trim(strtolower($input_terminal_name)));
            $trimmed_terminal_name_lower = preg_replace('/\s+/', ' ', trim(strtolower($terminal['name'])));

            if($terminal['terminal_id'] != $terminal_id && $trimmed_terminal_name_lower == $trimmed_input_terminal_name_lower){
                return false;
            }
        }
        return true;
    }
}