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


<body onload="showMyFace()">
<center>
<h1>DigLife Peer-to-Peer Chat</h1>
<h2 id="subtitle"></h2>

<div id="ourObjects">
</div>

<div id="ourVideo">
 <div id="yourVideoMask">
   <video id="yourVideo" autoplay muted></video>
 </div>

 <div id="friendsVideoMask">
   <p id="indicator"></p>
   <p id="name"></p>
   <video id="friendsVideo" autoplay></video>
 </div>

 <div id="friendsInfo">
   <p class="info"></p>
 </div>
</div>


<div style="clear: both;"></div>

<div class="buttons">
 <button id="call" class="highlight" disabled="true" onclick="acceptRequest()" type="button">Accept Call</button>
</div>

<div class="buttons">
 <button id="jump" onclick="jumpChannel()" disabled="true" type="button">CHANGE CHANNEL</button>
</div>

<div class="buttons">
 <button id="close" onclick="closeSession()" type="button">CLOSE CHAT</button>
</div>


</body>


<script>

$( "#subtitle" ).html( channel.toUpperCase().replace(/-/g," ") );



// PARSE THE INPUT 
switch (command) {


  case "request":
  
	myRef.update({ status: "requested", domain: team, channel: channel });
  	$('#call').hide(); $('#jump').hide();

  break;
 

  case "call":
  
	myRef.update({ status: "called", receiver: scope2, domain: team, channel: channel });
  	$('#call').hide(); $('#jump').hide();

  break;


  case "ask":
  
	myRef.update({ status: "asked", receiver: team+"-"+channel, domain: team, channel: channel });
  	$('#call').hide(); $('#jump').hide();

  break;


  case "offer":

	myRef.update({ status: "enabled", domain: team, channel: channel });
  	$('#call').show(); $('#jump').show();

  break;

}

<?php

  $command = $_GET['command'];
  $team = $_GET['team'];
  $channel = $_GET['channel'];
  $user = str_replace('.', '%2E', $_GET['user']);

  # GET existing webhook from Firebase 
  $url = FIREBASE_URL."hooks/".$team."/".$channel."/hook.json";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  $hook = str_replace("\"","", curl_exec($ch));




