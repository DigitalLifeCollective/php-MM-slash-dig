<!DOCTYPE HTML>

<html>
<head>
    <?php include('base.php'); ?>

	<title><?php echo MAIN_TITLE; ?> | Team Graph</title>

	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />

	<link rel="stylesheet" href="css/circle.css">
	<link rel="stylesheet" href="css/animate.css">
	<base href="<?php echo BASE_URL; ?>webhooks/">

	<script src="js/jquery.min.js"></script>
	<script src="https://d3js.org/d3.v4.min.js"></script>


</head>

<body>

<script>
  // PARSING PARAMETERS ///////////////////////////////////////////////
  var searchString = window.location.search.substring(1), i, val, params = searchString.split("&");
  //var activity="", command="", message="", username="";

  for (i=0;i<params.length;i++) {
    val = params[i].split("=");
    if (val[0] == "user") {
      var user = val[1];
    } else if (val[0] == "username") {
      var username = val[1];
    } else if (val[0] == "command") {
      var command = val[1];
    } else if (val[0] == "level") {
      level = val[1];
    } else if (val[0] == "role") {
      var role = val[1];
    } else if (val[0] == "channel") {
      var channel = val[1];
    }
  }

  if (command == "level") {
    message = "Congrats, "+user.toUpperCase()+" is now a "+level.replace("_"," ").toUpperCase()+"!";
  } else if (command == "role") {
    message = "Excellent, "+user.toUpperCase()+" is now a "+role.replace("_"," ").toUpperCase()+"!";
  } else if (command == "remove") {
    message = "OK, "+user.toUpperCase()+" has been removed from circle "+channel.replace("_"," ").toUpperCase()+"!";
  } else if (command == "sign") {
    message = "Splendid, "+user.toUpperCase()+" has signed an agreement in  "+channel.replace("_"," ").toUpperCase()+"!";
  } else {
    message = "<?php echo MAIN_TITLE; ?> Network Graph";
  }

</script>


<style>

.link {
    stroke: #ccc;
    stroke-width: 1px;
  }

.node {
  pointer-events: all;
  stroke:  #aaa;
  stroke-width: 1px;
}


img.logo {
	 	background: #00b0a0;
	 	height: 300px;
	 }

.links line {
  stroke: #999;
  stroke-opacity: 0.6;
}

.nodes circle {
  stroke: #fff;
  stroke-width: 1.5px;
}

.node text {
  font: 9px helvetica;
}

</style>



  <h1 id="title"></h1>
  <svg id="graph" width="800" height="600" viewBox="0 0 800 800" perserveAspectRatio="xMinYMid"></svg>


<?php

$command = $_GET['command'];
$team = $_GET['team'];
$channel = $_GET['channel'];
$activity = $_GET['activity'];
$user = $_GET['user'];
$role = $_GET['role'];
$level = $_GET['level'];





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

  case "remove":

  $url = FIREBASE_URL."teams/".$team."/".$channel."/".$user.".json";
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



   case "role":

    # update user contribution 
  $url = FIREBASE_URL."teams/".$team."/".$channel."/".$user."/level.json";
  $arr = array (  "role" => $role ); 
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
  $level = curl_exec($ch);

  # update user contribution 
  $url = FIREBASE_URL."teams/".$team."/".$channel."/".$user."/.json";
  $arr = array (  "role" => str_replace('"', "", $role), "level" => str_replace('"', "", $level) ); 
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



  case "level":

    $url = FIREBASE_URL."teams/".$team."/".$channel."/".$user."/role.json";
  $arr = array (  "role" => $role ); 
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
  $role = curl_exec($ch);


  # update user contribution 
  $url = FIREBASE_URL."teams/".$team."/".$channel."/".$user."/.json";
  $arr = array ( "role" => str_replace('"', "", $role), "level" => str_replace('"', "", $level) ); 
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

} # SWITCH

  
 ################################################################################
 # BUILD JSON FILES 
 ################################################################################

 ################################################################################
 # DOWNLOAD ENTIRE STRUCTURE 
 ################################################################################
 $url = FIREBASE_URL."teams.json";
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
 # TRAVERSE NOSQL TEAM STRUCTURE
 ################################################################################
 $jsonIterator = new RecursiveIteratorIterator(
    new RecursiveArrayIterator($json_arr),
    RecursiveIteratorIterator::SELF_FIRST);

 $depth[0] = 0;
 $depth[1] = 0;
 $depth[2] = 0;


