<!DOCTYPE html>
<head>
  <meta charset="utf-8">

  <?php include('base.php'); ?>

  <title><?php echo MAIN_TITLE; ?> | Holonic Chart</title>

  <!-- D3.js -->
  <script src="js/d3.min.js" charset="utf-8"></script>
  <script src="js/queue.v1.min.js"></script>

  <!-- stats -->
  <script src="js/Stats.js"></script>
  
  <!-- jQuery -->
  <script src="js/jquery.min.js"></script>
  
  <!-- Bootstrap -->
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <script src="js/bootstrap.min.js"></script>

  <!-- Combobox script for the search box -->
  <script src="js/bootstrap-combobox.js"></script>
  <link href="css/bootstrap-combobox.css" rel="stylesheet">

  <!-- Stylesheet -->
  <link rel="stylesheet" href="css/circle.css?v1">
  <link rel="stylesheet" href="css/animate.css">

</head>

<body>

<script>
  // PARSING PARAMETERS ///////////////////////////////////////////////
  var searchString = window.location.search.substring(1), i, val, params = searchString.split("&");
  var main_title = "<?php echo MAIN_TITLE; ?>";
  var chat_URL = "<?php echo CHAT_URL; ?>";
  var activity="", command="", duration="", channel="", message="", username="";

  for (i=0;i<params.length;i++) {
    val = params[i].split("=");
    if (val[0] == "user") {
      user = val[1];
    } else if (val[0] == "username") {
      user_name = val[1];
    } else if (val[0] == "command") {
      command = val[1];
    } else if (val[0] == "activity") {
      activity = decodeURIComponent(val[1]);
    } else if (val[0] == "duration") {
      duration = val[1];
    } else if (val[0] == "channel") {
      channel = val[1];
    }
  }


  if (command == "post") {
    message = "Congrats, "+user.toUpperCase()+" has claimed commitment level "+duration+"!";
  } else if (command == "delete" || command == "delete_archive" ) {
    message = "OK, "+activity.replace("_"," ").toUpperCase()+" has been removed!";
  } else if (command == "sign") {
    message = "Splendid, "+user.toUpperCase()+" has signed an agreement in  "+channel.replace("_"," ").toUpperCase()+"!";
  } else if (command == "archive") {
    message = "Roger that, "+activity.replace("_"," ").toUpperCase()+" has been archived!";
  } else if (command == "view_archive") {
    message = "<?php echo MAIN_TITLE; ?> Circle Archive";    
  } else {
    message = "<?php echo MAIN_TITLE; ?> Circle Structure";
  }

</script>

<style>

</style>


<?php

$command = $_GET['command'];
$team = $_GET['team'];
$channel = $_GET['channel'];
$activity = urldecode($_GET['activity']);
$user = $_GET['user'];
$user_name = $_GET['username'];
$duration = $_GET['duration'];
$signature = $_GET['signature'];


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
      $data = [ 'response_type' => 'ephemeral', 'text' => ':no_entry: No users have been defined for instance. Please contact your administrator to run the avatar script.' ];
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

   $user_api_arr = array();
   foreach ($jsonIterator as $key => $val) {
      $curr_depth = $jsonIterator->getDepth();
      if(is_array($val)) { # Level 1 - USER  ----------------------------

        $username = str_replace('%2E', '.', $key);

      } else {  # Level 2 - USER ATTR ----------------------------

        if ($key == "fullname")  {

	  $user_api_arr[$username]['name'] = $val;

        } else if ($key == "id") {

          $user_api_arr[$username]['id'] = $val;

        }
     }

  } # FOREACH



  ################################################################################
  # PARSE COMMAND
  ################################################################################
switch ($command) {

  case "delete":
  case "delete_archive":

  if ($command == "delete_archive") { $database = "archive"; }
  else { $database = "tokens"; }

  $url = FIREBASE_URL.$database."/".$team."/".$channel."/".$activity.".json";
	$arr = array( );  
	$data_string = json_encode($arr);
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Content-Length: ' . strlen($data_string))
	);
     $result = curl_exec($ch);
 break;