if ( isset($hook) && $hook != "null" ) { 

  if ($command == "request") { $message = "#### :game_die: @".str_replace('%2E','.',$user)." is requesting a call with a Collective member.\n Type ```/dig chat``` to respond. [What's this?](https://diglife.com/peer-to-peer-chat/)"; } 
  else if ($command == "ask") { $message = "#### :game_die: @".str_replace('%2E','.',$user)." is asking a Collective member in this channel.\n Type ```/dig chat``` to respond. [What's this?](https://diglife.com/peer-to-peer-chat/)"; }

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



// LISTEN TO REQUESTS

  // Show Social Objects related to the channel IF channel chat
  channelRef.on("child_added", function(snapshot) { // TOKEN LISTENER
    var data = snapshot.val();
    channel = snapshot.key;

    if ( data && data.name && data.type) {

        var title = data.name.replace(/\[(.*?)\]\((.*?)\)/, '$1').replace(/[ ]*:(.*?):[ ]*(.*?)/, '$2');
        var link = (data.name.includes("[") ? data.name.replace(/.*\[(.*?)\]\((.*?)\)/, '$2') : "");
	var linkContainer = $('<a class=\"element-item '+team+' '+data.type+'\" title=\"'+title+'\" target=\"_blank\" href=\"'+link+'\"></a>');
    	var elementContainer = $('<div ></div>');
    	linkContainer.append(elementContainer);
    	elementContainer.append('<h3 class=\"name\">'+title.substring(0,30)+(title.length>30?'&hellip;':'')+'</h3>');
        $('#ourObjects').append(linkContainer);

    }
  });


 
var caller = "";
chatRef.on("child_added", function(snapshot) { // CHAT LISTENER
    var data = snapshot.val();
    user = snapshot.key;
	console.log(user+" added.");

        // CASE: I AM REQUESTING A CALL
        if ( (data.status == "requested" ) && user == username  ) {
          $( "#indicator" ).html( "Request submitted.." );


        // CASE: SOMEONE IS REQUESTING A CALL
        } else if ( data.status == "requested"  && user != username  ) {

          console.log(user+" is now requesting call.");
	  caller = user;
          $( "#indicator" ).html( "Call requested.." );
	  $( "#audio" ).empty(); // remove all previous audio tags
          var audio = new Audio();
	  audio.src = "sounds/bell.mp3";
	  audio.loop = false;
          audio.volume = "1";
          audio.autoplay = true; audio.controls = false;
          $('#audio').append(audio); // MUST HAVE BR FOR FF
          audio.play(); // MUST BE AT THE END FOR FF

	  $('#call').attr("disabled", false);
	  $('#jump').attr("disabled", false);


        // CASE: SOMEONE IS REQUESTING A CHANNEL CALL
        } else if ( data.status == "asked"  && data.receiver == team+"-"+channel && user != username  ) {

          console.log(user+" is now requesting channel call.");
	  caller = user;
          $( "#indicator" ).html( "Channel call requested.." );
	  $( "#audio" ).empty(); // remove all previous audio tags
          var audio = new Audio();
	  audio.src = "sounds/bell.mp3";
	  audio.loop = false;
          audio.volume = "1";
          audio.autoplay = true; audio.controls = false;
          $('#audio').append(audio); // MUST HAVE BR FOR FF
          audio.play(); // MUST BE AT THE END FOR FF

	  $('#call').attr("disabled", false);
	  $('#jump').attr("disabled", false);


       
        // CASE: SOMEONE IS REQUESTING A DIRECT CALL
        } else if ( data.status == "called"  && data.receiver == username  ) {

          console.log(user+" is now requesting a direct call.");
	  caller = user;
          $( "#indicator" ).html( "@"+user+" calling.." );
	  $( "#audio" ).empty(); // remove all previous audio tags
          var audio = new Audio();
	  audio.src = "sounds/bell.mp3";
	  audio.loop = false;
          audio.volume = "1";
          audio.autoplay = true; audio.controls = false;
          $('#audio').append(audio); // MUST HAVE BR FOR FF
          audio.play(); // MUST BE AT THE END FOR FF

	  $('#call').attr("disabled", false);
	  $('#jump').attr("disabled", false);


        // CASE: I AM REQUESTING A DIRECT CALL
        } else if ( data.status == "called"  && user == username  ) {
          $( "#indicator" ).html( " Calling @"+data.receiver+".." );

        // CASE: I AM REQUESTING A CHANNEL CALL
        } else if ( data.status == "asked"  && user == username  ) {
          $( "#indicator" ).html( " Calling channel @"+channel+".." );


        // CASE: I HAVE ACCEPTED /////////////
        } else if ( data.status == "served" && data.receiver == username ) { 
          caller = user;

          $( "#indicator" ).html( " " );
	  $( "#close" ).html( "End Call" );


        // CASE: I WAS SERVED /////////////
        } else if ( data.status == "served" && user == username  ) { 
	  caller = user;
          $( "#indicator" ).html( " " );
	  $( "#close" ).html( "End Call" );

  	  userRef.child(data.receiver).once("value", function(nameSnapshot) {
     		data = nameSnapshot.val();

     		if ( data && data.fullname ) { $( "#name" ).html( data.fullname ); } else { $( "#name" ).html( data.receiver ); }
  	  });


        // CASE: SOMEONE ELSE HAS ACCEPTED /////////////
        } else if ( data.status == "served"  && data.receiver != username ) { 

          $( "#indicator" ).html( "Awaiting call.." );
         $( "#name" ).html( "" );
	  $('#call').attr("disabled", true); 
	  $('#jump').attr("disabled", true);



        // CASE: IDLE /////////////
        } else if ( data.status == "enabled"  && user == username && caller == "" ) { 

          $( "#indicator" ).html( "Awaiting call.." );
          $( "#name" ).html( "" );
	  $('#call').attr("disabled", true);
	  $('#jump').attr("disabled", true);



        }

});

chatRef.on("child_changed", function(snapshot) { // CHAT LISTENER
    var data = snapshot.val();
    user = snapshot.key;
    console.log(user+" changed.");


        // CASE: I AM REQUESTING A CALL
        if ( (data.status == "requested" ) && user == username  ) {
          $( "#indicator" ).html( "Request submitted.." );

        // CASE: SOMEONE IS REQUESTING A CALL
        } else if ( data.status == "requested"  && user != username  ) {
          console.log(user+" is now requesting call.");


	  caller = user;
          $( "#indicator" ).html( "Call requested.." );
	  $( "#audio" ).empty(); // remove all previous audio tags
          var audio = new Audio();
	  audio.src = "sounds/bell.mp3";
	  audio.loop = false;
          audio.volume = "0.8";
          audio.autoplay = true; audio.controls = false;
          $('#audio').append(audio); // MUST HAVE BR FOR FF
          audio.play(); // MUST BE AT THE END FOR FF

	  $('#call').attr("disabled", false);
	  $('#jump').attr("disabled", false);




        // CASE: SOMEONE IS REQUESTING A CHANNEL CALL
        } else if ( data.status == "asked"  && data.receiver == team+"-"+channel && user != username  ) {

          console.log(user+" is now requesting channel call.");
	  caller = user;
          $( "#indicator" ).html( "Channel call requested.." );
	  $( "#audio" ).empty(); // remove all previous audio tags
          var audio = new Audio();
	  audio.src = "sounds/bell.mp3";
	  audio.loop = false;
          audio.volume = "1";
          audio.autoplay = true; audio.controls = false;
          $('#audio').append(audio); // MUST HAVE BR FOR FF
          audio.play(); // MUST BE AT THE END FOR FF

	  $('#call').attr("disabled", false);
	  $('#jump').attr("disabled", false);




        // CASE: SOMEONE IS REQUESTING A DIRECT CALL
        } else if ( data.status == "called"  && data.receiver == username  ) {

          console.log(user+" is now requesting a direct call.");
	  caller = user;
          $( "#indicator" ).html( "@"+user+" calling.." );
	  $( "#audio" ).empty(); // remove all previous audio tags
          var audio = new Audio();
	  audio.src = "sounds/bell.mp3";
	  audio.loop = false;
          audio.volume = "1";
          audio.autoplay = true; audio.controls = false;
          $('#audio').append(audio); // MUST HAVE BR FOR FF
          audio.play(); // MUST BE AT THE END FOR FF

	  $('#call').attr("disabled", false);
	  $('#jump').attr("disabled", false);



        // CASE: I AM REQUESTING A DIRECT CALL
        } else if ( data.status == "called"  && user == username  ) {
          $( "#indicator" ).html( " Calling @"+data.receiver+".." );

        // CASE: I AM REQUESTING A CHANNEL CALL
        } else if ( data.status == "asked"  && user == username  ) {
          $( "#indicator" ).html( " Calling channel @"+channel+".." );

        
        // CASE: SOMEONE IS LEAVING A CALL -- CHANGED TO DISABLED
        } else if ( data.status == "disabled"  && user != username  ) {
          $( "#indicator" ).html( "Awaiting call.." );
         $( "#name" ).html( "" );
	  $('#call').attr("disabled", true);
	  $('#jump').attr("disabled", true);




        // CASE: SOMEONE ELSE HAS ACCEPTED THE PREV CALLER -- CHANGED TO SERVED
        } else if ( data.status == "served" && data.receiver != username && user == caller && user != username ) { 
          console.log(user+" is being served.");


	  // FIND ANOTHER CALLER
  	  chatRef.once("value", function(callerSnapshot) {
		var found = false;
		callerSnapshot.forEach(function(subcallerSnapshot) { // CHANNEL LOOP
			var data = subcallerSnapshot.val(); 
			if ( data.status == "requested"  && user != username  ) { //FOUND ONE
	  			caller = subcallerSnapshot.key; found = true;
			        console.log(caller+" found for request.");
			}
		});
		if ( ! found ) {
          		$( "#indicator" ).html( "Awaiting call.." );
         		$( "#name" ).html( "" );
	  		$( "#call" ).attr("disabled", true);
	  		$('#jump').attr("disabled", true);


		}
	  });

        // CASE: I HAVE ACCEPTED /////////////
        } else if ( data.status == "served" && data.receiver == username ) { 
	  caller = user;

          $( "#indicator" ).html( " " ); 
	  $( "#close" ).html( "End Call" );


        // CASE: I WAS SERVED /////////////
        } else if ( data.status == "served" && user == username  ) { 
	  caller = user;
          $( "#indicator" ).html( " " );
	  $( "#close" ).html( "End Call" );
  	  userRef.child(data.receiver).once("value", function(nameSnapshot) {
     		data = nameSnapshot.val();
     		if ( data && data.fullname ) { $( "#name" ).html( data.fullname ); } else { $( "#name" ).html( data.receiver ); }
  	  });

        // CASE: SOMEONE ELSE HAS ACCEPTED /////////////
        } else if ( data.status == "served"  && data.receiver != username ) { 

          $( "#indicator" ).html( "Awaiting call.." );
          $( "#name" ).html( "" );
	  $('#call').attr("disabled", true);
	  $('#jump').attr("disabled", true);



        // CASE: IDLE /////////////
        } else if ( data.status == "enabled"  && user == username  && caller == ""  ) { 

          $( "#indicator" ).html( "Awaiting call.." );
          $( "#name" ).html( "" );
	  $('#call').attr("disabled", true);
	  $('#jump').attr("disabled", true);



         }

});


function acceptRequest() { 

  webrtcRef.child(caller).on('child_added', readMessage);
  showFriendsFace();

  console.log("Accepting call for "+caller+"..");
  myRef.update({ status: "accepted" });
  $('#call').attr("disabled", true);
  $('#jump').attr("disabled", true);

  chatRef.child(caller).ref.update({ status: "served", receiver: username });
  // ADD FULLNAME
  userRef.child(caller).once("value", function(nameSnapshot) {
     data = nameSnapshot.val();
     if ( data && data.fullname != " " ) { $( "#name" ).html( data.fullname ); } else { $( "#name" ).html( caller ); }
  });

  // INCREASE SESSION COUNTERS
  myRef.child("sessions").transaction(function(currentValue) {
    return (currentValue || 0) + 1;
  });

  chatRef.child(caller).child("sessions").transaction(function(currentValue) {
    return (currentValue || 0) + 1; 
  });


}

function jumpChannel() {

   chatRef.child(caller).once("value", function(nameSnapshot) {
     data = nameSnapshot.val();
     if ( data ) { 
 	window.open("<?php echo BASE_URL; ?>webhooks/chat.php?command="+command+"&team="+data.domain+"&channel="+data.channel+"&user="+username+"&scope="+scope2, "_self"); 
     }
  });

}


function closeSession() { 

  disableVideo();
  //myRef.remove();
  myRef.update({ status: "disabled", receiver: null, domain: null, channel: null  });
  //webrtcRef.child(caller).off("child_added"); // close listener
  window.close();

}

function disableVideo() { 
 navigator.mediaDevices.getUserMedia({audio:true, video:true})
  .then(function(stream) {
    stream.getTracks().forEach(function(track) { track.stop(); })
    yourVideo.srcObject = null;
    pc.removeStream(stream);
  });
}


// ESTABLISH PEER CONNECTION 
var yourVideo = document.getElementById("yourVideo");
var friendsVideo = document.getElementById("friendsVideo");
var yourId = Math.floor(Math.random()*1000000000);
var servers = {'iceServers': [ {'urls': 'stun:stun.l.google.com:19302'} ]};
// DO NOT USE MORE THAN ONE STUN SERVER - IT WILL CREATE MSG CONFLICTS
//,{'urls': 'stun:stun.services.mozilla.com'},{'urls': 'turn:numb.viagenie.ca','credential': 'diglife12','username': 'joachim.stroh@diglife.com'}

var pc = new RTCPeerConnection(servers);
pc.onicecandidate = (event => event.candidate?sendMessage(yourId, JSON.stringify({'ice': event.candidate})):console.log("Sent All Ice") );
pc.onaddstream = (event => friendsVideo.srcObject = event.stream);

function sendMessage(senderId, data) {
 console.log("Sending to "+caller+"..");
 var msg = webrtcRef.child(caller).ref.push({ sender: senderId, message: data });
 msg.remove();
}

function readMessage(data) {
 var msg = JSON.parse(data.val().message);
 var sender = data.val().sender;
 if (sender != yourId) {
 if (msg.ice != undefined)
 pc.addIceCandidate(new RTCIceCandidate(msg.ice));
 else if (msg.sdp.type == "offer")
 pc.setRemoteDescription(new RTCSessionDescription(msg.sdp))
 .then(() => pc.createAnswer())
 .then(answer => pc.setLocalDescription(answer))
 .then(() => sendMessage(yourId, JSON.stringify({'sdp': pc.localDescription})));
 else if (msg.sdp.type == "answer")
 pc.setRemoteDescription(new RTCSessionDescription(msg.sdp));
 }
};


if ( command == "request" || command == "ask" || command == "call" ) { 
  webrtcRef.child(username).on('child_added', readMessage); }




function showMyFace() {
 navigator.mediaDevices.getUserMedia({audio:true, video:true})
 .then(stream => yourVideo.srcObject = stream)
 .then(stream => pc.addStream(stream));
}

function showFriendsFace() {
 pc.createOffer()
 .then(offer => pc.setLocalDescription(offer) )
 .then(() => sendMessage(yourId, JSON.stringify({'sdp': pc.localDescription})) );
}

window.onbeforeunload = function (event) {
  closeSession();
};

// HELPER FUNCTIONS HERE  ////////////////////////////////////////////
// String to Color
var stringToColor = function(str) {
    var hash = 0;
    for (var i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    var color = '#';
    for (var i = 0; i < 3; i++) {
        var value = (hash >> (i * 8)) & 0xFF;
        color += ('00' + value.toString(16)).substr(-2);
    }
    return color;
}


      $(document).ready(function() {
     
            setTimeout(function(){
		$('.element-item').fadeIn(100).addClass('animated bounceInDown');
            }, 1000);
        });  
</script>


</html>