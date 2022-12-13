<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');

	if(!$config['logged_in']) {
		// wrong data, kick user to login page
		header('HTTP/1.0 401 Unauthorized');
		header('Location: '.$config['url'].'/login');
		die();
	}

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

	$title_suffix = 'new post';
	require(ROOT.DS.'snippets'.DS.'header.snippet.php');

?><body>
	<div class="wrap">
		<?php require(ROOT.DS.'snippets'.DS.'nav.snippet.php'); ?>
		<?php if(isset($message['status']) && isset($message['message'])): ?>
		<p class="message <?= $message['status'] ?>"><?= $message['message'] ?></p>
		<?php endif; ?>
		<form action="" method="post">
			<textarea name="content" maxlength="<?= $config['max_characters'] ?>"></textarea>
			<p id="count"><?= $config['max_characters'] ?></p>
			<input type="submit" name="" value="Post" />
		</form>
	</div>
	<?php require(ROOT.DS.'snippets'.DS.'footer.snippet.php'); ?>
</body>
</html>
