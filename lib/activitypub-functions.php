<?php

if($config['subdir_install'] == true && $config['activitypub'] == true) {
	exit('For ActivityPub to work, you can\'t be running in a subdirectory, sadly.');
}

function ap_log($name, $data) {
	// file_put_contents(ROOT.DS.'inbox-log.txt', date('H:i:s ').$name.":\n".$data."\n\n", FILE_APPEND | LOCK_EX);
}

function activitypub_new_key($algo = 'sha512', $bits = 4096, $type = 'rsa') {
	global $db;

	$key_type = (mb_strtolower($type) == 'rsa') ? OPENSSL_KEYTYPE_RSA : $type; // todo: improve!

	$rsa = openssl_pkey_new([
		'digest_alg' => $algo,
		'private_key_bits' => $bits,
		'private_key_type' => $key_type
	]);
	openssl_pkey_export($rsa, $private_key);
	$public_key = openssl_pkey_get_details($rsa)['key'];
	$created = time();

	try {
		$statement = $db->prepare('INSERT INTO keys (key_private, key_public, key_algo, key_bits, key_type, key_created) VALUES (:private, :public, :algo, :bits, :type, :created)');

		$statement->bindValue(':private', $private_key, PDO::PARAM_STR);
		$statement->bindValue(':public', $public_key, PDO::PARAM_STR);
		$statement->bindValue(':algo', $algo, PDO::PARAM_STR);
		$statement->bindValue(':bits', $bits, PDO::PARAM_INT);
		$statement->bindValue(':type', mb_strtolower($type), PDO::PARAM_STR);
		$statement->bindValue(':created', $created, PDO::PARAM_INT);

		$statement->execute();

	} catch(PDOException $e) {
		ap_log('ERROR', $e->getMessage());
		return false;
	}

	if($db->lastInsertId() > 0) {
		return [
			'id' => $db->lastInsertId(),
			'key_private' => $private_key,
			'key_public' => $public_key,
			'key_algo' => $algo,
			'key_bits' => $bits,
			'key_type' => mb_strtolower($type),
			'key_created' => $created
		];
	}
	return false;
}

function activitypub_get_key($type = 'public') {
	global $db;
	
	$sql = '';

	if($type == 'public') {
		$sql = 'SELECT key_public FROM keys ORDER BY key_created DESC LIMIT 1';
	} elseif($type == 'private') {
		$sql = 'SELECT key_private FROM keys ORDER BY key_created DESC LIMIT 1';
	} else {
		$sql = 'SELECT * FROM keys ORDER BY key_created DESC LIMIT 1';
	}

	try {
		$statement = $db->prepare($sql);

		$statement->execute();
	} catch(PDOException $e) {
		ap_log('ERROR', $e->getMessage());
		return false;
	}

	$key = $statement->fetch(PDO::FETCH_ASSOC);

	if(!empty($key)) {
		if($type == 'public') {
			return $key['key_public'];
		} elseif($type == 'private') {
			return $key['key_private'];
		} else {
			return $key;
		}
	}
	
	return false;
}

function activitypub_get_actor_url($handle, $full_profile = false) {
	list($user, $host) = explode('@', ltrim($handle, '@'));

	$ch = curl_init();

	$url = sprintf('https://%s/.well-known/webfinger?resource=acct%%3A%s', $host, urlencode($user.'@'.$host));

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$server_response = curl_exec($ch);
	// ap_log('WEBFINGER RESPONSE', $server_response);

	curl_close($ch);

	$profile = json_decode($server_response, true);
	if($full_profile) {
		return $profile;
	}

	// make this more robust by iterating over links where href = self?
	return $profile['links'][1]['href'];
}

function activitypub_get_actor_data($actor_url='') {
	if(empty($actor_url)) return false;

	$opts = [
		"http" => [
			"method" => "GET",
			"header" => join("\r\n", [
				"Accept: application/activity+json",
				"Content-type: application/activity+json",
			])
		]
	];

	$context = stream_context_create($opts);

	$file = @file_get_contents($actor_url, false, $context); // fix?

	if(!empty($file)) {
		return json_decode($file, true);
	}

	return false;
}

