<?php
# ini_set('display_errors', 1);
# ini_set('display_startup_errors', 1);
# error_reporting(E_ALL);


# Welcome to the Social Ledger, a way to record social transactions in the flow
# A simple webhook called /diglife allows users to submit commands to the ledger
# As a response, the webhooks provides a list inside a table with further commands

$base = $_GET["base"];
if ( $base != '' ) {
	include('base-'.$base.'.php'); 
} else {
	include('base.php'); 
}

# Mattermost provides POST variables after the /command has been submitted
# This script parses and shifts the first parameter as the "context" or sub-command
$command = $_POST['command'];
$text = $_POST['text'];
$token = $_POST['token'];
$team = $_POST['team_domain'];
$channel = $_POST['channel_name'];
$user = str_replace('.', '%2E', $_POST['user_name']);  # Firebase does not allow periods
$user_name = $_POST['user_name'];
$params = explode(' ', str_replace('.', '%2E', $text)); # Firebase does not allow periods
$context = strtolower( array_shift($params) ); 

if ( ! empty ($params) && substr($params[0],0,1) == "@" && substr($params[0],1) == "me" ) { $scope = "me"; }
else if ( ! empty ($params) && substr($params[0],0,1) == "@" && substr($params[0],1) == "all" ) { $scope = "all"; }
else if ( ! empty ($params) && substr($params[0],0,1) == "@" && substr($params[0],1) == "channel" ) { $scope = "channel"; }
else if ( ! empty ($params) && substr($params[0],0,1) == "@" ) { $scope = "user"; }
else { $scope = "none"; }


  ################################################################################
  # GET USER DATA
  # We now retrieve the user data from the NoSQL database
  # This is replacing a previous API call which causes timeouts
  # You must run avatar.php once before this works
  ################################################################################
  $url = FIREBASE_URL."users.json";

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

  $result = curl_exec($ch);
  $json_arr = json_decode($result, true);


  # If there are no users to display, put out a warning message
  if ( ! isset($json_arr) ) {
      $data = [ 'response_type' => 'ephemeral', 'text' => ':no_entry: No users have been defined for instance. Please contact your administrator to run the helper scripts.' ];
      header('Content-Type: application/json');
      die (json_encode($data));
  }

  ################################################################################
  # TRAVERSE USER DATA
  # LEVEL 1 = USER   LEVEL 2 = USER ATTR
  ################################################################################
  $jsonIterator = new RecursiveIteratorIterator(
    new RecursiveArrayIterator($json_arr),
    RecursiveIteratorIterator::SELF_FIRST);

   $user_api_arr = array(); $user_id_arr = array();
   foreach ($jsonIterator as $key => $val) {
      $curr_depth = $jsonIterator->getDepth();
      if(is_array($val)) { # Level 1 - USER  ----------------------------

        $username = str_replace('%2E', '.', $key);

      } else {  # Level 2 - USER ATTR ----------------------------

        if ($key == "fullname")  {

	  $user_api_arr[$username]['name'] = $val;

        } else if ($key == "id") {

          $user_api_arr[$username]['id'] = $val;
          $user_id_arr[$val] = $username; # used in /dig search

	}
     }

  } # FOREACH


################################################################################
# PARSE THE INPUT 
# We switch the sub-command (context) parameter and display the result
# There are two types of contexts, one for members and one for activities
################################################################################
switch ($context) {


  case "search":
  case "s":

  # https://api.mattermost.com/#tag/posts%2Fpaths%2F~1teams~1%7Bteam_id%7D~1posts~1search%2Fpost
  # /teams/{team_id}/posts/search

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
  # The dummy account is now bridgebot and is defined in base.php
  ################################################################################
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
  # MATTERMOST API CALL: GET TEAMS=DOMAINS
  # Then we can get a list of all domains in Mattermost using the acquired token
  ################################################################################
  $url = CHAT_URL."api/v4/teams";
  $ch = curl_init($url);
  $authorization = "Authorization: Bearer ".$token;
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $response = curl_exec($ch);
  #$start = curl_getinfo($ch)['header_size'];
  #$body = substr($response,start);
  #$domains = json_decode($body,true);
  $domains = json_decode($response,true);
  foreach ( $domains as $domain ) {
    $teamname[$domain['id']]=$domain['name'];
    $displayname[$domain['id']]=$domain['display_name'];
  }

  # die(var_dump($teamname));


  ################################################################################
  # MATTERMOST API CALL: POST SEARCH
  # Search each domain using the domain ID and the acquired token
  ################################################################################
  $return="";
  # remove the @username parameter from search
  if ( $scope == "user" ) { $userparam = array_shift($params); } 

  foreach($teamname as $id => $name ) {
    $prefix="##### ".$displayname[$id]."\n";
    $url = CHAT_URL."api/v4/teams/$id/posts/search";
    $arr = array ( "terms" =>  implode(" ", $params),"is_or_search" => false );   
    $data_string = json_encode($arr);
    $ch = curl_init($url);
    $authorization = "Authorization: Bearer ".$token;
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Content-Length: ' . strlen($data_string),
      $authorization )
    );
    $response =  html_entity_decode(curl_exec($ch));
    $curl_info = curl_getinfo($ch);
    $header_size = $curl_info['header_size'];
    $result = substr($response, $header_size);

