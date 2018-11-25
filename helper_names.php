<?php

if ( $_GET['file'] ) { include( $_GET['file'] ); }
else  { include( 'base.php' ); }

# Welcome to the Social Ledger, a way to record social transactions in the flow
# A simple webhook called /diglife allows users to submit commands to the ledger
# As a response, the webhooks provides a list inside a table with further commands
# Webhooks only simple text and markdown in the responds, so the interaction needs 
# to be simple, which is a good thing


# This function is needed to extract the authentication token from the header
# Any other way will not work; the token is needed for subsequent API calls
function get_headers_from_curl_response($response) {
    $headers = array();

    $header_text = substr($response, 0, strpos($response, "\r\n\r\n"));

    foreach (explode("\r\n", $header_text) as $i => $line)
        if ($i === 0)
            $headers['http_code'] = $line;
        else {
            list ($key, $value) = explode(': ', $line);
            $headers[$key] = $value;
        }
    return $headers;
}

  ################################################################################
  # MATTERMOST API CALL: AUTHENTICATION
  # First we need to authenticate who we are via a dummy account with admin priv
  ################################################################################
  echo "Initiating API Call..<br>";
  $url = CHAT_URL."api/v4/users/login";
  $arr = array ( "login_id" => CHAT_USER,"password" => CHAT_PWD );   
  $data_string = json_encode($arr);
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_HEADER, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data_string))
  );
  $result = curl_exec($ch);
  $headers = get_headers_from_curl_response($result);
  $token = $headers['Token'];

  ################################################################################
  # MATTERMOST API CALL: USER LIST
  # Then we can get a list of all users in Mattermost using the acquired token
  ################################################################################
  $url = CHAT_URL."api/v4/users";
  $ch = curl_init($url);
  $authorization = "Authorization: Bearer ".$token;
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $result = curl_exec($ch);
  if ( ! $result ) { die("Unable to retrieve data from Mattermost. Please check your configuration file."); }

  ################################################################################
  # TRAVERSE TEAM  DATA
  # Now we are ready to traverse the resulting json structure to extract names 
  # We store the data in an associative array to merge the result with the NoSQL data 
  ################################################################################
  echo "Traversing Team Data..<br>";
  $json_arr = json_decode($result, true);
  $jsonIterator = new RecursiveIteratorIterator(
    new RecursiveArrayIterator($json_arr),
    RecursiveIteratorIterator::SELF_FIRST);

  $user_api_arr = array();
  foreach ($jsonIterator as $key => $val) {

   if ($key == "id") { $user_id = $val; }
     else if ($key == "username") { $username = $val; }
     else if ($key == "first_name") { $first_name = ucfirst($val); }
     else if ($key == "last_name") { $last_name = ucfirst($val); }
     else if ($key == "locale") { 
      $user_api_arr[$username]['id'] = $user_id; 
      $user_api_arr[$username]['name'] = $first_name." ".$last_name; 

      # Update Firebase with Fullname
      $username = str_replace('.', '%2E', $username); // No full stop in path name
      echo "Adding user ".$username."<br>";
      $url = FIREBASE_URL."users.json";
      $arr = [ $username => array ( "username" => $username, "id" => $user_id, "fullname" => $first_name." ".$last_name, "image" => 'avatar_'.$username.'.png' ) ]; 
      $data_string = json_encode($arr);
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
       'Content-Type: application/json',
       'Content-Length: ' . strlen($data_string))
      );
      $result = curl_exec($ch);

    } # IF

  } # FOREACH

  echo "Script completed!<br>";

?>