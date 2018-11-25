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
  # MATTERMOST API CALL: GET BOT USER
  # Then we can get a list of all users in Mattermost using the acquired token
  ################################################################################
  $url = CHAT_URL."api/v4/users/username/bridgebot";
  $ch = curl_init($url);
  $authorization = "Authorization: Bearer ".$token;
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $response = curl_exec($ch);
  $user = json_decode($response, true);
  $user_id = $user['id'];

  ################################################################################
  # MATTERMOST API CALL: GET TEAMS=DOMAINS
  ################################################################################
  $bot_domain_arr = array();
  $url = CHAT_URL."api/v4/teams";
  $ch = curl_init($url);
  $authorization = "Authorization: Bearer ".$token;
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $response = curl_exec($ch);
  $domains = json_decode($response,true);
  foreach ( $domains as $domain ) {
    $bot_domain_arr[] = $domain['id'];
  }

  # die(var_dump($bot_domain_arr ));

  ################################################################################
  # MATTERMOST API CALL: GET CHANNELS
  ################################################################################
  $bot_channel_arr = array();
  foreach ( $bot_domain_arr as $bot_domain ) {

    $url = CHAT_URL."api/v4/teams/".$bot_domain."/channels";
    $ch = curl_init($url);
    $authorization = "Authorization: Bearer ".$token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $response = curl_exec($ch);
    $channels = json_decode($response,true);
    foreach ( $channels as $channel ) {
      $bot_channel_arr[] = $channel['id'];
      echo "Targeting channel ".$channel['name']."..<br>";
    }
  }
  # die(var_dump($bot_channel_arr));

  ################################################################################
  # MATTERMOST API CALL: ASSIGN USER TO CHANNELS
  ################################################################################
  foreach ( $bot_channel_arr as $bot_channel ) {

    $url = CHAT_URL."api/v4/channels/".$bot_channel."/members";
    $ch = curl_init($url);
    $arr = array ( "user_id" => $user_id,  "post_root_id" => "This is bridgebot, resistance is futile."  );  
    $data_string = json_encode($arr);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    $authorization = "Authorization: Bearer ".$token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $response = curl_exec($ch);

  }


  echo "Script completed!<br>";



?>