# die ($result );

    $json_arr = json_decode($result, true);

    # TEST IS JSON RESULT IS WELL FORMATTED  
    if (json_last_error() != JSON_ERROR_NONE) {
      die(json_last_error_msg());
    }

    ################################################################################
    # TRAVERSE SEARCH DATA
    # $post also contains $user_id and $channel_id 
    ################################################################################
    $posts = $json_arr['posts'];

   # die(var_dump($posts ));



    $search_arr = array();
    foreach ($posts as $postid => $post) {
      $id = $post['user_id'];

      # add to search result ONLY IF non-user search or match on user search 
      if ($scope != "user" || ($scope == "user" && $user_id_arr[$id] == substr($userparam,1) )) {
 
        # determine string position of search term
        $pos = strpos(strtolower($post['message']), strtolower(implode(" ", $params)));
        # remove links from markdown
        $message = preg_replace('/\]\([^)]+\)/', '', $post['message']);
        # highlight search term
        $message = str_replace(implode(" ", $params), '```'.implode(" ", $params).'```', $post['message']);


        if ( $pos > 100 ) { $message = "..".str_replace(PHP_EOL, '', substr($message,$pos,100)).".."; } 
        else { $message =  str_replace(PHP_EOL, '', substr($message,0,100)).".."; }
        $return = $return.$prefix."- **@".$user_id_arr[$id]."** on ".date("M d, Y",intval($post['update_at']/1000))." [".$message."](".CHAT_URL."$name/pl/".$post['id'].")
";
        $prefix = "";

      } # IF @USER

    } # FOREACH POST

  } # FOREACH DOMAIN

  if ($return == "" ) { $result = "Your search yielded no results. Try another term with no stopwords or in quotes."; } else { $result = $return; }

#die($return);


  break;



  case "chat":
  case "c":

  ################################################################################
  # GET CHAT DATA
  # We now retrieve the chat data from the NoSQL database
  ################################################################################
  $url = FIREBASE_URL."chats.json";

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

  $result = curl_exec($ch);
  $json_arr = json_decode($result, true);

  ################################################################################
  # TRAVERSE CHAT DATA
  # LEVEL 1 = USER   LEVEL 2 = USER ATTR
  ################################################################################
  $jsonIterator = new RecursiveIteratorIterator(
    new RecursiveArrayIterator($json_arr),
    RecursiveIteratorIterator::SELF_FIRST);

   foreach ($jsonIterator as $key => $val) {
      $curr_depth = $jsonIterator->getDepth();
      if(is_array($val)) { # Level 1 - USER  ----------------------------

	$username = str_replace('%2E', '.', $key);

      } else {  # Level 2 - USER ATTR ----------------------------

	if ($key == "status") {

          $user_api_arr[$username]['status'] = $val; 

        } else if ($key == "sessions") {

          $user_api_arr[$username]['sessions'] = $val;
	}
     }

  } # FOREACH



  ################################################################################
  # FIND ENABLED AGENTS
  # Count how many agents are available
  # WARNING - MAKE SURE USER ATTR ARE NOT OVERWRITTEN WHEN RUNNING helper_names.php
  ################################################################################
  $counter = 0; $sessions = 0; 
  foreach ($user_api_arr as $user) {

    if ( $user['status'] && $user['status'] == "enabled" ) { $counter += 1; }
    if ( $user['sessions'] ) { $sessions += $user['sessions']; }

  } #FOREACH

  # OUTPUT FOR AVAILABLE AGENTS
  if ( $counter == 0 ) { $message = ":warning: Sorry, there are no members available for chat right now"; }
  else if  ( $counter == 1 ) { $message = ":warning: 1 member is available for chat right now"; }
  else  { $message = ":warning: ".$counter." members are available for chat right now"; }

  $message = $message."\n :dromedary_camel: Number of peer-to-peer video chats so far: ".$sessions / 2;


  # OUTPUT FOR AVAILABLE ACTIONS
  if ($scope == "user") { $scope = substr($params[0],1); $callercommand = "call"; $callerlabel = "Request Direct Chat"; }
  else if ($scope == "channel") { $scope = substr($params[0],1); $callercommand = "ask"; $callerlabel = "Request Channel Chat";}
  else { $callercommand = "request"; $callerlabel = "Request Chat"; }


  $action = "#### :game_die:  [".$callerlabel."](".BASE_URL."webhooks/chat.php?command=".urlencode($callercommand)."&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user_name)."&scope=".urlencode($scope).") &nbsp;&nbsp;&nbsp; :white_check_mark: [Offer Chat](".BASE_URL."webhooks/chat.php?command=offer&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user_name)."&scope=".urlencode($scope).") ";


    $result = $message."\n".$action;

  break;


  case "m":
  case "team":
  case "teams":
  case "member":
  case "members":

  ################################################################################
  # ADD NEW USERS
  # We patch new users (can be more than one) to the NoSQL database
  # The default settings for role and level are applied
  ################################################################################
  foreach ($params as $user) {

    # If there is no @ in the username, put our a warning message
    if ( substr($user, 0, 1) != "@" && $user != "" ) {
      $data = [ 'response_type' => 'ephemeral', 'text' => ':no_entry: User '.$user.' is missing @. Please use @ in front of a username, e.g. @jack' ];
      header('Content-Type: application/json');
      die (json_encode($data));
    }

    $url = FIREBASE_URL."teams/".$team."/".$channel.".json";
    $arr = [ substr($user, 1) => array ( "role" => "Actor", "level" => "Novice") ]; 
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
    $user = str_replace('%2E', '.', $user);

    # GENERATE INCOMING WEBHOOK FOR A SPECIFIC CHANNEL (SOCIAL LEDGER LAB)
    if ( $user != "" ) {
    $message = ":bell: **".substr($user,1)."** has been added to circle **".strtoupper($channel)."** in domain **".strtoupper($team)."** by user ".$user_name."!";

    $url = CHAT_URL."hooks/".CHAT_HOOK;
    $data_string = '{"icon_url" : "'.BASE_URL.'webhooks/images/logo_secondary_dark.png", "channel" : "social-ledger-bot", "username" : "Social Ledger","text" : "'.$message.'"}';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
       'Content-Type: application/json',
       'Content-Length: ' . strlen($data_string))
    );
    $result = curl_exec($ch);      
    } #IF

  } # FOREACH

  ################################################################################
  # GET CHANNEL DATA
  # We now retrieve the channel data from the NoSQL database
  ################################################################################
  $url = FIREBASE_URL."teams/".$team."/".$channel.".json";

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

  $result = curl_exec($ch);
  $json_arr = json_decode($result, true);


  # If there are no members to display, put our a warning message
  if ( ! isset($json_arr) ) {
      $data = [ 'response_type' => 'ephemeral', 'text' => ':no_entry: No teams have been defined for this circle. Type ```/diglife team <@users>``` to start now.' ];
      header('Content-Type: application/json');
      die (json_encode($data));
  }


  ################################################################################
  # TRAVERSE TEAM  DATA
  # After adding new users, we want to show the results back to the user
  # We are traversing the json result in a recursive way, since it is 3 levels deep
  # LEVEL 0 = CHANNEL  LEVEL 1 = USER   LEVEL 2 = USER ATTR
  ################################################################################
  $jsonIterator = new RecursiveIteratorIterator(
    new RecursiveArrayIterator($json_arr),
    RecursiveIteratorIterator::SELF_FIRST);

   $user_arr = array(); 
   foreach ($jsonIterator as $key => $val) {
      $curr_depth = $jsonIterator->getDepth();
      if(is_array($val)) { # Level 1 - USER  ----------------------------
        $user = str_replace('%2E', '.', $key);

      } else {  # Level 2 - USER ATTR ----------------------------

        if ($key == "role")  {

          $user_arr[$user]['username'] = $user; // NEEDED FOR PARAMS
          $user_arr[$user]['name'] = $user;

          # Add the API information here (name is not required) -------------------------------------------
          if ( $user_api_arr[$user]['name'] != " " ) { $user_arr[$user]['name'] = $user_arr[$user]['name']." (".$user_api_arr[$user]['name'].")"; } 
          else { $user_arr[$user]['name'] = $user_arr[$user]['name']." (No Full Name)"; }         
          $user_arr[$user]['role'] = $val;

        } else if ($key == "level") {
          $user_arr[$user]['level'] = $val;

        }
     }

  } # FOREACH


  ################################################################################
  # OUTPUT TEAM  DATA
  # After building the associate array, create a table in markdown for output 
  ################################################################################
   $table = "|Member|Role (click link to change)|Level (click link to change)|Action|
