<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');

	// check user credentials
	if(isset($_COOKIE['microblog_login']) && $_COOKIE['microblog_login'] === sha1($config['url'].$config['admin_pass'])) {
		// correct auth data, extend cookie life
		$domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
		setcookie('microblog_login', sha1($config['url'].$config['admin_pass']), NOW+$config['cookie_life'], '/', $domain, false);
	} else {
		// wrong data, kick user to login page
		header('HTTP/1.0 401 Unauthorized');
		header('Location: '.$config['url'].'/login');
		die();
	}

	header('Content-Type: text/html; charset=utf-8');

	$message = array();
	if(!empty($_POST['content'])) {

		$id = db_insert($_POST['content'], NOW);

		if($id > 0) {
			$message = array(
				'status' => 'success',
				'message' => 'Successfully posted status #'.$id
			);

			rebuild_feeds();
			if($config['ping'] == true) ping_microblog();
			if($config['crosspost_to_twitter'] == true) {
				$twitter_response = json_decode(twitter_post_status($_POST['content']), true);

				if(!empty($twitter_response['errors'])) {
					$message['message'] .= ' (But crossposting to twitter failed!)';
				}
			}

			header('Location: '.$config['url']);
			die();
		}
	}

?><!DOCTYPE html>
<html lang="<?= $config['language'] ?>" class="postform">
<head>
	<title>micro.blog</title>
	<meta name="viewport" content="width=device-width" />
	<link rel="stylesheet" href="<?= $config['url'] ?>/microblog.css" />
	<script src="<?= $config['url'] ?>/microblog.js" type="module" defer></script>
</head>
<body>
	<div class="wrap">
		<nav class="main">
			<ul>
				<li><a class="button" href="<?= $config['url'] ?>/">Timeline</a></li>
				<li><a class="button" href="<?= $config['url'] ?>/new">New Status</a></li>
			</ul>
		</nav>
		<?php if(isset($message['status']) && isset($message['message'])): ?>
		<p class="message <?= $message['status'] ?>"><?= $message['message'] ?></p>
		<?php endif; ?>
		<form action="" method="post">
			<textarea name="content" maxlength="<?= $config['max_characters'] ?>"></textarea>
			<p id="count"><?= $config['max_characters'] ?></p>
			<input type="submit" name="" value="Post" />
		</form>
	</div>
	<footer>
		<nav>
			<ul>
				<li><a href="<?= $config['url'] ?>/feed/feed.xml">ATOM Feed</a></li>
				<li><a href="<?= $config['url'] ?>/feed/feed.json">JSON Feed</a></li>
				<?php if($config['xmlrpc']): ?><li><a href="<?= $config['url'] ?>/xmlrpc.php">XML-RPC</a></li><?php endif; ?>
			</ul>
		</nav>
	</footer>
</body>
</html>
