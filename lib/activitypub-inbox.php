<?php

	// https://paul.kinlan.me/adding-activity-pub-to-your-static-site/
	// https://bovine.readthedocs.io/en/latest/tutorial_server.html
	// https://github.com/timmot/activity-pub-tutorial
	// https://magazine.joomla.org/all-issues/february-2023/turning-the-joomla-website-into-an-activitypub-server
	// https://codeberg.org/mro/activitypub/src/commit/4b1319d5363f4a836f23c784ef780b81bc674013/like.sh#L101

	// todo: handle account moves
	// https://seb.jambor.dev/posts/understanding-activitypub/

	if(!$config['activitypub']) exit('ActivityPub is disabled via config file.');

	$postdata = file_get_contents('php://input');

	if(!empty($postdata)) {

		$data = json_decode($postdata, true);
		$inbox = parse_url($config['url'].'/inbox');

		$request = [
			'host' => $inbox['host'],
			'path' => $inbox['path'],
			'digest' => $_SERVER['HTTP_DIGEST'],
			'date' => $_SERVER['HTTP_DATE'],
			'length' => $_SERVER['CONTENT_LENGTH'],
			'type' => $_SERVER['CONTENT_TYPE']
		];

		header('Content-Type: application/ld+json');

		ap_log('POSTDATA', $postdata);
		// ap_log('REQUEST', json_encode($request));

		// verify message digest
		$digest_verify = activitypub_digest($postdata);
		if($digest_verify === $request['digest']) {
			// ap_log('DIGEST', 'Passed verification for ' . $digest_verify);
		} else {
			ap_log('ERROR', json_encode(['digest verification failed!', $request['digest'], $digest_verify], JSON_PRETTY_PRINT));
		}

		// GET ACTOR DETAILS
		if(!empty($data) && !empty($data['actor'])) {
			$actor = activitypub_get_actor_data($data['actor']);

			if(!empty($actor)) {
				$actor_key = $actor['publicKey'];
				$info = parse_url($actor['inbox']);
			} else {
				exit('could not parse actor data');
			}
		} else {
			exit('no actor provided');
		}

		$signature = [];
		$signature_string = $_SERVER['HTTP_SIGNATURE'];
		$parts = explode(',', stripslashes($signature_string));
		foreach ($parts as $part) {
			$part = trim($part, '"');
			list($k, $v) = explode('=', $part);
			$signature[$k] = trim($v, '"');
		}

		// ap_log('SIGNATURE', json_encode($signature));
		// ap_log('ACTOR', json_encode($actor));
		// ap_log('PUBLIC KEY', str_replace("\n", '\n', $actor_key['publicKeyPem']));

		$plaintext = activitypub_plaintext($request['path'], $request['host'], $request['date'], $request['digest'], $request['type']);

		// verify request signature
		$result = activitypub_verify($signature['signature'], $actor_key['publicKeyPem'], $plaintext);

		if($result != 1) {
			ap_log('REQUEST', json_encode($request));
			ap_log('SIGNATURE', json_encode($signature));
			ap_log('PUBLIC KEY', str_replace("\n", '\n', $actor_key['publicKeyPem']));
			ap_log('RESULT', json_encode([$result, $plaintext], JSON_PRETTY_PRINT));
			ap_log('SSL ERROR', 'message signature did not match');
			exit('message signature did not match');
		} else {
			ap_log('SSL OKAY', json_encode([$request, $signature, $result, $plaintext, $actor_key['publicKeyPem']], JSON_PRETTY_PRINT));
		}

		// message signature was ok, now handle the request

		if(!empty($data['type'])) {
			if(mb_strtolower($data['type']) == 'follow') {
				// follow

				$accept_data = [
					'@context' => 'https://www.w3.org/ns/activitystreams',
					'id' => sprintf('%s/activity/%s', $config['url'], uniqid()),
					'type' => 'Accept',
					'actor' => sprintf('%s/actor', $config['url']),
					'object' => $data
				];

				// send back Accept activity
				activitypub_send_request($info['host'], $info['path'], $accept_data);

				$now = time();
				$follower = [
					'name' => $actor['preferredUsername'],
					'host' => $info['host'],
					'actor' => $data['actor'],
					'inbox' => $actor['inbox'],
					'added' => time()
				];
				try {
					$statement = $db->prepare('INSERT OR IGNORE INTO followers (follower_name, follower_host, follower_actor, follower_inbox, follower_shared_inbox, follower_added) VALUES (:follower_name, :follower_host, :follower_actor, :follower_inbox, :follower_shared_inbox, :follower_added)');

					$statement->bindValue(':follower_name', $follower['name'], PDO::PARAM_STR);
					$statement->bindValue(':follower_host', $follower['host'], PDO::PARAM_STR);
					$statement->bindValue(':follower_actor', $follower['actor'], PDO::PARAM_STR);
					$statement->bindValue(':follower_inbox', $follower['inbox'], PDO::PARAM_STR);
					$statement->bindValue(':follower_added', $follower['added'], PDO::PARAM_INT);

					// store shared inbox if possible
					if(!empty($actor['endpoints']) && !empty($actor['endpoints']['sharedInbox'])) {
						$statement->bindValue(':follower_shared_inbox', $actor['endpoints']['sharedInbox'], PDO::PARAM_STR);
					} else {
						$statement->bindValue(':follower_shared_inbox', null, PDO::PARAM_NULL);
					}

					$statement->execute();

				} catch(PDOException $e) {
					print 'Exception : '.$e->getMessage();
					ap_log('ERROR FOLLOWING', $e->getMessage());
				}

				ap_log('FOLLOW', json_encode([$actor['inbox'], $info, $accept_data], JSON_PRETTY_PRINT));
			
			} elseif(mb_strtolower($data['type']) == 'like') {
				// like/favorite
				ap_log('LIKE', json_encode([$actor['inbox'], $info, $data], JSON_PRETTY_PRINT));
				$post_id = activitypub_post_from_url($data['object']);
				activitypub_do('like', $actor['preferredUsername'], $info['host'], $post_id);
			} elseif(mb_strtolower($data['type']) == 'announce') {
				// boost
				ap_log('ANNOUNCE/BOOST', json_encode([$actor['inbox'], $info, $data], JSON_PRETTY_PRINT));
				$post_id = activitypub_post_from_url($data['object']);
				activitypub_do('announce', $actor['preferredUsername'], $info['host'], $post_id);
			} elseif(mb_strtolower($data['type']) == 'undo') {
				if(mb_strtolower($data['object']['type']) == 'follow') {
					// undo follow

					ap_log('UNDO FOLLOW', json_encode([$plaintext]));

					// remove from db
					$follower = [
						'name' => $actor['preferredUsername'],
						'host' => $info['host']
					];

					try {
						$statement = $db->prepare('DELETE FROM followers WHERE follower_name = :name AND follower_host = :host');
						$statement->bindValue(':name', $follower['name'], PDO::PARAM_STR);
						$statement->bindValue(':host', $follower['host'], PDO::PARAM_STR);

						$statement->execute();
					} catch(PDOException $e) {
						print 'Exception : '.$e->getMessage();
						ap_log('ERROR UNFOLLOWING', $e->getMessage());
					}
				
				} elseif(mb_strtolower($data['object']['type']) == 'like') {
					// undo like
					$post_id = activitypub_post_from_url($data['object']['object']);
					activitypub_undo('like', $actor['preferredUsername'], $info['host'], $post_id);
					ap_log('UNDO LIKE', json_encode([$actor['inbox'], $info, $data], JSON_PRETTY_PRINT));
				} elseif(mb_strtolower($data['object']['type']) == 'announce') {
					// undo boost
					$post_id = activitypub_post_from_url($data['object']['object']);
					activitypub_undo('announce', $actor['preferredUsername'], $info['host'], $post_id);
					ap_log('UNDO ANNOUNCE/BOOST', json_encode([$actor['inbox'], $info, $data], JSON_PRETTY_PRINT));
				}
			} elseif(mb_strtolower($data['type']) == 'delete') {
				// user is to be deleted and all references removed or replaced by Tombstone
				// https://www.w3.org/TR/activitypub/#delete-activity-inbox
				ap_log('DELETE 1', json_encode(['trying to delete', $data]));
				activitypub_delete_user($actor['preferredUsername'], $info['host']);
				ap_log('DELETE 2', json_encode([$actor['preferredUsername'], $info['host']]));
			}
		}

	} else {

		if(file_exists(ROOT.DS.'inbox-log.txt')) {
			echo(nl2br(file_get_contents(ROOT.DS.'inbox-log.txt')));
		} else {
			echo('no inbox activity');
		}
	}
