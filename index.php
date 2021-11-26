<?PHP 
$user     = 'jasontate'; 
$key      = 'apikey';
$status   = 'Last Played';
$endpoint = 'https://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=' . $user . '&limit=2&api_key=' . $key . '&format=json';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpoint);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); // 0 for indefinite
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second attempt before timing out
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
$response = curl_exec($ch);
$lastfm[] = json_decode($response, true);
curl_close($ch);

// Get the album artwork.
$artwork = $lastfm[0]['recenttracks']['track'][0]['image'][3]['#text'];

if ( empty( $artwork ) ) {  // Check if album artwork exists on last.fm, otherwise use a placeholder
	$artwork = 'artwork-placeholder.png';
}
else { // change the size to the largest available.
	$artwork = str_replace('300x300', '600x600', $artwork); 
}

// Populate array with the returned information.
$trackInfo = [
	'name'       => $lastfm[0]['recenttracks']['track'][0]['name'],
	'album'      => $lastfm[0]['recenttracks']['track'][0]['album']['#text'],
	'artist'     => $lastfm[0]['recenttracks']['track'][0]['artist']['#text'],
	'albumArt'   => $artwork,
	'status'     => $status
];

// Check if we are currently playing a song.
if ( !empty($lastfm[0]['recenttracks']['track'][0]['@attr']['nowplaying']) ) {
	$trackInfo['nowPlaying'] = $lastfm[0]['recenttracks']['track'][0]['@attr']['nowplaying'];
	$trackInfo['status'] = 'Now Playing';
}

// We are currently playing a song, get more information about it.
if($trackInfo['status'] == "Now Playing") {
	
	// Get the number of plays for the track.
	$endpoint = 'https://ws.audioscrobbler.com/2.0/?method=track.getInfo&username=' . $user . '&api_key=' . $key . '&artist=' . urlencode($trackInfo['artist']) . '&track=' . urlencode($trackInfo['name']) . '&format=json';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $endpoint);
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); // 0 for indefinite
	curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second attempt before timing out
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
	$response = curl_exec($ch);
	$track[] = json_decode($response, true);
	curl_close($ch);
	
	// Get the number of plays for this track.
	$plays = $track[0]['track']['userplaycount'];
	if($plays > 0) {
		$playcount = $plays . ' Plays';
	}
	// Check if it's a loved song.
	$loved = '';
	if($track[0]['track']['userloved'] == 1) {
		$loved = '&nbsp;&nbsp;❤️';
	}
	// We are playing something, let's check if the song changes every 15 seconds.
	$duration = 15000;
}

// We are not currently playing a song, let's show some stats instead.
if($trackInfo['status'] != "Now Playing") {
	
	// Get information about the user.
	$endpoint = 'https://ws.audioscrobbler.com/2.0/?method=user.getinfo&user=' . $user . '&api_key=' . $key . '&format=json';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $endpoint);
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); // 0 for indefinite
	curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second attempt before timing out
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
	$response = curl_exec($ch);
	$stats[] = json_decode($response, true);
	curl_close($ch);
	
	$username = $stats[0]['user']['realname'];
	$playcount = number_format($stats[0]['user']['playcount']);
	
	// Get infromation about the last played albums.
	$endpoint = 'https://ws.audioscrobbler.com/2.0/?method=user.gettopalbums&user=' . $user . '&api_key=' . $key . '&period=7day&limit=12&format=json';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $endpoint);
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); // 0 for indefinite
	curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second attempt before timing out
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
	$response = curl_exec($ch);
	$albumcharts[] = json_decode($response, true);
	curl_close($ch);
	
	// Get the top 12 albums and their artwork.	
	$album_1 = $albumcharts[0]['topalbums']['album'][0]['image'][3]['#text'];
	$album_2 = $albumcharts[0]['topalbums']['album'][1]['image'][3]['#text'];
	$album_3 = $albumcharts[0]['topalbums']['album'][2]['image'][3]['#text'];
	$album_4 = $albumcharts[0]['topalbums']['album'][3]['image'][3]['#text'];
	$album_5 = $albumcharts[0]['topalbums']['album'][4]['image'][3]['#text'];
	$album_6 = $albumcharts[0]['topalbums']['album'][5]['image'][3]['#text'];
	$album_7 = $albumcharts[0]['topalbums']['album'][6]['image'][3]['#text'];
	$album_8 = $albumcharts[0]['topalbums']['album'][7]['image'][3]['#text'];
	$album_9 = $albumcharts[0]['topalbums']['album'][8]['image'][3]['#text'];
	$album_10 = $albumcharts[0]['topalbums']['album'][9]['image'][3]['#text'];
	$album_11 = $albumcharts[0]['topalbums']['album'][10]['image'][3]['#text'];
	$album_12 = $albumcharts[0]['topalbums']['album'][11]['image'][3]['#text'];
	
	// Not playing anything, so let's refresh every 30 seconds.
	$duration = 30000;
}
?>