|:--------|:--------|:--------|:-----|
";

foreach ($user_arr as $user) {
    if ($user['role'] == "Guide") { $emoji[1] = ":white_check_mark:";  $emoji[2] = ":white_medium_square:";  $emoji[3] = ":white_medium_square:"; $emoji[4] = ":white_medium_square:";}
    else if ($user['role'] == "Scribe") { $emoji[1] = ":white_medium_square:";  $emoji[2] = ":white_check_mark:";  $emoji[3] = ":white_medium_square:"; $emoji[4] = ":white_medium_square:";}
    else if ($user['role'] == "Actor") { $emoji[1] = ":white_medium_square:";  $emoji[2] = ":white_medium_square:";  $emoji[3] = ":white_check_mark:"; $emoji[4] = ":white_medium_square:";}
    else if ($user['role'] == "Link") { $emoji[1] = ":white_medium_square:";  $emoji[2] = ":white_medium_square:";  $emoji[3] = ":white_medium_square:"; $emoji[4] = ":white_check_mark:";}

    if ($user['level'] == "Novice") { $emoji2[1] = ":black_circle:";  $emoji2[2] = ":white_circle:";  $emoji2[3] = ":white_circle:"; $emoji2[4] = ":white_circle:";  $emoji2[5] = ":white_circle:"; }
    else if ($user['level'] == "Apprentice") { $emoji2[1] = ":white_circle:";  $emoji2[2] = ":black_circle:";  $emoji2[3] = ":white_circle:"; $emoji2[4] = ":white_circle:";  $emoji2[5] = ":white_circle:"; }
    else if ($user['level'] == "Regular") { $emoji2[1] = ":white_circle:";  $emoji2[2] = ":white_circle:";  $emoji2[3] = ":black_circle:"; $emoji2[4] = ":white_circle:";  $emoji2[5] = ":white_circle:"; }
    else if ($user['level'] == "Expert") { $emoji2[1] = ":white_circle:";  $emoji2[2] = ":white_circle:";  $emoji2[3] = ":white_circle:"; $emoji2[4] = ":black_circle:";  $emoji2[5] = ":white_circle:"; }
    else if ($user['level'] == "Advisor") { $emoji2[1] = ":white_circle:";  $emoji2[2] = ":white_circle:";  $emoji2[3] = ":white_circle:"; $emoji2[4] = ":white_circle:";  $emoji2[5] = ":black_circle:"; }

    
    $table = $table."|".$user['name']."| ".$emoji[1]." [Guide](".BASE_URL."webhooks/graph.php?command=role&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user['username'])."&username=".urlencode($user_name)."&role=Guide) &nbsp;&nbsp;&nbsp;".$emoji[2]." [Scribe](".BASE_URL."webhooks/graph.php?command=role&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user['username'])."&username=".urlencode($user_name)."&role=Scribe) &nbsp;&nbsp;&nbsp;".$emoji[3]." [Actor](".BASE_URL."webhooks/graph.php?command=role&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user['username'])."&username=".urlencode($user_name)."&role=Actor) &nbsp;&nbsp;&nbsp;".$emoji[4]." [Link](".BASE_URL."webhooks/graph.php?command=role&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user['username'])."&username=".urlencode($user_name)."&role=Link) &nbsp;&nbsp;&nbsp; | ".$emoji2[1]." [Novice](".BASE_URL."webhooks/graph.php?command=level&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user['username'])."&username=".urlencode($user_name)."&level=Novice) &nbsp;&nbsp;&nbsp;".$emoji2[2]." [Apprentice](".BASE_URL."webhooks/graph.php?command=level&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user['username'])."&username=".urlencode($user_name)."&level=Apprentice) &nbsp;&nbsp;&nbsp;".$emoji2[3]." [Regular](".BASE_URL."webhooks/graph.php?command=level&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user['username'])."&username=".urlencode($user_name)."&level=Regular)  &nbsp;&nbsp;&nbsp;".$emoji2[4]." [Expert](".BASE_URL."webhooks/graph.php?command=level&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user['username'])."&username=".urlencode($user_name)."&level=Expert) &nbsp;&nbsp;&nbsp;".$emoji2[5]." [Advisor](".BASE_URL."webhooks/graph.php?command=level&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user['username'])."&username=".urlencode($user_name)."&level=Advisor)|"."[:x:](".BASE_URL."webhooks/graph.php?command=remove&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user['username'])."&username=".urlencode($user_name).") [:eyes:](".BASE_URL."webhooks/graph.php?command=view&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".str_replace('.','\.',urlencode($user['username'])).")|
