<?php
/**
 *
 * Should contain functions related to the broadcast page
 *
 * Created by PhpStorm.
 * User: USER
 * Date: 1/22/15
 * Time: 5:11 PM
 */

class AdvertisementController extends BaseController{

  public function postSliderImages() {
    if (Helper::isBusinessOwner(Input::get('business_id'), Helper::userId())) { // PAG added permission checking
      //$business_id = Input::get('business_id');
      //$count = 0;
      $ad_src = array();
      $res = AdImages::getAllImagesByBusinessId(Input::get('business_id'));
      foreach ($res as $count => $data) {
        $ad_src[] = array(
          'count' => $count,
          'path' => $data->path,
          'weight' => $data->weight,
          'img_id' => $data->img_id,
        );
      }
      /*
      $ad_directory = public_path() . '/ads/' . $business_id;
      if (file_exists($ad_directory)) {
        foreach (glob($ad_directory . '/*.*') as $filename) {
          $ad_src[] = array(
            'count' => $count,
            'path' => 'ads/' . Input::get('business_id') . '/' . basename($filename),
          );
          $count++;
        }
      }
      */
      return json_encode(array('slider_images' => $ad_src));
    }
    else {
      return json_encode(array('status' => 'You are not allowed to access this function.'));
    }
  }

  public function postDeleteImage() {
    if (Helper::isBusinessOwner(Input::get('business_id'), Helper::userId())) { // PAG added permission checking
      unlink(Input::get('path'));
      AdImages::deleteImageByPath(Input::get('path'));
      return json_encode(array('status' => 1));
    }
    else {
      return json_encode(array('status' => 'You are not allowed to access this function.'));
    }
  }

  public function postUploadImage() {
    if (Helper::isBusinessOwner(Input::get('business_id'), Helper::userId())) { // PAG added permission checking
      header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
      header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
      header("Cache-Control: no-store, no-cache, must-revalidate");
      header("Cache-Control: post-check=0, pre-check=0", FALSE);
      header("Pragma: no-cache");

      @set_time_limit(5 * 60);

      $targetDir = public_path() . '/ads/' . Input::get('business_id');
      $cleanupTargetDir = TRUE; // Remove old files
      //$maxFileAge = 5 * 3600; // Temp file age in seconds

      if (!file_exists($targetDir)) {
        @mkdir($targetDir);
      }

      if (isset($_REQUEST["name"])) {
        $fileName = $_REQUEST["name"];
      }
      elseif (!empty($_FILES)) {
        $fileName = $_FILES["file"]["name"];
      }
      else {
        $fileName = uniqid("file_");
      }

      $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
      $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
      $fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';

      // Clean the fileName for security reasons
      $fileName = preg_replace('/[^\w\._]+/', '_', $fileName);

      // Make sure the fileName is unique but only if chunking is disabled
      if ($chunks < 2 && file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName)) {
        $ext = strrpos($fileName, '.');
        $fileName_a = substr($fileName, 0, $ext);
        $fileName_b = substr($fileName, $ext);

        $count = 1;
        while (file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName_a . '_' . $count . $fileName_b)) {
          $count++;
        }

        $fileName = $fileName_a . '_' . $count . $fileName_b;
      }

      $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

      // Remove old temp files
      if ($cleanupTargetDir) {
        if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
          die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');
        }

