<!DOCTYPE html><html class=''>
<head><meta name="viewport" content="width=device-width, initial-scale=1">
<?php include('base.php'); ?>
<base href="<?php echo BASE_URL; ?>webhooks/">

<script src='js/jquery.min.js'></script>
<script src='js/isotope.pkgd.min.js'></script>
<script src="js/jquery.longpress.js"></script>
<script src="https://www.gstatic.com/firebasejs/4.1.3/firebase.js"></script>
<script>
  // Initialize Firebase

  var config = {
    apiKey: "<?php echo FIREBASE_API_KEY; ?>",
    authDomain: "<?php echo FIREBASE_DOMAIN; ?>",
    databaseURL: "<?php echo FIREBASE_URL; ?>",
    projectId: "<?php echo FIREBASE_PROJECT_ID; ?>",
    storageBucket: "<?php echo FIREBASE_BUCKET; ?>",
    messagingSenderId: "<?php echo FIREBASE_SENDER_ID; ?>"

  };

  var mainApp = firebase.initializeApp(config);
  //console.log(mainApp.name);  // "[DEFAULT]"

</script>



<link rel="stylesheet" href="css/dashboard.css?v7">

</head>


<body>

<div class="title">
  <h1>DigLife Dashboard</h1>
  <h2>Periodic Table of Organizational Elements</h2>
</div>

<div id="collective-link">
  <button class="button" data-filter=".collective">Collectives</button>
</div>

<div id="domain-link">
  <button class="button" data-filter=".meta">Domains</button>
</div>

<div class="search-box">
  <input type="text" id="quicksearch" placeholder="Search" />
</div>

<div id="filters" class="button-group">
  <button class="button is-checked" data-filter="*">all</button>
  <button class="button" data-filter=".action">actions</button>
  <button class="button" data-filter=".doc">docs</button>
  <button class="button" data-filter=".link">links</button>
  <button class="button" data-filter=".goal">goals</button>
  <button class="button" data-filter=".norm">norms</button>
  <button class="button" data-filter=".job">jobs</button>
  <button class="button" data-filter=".contract">contracts</button>
</div>

<div class="grid"></div>
<div class="clock"></div>

<div id="audio">
  <audio id="aud1" volume="0.05" style="position: absolute; bottom: 0; left: 0; opacity: .5;" autoplay controls loop>
    <source src="sounds/wind_calm.wav">
  </audio>
  <audio id="aud2" volume="0.01" style="position: absolute; bottom: 34px; left: 0; opacity: .5;" autoplay controls loop>
    <source src="sounds/water_lake.mp3">
  </audio>
</div>

<div id='video'></div>

<script>


// ADJUST VOLUME ///////////////////////////////////////////////
var audio = document.getElementById('aud1');
audio.volume = 0.05;
var audio = document.getElementById('aud2');
audio.volume = 0.01;

// PARSING PARAMETERS ///////////////////////////////////////////////
  var searchString = window.location.search.substring(1), i, val, params = searchString.split("&");
  //var activity="", command="", team="", channel="", scope="", username="";

  for (i=0;i<params.length;i++) {
    val = params[i].split("=");
    if (val[0] == "user") {
      var username = val[1];
    } else if (val[0] == "command") {
      var command = val[1];
    } else if (val[0] == "scope") {
      scope = val[1];
    } else if (val[0] == "team") {
      var team = val[1];
    } else if (val[0] == "channel") {
      var channel = val[1];
    } else if (val[0] == "database") {
      var database = val[1];
    } else if (val[0] == "search") {
      var search = val[1];
    }
  }

if (username == "" ) { alert("This application requires a username"); throw ''; }

// Access either the main tokens branch or the archive for activities
if ( database == "archive" || database == "archives" ) { var tokensRef = firebase.database().ref("archive"); }
else { var tokensRef = firebase.database().ref("tokens"); }
var teamsRef =  firebase.database().ref("teams");

// Passing the domain array from php to Javascript
var domains = <?php echo json_encode($domain); ?>;


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

function clock() {
  var local_time = new Date(),
  utc_time = local_time.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute:'2-digit'}),
  utc_date = local_time.toLocaleTimeString('en-US', { weekday: "long", year: "numeric", month: "long", day: "numeric" } );
  document.querySelectorAll('.clock')[0].innerHTML = "<h1>"+utc_time+"</h1><h2>"+ utc_date.substring(0, utc_date.lastIndexOf(","))+"</h2>";
}