"; 
}

$result = $table;

  break;



  case "a":
  case "action":
  case "actions": 
  case "doc":
  case "docs":
  case "link":
  case "links":
  case "goal":
  case "goals":
  case "norm":
  case "norms":
  case "job":
  case "jobs":
  case "contract":
  case "contracts":
  case "activity":
  case "activities":

  ################################################################################
  # DETERMINE ACTIVITY TYPE - GOALS, DOCS, JOBS, OR ACTIONS  
  # We want a singular value here to save on condition checking
  ################################################################################
  if ($context == "goal" || $context == "goals" ) {
    $type = "goal";
  } else if ($context == "doc" || $context == "docs" ) {
    $type = "doc";
  } else if ($context == "link" || $context == "links" ) {
    $type = "link";
  } else if ($context == "action" || $context == "actions" ) {
    $type = "action";
  } else if ($context == "activity" || $context == "activities" || $context == "archive" || $context == "archives" || $context == "a") {
    $type = "activity";
  } else if ($context == "norm" || $context == "norms" ) {
    $type = "norm";
  } else if ($context == "job" || $context == "jobs" ) {
    $type = "job";
  } else if ($context == "contract" || $context == "contracts" ) {
    $type = "contract";
  }



  ################################################################################
  # BRANCH: Are we ADDING activities?
  ################################################################################
  if ( ! empty ($params) && $scope == "none" && $context != "a" && $context != "activities" )  {


    # prepare activity_id for noSQL data key used in data structure 
    $activity = implode(' ', $params);
    $activity_id = preg_replace('/\[(.*?)\]\((.*?)\)/', '\1', $activity);
    $activity_id = preg_replace('/[ ]*:(.*?):[ ]*(.*?)/', '\2', $activity_id);
    $activity_id = strtolower( str_replace(' ','-', $activity_id ) );
    $activity_id = str_replace('.','', $activity_id );

    # test for alphanum characters
    $valid_chars = array('.','&','!',':', '-', '_', ' '); ##### FULL STOP AND HASHTAG NO GOOD

    if (!ctype_alnum(str_replace($valid_chars, '', $activity_id))) { 
      # execute response to Mattermost
      $data = [ 'response_type' => 'ephemeral', 'text' => ':no_entry: Only alpha-numeric characters are allowed, please try again!
##### '.$command.' '. str_replace('[','\[',$text). " (copy/paste or Control-ArrowUP to retry)" ];
      header('Content-Type: application/json');
      die (json_encode($data));
    }

    if ($context == "activity" || $context == "activities") { 
      # execute response to Mattermost
      $data = [ 'response_type' => 'ephemeral', 'text' => ':no_entry: Please select from one of the activity types goal, action, doc, norm or job)' ];
      header('Content-Type: application/json');
      die (json_encode($data));
    }

    ################################################################################
    # PATCH ACTIVITY
    # Add activity to current and archive NoSQL database 
    # The defaul settings for size and tokens are applied
    ################################################################################
    $url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity_id.".json";
    $arr = [ "name" => $activity, "type" => $type, "size" => 1,  $user => array ( "enacted" => 0, "entrusted" => 0) ]; 
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

    # archive activity 
    # $url = FIREBASE_URL."archive/".$team."/".$channel."/".$activity_id.".json";
    # $result = curl_exec($ch);

    # GENERATE INCOMING WEBHOOK FOR A SPECIFIC CHANNEL (SOCIAL LEDGER LAB)
    $message = ":bell: **".$user."** has created ".$type." '**".$activity."**' in circle **".strtoupper($channel)."** and domain **".strtoupper($team)."**!";

    # $message = $message." Are you part of this activity? If so, select your commitment: ".$commitment;


    $url = CHAT_URL."hooks/".CHAT_HOOK;
    $data_string = '{"icon_url" : "'.BASE_URL.'webhooks/images/logo_secondary_dark.png", "channel" : "social-ledger-bot", "username" : "Social Ledger","text" : "'.$message.'"}';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
       'Content-Type: application/json',
       'Content-Length: ' . strlen($data_string))
    );
    $result = curl_exec($ch);

  # THIS IS THE SUDO FUNCTION, SO CHANGE USER TO SUDO
  } else if ( $scope == "user" )  {
    $user = substr($params[0], 1);
  }


  ################################################################################
  # BRANCH: Are we LISTING ALL activities using @me or @all?
  ################################################################################
  if ($context == "archive" || $context == "archives") { $database = "archive"; }
  else { $database = "tokens"; }

  if ( $scope == "me" || $scope == "all" ) {

    $users = $params;

    ################################################################################
    # GET ALL DATA
    ################################################################################
    $url = FIREBASE_URL.$database.".json";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $result = curl_exec($ch);

  } else {

    ################################################################################
    # GET CHANNEL DATA ONLY
    ################################################################################
    $url = FIREBASE_URL.$database."/".$team."/".$channel.".json";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $result = curl_exec($ch);

  } //IF

  $json_arr = json_decode($result, true);

  # If there are no activities to display, put our a warning message
  if ( ! isset($json_arr) ) {
      $data = [ 'response_type' => 'ephemeral', 'text' => ':no_entry: No activities have been defined for this circle. Type ```/diglife goal|action|doc|goal|norm|contract <activity>``` to start now.' ];
      header('Content-Type: application/json');
      die (json_encode($data));
  }

  ################################################################################
  # TRAVERSE CHANNEL DATA AND RETURN ACTIVITIES
  ################################################################################

  $jsonIterator = new RecursiveIteratorIterator(
    new RecursiveArrayIterator($json_arr),
    RecursiveIteratorIterator::SELF_FIRST);

   $i = 0; $username = ""; $name_arr = array(); $enact_arr = array(); $entrust_arr = array();  $act_arr = array();  $type_arr = array();  $chan_arr = array();  $user_arr = array();  $team_arr = array(); $commit_arr = array();
   # LOOP THROUGH DATA STRUCTURE - BEGINNING WITH ACTIVITY OR TEAM LVL
   foreach ($jsonIterator as $key => $val) {
      $curr_depth = $jsonIterator->getDepth();
      if(is_array($val)) { # has children

        # team   ----------------------------
        if ( $curr_depth == 0 && ( $scope == "me" || $scope == "all" ) )  { $team = $key;  } 

        # channel   ----------------------------
        if ( $curr_depth == 1 && ( $scope == "me" || $scope == "all" ) )  { $channel = $key;  } 

        # activity --- three cases 
        if ( $curr_depth == 0 || ( $curr_depth == 2 && ! empty ($params) ) )  {  $team_arr[$i] = $team; $chan_arr[$i] = $channel; $act_arr[$i] = $key; $commit_arr[$i] = 0;  }

        # user ---------------------------------------
        # CASE /DIG ACTIONS @ME or @ALL 
        if      ( $curr_depth == 3 && $scope == "all" && $key != $user )  { $username = $key; $user_arr[$i] = $key; } 
        else if ( $curr_depth == 3 && $scope == "all" && $key == $user )  { $username = $key; $user_arr[$i] = $key; $commit_arr[$i] = $val['enacted']; }
        else if ( $curr_depth == 3 && $scope == "me" && $key != $user ) { $username = $key; $user_arr[$i] = $key; }
        else if ( $curr_depth == 3 && $scope == "me" && $key == $user ) { $username = $user; $user_arr[$i] = $user;  $commit_arr[$i] = $val['enacted']; }

        # CASE /DIG ACTIONS USER AND SUDO (ASSIGNED ABOVE)
        else if ( $curr_depth == 3 && $key == $user ) { $username = $user; $user_arr[$i] = $user;  $commit_arr[$i] = $val['enacted']; }

	# CASE /DIG ACTIONS NO USER (ASSIGN TO YOURSELF)
        else  if ( ! isset($user_arr[$i]) ) { $username = $user; $user_arr[$i] = $user;  $commit_arr[$i] = 0; }



        # CASE /DIG ACTION XYZ or /DIG ACTIONS
        if ( $curr_depth == 1 &&  ! empty ($params) && $scope == "none" ) {  $username = $user; $user_arr[$i] = $user; } 

        # CASE /DIG ACTIONS @USER
        if ( $curr_depth == 1 && $scope == "user" ) {  $username = substr($params[0],1); $user_arr[$i] = substr($params[0],1); } 

        # CASE /DIG ACTIONS and user match
        if ( $curr_depth == 1 && $key == $user ) {  $commit_arr[$i] = $val['enacted']; } 

    } else {  # activity-level node

      if ( empty ($params) || ( $scope == "me" || $scope == "all" || $scope == "none" || substr($params[0],1) == $username ) ) {  // CHECK THIS

        if ($key == "enacted")  {
          $enact_arr[$i] = $enact_arr[$i] + $val;

        } else if ($key == "entrusted") {
          $entrust_arr[$i] = $entrust_arr[$i] + $val;

        } else if ($key == "name") {
          $name_arr[$i] = $val; 
         
        } else if ($key == "type") {  // ASSUME THAT THIS COMES AFTER NAME AND IS THE LAST ELEMENT
           $type_arr[$i] = $val;

           if ($val == "goal") {
            $name_arr[$i] = ":dart: ".$name_arr[$i]; 
           } else if ($val == "doc") {
            $name_arr[$i] = ":notebook: ".$name_arr[$i];            
           } else if ($val == "action") {
            $name_arr[$i] = ":clapper: ".$name_arr[$i];
           } else if ($val == "norm") {
            $name_arr[$i] = ":heart: ".$name_arr[$i];
           } else if ($val == "job") {
            $name_arr[$i] = ":briefcase: ".$name_arr[$i];
           } else if ($val == "contract") {
            $name_arr[$i] = ":page_with_curl: ".$name_arr[$i];
           } else if ($val == "link") {
            $name_arr[$i] = ":link: ".$name_arr[$i];
           }

           $i = $i + 1; 
           $username = ""; 

        } #IF KEY 


      } # IF USERNAME
    } #IF ARRAY

} # FOREACH



 ################################################################################
 # OUTPUT ACTIVITY  DATA
 # After new activity has been added 
 ################################################################################


  if (  ! empty ($params) && $scope == "none" && $type == "action" )  {


    $commitments = "[:no_mouth:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user_name)."&username=".urlencode($user_name)."&activity=".urlencode($activity_id)."&duration=20)"." [:open_mouth:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user_name)."&username=".urlencode($user_name)."&activity=".urlencode($activity_id)."&duration=40)"." [:neutral_face:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user_name)."&username=".urlencode($user_name)."&activity=".urlencode($activity_id)."&duration=60)"." [:smile:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user_name)."&username=".urlencode($user_name)."&activity=".urlencode($activity_id)."&duration=120)"." [:sweat_smile:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team)."&channel=".urlencode($channel)."&user=".urlencode($user_name)."&username=".urlencode($user_name)."&activity=".urlencode($activity_id)."&duration=240)";


      $data = [ 'response_type' => 'in_channel', 'text' => '#### :clapper: New '.$type.' "'.$activity.'" has been created by @'.$user_name ];
      header('Content-Type: application/json');
      die (json_encode($data));

  } else if (  ! empty ($params) && $scope == "none" && $type == "goal" )  {

      $data = [ 'response_type' => 'in_channel', 'text' => '#### :dart: New '.$type.' "'.$activity.'" has been created by @'.$user_name ];
      header('Content-Type: application/json');
      die (json_encode($data));
}


 ################################################################################
 # OUTPUT ACTIVITY  DATA
 # After list of activities has been requested 
 ################################################################################