function activitypub_plaintext($path, $host, $date, $digest, $type='application/activity+json'): string {
	$plaintext = sprintf(
		"(request-target): post %s\nhost: %s\ndate: %s\ndigest: %s\ncontent-type: %s",
		$path,
		$host,
		$date,
		$digest,
		$type
	);

	// ap_log('PLAINTEXT', $plaintext);

	return $plaintext;
}

function activitypub_digest(string $data): string {
	return sprintf('SHA-256=%s', base64_encode(hash('sha256', $data, true)));
}

function activitypub_sign($path, $host, $date, $digest): string {
	$private_key = activitypub_get_key('private');

	openssl_sign(activitypub_plaintext($path, $host, $date, $digest), $signature, openssl_get_privatekey($private_key), OPENSSL_ALGO_SHA256);

	return $signature;
}

function activitypub_verify(string $signature, string $pubkey, string $plaintext): bool {
	return openssl_verify($plaintext, base64_decode($signature), $pubkey, OPENSSL_ALGO_SHA256);
}

function activitypub_send_request($host, $path, $data): void {
	global $config;

	$encoded = json_encode($data);

	$date = gmdate('D, d M Y H:i:s T', time());
	$digest = activitypub_digest($encoded);

	$signature = activitypub_sign(
		$path,
		$host,
		$date,
		$digest
	);
	
	$signature_header = sprintf(
		'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date digest content-type",signature="%s"',
		$config['url'].'/actor#main-key',
		base64_encode($signature)
	);

	// DEBUG
	$fp = fopen(ROOT.DS.'inbox-log.txt', 'a');

	$curl_headers = [
		'Content-Type: application/activity+json',
		'Date: ' . $date,
		'Signature: ' . $signature_header,
		'Digest: ' . $digest
	];

	ap_log('SEND MESSAGE', json_encode([$data, $curl_headers], JSON_PRETTY_PRINT));

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, sprintf('https://%s%s', $host, $path));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, false);
	curl_setopt($ch, CURLOPT_STDERR, $fp);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$server_output = curl_exec($ch);

	curl_close($ch);
	fclose($fp);

	ap_log('SERVER RESPONSE', $server_output);
}

