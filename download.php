<?php

require "assets/init.php";
require 'assets/libs/smartRead.php';

set_time_limit(0);
error_reporting(0);

if (empty($_GET['id'])) {
	exit('Empty ID, which track though?');
}

$getSong = $db->where('audio_id', secure($_GET['id']))->getOne(T_SONGS);

if (empty($getSong)) {
	exit('File not found.');
}



if (!empty($_SESSION['d_session_hash']) && !empty($_GET['hash']) && !empty($_GET['download'])) {
	if ($_SESSION['d_session_hash'] == $_GET['hash']) {
		$fingerprint = 0;
		$user_id = 0;
		if (IS_LOGGED) {
			$user_id = $user->id;
		}
		if (!empty($_SESSION['fingerPrint'])) {
			$fingerprint = $_SESSION['fingerPrint'];
		}
		$insert_data = [
			'track_id' => $getSong->id,
			'fingerprint' => $fingerprint,
			'user_id' => $user_id,
			'time' => time()
		];
		if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == "on" || $music->config->spaces == "on" || $music->config->google_drive == "on" || $music->config->wasabi_storage == "on" || $music->config->backblaze_storage == "on") {
			$found = $db->where('track_id',$getSong->id)->where('expire',0,'!=')->getOne(T_DOWNLOADS);
			if (!empty($found)) {
				$db->where('id',$found->id)->update(T_DOWNLOADS,array(
					'expire' => (time() + (60 * 60 * 24))
				));
			}
			else{
				$insert_data['expire'] = (time() + (60 * 60 * 24));
			}
		}
		$insertDownload = $db->insert(T_DOWNLOADS, $insert_data);
		session_write_close();
		$getSong->audio_location = $getSong->audio_location;
		if (!empty($getSong->demo_track) && !isTrackPurchased($getSong->id) && !empty($getSong->price)) {
			if (IS_LOGGED == true) {
				if ($getSong->user_id != $user->id) {
					$getSong->audio_location = $getSong->demo_track;
				}
			} else {
				$getSong->audio_location = $getSong->demo_track;
			}
		}
		runPlugin('OnSongDownload', ["id" => $getSong->id]);
		smartReadFile($getSong->audio_location, html_entity_decode($getSong->title, ENT_QUOTES) . '.mp3', 'audio/mpeg');
		exit();
	} else {
		exit('Please click on download link again, this link is expired');
	}
}

$getSong = $db->where('audio_id', secure($_GET['id']))->getOne(T_SONGS);

$can_download = false;
$notPurchased = false;
$isPurchased = isTrackPurchased($getSong->id);
// if (IS_LOGGED) {
// 	if($getSong->owner == true || isAdmin()) {
// 		$can_download = true;
//  	} else {
//  		if ($getSong->price > 0) {
// 	 		if ($isPurchased) {
// 		 		$can_download = true;
// 		 	} else {
// 		 		$notPurchased = true;
// 		 	}
// 	 	}
// 	 	if ($music->config->go_pro == 'on') {
// 	 		if ($user->is_pro == 1) {
// 	 			$can_download = true;
// 	 		}
// 	 	} else if ($notPurchased == false) {
// 	 		$can_download = true;
// 	 	}
//  	}
// }
if (IS_LOGGED) {
	if($getSong->owner == true || isAdmin()) {
		$can_download = true;
 	} else {
 		if ($music->config->can_use_download) {
	 		$can_download = true;
	 	}
	 	else{
	 		$can_download = false;
	 	}
 		if ($getSong->price > 0) {
	 		if ($isPurchased) {
		 		$can_download = true;
		 	} else {
		 		$can_download = false;
		 	}
	 	}
 	}
}

if ($can_download == true && empty($_GET['hash'])) {
	$_SESSION['d_session_hash'] = md5(rand());
	$loc = '';
    RecordUserActivities('track_download',array('audio_id' => $getSong->audio_id));
	if ($music->config->s3_upload == 'on' || $music->config->ftp_upload == "on" || $music->config->spaces == "on" || $music->config->google_drive == "on" || $music->config->wasabi_storage == "on" || $music->config->backblaze_storage == "on") {
        $loc = getMedia($getSong->audio_location);
        $loc = SaveStorageVideo($loc,$getSong->audio_location);
        $loc = $site_url . '/download.php?id='. $getSong->audio_id . '&hash=' . $_SESSION['d_session_hash'] . '&download=true';
	} else {
        $loc = $site_url . '/download.php?id='. $getSong->audio_id . '&hash=' . $_SESSION['d_session_hash'] . '&download=true';
	}
    header("Location: $loc");
    exit();
	
}
exit("You can't access this item");