if ($context != "archive" && $context != "archives" && $type != "activity") {
    $table = "|Circle|".ucfirst($type)."|Engaged? Checked?|Action|
|:--|:--|:--|:--|
";
} else if ($context != "archive" && $context != "archives" && $type == "activity") {
    $table = "|Circle|Social Object|Engaged? Checked?|Action|
|:--|:--|:--|:--|
";
} else {
    $table = "##### :package: YOU ARE LOOKING AT THE ARCHIVE :package:
|Circle|ARCHIVED Activity|Action|
|:--|:--|:--|
";
}


for ($i = 0; $i < count($name_arr); $i++) {

  # this is a @me filter for activities my name is on, otherwise we will show the last person's name
  if ( ( $scope == "me" && $commit_arr[$i] > 0 && ($type == "activity" || $type_arr[$i] == $type )) 
    || ( $scope != "me" && ($type == "activity" || $type_arr[$i] == $type )) ) {

    # Removed the enacted and entrusted token columns (keeping it simple)
    # To add this back, add after the name column: ".$enact_arr[$i]."|".$entrust_arr[$i]."

    # convert level of commitment to emoji
    if ( isset($commit_arr[$i]) &&  $commit_arr[$i] == 0 && $type_arr[$i] == "action") { $commit_emoji = ":sleeping:"; }
    else if ( isset($commit_arr[$i]) &&  $commit_arr[$i] == 0 && $type_arr[$i] != "action") { $commit_emoji = ":ballot_box_with_check:"; }
    else if ( isset($commit_arr[$i]) &&  $commit_arr[$i] <= 20 && $type_arr[$i] == "action") { $commit_emoji = ":no_mouth:"; }
    else if ( isset($commit_arr[$i]) &&  $commit_arr[$i] >= 20 && $type_arr[$i] != "action") { $commit_emoji = ":white_check_mark:"; }
    else if ( isset($commit_arr[$i]) &&  $commit_arr[$i] <= 40 ) { $commit_emoji = ":open_mouth:"; }
    else if ( isset($commit_arr[$i]) &&  $commit_arr[$i] <= 60 ) { $commit_emoji = ":slightly_smiling_face:"; }
    else if ( isset($commit_arr[$i]) &&  $commit_arr[$i] <= 120 ) { $commit_emoji = ":smile:"; }
    else if ( isset($commit_arr[$i]) &&  $commit_arr[$i] > 120 ) { $commit_emoji = ":sweat_smile:"; }
    else  { $commit_emoji = ":sleeping:"; }

    ################################################################################
    # ADD ACTIONS
    # Depending the context and activity, a different set of actions are present 
    ################################################################################
    $actions = "";

    # activity type 'contract' has a special action to sign it with the user ID
    if ($type == "contract") {
      $actions = $actions." :memo: [sign](".BASE_URL."webhooks/circle.php?command=sign&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i])."&signature=".urlencode($user_api_arr[$user_name]['id']).")";
    }

    # archive does not show the archive button 
    if ($context != "archive" && $context != "archives") {

      $actions = $actions." [:eyes:](".BASE_URL."webhooks/circle.php?command=view&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i]).")";
      # $actions = $actions." [:package:](".BASE_URL."webhooks/circle.php?command=archive&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i]).")";
      # $actions = $actions." [:x:](".BASE_URL."webhooks/circle.php?command=delete&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i]).")";
      $actions = $actions." [:art:](".BASE_URL."webhooks/dashboard.php?user=".urlencode($user)."&username=".urlencode($user_name)."&team=".urlencode($team)."&channel=".urlencode($channel)."&database=tokens&scope=".urlencode($scope)."&search=)";

	# Show commitments -- ONLY for activty type == action

    	if ($type_arr[$i] == "action") { # SHOW FACES
      	$commitments = $commit_emoji." &vellip; [:no_mouth:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i])."&duration=20)"." [:open_mouth:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i])."&duration=40)"." [:slightly_smiling_face:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i])."&duration=60)"." [:smile:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i])."&duration=120)"." [:sweat_smile:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i])."&duration=240) |";

	} else { # SHOW CHECKMARKS

	if ($commit_emoji == ":white_check_mark:" ) {

		$commitments = "[:white_check_mark:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i])."&duration=0)|"; }
	else {
		$commitments = "[:ballot_box_with_check:](".BASE_URL."webhooks/circle.php?command=post&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i])."&duration=20)|"; }

	}

    } else {
      $actions = $actions." [:eyes:](".BASE_URL."webhooks/circle.php?command=view_archive&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i]).")";
      $actions = $actions." [:x:](".BASE_URL."webhooks/circle.php?command=delete_archive&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i]).")";
      $actions = $actions." [:arrows_clockwise:](".BASE_URL."webhooks/circle.php?command=undo_archive&team=".urlencode($team_arr[$i])."&channel=".urlencode($chan_arr[$i])."&user=".urlencode($user_arr[$i])."&username=".urlencode($user_name)."&activity=".urlencode($act_arr[$i]).")";
      $actions = $actions." [:art:](".BASE_URL."webhooks/dashboard.php?user=".urlencode($user)."&username=".urlencode($user_name)."&team=".urlencode($team)."&channel=".urlencode($channel)."&database=archive&scope=".urlencode($scope)."&search=)";
      $commitments = "";
    }

    ################################################################################
    # OUTPUT ACTIVITY DATA
    # Create table row for main output 
    ################################################################################
    $table = $table."|[".ucwords(str_replace("-", " ", $chan_arr[$i]))."](".CHAT_URL.$team_arr[$i]."/channels/".$chan_arr[$i].")|".$name_arr[$i]."| ".$commitments.$actions." |
    ";

  } // IF


} //FOR ALL ACTIVITIES