setInterval(clock, 1000);



// ISOTOPE FUNCTIONS HERE ////////////////////////////////////////////

// quick search regex
var qsRegex;
var buttonFilter;

// you can pass a search parameter in the URL
$('#quicksearch').val(search);
qsRegex = new RegExp( $('#quicksearch').val(), 'gi' );

// set the team  from URL
if (team  == "meta") { teamFilter = '.'+team; }
else if (team) { teamFilter = ':not(.meta).'+team; } 
else { teamFilter = ""; } 


// set the channel from URL
if (channel == "meta") { channelFilter = '.'+channel; }
else if (channel) { channelFilter = ':not(.meta).'+channel; } 
else { channelFilter = ""; } 

// init Isotope
var $grid = $('.grid').isotope({
  itemSelector: '.element-item',
  layoutMode: 'fitRows',
stagger: 20, 
transitionDuration: 200,
  filter: function() {
    var $this = $(this);
    var searchResult = qsRegex ? $this.text().match( qsRegex ) : true;
    var buttonResult = buttonFilter ? $this.is( buttonFilter ) : true;  // ':not(.meta)' would hide it all the time
    var channelResult = channelFilter ? $this.is( channelFilter ) : true;
    var teamResult = teamFilter ? $this.is( teamFilter ) : true;

    return searchResult && buttonResult && channelResult && teamResult;
  }
});

$('#filters').on( 'click', 'button', function() {
  buttonFilter = $( this ).attr('data-filter');
  $grid.isotope();
});

$('#domain-link').on( 'click', 'button', function() {
  channelFilter = $( this ).attr('data-filter');
  teamFilter = "";
  $grid.isotope();
});

// use value of search field to filter
var $quicksearch = $('#quicksearch').keyup( debounce( function() {
  qsRegex = new RegExp( $quicksearch.val(), 'gi' );
  teamFilter = "";
  channelFilter = "";
  $grid.isotope();
}) );


  // change is-checked class on buttons
$('.button-group').each( function( i, buttonGroup ) {
  var $buttonGroup = $( buttonGroup );
  $buttonGroup.on( 'click', 'button', function() {
 channelFilter = "";  teamFilter = "";
    $buttonGroup.find('.is-checked').removeClass('is-checked');
    $( this ).addClass('is-checked');

  });
});
  

// debounce so filtering doesn't happen every millisecond
function debounce( fn, threshold ) {
  var timeout;
  return function debounced() {
    if ( timeout ) {
      clearTimeout( timeout );
    }
    function delayed() {
      fn();
      timeout = null;
    }
    setTimeout( delayed, threshold || 100 );
  };
}


</script>