case "archive":

  # get subtree from activity leaf 
  $url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity."/.json";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $data_string = curl_exec($ch);

  # patch subtree to archive 
  $url = FIREBASE_URL."archive/".$team."/".$channel."/".$activity."/.json";
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

  # delete activity
  $url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity.".json";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $result = curl_exec($ch);
  
    break;

case "undo_archive":

  # get subtree from activity leaf 
  $url = FUREBASE_URL."archive/".$team."/".$channel."/".$activity."/.json";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $data_string = curl_exec($ch);

  # patch subtree to archive 
  $url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity."/.json";
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
  
    break;

case "sign":

  # get earlier user contribution 
  $url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity."/".$user."/enacted.json";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $enacted = intval(str_replace("\"","", curl_exec($ch)));

  # get earlier user contribution 
  $url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity."/".$user."/entrusted.json";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $entrusted = intval(str_replace("\"","", curl_exec($ch)));

  # update user contribution 
  $url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity."/.json";
  $arr = array ( $user => array( "signed" => $signature, "enacted" => $enacted, "timestamp" => time() )); 
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
  
    break;


  case "post":

  # get earlier user contribution 
  $url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity."/size.json";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $size = intval(curl_exec($ch));

  if ($size == 0) { ## need to update size before drawing the circles
  $url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity."/.json";
  	$arr = array ( "size" => intval($duration)); 
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
  }


  # update user contribution 
  $url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity."/.json";
  $arr = array ( $user => array( "enacted" => intval($duration), "timestamp" => time() )); 
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
  



    break;

    default:

}

  
 ################################################################################
 # BUILD CSV AND JSON FILES 
 ################################################################################

 ################################################################################
 # DOWNLOAD ENTIRE STRUCTURE 
 ################################################################################
 if ($command == "archive" || $command == "view_archive" || $command == "delete_archive") { $database = "archive.json"; }
 else { $database = "tokens.json"; }

 $url = FIREBASE_URL.$database;
 $data_string = json_encode($arr);
 $ch = curl_init($url);
 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
 curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
 curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data_string))
 );

 $result = curl_exec($ch);
 $json_arr = json_decode($result, true);


 ################################################################################
 # TRAVERSE ENTIRE STRUCTURE
 ################################################################################
 $jsonIterator = new RecursiveIteratorIterator(
    new RecursiveArrayIterator($json_arr),
    RecursiveIteratorIterator::SELF_FIRST);

 $depth[0] = 0;
 $depth[1] = 0;
 $depth[2] = 0;


$fp = fopen('data/ID.csv', 'w');
fputcsv($fp, array('name', 'ID'));

$fp2 = fopen('data/tokens.csv', 'w');
fputcsv($fp2, array('ID', 'age', 'value'));