$result = " Select engagement level: :sleeping:  none :no_mouth: a bit  :open_mouth: more :smile: strong :sweat_smile: intense | :ballot_box_with_check: not selected :white_check_mark: selected\n\n".$table;



    break;





    case "circle":
    case "circles":

 $table = ":warning: This command is no longer supported.";

$result = $table;

    break;



  case "start":
  case "novice":

  $result = "### :tada: Welcome to the Collective @".$user." :grinning:  !
---
_Congratulations, you've made it! You've decided to join the Digital Life Collective, or as we call it, the Collective. There is lots to explore around here and we are constantly trying to improve our onboarding process. To get you up to speed on what is happening next and what you can do for the Collective, follow the links below.._

##### :notebook: [Read the Welcome Guide](https://diglife.com/member-orientation/)
##### :art: [Tech We Use](".BASE_URL."webhooks/dashboard.php?user=".str_replace('.', '%2E', $_POST['user_name'])."&team=technology-crew&channel=tech-we-use&database=tokens&scope=".urlencode($scope)."&search=)
##### :clapper: [Introduce yourself](https://chat.diglife.com/the-collective/channels/collective-open-chat)
##### :notebook: [Understand Mattermost](https://diglife.com/team-chat-guide/)
##### :clapper: [Tell us how to improve this process](https://chat.diglife.com/the-collective/channels/collective-open-chat)

     For key links and tools type: /dig | For a list of commands type: /dig help | For a list of activities in a channel type: /dig a