<!DOCTYPE html>   
<!--[if lt IE 7 ]> <html lang="en" class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>    <html lang="en" class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>    <html lang="en" class="no-js ie8"> <![endif]-->
<!--[if IE 9 ]>    <html lang="en" class="no-js ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en" class="no-js"> <!--<![endif]-->
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title><?php echo $trackInfo['status']; ?> from Last.fm</title>
	<link rel="stylesheet" href="/static/css/normalize.min.css">
	<link rel="stylesheet" href="style.min.css?v1">
	<style>
		.artwork {
			background-image: url("<?php echo $trackInfo['albumArt']; ?>");
		}
	</style>
	<script>
		let playing = false;
	
		function requestUpdate() {
			let url = `https://ws.audioscrobbler.com/2.0/?api_key=<?php echo $key; ?>&method=user.getRecentTracks&user=<?php echo $user ?>&extended=1&limit=1&format=json`;

			let request = new Request(url, {
				"method": "GET",
			});

			return fetch(request);
		}
		
		function successHandler(value) {
			value.json().then(data => {
				//console.log(data);
				
				let track = data.recenttracks.track[0];
				let title = data.recenttracks.track[0].name;
				let old_title = document.querySelector(".track");

				console.log(title);
				console.log(old_title.innerText);

				if (track["@attr"] == undefined) {
					if (playing) {
						// Stop Playing
						console.log("Not playing anything.");
						playing = false;
						window.location.reload(1);
					}
				} else if (playing == false) {
					// Start Playing
					console.log("Now playing.");
					playing = true;
				} 
				if(title && old_title.innerText){
					if(title != old_title.innerText) {
						// Refresh page.
						window.location.reload(1);
					}
				}
			}, reason => {
		
			})
			setTimeout(tick, <?php echo $duration; ?>);
		}
		
		function failureHandler(reason) {
			console.log("Last.fm query failed:", reason);
			setTimeout(tick, 60000);
		}
		
		function tick() {
			let rq = requestUpdate();
			rq.then(successHandler, failureHandler);
			rq.catch(failureHandler);
		}
		
		(function() {
			tick();
		})();
	</script>
<?php if ($trackInfo['status'] == 'Now Playing') { ?>
</head>
<body>
	<div id="container">	
		<div class="artwork"></div>
		<section id="main">
			<img class="art_image" src="<?php echo $trackInfo['albumArt']; ?>" width="500" height="500">
			<div class="text">
				<div class="track"><?php echo $trackInfo['name']; ?></div>
				<div class="artist"><?php echo $trackInfo['artist']; ?></div>
				<div class="album"><?php echo $trackInfo['album']; ?></div>
				<div class="number"><?php echo $playcount; ?><?php echo $loved; ?></div>
			</div>		
		</section>		
	</div>
</body>
</html>
<?php } else { ?>
</head>
<body>
	<div id="container" class="last_played">	
			<div class="user"><?php echo $username; ?></div>
			<div class="stat_box">
				<div class="header">scrobbles</div>
				<div class="scrobbles"><?php echo $playcount; ?></div>
			</div>
			<div class="stat_box">
				<div class="header">last played</div>
				<div class="play_box">
				<div class="track"><?php echo $trackInfo['name']; ?></div>
				<div class="artist"><?php echo $trackInfo['artist']; ?></div>
				</div>
			</div>
			<div class="list">Top Albums - Last 7 Days</div>
			<div class="albums">
				<img class="top_albums" src="<?php echo $album_1; ?>">
				<img class="top_albums" src="<?php echo $album_2; ?>">
				<img class="top_albums" src="<?php echo $album_3; ?>">
				<img class="top_albums" src="<?php echo $album_4; ?>">
				<img class="top_albums" src="<?php echo $album_5; ?>">
				<img class="top_albums" src="<?php echo $album_6; ?>">
				<img class="top_albums" src="<?php echo $album_7; ?>">
				<img class="top_albums" src="<?php echo $album_8; ?>">
				<img class="top_albums" src="<?php echo $album_9; ?>">
				<img class="top_albums" src="<?php echo $album_10; ?>">
				<img class="top_albums" src="<?php echo $album_11; ?>">
				<img class="top_albums" src="<?php echo $album_12; ?>">
			</div>			
	</div>
</body>
</html>
<?php } ?>