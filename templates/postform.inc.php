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

			// handle files
			if(!empty($_FILES['attachments'])) {
				attach_uploaded_files($_FILES['attachments'], $id);
			}

			rebuild_feeds();

			if($config['activitypub'] == true) activitypub_notify_followers($id);
			if(isset($config['at_enabled']) && $config['at_enabled'] == true) at_post_status($id);
			if($config['ping'] == true) ping_microblog();
			/*
			if($config['crosspost_to_twitter'] == true) {
				$twitter_response = json_decode(twitter_post_status($_POST['content']), true);

				if(!empty($twitter_response['errors'])) {
					$message['message'] .= ' (But crossposting to twitter failed!)';
				}
			}
			*/

			header('Location: '.$config['url']);
			die();
		}
	}

	$title_suffix = 'new post';
	require(ROOT.DS.'snippets'.DS.'header.snippet.php');

?><body ontouchstart="">
	<div class="wrap">
		<?php require(ROOT.DS.'snippets'.DS.'nav.snippet.php'); ?>
		<?php if(isset($message['status']) && isset($message['message'])): ?>
		<p class="message <?= $message['status'] ?>"><?= $message['message'] ?></p>
		<?php endif; ?>
		<form action="" method="post" enctype="multipart/form-data" id="post-new-form" data-redirect="<?= $config['url'] ?>">
			<textarea name="content" maxlength="<?= $config['max_characters'] ?>"></textarea>

			<div class="post-nav">
				<label id="post-attachments-label">Add Files<input type="file" multiple="multiple" name="attachments[]" id="post-attachments" accept="image/*" /></label>
				<div id="post-droparea" class="hidden">Add Files</div>
				<ul id="post-attachments-list"></ul>
				<p id="count"><?= $config['max_characters'] ?></p>
				<input type="submit" name="" value="Post" />
			</div>
		</form>
	</div>
	<?php require(ROOT.DS.'snippets'.DS.'footer.snippet.php'); ?>
</body>
</html>
