<?php

function path($fragment=null) {
	global $config;
	if($fragment === null) return $config['path'];
	return (!empty($config['path'][$fragment])) ? $config['path'][$fragment] : false;
}

function db_insert($content, $timestamp=NOW) {
	global $db;
	if(empty($db)) return false;

	$statement = $db->prepare('INSERT INTO posts (post_content, post_timestamp) VALUES (:post_content, :post_timestamp)');
	$statement->bindValue(':post_content', $content, PDO::PARAM_STR);
	$statement->bindValue(':post_timestamp', $timestamp, PDO::PARAM_INT);

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

function db_update($post_id, $content) {
	global $db;
	if(empty($db)) return false;
	if(empty($content)) return false;
	if(!is_numeric($post_id) || $post_id <= 0) return false;

	$statement = $db->prepare('UPDATE posts SET post_content = :post_content, post_edited = :post_edited WHERE id = :id');
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

function db_select_posts($from=NOW, $amount=10, $sort='desc', $page=1) {
	global $config;
	global $db;
	if(empty($db)) return false;
	if($sort !== 'desc') $sort = 'asc';

	$offset = ($page-1)*$config['posts_per_page'];

	$statement = $db->prepare('SELECT * FROM posts WHERE post_timestamp < :post_timestamp AND post_deleted IS NULL ORDER BY id '.$sort.' LIMIT :limit OFFSET :page');
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
	$feed_url = $config['url'].'/feed.json';

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

function rebuild_feed($amount=10) {
	global $config;

	$feed = array(
		'version' => 'https://jsonfeed.org/version/1',
		'title' => 'status updates by '.$config['microblog_account'],
		'description' => '',
		'home_page_url' => $config['url'],
		'feed_url' => $config['url'].'/feed.json',
		'user_comment' => '',
		'favicon' => '',
		'author' => array('name' => $config['microblog_account']),
		'items' => array()
	);

	// make a timezone string for dates
	$timezone_offset_string = '+00:00'; // because unix timestamps are always GMT?

	$posts = db_select_posts(NOW+60, $amount, 'desc');

	foreach($posts as $post) {

		$date = date_create();
		date_timestamp_set($date, $post['post_timestamp']);

		$feed['items'][] = array(
			'id' => $config['url'].'/'.$post['id'],
			'url' => $config['url'].'/'.$post['id'],
			'title' => '',
			'content_html' => $post['post_content'],
			'date_published' => date_format($date, 'Y-m-d\TH:i:s').$timezone_offset_string
		);
	}

	if(file_put_contents(ROOT.DS.'feed.json', json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
		return true;
	} else return false;
}

function twitter_post_status($status='') {
	global $config;
	require_once(ROOT.DS.'twitter_api.php');

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