---";

    break;




  case "dashboard":
  case "archive":
  case "archives":
  case "y":
  case "d":

  if ($context == "archive" || $context == "archives") { $database = "archive"; } else { $database = "tokens"; }
  $result = "#### :art: [Open Dashboard](".BASE_URL."webhooks/dashboard.php?user=".str_replace('.', '%2E', $_POST['user_name'])."&team=".urlencode($team)."&channel=".urlencode($channel)."&database=".urlencode($database)."&scope=".urlencode($scope)."&search=)";
  $url = BASE_URL."webhooks/dashboard.php?user=".urlencode($user)."&team=".urlencode($team)."&channel=".urlencode($channel)."&database=".urlencode($database)."&scope=".urlencode($scope)."&search=";

    break;





    case "help":
    case "?":

 $table = "
|:--------|:--------|:--------------|:------------|
";

    $table = "|**RETRIEVE**|
    |:--|:--|:--|:--|
    |Command|Optional|Description|Example|
    |```/dig``` ```members```/```m```|&nbsp;|:busts_in_silhouette: List all team members for a given circle|```/dig m```|
    |```/dig``` ```goals```|```@username```|:dart: List all goals for a given circle or as sudo @username|```/dig goals```|
    |```/dig``` ```docs```|```@username```|:notebook: List all documents for a given circle or as sudo @username|```/dig docs```|
    |```/dig``` ```links```|```@username```|:link: List all links for a given circle or as sudo @username|```/dig docs```|
    |```/dig``` ```actions```|```@username```|:clapper: List all actions for a given circle or as sudo @username|```/dig actions```|
    |```/dig``` ```norms```|```@username```|:heart: List all norms for a given circle or as sudo  @username|```/dig norms```|
    |```/dig``` ```jobs```|```@username```|:briefcase: List all jobs for a given circle or as sudo @username|```/dig norms```|
    |```/dig``` ```contracts```|```@username```|:page_with_curl:List all contracts for a given circle or as sudo @username|```/dig norms```|
    |```/dig``` ```archives```|```@username```|:package: List all archived activities for a given circle or as sudo @username|```/dig archives```|
    |```/dig``` ```archives```|```@me``` or ```@all```|:package: List all archived activities across ALL circles for me or all|```/dig archives```|
    |```/dig``` ```activities``` or ```a```|```@username```|List all activities for a given circle or as sudo @username|```/dig a```|
    |```/dig``` ```activities``` or ```a```|```@me``` or ```@all```|List all activities across ALL circles for me or all|```/dig a```|
    |```/dig``` ```dashboard``` or ```d```| |Open dashboard link for this circle.|```/dig d```|
    |```/dig``` ```search``` or ```s```|```@username```|Search for term and filter by @username.|```/dig s @joachim test```|
    |**ADD**|
    |Command|Parameters|Description|Example|
    |```/dig``` ```member```|```@usernames```|:bust_in_silhouette: Add one or more team members to a given circle (use @-mention)|```/dig team @jim @john```|
    |```/dig``` ```goal```|```title```|:dart: Add a goal to a circle (use markdown for link)|```/dig goal Find 150 members```|
    |```/dig``` ```doc```|```title```|:notebook: Add a document to a circle (use markdown for link)|```/dig doc Team Glossary```|
    |```/dig``` ```link```|```title```|:link: Add a link to a circle (use markdown for link)|```/dig link [Ghost Dev Blog](link)```|
    |```/dig``` ```action```|```title```|:clapper: Add an action to a circle (use markdown for link)|```/dig action This is my action```|
    |```/dig``` ```norm```|```title```|:heart: Add a norm to a given (use markdown for link)|```/dig norm Give everyone a voice```|
    |```/dig``` ```job```|```title```|:briefcase: Add a job to a circle (use markdown for link)|```/dig job PHP Programmer```|
    |```/dig``` ```contract```|```title```|:page_with_curl: Add a contract to a circle (use markdown for link)|```/dig contract Our Gardening Agreement```|
    |**ACTION**|&nbsp;|&nbsp;|
    |&nbsp;|&nbsp;|:eyes: View all items |
    |&nbsp;|&nbsp;|:x: Delete selected item |
    |&nbsp;|&nbsp;|:package: Archive selected item |
    |&nbsp;|&nbsp;|:memo: Sign selected item |
    |&nbsp;|&nbsp;|:arrows_clockwise: Copy archived item |
    |```/dig dashboard```|```@me``` ```@all```|:art: Open dashboard, also ```/dig d``` |
    |```/dig chat```|```@user``` ```@channel```|:game_die: Peer-to-Peer Chat  |