function activitypub_activity_from_post($post, $json=false) {
	global $config;

	if(empty($post)) return false;

	$output = [
		'@context' => 'https://www.w3.org/ns/activitystreams',

		'id' => $config['url'].'/'.$post['id'].'/json',
		'type' => 'Create',
		'actor' => $config['url'].'/actor',
		'to' => ['https://www.w3.org/ns/activitystreams#Public'],
		'cc' => [$config['url'].'/followers'],
		'object' => [
			'id' => $config['url'].'/'.$post['id'],
			'type' => 'Note',
			'published' => gmdate('Y-m-d\TH:i:s\Z', $post['post_timestamp']),
			'attributedTo' => $config['url'].'/actor',
			'content' => filter_tags($post['post_content']),
			'to' => ['https://www.w3.org/ns/activitystreams#Public']
		]
	];

	$attachments = db_get_attached_files($post['id']);

	if(!empty($attachments) && !empty($attachments[$post['id']])) {
		$output['object']['attachment'] = [];

		foreach ($attachments[$post['id']] as $key => $a) {
			if(strpos($a['file_mime_type'], 'image') !== 0) continue; // skip non-image files

			$url = $config['url'] .'/'. get_file_path($a);

			$output['object']['attachment'][] = [
				'type' => 'Image',
				'mediaType' => $a['file_mime_type'],
				'url' => $url,
				'name' => null
			];
		}
	}

	if ($json) {
		return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	return $output;
}

function activitypub_notify_followers($post_id): void {
	global $db;
	// todo: make this a queue

	// API ENDPOINTS
	// https://mastodon.social/api/v2/instance

	// users without shared inbox
	$statement = $db->prepare('SELECT * FROM followers WHERE follower_shared_inbox IS NULL');
	$statement->execute();
	$followers = $statement->fetchAll(PDO::FETCH_ASSOC);

	// users with shared inbox
	$statement = $db->prepare('SELECT follower_shared_inbox as shared_inbox, GROUP_CONCAT(follower_name) as shared_inbox_followers FROM followers WHERE follower_shared_inbox IS NOT NULL GROUP BY follower_shared_inbox');
	$statement->execute();
	$shared_inboxes = $statement->fetchAll(PDO::FETCH_ASSOC);

	// get the activity data, eg. https://microblog.oelna.de/11/json
	$post = db_select_post($post_id);
	$post_activity = activitypub_activity_from_post($post);

	$update = [
		'id' => null,
		'inbox' => null,
		'actor' => null
	];

	// prepare db for possible updates
	$statement = $db->prepare('UPDATE followers SET follower_inbox = :inbox, follower_actor = :actor WHERE id = :id');
	$statement->bindParam(':id', $update['id'], PDO::PARAM_INT);
	$statement->bindParam(':inbox', $update['inbox'], PDO::PARAM_STR);
	$statement->bindParam(':actor', $update['actor'], PDO::PARAM_STR);

	// iterate over shared inboxes to deliver those quickly
	foreach($shared_inboxes as $inbox) {
		$info = parse_url($inbox['shared_inbox']);
		// ap_log('SHARED_INBOX_DELIVERY', json_encode([$inbox, $info, $post_activity], JSON_PRETTY_PRINT));
		// todo: verify we don't need to handle single usernames here
		// using the followers URL as CC is enough?
		activitypub_send_request($info['host'], $info['path'], $post_activity);
	}

	// iterate over followers and send create activity
	foreach($followers as $follower) {

		// retrieve actor info, if missing (is this necessary?)
		if(empty($follower['follower_inbox'])) {

			$actor_url = activitypub_get_actor_url($follower['follower_name'].'@'.$follower['follower_host']);
			if (empty($actor_url)) continue;

			$actor_data = activitypub_get_actor_data($actor_url);
			if (empty($actor_data) || empty($actor_data['inbox'])) continue;

			// cache this info
			$update['id'] = $follower['id'];
			$update['inbox'] = $actor_data['inbox'];
			$update['actor'] = $actor_url;

			try {
				$statement->execute();
			} catch(PDOException $e) {
				continue;
			}

			$follower['follower_inbox'] = $actor_data['inbox'];
		}

		$info = parse_url($follower['follower_inbox']);

		activitypub_send_request($info['host'], $info['path'], $post_activity);

		ap_log('SENDING TO', json_encode([$info['host'], $info['path']], JSON_PRETTY_PRINT));
	}
}

function activitypub_post_from_url($url="") {
	// todo: this should be more robust and conform to url scheme on this site

	$path = parse_url($url, PHP_URL_PATH);

	$items = explode('/', $path);
	$post_id = end($items);

	if (is_numeric($post_id)) {
		return (int) $post_id;
	}

	return false;
}

function activitypub_do($type, $user, $host, $post_id) {
	if (empty($type)) return false;

	global $db;

	$activity = [
		'actor_name' => $user,
		'actor_host' => $host,
		'type' => (mb_strtolower($type) == 'like') ? 'like' : 'announce',
		'object_id' => (int) $post_id,
		'updated' => time()
	];

	try {
		$statement = $db->prepare('INSERT OR IGNORE INTO activities (activity_actor_name, activity_actor_host, activity_type, activity_object_id, activity_updated) VALUES (:actor_name, :actor_host, :type, :object_id, :updated)');

		$statement->bindValue(':actor_name', $activity['actor_name'], PDO::PARAM_STR);
		$statement->bindValue(':actor_host', $activity['actor_host'], PDO::PARAM_STR);
		$statement->bindValue(':type', $activity['type'], PDO::PARAM_STR);
		$statement->bindValue(':object_id', $activity['object_id'], PDO::PARAM_INT);
		$statement->bindValue(':updated', $activity['updated'], PDO::PARAM_INT);

		$statement->execute();

	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		ap_log('ERROR', $e->getMessage());
		return false;
	}

	ap_log('INSERTED ACTIVITY', json_encode([$activity, $db->lastInsertId()], JSON_PRETTY_PRINT));
	return $db->lastInsertId();
}

function activitypub_undo($type, $user, $host, $post_id) {
	if (empty($type)) return false;

	global $db;

	$activity = [
		'actor_name' => $user,
		'actor_host' => $host,
		'type' => (mb_strtolower($type) == 'like') ? 'like' : 'announce', // todo: make this safer
		'object_id' => (int) $post_id
	];
	try {
		$statement = $db->prepare('DELETE FROM activities WHERE activity_actor_name = :actor_name AND activity_actor_host = :actor_host AND activity_type = :type AND activity_object_id = :object_id');
		$statement->bindValue(':actor_name', $activity['actor_name'], PDO::PARAM_STR);
		$statement->bindValue(':actor_host', $activity['actor_host'], PDO::PARAM_STR);
		$statement->bindValue(':type', $activity['type'], PDO::PARAM_STR);
		$statement->bindValue(':object_id', $activity['object_id'], PDO::PARAM_INT);

		$statement->execute();
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		ap_log('ERROR', $e->getMessage());
		return false;
	}

	ap_log('SQL DELETE', json_encode([$statement->rowCount()]));
	return true;
	return $statement->rowCount();
}

function activitypub_update_post($post_id) {
	// https://www.w3.org/TR/activitypub/#update-activity-inbox
}

function activitypub_delete_user($name, $host) {
	if(empty($name) || empty($host)) return false;

	global $db;

	// delete all records of user as follower
	try {
		$statement = $db->prepare('DELETE FROM followers WHERE follower_name = :actor_name AND follower_host = :actor_host');
		$statement->bindValue(':actor_name', $name, PDO::PARAM_STR);
		$statement->bindValue(':actor_host', $host, PDO::PARAM_STR);

		$statement->execute();
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		ap_log('ERROR', $e->getMessage());
		return false;
	}

	// remove likes and boosts
	try {
		$statement = $db->prepare('DELETE FROM activities WHERE activity_actor_name = :actor_name AND activity_actor_host = :actor_host');
		$statement->bindValue(':actor_name', $name, PDO::PARAM_STR);
		$statement->bindValue(':actor_host', $host, PDO::PARAM_STR);

		$statement->execute();
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		ap_log('ERROR', $e->getMessage());
		return false;
	}

	return true;
}

function activitypub_get_post_stats($type="like", $post_id=null) {
	global $db;
	if(empty($db)) return false;
	if(empty($post_id)) return false;

	// normalize type input, liberally
	if(in_array($type, ['announce', 'announced', 'boost', 'boosts', 'boosted'])) $type = 'announce';
	if($type == 'both' || $type == 'all') $type = 'both';
	if($type !== 'both' && $type !== 'announce') $type = 'like';

	$type_clause = 'activity_type = "like"';
	if($type == 'both') {
		$type_clause = '(activity_type = "like" OR activity_type = "announce")';
	} elseif($type == 'announce') {
		$type_clause = 'activity_type = "announce"';
	}

	$sql = 'SELECT activity_type, COUNT(id) AS amount FROM activities WHERE activity_object_id = :post_id AND '.$type_clause.' GROUP BY activity_type ORDER BY activity_type ASC';

	try {
		$statement = $db->prepare($sql);
		$statement->bindValue(':post_id', (int) $post_id, PDO::PARAM_INT);
		$statement->execute();
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		return false;
	}

	$return = [
		'announce' => 0,
		'like' => 0
	];

	if(!empty($rows)) {
		foreach ($rows as $row) {
			if($row['activity_type'] == 'announce') {
				$return['announce'] = (int) $row['amount'];
			} else if($row['activity_type'] == 'like') {
				$return['like'] = (int) $row['amount'];
			}
		}
	}

	if($type == 'both') {
		return $return;
	} elseif($type == 'announce') {
		unset($return['like']);
		return $return;
	} else {
		unset($return['announce']);
		return $return;
	}

	return $return;
}
