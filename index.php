<?php
session_start();

// Constants
define('LASTFM_API_KEY', 'yourapikey');
define('LASTFM_USER', 'yourusername');
define('CACHE_DURATION', 300); // 5 minutes

// Helper functions
function fetchFromLastFM($endpoint) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $endpoint);
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	$response = curl_exec($ch);
	curl_close($ch);
	return json_decode($response, true);
}

function titleCase($string) {
	$minorWords = ['a', 'an', 'and', 'as', 'at', 'but', 'by', 'en', 'for', 'if', 'in', 'of', 'on', 'or', 'per', 'the', 'to', 'via'];
	$pieces = preg_split('/(\s+|[(){}[\]<>.,;!?])/u', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
	foreach ($pieces as $key => $word) {
		if (ctype_alpha($word) && (!in_array(mb_strtolower($word), $minorWords) || $key === 0 || $key === count($pieces) - 1)) {
			$pieces[$key] = ucfirst(mb_strtolower($word));
		} else {
			$pieces[$key] = mb_strtolower($word);
		}
	}
	return implode('', $pieces);
}

function getCachedData($cacheKey, $fetchFunction) {
	if (isset($_SESSION[$cacheKey]) && time() - $_SESSION[$cacheKey]['timestamp'] < CACHE_DURATION) {
		return $_SESSION[$cacheKey]['data'];
	}

	$data = $fetchFunction();
	$_SESSION[$cacheKey] = ['data' => $data, 'timestamp' => time()];
	return $data;
}

// API Endpoints
$recentTracksEndpoint = "https://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=" . LASTFM_USER . "&limit=2&api_key=" . LASTFM_API_KEY . "&format=json";
$userInfoEndpoint = "https://ws.audioscrobbler.com/2.0/?method=user.getinfo&user=" . LASTFM_USER . "&api_key=" . LASTFM_API_KEY . "&format=json";
$topAlbumsEndpoint = "https://ws.audioscrobbler.com/2.0/?method=user.gettopalbums&user=" . LASTFM_USER . "&api_key=" . LASTFM_API_KEY . "&period=7day&limit=12&format=json";

// Fetch recent tracks
$recentTracks = fetchFromLastFM($recentTracksEndpoint);
$currentTrack = $recentTracks['recenttracks']['track'][0] ?? null;

// Track info
$isNowPlaying = isset($currentTrack['@attr']['nowplaying']);
$trackInfo = [
	'name' => titleCase($currentTrack['name'] ?? ''),
	'album' => titleCase($currentTrack['album']['#text'] ?? ''),
	'artist' => titleCase($currentTrack['artist']['#text'] ?? ''),
	'albumArt' => str_replace('300x300', '600x600', $currentTrack['image'][3]['#text'] ?? 'artwork-placeholder.png'),
	'status' => $isNowPlaying ? 'Now Playing' : 'Last Played',
	'playcount' => '',
	'loved' => false,
];

// Fetch play count and loved status if playing
if ($isNowPlaying) {
	$trackInfoEndpoint = "https://ws.audioscrobbler.com/2.0/?method=track.getInfo&username=" . LASTFM_USER . "&artist=" . urlencode($trackInfo['artist']) . "&track=" . urlencode($trackInfo['name']) . "&api_key=" . LASTFM_API_KEY . "&format=json";
	$trackDetails = fetchFromLastFM($trackInfoEndpoint);
	$trackInfo['playcount'] = $trackDetails['track']['userplaycount'] ?? 0;
	$trackInfo['loved'] = ($trackDetails['track']['userloved'] ?? 0) == 1;
}

// Fetch and cache top albums
$topAlbums = getCachedData('top_albums', function () use ($topAlbumsEndpoint) {
	return fetchFromLastFM($topAlbumsEndpoint)['topalbums']['album'] ?? [];
});
?>

<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title><?php echo $trackInfo['status']; ?> from Last.fm</title>
	<link rel="stylesheet" href="/static/css/normalize.min.css">
	<link rel="stylesheet" href="style.min.css?v1">
	<style>
		.artwork { background-image: url("<?php echo $trackInfo['albumArt']; ?>"); }
	</style>
	<script>
		let playing = <?php echo $isNowPlaying ? 'true' : 'false'; ?>;
		let pollInterval = playing ? 10000 : 30000; // 10 seconds if playing, 30 seconds if idle

		async function fetchTrackData() {
			try {
				const response = await fetch('<?php echo $recentTracksEndpoint; ?>');
				const data = await response.json();
				const tracks = data.recenttracks.track;
		
				const nowPlaying = tracks[0] && tracks[0]["@attr"] && tracks[0]["@attr"].nowplaying;
				const trackName = tracks[0]?.name || 'Unknown Track';
				const artistName = tracks[0]?.artist['#text'] || 'Unknown Artist';
		
				if (nowPlaying) {
					updatePlayingTrack(tracks[0]);
					pollInterval = 10000; // Check every 10 seconds
				} else if (
					currentTrackCache.name === trackName &&
					currentTrackCache.artist === artistName &&
					currentTrackCache.endTime &&
					Date.now() < currentTrackCache.endTime
				) {
					// Assume the track is paused or scrobbled prematurely; keep "Now Playing"
					console.log('Track might be paused; keeping Now Playing.');
					pollInterval = 10000; // Poll more frequently
				} else {
					const lastPlayedTrack = tracks[0]; // Use the first track if nothing is playing
					updateIdleState(lastPlayedTrack);
					pollInterval = 30000; // Switch to idle state polling
				}
			} catch (error) {
				console.error('Error fetching track data:', error);
			} finally {
				setTimeout(fetchTrackData, pollInterval);
			}
		}

		let currentTrackCache = { name: '', artist: '', endTime: null };
		
		function updatePlayingTrack(track) {
			const trackName = track.name || 'Unknown Track';
			const artistName = track.artist['#text'] || 'Unknown Artist';
		
			// Check if the track has changed
			if (currentTrackCache.name === trackName && currentTrackCache.artist === artistName) {
				return; // No need to fetch details again
			}
		
			currentTrackCache = { name: trackName, artist: artistName, endTime: null };
		
			if (!document.querySelector('.art_image')) {
				renderNowPlayingLayout();
			}
			
			document.title = `${trackName} by ${artistName} - Now Playing`;
		
			const albumName = track.album['#text'] || 'Unknown Album';
			const albumArt = track.image[3]['#text'] || 'artwork-placeholder.png';
		
			document.querySelector('.track').textContent = trackName;
			document.querySelector('.artist').textContent = artistName;
			document.querySelector('.album').textContent = albumName;
			document.querySelector('.art_image').src = albumArt;
		
			fetchTrackDetails(artistName, trackName);
		}
		
		async function fetchTrackDetails(artist, track) {
			try {
				const response = await fetch(`https://ws.audioscrobbler.com/2.0/?method=track.getInfo&username=<?php echo LASTFM_USER; ?>&artist=${encodeURIComponent(artist)}&track=${encodeURIComponent(track)}&api_key=<?php echo LASTFM_API_KEY; ?>&format=json`);
				const data = await response.json();
		
				const playcount = data.track?.userplaycount || 0;
				const loved = data.track?.userloved === "1";
				const durationMs = parseInt(data.track?.duration, 10); // Duration in milliseconds
		
				const playcountElement = document.querySelector('.number');
				if (playcountElement) {
					playcountElement.textContent = `${playcount.toLocaleString()} Plays${loved ? ' ❤️' : ''}`;
				}
		
				// Calculate end time if duration is available
				if (durationMs > 0) {
					const currentTime = Date.now();
					currentTrackCache.endTime = currentTime + durationMs;
				}
			} catch (error) {
				console.error('Error fetching track details:', error);
			}
		}
		
		function updateTrackDetails({ playcount, loved }) {
			const playcountElement = document.querySelector('.number');
			if (playcountElement) {
				playcountElement.textContent = `${playcount.toLocaleString()} Plays${loved ? ' ❤️' : ''}`;
			}
		}

		async function updateIdleState(lastPlayedTrack) {
			if (!document.querySelector('.stat_box.last_played')) {
				renderIdleLayout();
			}

			const trackName = lastPlayedTrack.name || 'Unknown Track';
			const artistName = lastPlayedTrack.artist['#text'] || 'Unknown Artist';

			document.title = `Last Played: ${trackName} by ${artistName}`;

			document.querySelector('.last_played .track').textContent = trackName;
			document.querySelector('.last_played .artist').textContent = artistName;

			try {
				const response = await fetch('<?php echo $userInfoEndpoint; ?>');
				const data = await response.json();
				const username = data.user.realname || 'Unknown User';
				const totalScrobbles = parseInt(data.user.playcount || 0, 10);

				const userElement = document.querySelector('.user');
				const scrobblesElement = document.querySelector('.scrobbles');

				if (userElement) {
					userElement.textContent = username;
				}

				if (scrobblesElement) {
					scrobblesElement.textContent = totalScrobbles.toLocaleString();
				}
			} catch (error) {
				console.error('Error fetching user info:', error);
			}
		}

		function renderNowPlayingLayout() {
			const container = document.getElementById('container');
			container.innerHTML = `
				<div class="artwork"></div>
				<section id="main">
					<img class="art_image" src="" width="500" height="500">
					<div class="text">
						<div class="track">Unknown Track</div>
						<div class="artist">Unknown Artist</div>
						<div class="album">Unknown Album</div>
						<div class="number">0 Plays</div>
					</div>
				</section>
			`;
		}

		function renderIdleLayout() {
			const container = document.getElementById('container');
			container.innerHTML = `
				<div class="user">Loading...</div>
				<div class="stat_box">
					<div class="header">Scrobbles</div>
					<div class="scrobbles">Loading...</div>
				</div>
				<div class="stat_box last_played">
					<div class="header">Last Played</div>
					<div class="play_box">
						<div class="track">Unknown Track</div>
						<div class="artist">Unknown Artist</div>
					</div>
				</div>
				<div class="list">Top Albums - Last 7 Days</div>
				<div class="albums">
					<?php foreach ($topAlbums as $album): ?>
						<img class="top_albums" src="<?php echo $album['image'][3]['#text'] ?? 'artwork-placeholder.png'; ?>">
					<?php endforeach; ?>
				</div>
			`;
		}

		document.addEventListener("DOMContentLoaded", fetchTrackData);
	</script>
</head>
<body>
	<div id="container"></div>
</body>
</html>