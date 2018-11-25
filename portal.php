<!DOCTYPE html><html class=''>
<head><meta name="viewport" content="width=device-width, initial-scale=1">
<?php include('base.php'); ?>
<base href="https://diglife.com/webhooks/">

<script src='js/jquery.min.js'></script>
<script src="https://www.gstatic.com/firebasejs/4.1.3/firebase.js"></script>

<link rel="stylesheet" href="css/chat.css?v3">
<link rel="stylesheet" href="css/animate.css">


<script>

  // INITIALIZE FIREBASE ///////////////////////////////////////////////
  var config = {
    apiKey: "<?php echo FIREBASE_API_KEY; ?>",
    authDomain: "<?php echo FIREBASE_DOMAIN; ?>",
    databaseURL: "<?php echo FIREBASE_URL; ?>",
    projectId: "<?php echo FIREBASE_PROJECT_ID; ?>",
    storageBucket: "<?php echo FIREBASE_BUCKET; ?>",
    messagingSenderId: "<?php echo FIREBASE_SENDER_ID; ?>"

  };

  // PARSING PARAMETERS ///////////////////////////////////////////////
  var searchString = window.location.search.substring(1), i, val, params = searchString.split("&");
  //var activity="", command="", team="", channel="", scope2="", username="";

  for (i=0;i<params.length;i++) {
    val = params[i].split("=");
    if (val[0] == "user") {
      var username = val[1];
    } else if (val[0] == "command") {
      var command = val[1];
    } else if (val[0] == "scope") {
      scope2 = val[1];
    } else if (val[0] == "team") {
      var team = val[1];
    } else if (val[0] == "channel") {
      var channel = val[1];
    }
  }


  // ASSIGNING VARS ///////////////////////////////////////////////
  var mainApp = firebase.initializeApp(config);
  var webrtcRef = firebase.database().ref("webrtc");
  var chatRef = firebase.database().ref("chats");
  var userRef = firebase.database().ref("users");
  var channelRef = firebase.database().ref("tokens/"+team+"/"+channel);
  var myRef = firebase.database().ref("chats/"+username);

</script>

</head>


<body>

<style>

body { margin: 0px; }
a { display: block;}
#leftnav { float: left; width:10%; height: 100%; display: none; }
iframe {float: left; width: 90%; height: 500px; border: 0px;}
</style>


<div id="leftnav">
  <a href="https://chat.diglife.com/" target="myiframe">Diglife Chat</a>
  <a href="https://diglife.com/" target="myiframe">Diglife Site</a>
  <a href="https://diglife.com/webhooks/dashbaord.php" target="myiframe">Diglife Dashboard</a>
  <a href="https://chat.holochain.net/" target="myiframe">Holochain</a>
  <a href="https://chat.divvydao.net/" target="myiframe">DivvyDAO</a>
  <a href="https://rchain.divvydao.net/" target="myiframe">RChain</a>
  <a href="https://hub.decstack.com/" target="myiframe">DecStack</a>
  <a href="https://chat.diglife.com/practices/channels/social-ledger-lab" target="myiframe">Social Ledger Lab</a>



</div>
  <iframe name="myiframe2" src="https://diglife.com/webhooks/circle.php"></iframe>
  <iframe name="myiframe" src="https://chat.diglife.com/"></iframe>


</html>