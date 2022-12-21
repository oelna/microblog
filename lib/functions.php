<?php

function path($fragment=null) {
	global $config;
	if($fragment === null) return $config['path'];
	return (!empty($config['path'][$fragment])) ? $config['path'][$fragment] : false;
}

function check_login() {
	global $config;

	if(isset($_COOKIE['microblog_login'])) {
		if($_COOKIE['microblog_login'] === sha1($config['url'].$config['admin_pass'])) {
			// correct auth data, extend cookie life
			$domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
			setcookie('microblog_login', sha1($config['url'].$config['admin_pass']), NOW+$config['cookie_life'], '/', $domain, false);

			return true;
		} else {
			// invalid cookie data
			unset($_COOKIE['microblog_login']);
			setcookie('microblog_login', '', time()-3600, '/', $domain, false);
		}
	}

	return false;
}

function db_insert($content, $timestamp=NOW) {
	global $db;
	if(empty($db)) return false;

	$statement = $db->prepare('INSERT INTO posts (post_content, post_timestamp, post_guid) VALUES (:post_content, :post_timestamp, :post_guid)');
	$statement->bindValue(':post_content', $content, PDO::PARAM_STR);
	$statement->bindValue(':post_timestamp', $timestamp, PDO::PARAM_INT);
	$statement->bindValue(':post_guid', uuidv4(), PDO::PARAM_STR);

	$statement->execute();

	return $db->lastInsertId();
}

function db_delete($post_id, $undelete=false) {
	global $db;
	if(empty($db)) return false;
	if(!is_numeric($post_id) || $post_id <= 0) return false;

	/*
	$statement = $db->prepare('DELETE FROM posts WHERE id = :id');
	$statement->bindParam(':id', $post_id, PDO::PARAM_INT);
	*/

	// delete or undelete/restore
	$post_deleted = !$undelete ? time() : null;
	$type = !$undelete ? PDO::PARAM_INT : PDO::PARAM_NULL;

	// mark as deleted instead (for undo?!)
	$statement = $db->prepare('UPDATE posts SET post_deleted = :post_deleted WHERE id = :id');
	$statement->bindValue(':id', $post_id, PDO::PARAM_INT);
	$statement->bindValue(':post_deleted', $post_deleted, $type);

	$statement->execute();

	return $statement->rowCount();
}

function db_update($post_id, $content, $timestamp=null) {
	global $db;
	if(empty($db)) return false;
	if(empty($content)) return false;
	if(!is_numeric($post_id) || $post_id <= 0) return false;

	if($timestamp !== null) {
		$statement = $db->prepare('UPDATE posts SET post_content = :post_content, post_edited = :post_edited, post_timestamp = :post_timestamp WHERE id = :id');
		$statement->bindValue(':post_timestamp', $timestamp, PDO::PARAM_INT);
	} else {
		$statement = $db->prepare('UPDATE posts SET post_content = :post_content, post_edited = :post_edited WHERE id = :id');
	}
	$statement->bindValue(':id', $post_id, PDO::PARAM_INT);
	$statement->bindValue(':post_content', $content, PDO::PARAM_STR);
	$statement->bindValue(':post_edited', time(), PDO::PARAM_INT);

	$statement->execute();

	return $statement->rowCount();
}