$curr_team = ""; $curr_channel = ""; $res_arr = array(); $user_arr = array();  $chan_arr = array(); $nodes_arr = array(); $link_arr = array();
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

        # team-level node ----------------------------
        if ( $curr_depth == 0 ) { 
          #echo $key."---".$id."<br><br>";
            $curr_team = $key; $curr_channel = "";

        # channel-level node ----------------------------
        } else if ( $curr_depth == 1 ) {  
          #echo $key."---".$id."<br><br>";
            $curr_channel = $key;

        # user-level node ----------------------------
        } else if ( $curr_depth == 2 ) { 
          #echo $key."---".$id."<br><br>";

            $user = str_replace('%2E', '.', $key);
            $user_arr[$user]['id'] = str_replace('%2E', '.', $key);                    # username
            $user_arr[$user]['group'] = $depth[0];            # domain id (sequential number)
            $user_arr[$user]['team'] = $curr_team;            # domain
            $user_arr[$user]['channel'] = $curr_channel;      # circle (channel)
            $user_arr[$user]['name'] = $user_api_arr[$user]['name'];  # Full name from API call

            $chan_arr[$curr_channel][] = str_replace('%2E', '.', $user);               # chan[social-ledger-lab][joachim]

        }


    } else {  #leaf-level node ----------------------------

        if ($key == "role") { 
          #echo $id, $key, $val."<br><br>";

        } else if ( $key == "level" ) {
           #echo $id, $key, $val."<br><br>";

        }
    }

} # FOREACH

//echo var_dump($user_arr);

# build the nodes array from user array via the traversal
# users can be in multiple channels so we only use the last one
foreach ($user_arr as $user) { 
  $nodes_arr [] = array ( "id" => $user['id'], "name" => $user['name'], "group" => $user['group'], "team" => $user['team'], "channel" => $user['channel'] );
}

# build the link array from channel array
# loop through each channel and user added to channel
# we link each user with the next user in the array
# [jim, joe, jeff] ->  jim-joe, jim-jeff, joe-jeff
# value is the weight of the link 
$edge_arr = array ();
foreach ($chan_arr as $chan => $users) {
  $k = 0;
  foreach ($users as $key => $val) {
      $k++;
      for($i = $k; $i < count($users); ++$i) {
        $edge_arr[$val][$users[$i]] += 1;
        #echo $val."-".$users[$i].":".$edge_arr[$val][$users[$i]]."\n";
        $link_arr [] = array ( "source" => $val, "target" => $users[$i], "value" => $edge_arr[$val][$users[$i]] );
      }
  }
}

# prepare the structure to load into D3 network graph
$teams = "{\"nodes\": ".json_encode($nodes_arr).", \"links\" : ".json_encode($link_arr)."}";

# store the file for import
$fp = fopen('data/teams.json', 'w');
fwrite($fp, $teams);
fclose($fp);


# GENERATE INCOMING WEBHOOK FOR A SPECIFIC CHANNEL (SOCIAL LEDGER LAB)
if ($command == "level") {
    $message = ":bell: **". $_GET['user']."** is now a ".$_GET['level']." for domain **".$_GET['team']."** and circle **".$_GET['channel']."** (done by ". $_GET['username'].")!";
  } else if ($command == "role") {
    $message = ":bell: **".$_GET['user']."** is now a ".$_GET['role']." for domain **".$_GET['team']."** and circle **".$_GET['channel']."** (done by ". $_GET['username'].")!";
  } else if ($command == "remove") {
    $message = ":bell: **". $_GET['user']."** has been removed from circle **".$_GET['channel']."** in domain **".$_GET['team']."** by ". $_GET['username']."!";
  }


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

?>  


  <script src="js/graph.js"></script>


  <script>
      $(document).ready(function() {
          if (message != "") { // SEND MESSAGE TO UI
              $('#graph').fadeTo( "fast" , 0.2);
              $('#title').append(message);
              $('#title').addClass('animated lightSpeedIn');
              $('#title').one('webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend', function() {
              $(this).removeClass('animated lightSpeedIn'); $(this).addClass('delay'); $(this).fadeOut(1000); $('#graph').delay(1000).fadeTo( "slow" , 1); });
           }


           setTimeout(function(){
                }, 0);
        });   
  </script>

</body>

</html>