<script>

  // INITIALIZE SKILL FACTORS
  var skill = [];
  skill['Null'] = 0.25; // need min skill otherwise the efficacy is zero
  skill['Novice'] = 0.25;
  skill['Apprentice'] = 0.5;
  skill['Regular'] = 1.0;
  skill['Expert'] = 2.0;
  skill['Advisor'] = 4.0;

  var title, link, size, type, user, isMe = false, isSudo = false, tokens = 0, commitment = 0, isChannel = false;
  var channelCommitment = 0, channelValue = 0, personalCommitment = 0, personalValue = 0, activityCommitment = 0, activityValue = 0, activityTotal = 0, tileValue = 0, tileCommitment = 0;
  var channelTotal = [], personalTotal = [], channelValue = [], personalValue = [], activityValue = [], activityTotal = [], activityCommitment = [], tileValue = [], tileCommitment = [], activityRatio = [], channelRatio = [];

  // DOMAIN -----------------------------------------------------------------------------------------------
  tokensRef.on("child_added", function(snapshot) { // DOMAIN LISTENER
    var domain = snapshot.key;
    if ( domains[domain] ) { domain = domains[domain]; }


    snapshot.forEach(function(channelSnapshot) { // CHANNEL LOOP

        channelCom = 0; personalCom = 0; personalVal = 0; channelVal = 0; 
        // ACTIVITY AGGREGATION FOR ALL USERS OF CHANNEL -----------------------------------------------------------------------------------------------
        channelSnapshot.forEach(function(activitySnapshot) { // CHANNEL PRELOOP

	  activityTotal = 0; activityVal = 0; activityCom = 0;  tileVal = 0; tileCom = 0;
          activitySnapshot.forEach(function(innerSnapshot) { // ACTIVITY PRELOOP
            // accumulate all claimed tokens for to right number in element
            var key = innerSnapshot.key;
	    if ( key != "type" && key != "name" && key != "size") {

            innerSnapshot.ref.once("value", function(userSnapshot) {
                 userdata = userSnapshot.val();

		 // GET THE SKILL LEVEL FOR USER 
		 teamsRef.child(snapshot.key).child(channelSnapshot.key).child(userSnapshot.key).child("level").once("value", function(skillSnapshot) {
                   var data = skillSnapshot.val(); 
	  	   if (data !== null) { level = data; } else { level = "Null"; }
		   innerSnapshot.ref.update({ skill: skill[level] });
                 }); // TEAM 

                 if (userdata !== null && userdata.enacted) { 
			channelCom += parseInt(userdata.enacted); 
			channelVal += parseInt(userdata.enacted) * userdata.skill; 
			activityCom += parseInt(userdata.enacted); 
			activityVal += parseInt(userdata.enacted) * userdata.skill; 
		 }
                 if (userdata !== null && userdata.enacted && innerSnapshot.key == username) { 
			personalCom += parseInt(userdata.enacted); 
			personalVal += parseInt(userdata.enacted) * userdata.skill;
			tileCom += parseInt(userdata.enacted); 
			tileVal += parseInt(userdata.enacted) * userdata.skill; 
		 }

            });  // INNER
	
	     } // IF

    	  });  // ACTIVITY

	  // UPDATE METRICS ON TILES
  	  // only a simple var is working in the loop;
	  var loc = snapshot.key+channelSnapshot.key+activitySnapshot.key;
	  tileCommitment[loc]=tileCom; // User commitment on tile
	  tileValue[loc]=tileVal; // User efficacy on tile
	  activityValue[loc]=activityVal;  // Activity commitment on tile
	  activityCommitment[loc]=activityCom;  // Activity efficacy on tile
	  if ( activityVal == null || activityVal == 0) { activityRatio[loc] = 0; } else { activityRatio[loc] = (tileVal/activityVal*100).toFixed(0); } 

	  activitySnapshot.ref.update({ size: activityVal }); 

   	}); // CHANNEL


	// Update the total size of all activities for a given channel
  	// only a simple var is working in the loop;
	var loc = snapshot.key+channelSnapshot.key;
	channelTotal[loc]=channelCom; // Channel Commitment
	channelValue[loc]=channelVal;  // Channel Efficacy
	personalTotal[loc]=personalCom;  // User Channel Commitment
	personalValue[loc]=personalVal;  // User Channel Efficacy
	if (channelVal == 0) { channelRatio[loc] = 0; } else { channelRatio[loc] = (personalVal/channelVal*100).toFixed(0); } 
//console.log(loc+channelRatio[loc]+"%"+personalVal+"/"+channelVal);


  	// CHANNEL -----------------------------------------------------------------------------------------------
        channelSnapshot.ref.on("child_added", function(activitySnapshot) { // ACTIVITY LISTENER


   	  //commitment = 0; tokens = 0; 
	  // ACTIVITY AGGREGATION FOR USER AND ALL USERS OF ACTIVITY -----------------------------------------------------------------------------------------------
          //activitySnapshot.forEach(function(innerSnapshot) { // ACTIVITY PRELOOP >>> WARNING: THIS IS ASYNC
            // accumulate all claimed tokens for to right number in element
            //innerSnapshot.ref.once("value", function(userSnapshot) {
            //   var data = userSnapshot.val();

           //    if (data !== null && data.enacted) { tokens = tokens + parseInt(data.enacted); }
  	       
         //   });
   	  //});
 	  // Update the size of the activity (this works async, because the activitySnapshot is used)
	  // Do not use the tokens variable in the loop below, because of the asynchronicity here
	  //activitySnapshot.ref.update({ size: tokens });


	  // ACTIVITY -----------------------------------------------------------------------------------------------
          activitySnapshot.forEach(function(innerSnapshot) { // ACTIVITY LOOP

            if (innerSnapshot.key == "name") {
              title = innerSnapshot.val().replace(/\[(.*?)\]\((.*?)\)/, '$1').replace(/[ ]*:(.*?):[ ]*(.*?)/, '$2');
              link = innerSnapshot.val().replace(/.*\[(.*?)\]\((.*?)\)/, '$2');
              if ( !innerSnapshot.val().includes('](') ) { link = "<?php echo CHAT_URL; ?>"+snapshot.key+"/channels/"+channelSnapshot.key; }

            } else if  (innerSnapshot.key == "size") {
              size = innerSnapshot.val();

            } else if  (innerSnapshot.key == "type") { // assumption is this is the LAST element, but it is not (alpha order) ///////////////////////////////

              // Cases of when to add an element 
              if (scope == "none" || scope == "all" || (scope == "me" && isMe) || (scope != "" && isSudo) ) {


                var linkContainer = $('<a target=\"_blank\" href=\"'+link.replace("%USER%", username)+'\"></a>');
                var elementContainer = $('<div id=\"test\" class=\"element-item '+snapshot.key+' '+channelSnapshot.key+' '+innerSnapshot.val()+'\"></div>');
                linkContainer.append(elementContainer);
                elementContainer.append('<h3 class=\"name\">'+title+'</h3>');
                elementContainer.append('<p class=\"symbol\">'+domain.charAt(0).toUpperCase()+domain.charAt(1).toLowerCase()+'</p>');

		if ( innerSnapshot.val() == "action" )
                	{ var commitButton = $('<p class=\"number\">'+activityRatio[snapshot.key+channelSnapshot.key+activitySnapshot.key]+'%'+'</p>'); }
		else if ( commitment > 0 ) { var commitButton = $('<p class=\"checkbox_checked \"></p>').on( 'click', function(event) { 
				if ( $(this).hasClass("checkbox_checked") ) { $(this).removeClass("checkbox_checked"); $(this).addClass("checkbox_unchecked"); 
					tokensRef.child(snapshot.key+"/"+channelSnapshot.key+"/"+activitySnapshot.key+"/"+username).remove(); }
				else {  $(this).removeClass("checkbox_unchecked"); $(this).addClass("checkbox_checked");
					tokensRef.child(snapshot.key+"/"+channelSnapshot.key+"/"+activitySnapshot.key+"/"+username).update({ enacted: 20, timestamp: Date.now() }); }
				return false; }); 
			}
		else { var commitButton = $('<p class=\"checkbox_unchecked\"></p>').on( 'click', function(event) { 
				if ( $(this).hasClass("checkbox_checked") ) { $(this).removeClass("checkbox_checked"); $(this).addClass("checkbox_unchecked"); 
					tokensRef.child(snapshot.key+"/"+channelSnapshot.key+"/"+activitySnapshot.key+"/"+username).remove(); }
				else {  $(this).removeClass("checkbox_unchecked"); $(this).addClass("checkbox_checked");
					tokensRef.child(snapshot.key+"/"+channelSnapshot.key+"/"+activitySnapshot.key+"/"+username).update({ enacted: 20, timestamp: Date.now() }); }
				return false; }); 
			}
		elementContainer.append(commitButton);


                elementContainer.append('<p class=\"channel\">'+channelSnapshot.key.replace(/\-/g," ").toUpperCase()+'</p>');
                elementContainer.append('<p class=\"emoji '+innerSnapshot.val()+'\"></p>');

		// The click event deletes the entry from the ledger, it is not reversible
		var deleteButton = $('<p class=\"buttons delete\"></p>').on( 'click', function(event) { 
			tokensRef.child(snapshot.key+"/"+channelSnapshot.key+"/"+activitySnapshot.key).remove(); 
			$(this).parent().fadeOut(500);
                  	$grid.isotope( 'remove', this.parentElement).isotope('layout');
			return false; 
		});

		// The click event archives an entry from the ledger, it can be retrieved from Mattermost with /dig archive
		var archiveButton = $('<p class=\"buttons archive\"></p>').on( 'click', function(event) {
     			tokensRef.child(snapshot.key+"/"+channelSnapshot.key+"/"+activitySnapshot.key).once('value', function(snap)  {
          			firebase.database().ref().child("archive/"+snapshot.key+"/"+channelSnapshot.key+"/"+activitySnapshot.key).set( snap.val(), function(error) {
               				if( !error ) {   tokensRef.child(snapshot.key+"/"+channelSnapshot.key+"/"+activitySnapshot.key).remove(); }
               				else if( typeof(console) !== 'undefined' && console.error ) {  console.error(error); }
          			});
     			});

			$(this).parent().fadeOut(500);
                  	$grid.isotope( 'remove', this.parentElement).isotope('layout');
			return false; 
		});

		var renewButton = $('<p class=\"buttons commitment_'+commitment+'\"></p>').on( 'click', function() {
			if ( $(this).hasClass("commitment_0") ) { var value = 0; } 
			else if ( $(this).hasClass("commitment_20") ) { var value = 20; }  
			else if ( $(this).hasClass("commitment_40") ) { var value = 40; }  
			else if ( $(this).hasClass("commitment_60") ) { var value = 60; }  
			else if ( $(this).hasClass("commitment_120") ) { var value = 120; } 
			else if ( $(this).hasClass("commitment_240") ) { var value = 240; }  
			tokensRef.child(snapshot.key+"/"+channelSnapshot.key+"/"+activitySnapshot.key+"/"+username).update({ enacted: value, timestamp: Date.now() });
			// Hide button after pressing
			$(this).hide(); $(this).next().hide(); $(this).next().next().hide(); 
			// Change the number value after pressing
			//var str_arr = $(this).prev().prev().prev().prev().prev().text().split("/");
			$(this).prev().prev().prev().prev().prev().text( "" );
			//window.open("circle.php?command=view&team="+snapshot.key+"&channel="+channelSnapshot.key+"&user="+username+"&activity="+activitySnapshot.key);
			return false; 
		});
		var renewRightButton = $('<p class=\"buttonright commitment_'+commitment+'\"></p>').on( 'click', function() { 
			var element = $(this).prev();  // GET POINTER TO renewButton 
			if ( element.hasClass("commitment_0") ) { element.removeClass("commitment_0"); element.addClass("commitment_20"); return false; } 
			else if ( element.hasClass("commitment_20") ) { element.removeClass("commitment_20");element.addClass("commitment_40"); return false; }  
			else if ( element.hasClass("commitment_40") ) { element.removeClass("commitment_40");element.addClass("commitment_60"); return false; }  
			else if ( element.hasClass("commitment_60") ) { element.removeClass("commitment_60");element.addClass("commitment_120"); return false; }  
			else if ( element.hasClass("commitment_120") ) { element.removeClass("commitment_120");element.addClass("commitment_240"); return false; } 
			else if ( element.hasClass("commitment_240") ) { element.removeClass("commitment_240");element.addClass("commitment_0"); return false; }  
 
		});

		var renewLeftButton = $('<p class=\"buttonleft commitment_'+commitment+'\"></p>').on( 'click', function() { 
			var element = $(this).prev().prev(); // GET POINTER TO renewButton 
			if ( element.hasClass("commitment_0") ) { element.removeClass("commitment_0"); element.addClass("commitment_240"); return false; } 
			else if ( element.hasClass("commitment_20") ) { element.removeClass("commitment_20");element.addClass("commitment_0"); return false; }  
			else if ( element.hasClass("commitment_40") ) { element.removeClass("commitment_40");element.addClass("commitment_20"); return false; }  
			else if ( element.hasClass("commitment_60") ) { element.removeClass("commitment_60");element.addClass("commitment_40"); return false; }  
			else if ( element.hasClass("commitment_120") ) { element.removeClass("commitment_120");element.addClass("commitment_60"); return false; } 
			else if ( element.hasClass("commitment_240") ) { element.removeClass("commitment_240");element.addClass("commitment_120"); return false; }  
 
		});


		elementContainer.append(deleteButton);
		elementContainer.append(archiveButton);
		elementContainer.append(renewButton);
		elementContainer.append(renewRightButton);
		elementContainer.append(renewLeftButton);
		elementContainer.on( 'contextmenu', function(event) { 
			$(this).children('.buttons').fadeIn(100); $(this).children('.buttonright').show(); $(this).children('.buttonleft').show();  
			return false; 
		});
		elementContainer.on( 'mouseleave', function(event) { $(this).children('.buttons').hide();  $(this).children('.buttonright').hide(); $(this).children('.buttonleft').hide(); });

		elementContainer.css('background-color', stringToColor(domain));

                $('.grid').append(linkContainer);
                $('.grid').isotope( 'appended', jQuery( linkContainer ) );
                isMe = false; isSudo = false; link = ""; //tokens = 0;
              }



            } else { // USER LEVEL

              user = innerSnapshot.key; // use this value to compare with scope=all,me, or sudo
              if (user == username) { isMe = true; commitment = parseInt(innerSnapshot.val().enacted); } // first match needs to be recorded
              if (user == scope) { isSudo = true; } // first match needs to be recorded

            }

          }); // INNER SNAPSHOT
       }); // ACTIVITY SNAPSHOT


	// ---------------------------------------------------------------------------------------
        // Add meta tiles for each channel 
	// ---------------------------------------------------------------------------------------



                // THIS TILE IS FOR TOP LEVEL NAVIGATION ONLY ///////////////////////////////////////////////////////////
                var linkContainer = $('<a data-filter=\".'+channelSnapshot.key+'.'+snapshot.key+'\" href=\"#\"></a>');
                linkContainer.click( function() {
                  
		  // LAUNCH VIDEO ///////////////////////////////////////////////
		  if ( channelSnapshot.key == "social-ledger-lab" || channelSnapshot.key == "digital-identity" ) {

  		  var video = document.createElement('video'); //"<video id='videotag'></video>"; // setup the video element
		  var source = document.createElement('source');
		  if ( channelSnapshot.key == "social-ledger-lab") { source.src = 'images/space.mp4'; } else { source.src = 'images/beach.mp4'; }
		  source.type = 'video/mp4';
		  video.autoPlay = true;
		  video.loop = true; 
		  video.muted = true;
		  video.preload = 'none'; 
		  video.volume = 1;
		  video.appendChild(source);
		  $('#video').append(video); // insert the video element into its container
		  video.play();
		  $('#video').show();

                  $( "#audio" ).empty(); // remove all previous audio tags
                  var audio = new Audio();

		  if ( channelSnapshot.key == "social-ledger-lab") { audio.src = "sounds/harry_gregson-williams_mars.mp3"; } else { audio.src = "sounds/beach.mp3"; }
		  audio.loop = false;
                  audio.autoplay = true; audio.controls = true;
                  $('#audio').append(audio); // MUST HAVE BR FOR FF
                  audio.play(); // MUST BE AT THE END FOR FF
                  audio.volume = "0.3";


		} else {   $('#video').hide(); $('#audio').empty(); }


                  channelFilter = ':not(.meta)' + $( this ).attr('data-filter');
                  $grid.isotope();
                  return false;
                });
                var elementContainer = $('<div class=\"element-item meta '+snapshot.key+' '+channelSnapshot.key+'\"></div>');
                linkContainer.append(elementContainer);
                elementContainer.append('<h3 class=\"name\">'+channelSnapshot.key.replace(/\-/g," ").toUpperCase()+'</h3>');
                elementContainer.append('<p class=\"symbol\">'+domain.charAt(0).toUpperCase()+domain.charAt(1).toLowerCase()+'</p>');
                elementContainer.append('<p class=\"number\">'+channelRatio[snapshot.key+channelSnapshot.key]+'%'+'</p>');
                elementContainer.append('<p class=\"channel\">'+channelSnapshot.key.replace(/\-/g," ").toUpperCase()+'</p>');
                elementContainer.append('<p class=\"emoji meta\"></p>');
		elementContainer.css('background-color', stringToColor(domain));

		// The click event deletes the channel from the ledger, it is not reversible
		var deleteButton = $('<p class=\"buttons delete\"></p>').on( 'click', function(event) { 
			if ( confirm('Are you sure you want to delete this channel?') ) {
				tokensRef.child(snapshot.key+"/"+channelSnapshot.key).remove(); 
				teamsRef.child(snapshot.key+"/"+channelSnapshot.key).remove(); 
				$(this).parent().fadeOut(500);
                  		$grid.isotope( 'remove', this.parentElement).isotope('layout');
			}
			return false; 
		});
		// The click event archives an entry from the ledger, it can be retrieved from Mattermost with /dig archive
		var archiveButton = $('<p class=\"buttons archive\"></p>').on( 'click', function(event) {
     			tokensRef.child(snapshot.key+"/"+channelSnapshot.key).once('value', function(snap)  {
          			firebase.database().ref().child("archive/"+snapshot.key+"/"+channelSnapshot.key).set( snap.val(), function(error) {
               				if( !error ) {   tokensRef.child(snapshot.key+"/"+channelSnapshot.key).remove(); }
               				else if( typeof(console) !== 'undefined' && console.error ) {  console.error(error); }
          			});
     			});

			$(this).parent().fadeOut(500);
                  	$grid.isotope( 'remove', this.parentElement).isotope('layout');
			return false; 
		});
		elementContainer.append(deleteButton);
		elementContainer.append(archiveButton);
		elementContainer.on( 'contextmenu', function(event) { 
			$(this).children('.buttons').fadeIn(100);
			return false; 
		});
		elementContainer.on( 'mouseleave', function(event) { $(this).children('.buttons').hide(); });

                $('.grid').append(linkContainer);
                $('.grid').isotope( 'appended', jQuery( linkContainer ) );


                // THIS TILE IS FOR OPENING MATTERMOST ONLY ///////////////////////////////////////////////////////////////////////////////////////
                linkContainer = $('<a target=\"myiframe\" href=\"<?php echo CHAT_URL; ?>'+snapshot.key+'/channels/'+channelSnapshot.key+'\"></a>');
                var elementContainer = $('<div class=\"element-item '+snapshot.key+' '+channelSnapshot.key+'\"></div>');
                linkContainer.append(elementContainer);
                elementContainer.append('<h3 class=\"name\">'+channelSnapshot.key.replace(/\-/g," ").toUpperCase()+' CHANNEL</h3>');
                elementContainer.append('<p class=\"symbol\">'+domain.charAt(0).toUpperCase()+domain.charAt(1).toLowerCase()+'</p>');
                elementContainer.append('<p class=\"number\">'+channelRatio[snapshot.key+channelSnapshot.key]+'%'+'</p>');
                elementContainer.append('<p class=\"channel\">'+channelSnapshot.key.replace(/\-/g," ").toUpperCase()+'</p>');
                elementContainer.append('<p class=\"emoji mattermost\"></p>');
		elementContainer.css('background-color', stringToColor(snapshot.key));


                $('.grid').append(linkContainer);
                $('.grid').isotope( 'appended', jQuery( linkContainer ) );

                // THIS TILE IS FOR LISTING COLLECTIVES ONLY ///////////////////////////////////////////////////////////////////////////////////////
		// LOOP THROUGH $Collective (Declare via php on top)
		// ADD BLACK TILES


       // THIS TILE IS FOR ADDING NEW TILE ONLY ///////////////////////////////////////////////////////////////////////////////////////
       linkContainer = $('<a target=\"_blank\"></a>').on( 'click', function( ) { alert ( "Coming soon to a collective near you!"); }); 
        var elementContainer = $('<div class=\"element-item '+snapshot.key+' '+channelSnapshot.key+'\"></div>');
       linkContainer.append(elementContainer);
       elementContainer.append('<h3 class=\"name\" style=\"font-size: 200px; margin: 0; padding:0; line-height: 0; position: absolute; top: 65px; left: 25px; overflow: visible; opacity: 0.8; text-shadow: -1px -1px 1px #fff, 1px 1px 1px #000;\">+</h3>');
       elementContainer.append('<p class=\"symbol\"></p>');
       elementContainer.append('<p class=\"number\"></p>');
       elementContainer.append('<p class=\"channel\"></p>');
       elementContainer.append('<p class=\"emoji \"></p>');
	elementContainer.css('background-color', stringToColor(domain));
       $('.grid').append(linkContainer);
       $('.grid').isotope( 'appended', jQuery( linkContainer ) );


    }); // CHANNEL SNAPSHOT


  }); // DOMAIN SNAPSHOT




</script>
<scr ipt src="js/particles.js"></script>


</body></html>