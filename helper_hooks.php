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


  $isChannelComplete = false;
  ################################################################################
  # MATTERMOST API CALL: DOMAIN LIST
  # Needed to traverse through all channels of each domain
  ################################################################################
  $url = CHAT_URL."api/v4/teams";
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
  # TRAVERSE DOMAIN DATA
  # Now we are ready to traverse the resulting json structure to extract names 
  # We store the data in an associative array to merge the result with the NoSQL data 
  ################################################################################
  $json_arr = json_decode($result, true);
  $jsonIterator = new RecursiveIteratorIterator(
    new RecursiveArrayIterator($json_arr),
    RecursiveIteratorIterator::SELF_FIRST);

  foreach ($jsonIterator as $key => $val) {

    if ( ! $isChannelComplete ) {

    if ($key == "id") { 
  
      $domain_id = $val; }

    else if ($key == "name") {

      $domain_name = $val;
   

     ################################################################################
     # TRAVERSE CHANNEL  DATA
     # Now we are ready to traverse the resulting json structure to extract names 
     ################################################################################
     echo "Traversing domain ".$domain_name."..<br>";

     ################################################################################
     # MATTERMOST API CALL: :PUBLIC CHANNEL LIST
     # Then we can get a list of all users in Mattermost using the acquired token
     ################################################################################
     $url = CHAT_URL."api/v4/teams/".$domain_id."/channels";

     $ch = curl_init($url);
     $authorization = "Authorization: Bearer ".$token;
     curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization));
     curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
     $result = curl_exec($ch);

     $channel_arr = json_decode($result, true);
     $channelIterator = new RecursiveIteratorIterator(
     new RecursiveArrayIterator($channel_arr),
     RecursiveIteratorIterator::SELF_FIRST);

     foreach ($channelIterator as $key => $val) {

      if ($key == "id") { 

        $channel_id = $val; }

      else if ($key == "name") {

        $channel_name = $val;

        # GET existing webhook from Firebase 
        $url = FIREBASE_URL."hooks/".$domain_name."/".$channel_name."/hook.json";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $hook = curl_exec($ch);

	# If there is a webhook DO NOT add another one, skip this
        if ( $hook == "null" ) {
	      
		# CREATE new incoming webhook in Mattermost
     		$url = CHAT_URL."api/v4/hooks/incoming";
     		$ch = curl_init($url);
		$arr = array ( "channel_id" => $channel_id, "display_name" => "Social Ledger Notification" );   
  		$data_string = json_encode($arr);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
     		$authorization = "Authorization: Bearer ".$token;
     		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization));
     		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
     		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
     		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
     		$result = curl_exec($ch);
		$result_arr = json_decode($result, true);
		$hook = $result_arr['id'];

  		# UPDATE new webhook in Firebase
        	$url = FIREBASE_URL."hooks/".$domain_name."/".$channel_name.".json";
  		$arr = array ( "hook" => $hook ); 
  		$data_string = json_encode($arr);
  		$ch = curl_init($url);
  		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH"); # WILL OVERWRITE 
  		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
  		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    			'Content-Type: application/json',
    			'Content-Length: ' . strlen($data_string))
  		);
  		$result = curl_exec($ch);
		$isChannelComplete = true; # RUN THIS ONLE PER DOMAIN, THEN STOP DUE TO TIME OUTS

	      	echo "Added new webhook to domain ".$domain_name." and channel ".$channel_name."..<br>";


	 } # IF HOOK

      } # IF CHANNEL NAME

    } # FOREACH CHANNEL

  } # IF DOMAIN NAME

  } # IF CHANNEL COMPLETE

} # FOREACH DOMAIN

if ( ! $isChannelComplete ) { echo "Script successfully completed!<br>"; }
else { echo "Please run the script again to complete all domains!<br>"; }


?>