";

$result = $table;

    break;


    default:
  # List all available commands 
  $url = FIREBASE_DEFAULT_PATH.".json";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $result = curl_exec($ch);
  $json_arr = json_decode($result, true);

   $table = "|KEY LINKS AND TOOLS|
|:--------|
";

   foreach ($json_arr as $key => $value) {
    foreach ($value as $key2 => $value2) {
      if ($key2 == "name") { $name2 = $value2; }

      else if ($key2 == "type") {

           if ($value2 == "goal") {
            $table = $table."| :dart: ".$name2."|
"; }
           else if ($value2 == "doc") {
            $table = $table."| :notebook: ".$name2."|
"; }          
           else if ($value2 == "action") {
            $table = $table."| :clapper: ".$name2."|
"; }
           else if ($value2 == "norm") {
            $table = $table."| :heart: ".$name2."|
"; }
           else if ($value2 == "job") {
            $table = $table."| :briefcase: ".$name2."|
"; }
           else if ($value2 == "contract") {
            $table = $table."| :page_with_curl: ".$name2."|
"; }
           else if ($value2 == "link") {
            $table = $table."| :link: ".$name2."|
"; }

      } # IF TYPE 
    } # FOREACH
   } # FOREACH
$result = $table." For a list of commands type: /dig help | For a list of activities in a channel type: /dig a";

} # CASE

################################################################################
# EXECUTE REPONSE
# Use ephemeral to suppress output for other users 
################################################################################
$data = [ 'response_type' => 'ephemeral', 'text' => $result ];
header('Content-Type: application/json');
echo json_encode($data);