function db_select_post($id=0) {
	global $db;
	if(empty($db)) return false;
	if($id === 0) return false;

	$statement = $db->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
	$statement->bindValue(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	$row = $statement->fetch(PDO::FETCH_ASSOC);

	return (!empty($row)) ? $row : false;
}

function db_get_attached_files($post_id, $include_deleted=false) {
	global $db;
	if(empty($db)) return false;

	$rows = [];

	if($include_deleted) {
		$sql = 'SELECT f.* FROM files f LEFT JOIN file_to_post p WHERE f.id = p.file_id AND p.post_id = :post_id ORDER BY f.file_timestamp ASC';
	} else {
		$sql = 'SELECT f.* FROM files f LEFT JOIN file_to_post p WHERE f.id = p.file_id AND p.post_id = :post_id AND p.deleted IS NULL ORDER BY f.file_timestamp ASC';
	}

	try {
		$statement = $db->prepare($sql);
		$statement->bindValue(':post_id', $post_id, PDO::PARAM_INT);
		
		$statement->execute();

		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
	} catch(PDOException $e) {
		// print 'Exception : '.$e->getMessage();
		return false;
	}

	return (!empty($rows)) ? $rows : false;
}

function db_select_posts($from, $amount=10, $sort='desc', $offset=0) {
	global $db;
	if(empty($db)) return false;
	if(empty($from)) $from = time();
	if($sort !== 'desc') $sort = 'asc';

	$statement = $db->prepare('SELECT * FROM posts WHERE post_timestamp < :post_timestamp AND post_deleted IS NULL ORDER BY post_timestamp '.$sort.' LIMIT :limit OFFSET :page');
	$statement->bindValue(':post_timestamp', $from, PDO::PARAM_INT);
	$statement->bindValue(':limit', $amount, PDO::PARAM_INT);
	$statement->bindValue(':page', $offset, PDO::PARAM_INT);
	$statement->execute();
	$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

	return (!empty($rows)) ? $rows : false;
}

function db_posts_count() {
	global $config;
	global $db;
	if(empty($db)) return false;

	$statement = $db->prepare('SELECT COUNT(*) AS posts_count FROM posts WHERE post_deleted IS NULL');
	$statement->execute();
	$row = $statement->fetch(PDO::FETCH_ASSOC);

	return (int) $row['posts_count'];
}

function convert_files_array($input_array) {
	$file_array = [];
	$file_count = count($input_array['name']);
	$file_keys = array_keys($input_array);

	for ($i=0; $i<$file_count; $i++) {
		foreach ($file_keys as $key) {
			$file_array[$i][$key] = $input_array[$key][$i];
		}
	}

	return $file_array;
}

function attach_uploaded_files($files=[], $post_id=null) {
	if(empty($files['tmp_name'][0])) return false;
	
	$files = convert_files_array($files);
	//var_dump($files);exit();

	foreach($files as $file) {

		if (!isset($file['error']) || is_array($file['error'])) {
			// invalid parameters
			// var_dump('bad file info');exit();
			continue; // skip this file
		}

		if($file['size'] > 20000000) {
			// Exceeded filesize limit.
			// var_dump('invalid file size');exit();
			continue;
		}

		$mime = mime_content_type($file['tmp_name']);
		if (false === $ext = array_search(
			$mime,
			array(
				'jpg' => 'image/jpeg',
				'png' => 'image/png',
				'gif' => 'image/gif',
				'avif' => 'image/avif',
				'webp' => 'image/webp',
				// todo: video
				'txt' => 'text/plain',
				'md' => 'text/markdown',
			),
			true
		)) {
			// Invalid file format.
			// var_dump('invalid format');exit();
			continue;
		}

		save_file($file['name'], $ext, $file['tmp_name'], $post_id, $mime);
	}
}

function detatch_files($file_ids=[], $post_id=null) {
	global $db;
	if(empty($db)) return false;
	if(empty($file_ids)) return false;
	if(!$post_id) return false;

	$file_id = null;

	try {
		$statement = $db->prepare('UPDATE file_to_post SET deleted = :delete_time WHERE file_id = :file_id AND post_id = :post_id');

		$statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
		$statement->bindParam(':post_id', $post_id, PDO::PARAM_INT);
		$statement->bindValue(':delete_time', time(), PDO::PARAM_INT);

		foreach ($file_ids as $id) {
			$file_id = $id;
			$statement->execute();
		}

	} catch(PDOException $e) {
		// print 'Exception : '.$e->getMessage();
		return false;
	}

	return true;
}

function db_select_file($query, $method='id') {
	global $db;
	if(empty($db)) return false;
	if($id === 0) return false;

	switch ($method) {
		case 'hash':
			$statement = $db->prepare('SELECT * FROM files WHERE file_hash = :q LIMIT 1');
			$statement->bindValue(':q', $query, PDO::PARAM_STR);
			break;
		case 'filename':
			$statement = $db->prepare('SELECT * FROM files WHERE file_filename = :q LIMIT 1');
			$statement->bindValue(':q', $query, PDO::PARAM_STR);
			break;
		default:
			$statement = $db->prepare('SELECT * FROM files WHERE id = :q LIMIT 1');
			$statement->bindValue(':q', $query, PDO::PARAM_INT);
			break;
	}
	
	$statement->execute();
	$row = $statement->fetch(PDO::FETCH_ASSOC);

	return (!empty($row)) ? $row : false;
}

function db_link_file($file_id, $post_id) {
	global $db;
	if(empty($db)) return false;

	try {
		$statement = $db->prepare('INSERT INTO file_to_post (file_id, post_id) VALUES (:file_id, :post_id)');

		$statement->bindValue(':file_id', $file_id, PDO::PARAM_INT);
		$statement->bindValue(':post_id', $post_id, PDO::PARAM_INT);

		$statement->execute();
	} catch(PDOException $e) {
		// print 'Exception : '.$e->getMessage();
		return false;
	}

	return true;
}

function save_file($filename, $extension, $tmp_file, $post_id, $mime='') {
	global $db;
	if(empty($db)) return false;

	$files_dir = ROOT.DS.'files';
	$hash_algo = 'sha1';

	$insert = [
		'file_extension' => $extension,
		'file_original' => $filename,
		'file_mime_type' => $mime,
		'file_size' => filesize($tmp_file),
		'file_hash' => hash_file($hash_algo, $tmp_file),
		'file_hash_algo' => $hash_algo,
		'file_meta' => '{}',
		'file_dir' => date('Y'),
		'file_subdir' => date('m'),
		'file_timestamp' => time()
	];

	if(!is_dir($files_dir)) {
		mkdir($files_dir, 0755);
	}

	if(!is_dir($files_dir.DS.$insert['file_dir'])) {
		mkdir($files_dir.DS.$insert['file_dir'], 0755);
	}

	if(!is_dir($files_dir.DS.$insert['file_dir'].DS.$insert['file_subdir'])) {
		mkdir($files_dir.DS.$insert['file_dir'].DS.$insert['file_subdir'], 0755);
	}

	$insert['file_filename'] = $post_id . '-' . substr($insert['file_hash'], 0, 7);
	$path = $files_dir.DS.$insert['file_dir'].DS.$insert['file_subdir'];

	if(move_uploaded_file($tmp_file, $path.DS.$insert['file_filename'] .'.'. $insert['file_extension'])) {
		// add to database

		// check if file exists already
		$existing = db_select_file($insert['file_hash'], 'hash');

		if(!empty($existing)) {
			// just link existing file
			if(db_link_file($existing['id'], $post_id)) {
				return $existing['id'];
			} else {
				return false;
			}
		} else {
			// insert new
			try {
				$statement = $db->prepare('INSERT INTO files (file_filename, file_extension, file_original, file_mime_type, file_size, file_hash, file_hash_algo, file_meta, file_dir, file_subdir, file_timestamp) VALUES (:file_filename, :file_extension, :file_original, :file_mime_type, :file_size, :file_hash, :file_hash_algo, :file_meta, :file_dir, :file_subdir, :file_timestamp)');

				$statement->bindValue(':file_filename', $insert['file_filename'], PDO::PARAM_STR);
				$statement->bindValue(':file_extension', $insert['file_extension'], PDO::PARAM_STR);
				$statement->bindValue(':file_original', $insert['file_original'], PDO::PARAM_STR);
				$statement->bindValue(':file_mime_type', $insert['file_mime_type'], PDO::PARAM_STR);
				$statement->bindValue(':file_size', $insert['file_size'], PDO::PARAM_INT);
				$statement->bindValue(':file_hash', $insert['file_hash'], PDO::PARAM_STR);
				$statement->bindValue(':file_hash_algo', $insert['file_hash_algo'], PDO::PARAM_STR);
				$statement->bindValue(':file_meta', $insert['file_meta'], PDO::PARAM_STR);
				$statement->bindValue(':file_dir', $insert['file_dir'], PDO::PARAM_STR);
				$statement->bindValue(':file_subdir', $insert['file_subdir'], PDO::PARAM_STR);
				$statement->bindValue(':file_timestamp', $insert['file_timestamp'], PDO::PARAM_INT);

				$statement->execute();

				// todo: check this?
				db_link_file($db->lastInsertId(), $post_id);

				return $db->lastInsertId();
			} catch(PDOException $e) {
				print 'Exception : '.$e->getMessage();
				return false;
			}
		}
	}

	return false;
}

function get_file_path($file) {
	$url = '';

	$url .= 'files/';
	$url .= $file['file_dir'] . '/';
	$url .= $file['file_subdir'] . '/';
	$url .= $file['file_filename'] . '.' . $file['file_extension'];

	return $url;
}

/* function that pings the official micro.blog endpoint for feed refreshes */
function ping_microblog() {
	global $config;
	$ping_url = 'https://micro.blog/ping';
	$feed_url = $config['url'].'/feed/json';

	$ch = curl_init($ping_url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, 'url='.urlencode($feed_url));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$response = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	return ($status == 200) ? true : false;
}

function rebuild_feeds($amount=10) {

	$posts = db_select_posts(NOW+60, $amount, 'desc');

	rebuild_json_feed($posts);
	rebuild_atom_feed($posts);
}

function rebuild_json_feed($posts=[]) {
	global $config;

	if (!file_exists(ROOT.DS.'feed')) {
		mkdir(ROOT.DS.'feed', 0755);
	}

	$filename = ROOT.DS.'feed'.DS.'feed.json';

	$feed = array(
		'version' => 'https://jsonfeed.org/version/1',
		'title' => 'status updates by '.$config['microblog_account'],
		'description' => '',
		'home_page_url' => $config['url'],
		'feed_url' => $config['url'].'/feed/feed.json',
		'user_comment' => '',
		'favicon' => '',
		'author' => array('name' => $config['microblog_account']),
		'items' => array()
	);

	foreach($posts as $post) {

		$attachments = db_get_attached_files($post['id']);
		$post_attachments = [];
		if(!empty($attachments)) {
			foreach ($attachments as $a) {
				$post_attachments[] = [
					'url' => $config['url'] .'/'. get_file_path($a),
					'mime_type' => $a['file_mime_type'],
					'size_in_bytes' => $a['file_size']
				];
			}
		}

		$post_images = array_filter($post_attachments, function($v) {
			return strpos($v['mime_type'], 'image') === 0;
		});

		$feed['items'][] = array(
			'id' => ($post['post_guid'] ? 'urn:uuid:'.$post['post_guid'] : $config['url'].'/'.$post['id']),
			'url' => $config['url'].'/'.$post['id'],
			'title' => '',
			'content_html' => $post['post_content'],
			'date_published' => gmdate('Y-m-d\TH:i:s\Z', $post['post_timestamp']),
			'image' => !empty($post_images) ? $post_images[0]['url'] : '',
			'attachments' => $post_attachments
		);

		
	}

	if(file_put_contents($filename, json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
		return true;
	} else return false;
}

function rebuild_atom_feed($posts=[]) {
	global $config;

	if (!file_exists(ROOT.DS.'feed')) {
		mkdir(ROOT.DS.'feed', 0755);
	}

	$filename = ROOT.DS.'feed'.DS.'feed.xml';

	$feed  = '<?xml version="1.0" encoding="UTF-8" ?'.'>'.NL;
	$feed .= '<feed xmlns="http://www.w3.org/2005/Atom">'.NL;
	$feed .= '<author><name>'.$config['microblog_account'].'</name></author>'.NL;
	$feed .= '<title>status updates by '.$config['microblog_account'].'</title>'.NL;
	$feed .= '<id>'.$config['url'].'</id>'.NL;
	$feed .= '<updated>'.gmdate('Y-m-d\TH:i:s\Z').'</updated>'.NL;

	foreach($posts as $post) {

		$published = gmdate('Y-m-d\TH:i:s\Z', $post['post_timestamp']);
		$updated = ($post['post_edited'] > $post['post_timestamp']) ? gmdate('Y-m-d\TH:i:s\Z', $post['post_edited']) : $published;

		$feed .= '<entry>'.NL;
		$feed .= '<title type="text">'.date('Y-m-d H:i', $post['post_timestamp']).'</title>'.NL;
		$feed .= '<link rel="alternate" type="text/html" href="'.$config['url'].'/'.$post['id'].'" />'.NL;
		$feed .= '<id>'.($post['post_guid'] ? 'urn:uuid:'.$post['post_guid'] : $config['url'].'/'.$post['id']).'</id>'.NL;
		$feed .= '<updated>'.$updated.'</updated>'.NL;
		$feed .= '<published>'.$published.'</published>'.NL;
		$feed .= '<content type="text">'.$post['post_content'].'</content>'.NL;
		$feed .= '</entry>'.NL;
	}

	$feed .= '</feed>';

	if(file_put_contents($filename, $feed)) {
		return true;
	} else return false;
}

function uuidv4($data = null) { // https://stackoverflow.com/a/15875555/3625228

	$data = $data ?? openssl_random_pseudo_bytes(16);
	assert(strlen($data) == 16);

	$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
	$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function twitter_post_status($status='') {
	global $config;
	require_once(ROOT.DS.'lib'.DS.'twitter_api.php');

	if(empty($status)) return array('errors' => 1);
	if(empty($config['twitter']['oauth_access_token']) ||
		empty($config['twitter']['oauth_access_token_secret']) ||
		empty($config['twitter']['consumer_key']) ||
		empty($config['twitter']['consumer_secret'])) return array('errors' => 2);

	$url = 'https://api.twitter.com/1.1/statuses/update.json';
	$postfields = array(
		'status' => $status,
		'trim_user' => 1
	);

	$twitter = new TwitterAPIExchange($config['twitter']);
	return $twitter->buildOauth($url, 'POST')->setPostfields($postfields)->performRequest();
}
