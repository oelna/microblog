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

function db_delete($post_id) {
	global $db;
	if(empty($db)) return false;
	if(!is_numeric($post_id) || $post_id <= 0) return false;

	/*
	$statement = $db->prepare('DELETE FROM posts WHERE id = :id');
	$statement->bindParam(':id', $post_id, PDO::PARAM_INT);
	*/

	// mark as deleted instead (for undo?!)
	$statement = $db->prepare('UPDATE posts SET post_deleted = :post_deleted WHERE id = :id');
	$statement->bindValue(':id', $post_id, PDO::PARAM_INT);
	$statement->bindValue(':post_deleted', time(), PDO::PARAM_INT);

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

	$statement = $db->prepare('SELECT COUNT(*) AS posts_count FROM posts');
	$statement->execute();
	$row = $statement->fetch(PDO::FETCH_ASSOC);

	return (int) $row['posts_count'];
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

		$feed['items'][] = array(
			'id' => ($post['post_guid'] ? 'urn:uuid:'.$post['post_guid'] : $config['url'].'/'.$post['id']),
			'url' => $config['url'].'/'.$post['id'],
			'title' => '',
			'content_html' => $post['post_content'],
			'date_published' => gmdate('Y-m-d\TH:i:s\Z', $post['post_timestamp'])
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