$curr_team = ""; $curr_channel = ""; $res_arr = array(); $tok_arr = array();
foreach ($jsonIterator as $key => $val) {

    if(is_array($val)) { # do not go to user attr level 3

        # compute the ID ----------------------------
        $prev_depth = $curr_depth;  $tmp_arr = null;
        $curr_depth = $jsonIterator->getDepth();
       
        if ($prev_depth != $curr_depth) {
          $depth[$curr_depth+1] = 0; $depth[$curr_depth+2] = 0; $depth[$curr_depth+3] = 0;
        } 
        $depth[$curr_depth] = $depth[$curr_depth] + 1;
        $id = $depth[0].".".$depth[1].".".$depth[2];

        # domain/team-level node ----------------------------
        if ( $curr_depth == 0 ) { 
          #echo $key."---".$id."<br><br>";
            if ( $domain[$key] ) { $curr_team = $domain[$key]; } else {  $curr_team = $key; }; 
	    $curr_channel = "";
            $tmp_arr = array ( "name" => $curr_team, "parent_id" => null, "ID" => $id);
            fputcsv($fp, array($key, $id) );

        # channel-level node ----------------------------
        } else if ( $curr_depth == 1 ) {  
          #echo $key."---".$id."<br><br>";
            $curr_channel = $key;
            $tmp_arr = array ( "name" => $curr_channel, "parent_id" => $depth[0].".0.0", "ID" => $id);
            fputcsv($fp, array($key, $id) );

        # activity-level node ----------------------------
        } else if ( $curr_depth == 2 ) { 
          #echo $key."---".$id."<br><br>";
            fputcsv($fp, array($key, $id) );

        } else if ( $curr_depth == 3 ) { 
          #echo $key."---".$id."<br><br>";
            $user = str_replace('%2E', '.', $key);
        }


    } else {  # activity-level node ----------------------------

        #echo $key."---".$curr_depth."<br><br>";
        $id = $depth[0].".".$depth[1].".".$depth[2];  $tmp_arr = null;
        $channel_id = $depth[0].".".$depth[1];
        $team_id = $depth[0];
        
        if ($key == "enacted") { 
          fputcsv($fp2, array($id, $user_api_arr[$user]['name'], $val));
          #echo $id.'<br>', $key.'<br>', $val."<br><br>";
          # aggregate sum total of all claims for circle size
          #$tok_arr[$id] = $tok_arr[$id] + $val;
          #$tok_arr[$channel_id] = $tok_arr[$channel_id] + $val;
          #$tok_arr[$team_id] = $tok_arr[$team_id] + $val;

        } else if ($key == "name" && $jsonIterator->getDepth() == 3) {
           $val = preg_replace('/\[(.*?)\]\((.*?)\)/', '\1', $val); # remove markdown links
           $val = preg_replace('/[ ]*:(.*?):[ ]*(.*?)/', '\2', $val); #remove emojis
           $val = str_replace('.', '', $val); #remove '.' since this is not a valid char in noSQL but needed in UI
           $title = $val;
           
        } else if ($key == "size" && $jsonIterator->getDepth() == 3) {
	   # create array for activity level node, if size = 0 it will crash the drawing!
           if ($val > 0) { $tmp_arr = array ( "name" => strtoupper($title), "parent_id" => $depth[0].".".$depth[1].".0", "ID" => $id, "size" => $val); }
        }
    }

    if ($tmp_arr != null) { $res_arr[] = $tmp_arr; }

} # FOREACH
#echo json_encode($res_arr);


################################################################################
# BUILD FLARE.JSON FOR CIRCLE PACKING ALGO 
################################################################################
#$res_arr = array( array("id"=>"1.0.0", "parent_id"=>null, "name"=>"finance-team" ),array("id"=>"1.1.0", "parent_id"=>"1.0.0", "name"=>"accounts-payable"), array("id"=>"1.1.1", "parent_id"=>"1.1.0", "name"=>"pay-the-bills"));
#echo (json_encode($res_arr)."<br><br>");

$itemsByReference = array();
// Build array of item references:
foreach($res_arr as $key => &$item) {
   $itemsByReference[$item['ID']] = &$item;
   // Children array:
   $itemsByReference[$item['ID']]['children'] = array();
   // Empty data class (so that json_encode adds "data: {}" ) 
   #$itemsByReference[$item['id']]['data'] = new StdClass();
}

// Set items as children of the relevant parent item.
foreach($res_arr as $key => &$item)
   if($item['parent_id'] && isset($itemsByReference[$item['parent_id']]))
      $itemsByReference [$item['parent_id']]['children'][] = &$item;

// Remove items that were added to parents elsewhere:
foreach($res_arr as $key => &$item) {
   if($item['parent_id'] && isset($itemsByReference[$item['parent_id']]))
      unset($res_arr[$key]);
}

