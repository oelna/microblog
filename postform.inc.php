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
	if(!empty($_POST['message'])) {

		$id = db_insert($_POST['message'], NOW);

		if($id > 0) {
			$message = array(
				'status' => 'success',
				'message' => 'Successfully posted status #'.$id
			);

			rebuild_feed();
			if($config['ping'] == true) ping_microblog();
			if($config['crosspost_to_twitter'] == true) {
				$twitter_response = json_decode(twitter_post_status($_POST['message']), true);

				if(!empty($twitter_response['errors'])) {
					$message['message'] .= ' (But crossposting to twitter failed!)';
				}
			}
		}
	}

?><!DOCTYPE html>
<html lang="<?= $config['language'] ?>" class="postform">
<head>
	<title>micro.blog</title>
	<meta name="viewport" content="width=device-width" />
	<link rel="stylesheet" href="<?= $config['url'] ?>/microblog.css" />
</head>
<body>
	<div class="wrap">
		<nav>
			<ul>
				<li><a href="<?= $config['url'] ?>/">Timeline</a></li>
				<li><a href="<?= $config['url'] ?>/new">New Status</a></li>
			</ul>
		</nav>
		<?php if(isset($message['status']) && isset($message['message'])): ?>
		<p class="message <?= $message['status'] ?>"><?= $message['message'] ?></p>
		<?php endif; ?>
		<form action="" method="post">
			<textarea name="message" maxlength="<?= $config['max_characters'] ?>"></textarea>
			<p id="count"><?= $config['max_characters'] ?></p>
			<input type="submit" name="" value="Post" />
		</form>
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var textarea = document.querySelector('textarea[name="message"]');
			var charCount = document.querySelector('#count');
			var maxCount = parseInt(textarea.getAttribute('maxlength'));

			charCount.innerHTML = maxCount;

			textarea.addEventListener('input', function() {
				var textLength = [...this.value].length;

				charCount.innerHTML = maxCount - textLength;
			}, false)
		});
	</script>
</body>
</html>