        while (($file = readdir($dir)) !== FALSE) {
          $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

          // If temp file is current file proceed to the next
          if ($tmpfilePath == "{$filePath}.part") {
            continue;
          }

          // Remove temp file if it is older than the max age and is not the current file
          //if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge)) {
          //  @unlink($tmpfilePath);
          //}
        }
        closedir($dir);
      }


      // Open temp file
      if (!$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb")) {
        die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
      }

      if (!empty($_FILES)) {
        if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
          die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
        }

        // Read binary input stream and append it to temp file
        if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
          die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
        }
      }
      else {
        if (!$in = @fopen("php://input", "rb")) {
          die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
        }
      }

      while ($buff = fread($in, 4096)) {
        fwrite($out, $buff);
      }

      @fclose($out);
      @fclose($in);

      // Check if file has been uploaded
      if (!$chunks || $chunk == $chunks - 1) {
        // Strip the temp .part suffix off
        rename("{$filePath}.part", $filePath);
      }

      // save to json file
      /*
      $data = json_decode(file_get_contents(public_path() . '/json/' . $business_id . '.json'));
      $data->ad_image = $data->ad_image . '|ads/' . $business_id . '/' . basename($filePath);
      $encode = json_encode($data);
      file_put_contents(public_path() . '/json/' . $business_id . '.json', $encode);
      */

      AdImages::saveImages('ads/' . Input::get('business_id') . '/' . basename($filePath), Input::get('business_id'));

      // Return Success JSON-RPC response
      die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');

    }
    else {
      return json_encode(array('status' => 'You are not allowed to access this function.'));
    }

  }

  public function postEmbedVideo() {
    $post = json_decode(file_get_contents("php://input"));
    if ($post) {
      if (Helper::isBusinessOwner($post->business_id, Helper::userId())) { // PAG added permission checking
        $data = json_decode(file_get_contents(public_path() . '/json/' . $post->business_id . '.json'));
        $data->ad_video = preg_replace("/\s*[a-zA-Z\/\/:\.]*youtube.com\/watch\?v=([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i", "//www.youtube.com/embed/$1", $post->ad_video);
        $encode = json_encode($data);
        file_put_contents(public_path() . '/json/' . $post->business_id . '.json', $encode);
        return json_encode(array('ad_video' => $data->ad_video));
      }
      else {
        return json_encode(array('status' => 'You are not allowed to access this function.'));
      }
    }
    else {
      return json_encode(array('status' => 'Something went wrong..'));
    }
  }

  public function postTvSelect() {
    $post = json_decode(file_get_contents("php://input"));
    if ($post) {
      if (Helper::isBusinessOwner($post->business_id, Helper::userId())) { // PAG added permission checking
        $data = json_decode(file_get_contents(public_path() . '/json/' . $post->business_id . '.json'));
        //$data->ad_video = preg_replace("/\s*[a-zA-Z\/\/:\.]*youtube.com\/watch\?v=([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i", "//www.youtube.com/embed/$1", $_POST['ad_video']);
        $data->tv_channel = $post->tv_channel;
        $encode = json_encode($data);
        file_put_contents(public_path() . '/json/' . $post->business_id . '.json', $encode);
      }
      else {
        return json_encode(array('status' => 'You are not allowed to access this function.'));
      }
    }
    else {
      return json_encode(array('status' => 'Something went wrong..'));
    }
  }

  public function postTurnOnTv() {
    $post = json_decode(file_get_contents("php://input"));
    if ($post) {
      if (Helper::isBusinessOwner($post->business_id, Helper::userId())) { // PAG added permission checking
        $data = json_decode(file_get_contents(public_path() . '/json/' . $post->business_id . '.json'));
        //$data->ad_video = preg_replace("/\s*[a-zA-Z\/\/:\.]*youtube.com\/watch\?v=([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i", "//www.youtube.com/embed/$1", $_POST['ad_video']);
        $data->turn_on_tv = $post->status;
        $encode = json_encode($data);
        file_put_contents(public_path() . '/json/' . $post->business_id . '.json', $encode);
        //return json_encode(array('turn_on_tv' => $data->turn_on_tv));
      }
      else {
        return json_encode(array('status' => 'You are not allowed to access this function.'));
      }
    }
    else {
      return json_encode(array('status' => 'Something went wrong..'));
    }
  }

  public function postAdType() {
    $post = json_decode(file_get_contents("php://input"));
    if ($post) {
      if (Helper::isBusinessOwner($post->business_id, Helper::userId())) { // PAG added permission checking
        $data = json_decode(file_get_contents(public_path() . '/json/' . $post->business_id . '.json'));
        $data->ad_type = $post->ad_type;
        $encode = json_encode($data);
        file_put_contents(public_path() . '/json/' . $post->business_id . '.json', $encode);
        //return json_encode(array('ad_type' => $data->ad_type));
      }
      else {
        return json_encode(array('status' => 'You are not allowed to access this function.'));
      }
    }
    else {
      return json_encode(array('status' => 'Something went wrong..'));
    }
  }

  public function postSaveTicker() {
    $business_id = Input::get('business_id');
    if (Helper::isBusinessOwner($business_id, Helper::userId())) { // PAG added permission checking
      $data = json_decode(file_get_contents(public_path() . '/json/' . $business_id . '.json'));
      $data->ticker_message = Input::get('ticker_message');
      $data->ticker_message2 = Input::get('ticker_message2');
      $data->ticker_message3 = Input::get('ticker_message3');
      $data->ticker_message4 = Input::get('ticker_message4');
      $data->ticker_message5 = Input::get('ticker_message5');
      $encode = json_encode($data);
      file_put_contents(public_path() . '/json/' . $business_id . '.json', $encode);
    }
    else {
      return json_encode(array('status' => 'You are not allowed to access this function.'));
    }
  }

  public function postCarouselDelay() {
    $business_id = Input::get('business_id');
    if (Helper::isBusinessOwner($business_id, Helper::userId())) { // PAG added permission checking
      $data = json_decode(file_get_contents(public_path() . '/json/' . $business_id . '.json'));
      $data->carousel_delay = (int)Input::get('carousel_delay') * 1000;
      $encode = json_encode($data);
      file_put_contents(public_path() . '/json/' . $business_id . '.json', $encode);
    }
    else {
      return json_encode(array('status' => 'You are not allowed to access this function.'));
    }
  }

  public function postReorderImages() {
    if (Helper::isBusinessOwner(Input::get('business_id'), Helper::userId())) { // PAG added permission checking
      AdImages::setWeight(Input::get('weight'), Input::get('img_id'));
    }
    else {
      return json_encode(array('status' => 'You are not allowed to access this function.'));
    }
  }

}