# remove some unwanted elements from json 
$teams = str_replace(",\"children\":[]","", json_encode($res_arr));
$teams = preg_replace( '#},\"([0-9]+)\":{#', "},{", $teams); 
$teams = preg_replace( '#,\"parent_id\":\"([0-9\.]+)\",#', ",", $teams); 
$teams = preg_replace( '#,\"parent_id\":null,#', ",", $teams); 
#$teams = preg_replace( '#,\"ID\":\"([1-9]\.[1-9]\.0)\",#', ",", $teams); 
#$teams = preg_replace( '#,\"ID\":\"([1-9]\.0\.0)\",#', ",", $teams); 

#remove special 0 level that occurs after 2 top level entities -- {"0":
if (strpos($teams, '{"0":') !== false) {
  $teams = substr($teams, 5, -1); 
  $teams = "{\"name\": \"activities\", \"children\": [".$teams."]}";

} else {
  $teams = "{\"name\": \"activities\", \"children\": ".$teams."}";
}


fclose($fp);
fclose($fp2);

$fp3 = fopen('data/activities.json', 'w');
fwrite($fp3, $teams);
fclose($fp3);


####################################################################################
# GENERATE INCOMING WEBHOOK
####################################################################################

if ($command == "post") {
    $message = ":bell: **". $_GET['user']."** has claimed commitment level **".$_GET['duration']."** tokens for activity **".$_GET['activity']."** in circle **".$_GET['channel']."** (done by ". $_GET['username'].")!";
} else if ($command == "delete" || $command == "delete_archive") {
    $message = ":bell: Activity **".$_GET['activity']."** has been removed from circle **".$_GET['channel']."** (done by ". $_GET['username'].")!";

} else if ($command == "sign") {
    $message = ":bell: Activity **". $_GET['user']."** has signed agreement **".$_GET['activity']."** in circle **".$_GET['channel']."**!";
} 

# FOR A SPECIFIC CHANNEL (SOCIAL LEDGER LAB)
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

# FOR CURRENT CHANNEL
# GET existing name from Firebase 
$url = FIREBASE_URL."tokens/".$team."/".$channel."/".$activity."/name.json";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$activity_name = str_replace("\"","", curl_exec($ch));

# GET existing webhook from Firebase 
$url = FIREBASE_URL."hooks/".$team."/".$channel."/hook.json";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$hook = str_replace("\"","", curl_exec($ch));


if ( false && isset($hook) && $hook != "null" ) { ## THIS IS OFFLINE FOR NOW -- TOO MUCH NOISE

	if ($command == "post") {
    $message = "#### :sparkles: New commitment level ".$duration." claimed by @".$_GET['user']." for activity ".$activity_name." (done by @".$user_name.")";
} else if ($command == "delete" || $command == "delete_archive") {
    $message = "#### :x: Activity ".$activity_name." deleted by @".$_GET['user']." (done by @".$user_name.")";

} else if ($command == "sign") {
    $message = "#### :memo: Activity ".$activity_name." signed by @".$_GET['user']." (done by @". $user_name.")";
} 

	$url = CHAT_URL."hooks/".$hook;
	$data_string = '{"icon_url" : "'.BASE_URL.'webhooks/images/logo_secondary_dark.png", "channel" : "'.$channel.'", "username" : "Social Ledger","text" : "'.$message.'"}';
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

} # IF HOOK







?>

  <h1 id="title"></h1>
  <div id="chart"></div>

  <script src="js/circle.js?v1"></script>
  <script>
      $(document).ready(function() {
     
      if ( message != "" ) {
          $('#chart').fadeTo( "fast" , 0.2);
          $('#title').append(message);
          $('#title').addClass('animated lightSpeedIn');
          $('#title').one('webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend', function() {
          $(this).removeClass('animated lightSpeedIn'); $(this).addClass('delay'); $(this).fadeOut(1000); $('#chart').delay(3000).fadeTo( "slow" , 1); });
      }

            setTimeout(function(){
              //alert(activity.replace(/(\-)/g,' '));
              searchEvent(activity.replace(/(\-)/g,' ').toUpperCase());
            }, 2000);
        });   
  </script>

</body>

</html>
