<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');

	$id = (!empty(path(0))) ? (int) path(0) : 0;

	$action = 'display';
	if(mb_strtolower(path(1)) == 'delete') $action = 'delete';
	if(mb_strtolower(path(1)) == 'undelete') $action = 'undelete';
	if(mb_strtolower(path(1)) == 'edit') $action = 'edit';
	if(mb_strtolower(path(1)) == 'json') $action = 'json';

	$error = false;
	if($config['logged_in']) {

		// delete post
		if(!empty($_POST['action']) && $_POST['action'] == 'delete') {
			$result = db_delete((int) $_POST['id']);

			if(!$result) {
				$error = 'Post could not be deleted!';
			} else {

				rebuild_feeds();

				if($config['activitypub']) {
					// todo: send DELETE activity to followers
					// https://www.w3.org/TR/activitypub/#delete-activity-inbox
					activitypub_delete_post($_POST['id']);
				}

				header('Location: '.$config['url']);
				die();
			}
		}

		// undelete post
		if($action == 'undelete') {
			$result = db_delete((int) $id, true);

			if(!$result) {
				$error = 'Post could not be restored!';
			} else {
				rebuild_feeds();
			}
		}

		// edit post
		if(!empty($_POST['action']) && $_POST['action'] == 'edit') {

			// check changes to attachments
			$attached_files = db_get_attached_files($_POST['id']);
			if(!empty($attached_files)) {
				$files_ids = array_column($attached_files, 'id');

				if(empty($_POST['attachments'])) {
					// remove ALL attachments
					$to_remove = $files_ids;
				} else {
					// remove specified attachments
					/*
					$to_remove = array_filter($attached_files, function($v) {
						return !in_array($v['id'], $_POST['attachments']);
					});
					*/
					$to_remove = array_diff($files_ids, $_POST['attachments']);
				}

				if(count($to_remove) > 0) {
					if(!detatch_files($to_remove, $_POST['id'])) {
						// could not remove attachments
						// var_dump($to_remove);
					}
				}
			}

			$result = db_update((int) $_POST['id'], $_POST['content']);

			if(!$result) {
				$error = 'Post could not be updated!';
			} else {
				rebuild_feeds();

				if($config['activitypub']) {
					// todo: send UPDATE activity to followers
					// https://www.w3.org/TR/activitypub/#update-activity-inbox
					activitypub_update_post($_POST['id']);
				}

				header('Location: '.$config['url'].'/'.$_POST['id']);
				die();
			}
		}
	}

	// load the actual post
	$post = db_select_post($id);
	if(is_numeric($post['post_deleted'])) {
		if(!$config['logged_in']) {
			header('Location: '.$config['url']);
		}
	}

	if($action == 'json') {

		$json = activitypub_activity_from_post($post, true);

		header('Content-Type: application/ld+json');
		echo($json);
		die();
	}

	$title_suffix = 'entry #' . $id;
	require(ROOT.DS.'snippets'.DS.'header.snippet.php');

?><body ontouchstart="">
	<div class="wrap">
		<?php require(ROOT.DS.'snippets'.DS.'nav.snippet.php'); ?>
		<ul class="posts">
		<?php if(!empty($post)): ?>
			<li class="single-post" data-post-id="<?= $post['id'] ?>">
			<?php if($action == 'edit'): ?>
				<form action="" method="post" class="edit">
					<input type="hidden" name="action" value="edit" />
					<input type="hidden" name="id" value="<?= $post['id'] ?>" />

					<textarea name="content" maxlength="<?= $config['max_characters'] ?>"><?= $post['post_content'] ?></textarea>

					<div class="post-nav">
						<!--<label id="post-attachments-label">Add Files<input type="file" multiple="multiple" name="attachments[]" id="post-attachments" accept="image/*" /></label>
						<div id="post-droparea" class="hidden">Add Files</div>-->
						<ul id="post-attachments-list">
							<?php
								$attachments = db_get_attached_files($post['id']);
							?>
							<?php if(!empty($attachments) && !empty($attachments[$post['id']])): ?>
								<?php foreach($attachments[$post['id']] as $a): ?>
									<?php if(strpos($a['file_mime_type'], 'image') === 0): ?>
									<?php
										$abs = ROOT.DS.get_file_path($a);
										list($width, $height, $_, $size_string) = getimagesize($abs);
										$url = $config['url'] .'/'. get_file_path($a);
									?>
										<li>
											<label>
												<input type="checkbox" name="attachments[]" value="<?= $a['id'] ?>" checked /> 
												<img class="file-preview" src="<?= $url ?>" alt="<?= $a['file_original'] ?>" <?= $size_string ?> loading="lazy" />
												<?= $a['file_original'] ?>
											</label>
										</li>
								<?php else: ?>
								<?php endif; ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</ul>
						<p id="count"><?= $config['max_characters'] ?></p>
						<input type="submit" class="button" value="Update this post" />
					</div>
				</form>
			<?php else: ?>
				<?php
					$date = date_create();
					date_timestamp_set($date, $post['post_timestamp']);
					
					$datetime = date_format($date, 'Y-m-d H:i:s');
					$formatted_time = date_format($date, 'M d Y H:i');

					$attachments = db_get_attached_files($post['id']);
				?>
				<span class="post-timestamp">
					<time class="published" datetime="<?= $datetime ?>" data-unix-time="<?= $post['post_timestamp'] ?>"><?= $formatted_time ?></time>
					<?php if(is_numeric($post['post_edited']) && $config['show_edits']): ?>
					<time class="modified" datetime="<?= gmdate('Y-m-d\TH:i:s\Z', $post['post_edited']) ?>" data-unix-time="<?= $post['post_edited'] ?>">Edited on <?= date('M d Y H:i', $post['post_edited']) ?></time>
					<?php endif; ?>
				</span>
				<nav class="post-meta">
					<ul>
						<?php if($config['activitypub']):
							// todo: is it possible to retrieve this at the same time as post data?
							$post_stats = activitypub_get_post_stats('both', $post['id']);
						?>
						<li class="post-likes"><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/likes" title="This post has been liked <?= $post_stats['like'] ?> times in the Fediverse"><span class="amount"><?= $post_stats['like'] ?></span><span class="word">Likes</span></a></li>
						<li class="post-boosts"><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/boosts" title="This post has been announced <?= $post_stats['announce'] ?> times in the Fediverse"><span class="amount"><?= $post_stats['announce'] ?></span><span class="word">Boosts</span></a></li>
						<?php endif; ?>

						<?php if($config['logged_in']): ?>
						<?php if(is_numeric($post['post_deleted'])): ?>
						<li><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/undelete" title="Restore">Deleted on <?= date('M d Y', $post['post_deleted']) ?></a></li>
						<?php else: ?>
						<li><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/edit">Edit</a></li>
						<li><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/delete">Delete</a></li>
						<?php endif; ?>
						<?php endif; ?>
					</ul>
				</nav>
				<div class="post-content"><?= nl2br(autolink($post['post_content'])) ?></div>
				<?php if(!empty($attachments) && !empty($attachments[$post['id']])): ?>
				<ul class="post-attachments">
					<?php foreach($attachments[$post['id']] as $a): ?>
					<li>
						<?php if(strpos($a['file_mime_type'], 'image') === 0): ?>
							<?php
								$abs = ROOT.DS.get_file_path($a);
								list($width, $height, $_, $size_string) = getimagesize($abs);
								$url = $config['url'] .'/'. get_file_path($a);
							?>
							<a href="<?= $url ?>">
								<picture>
									<source srcset="<?= $url ?>" type="image/jpeg" />
									<img src="<?= $url ?>" alt="<?= $a['file_original'] ?>" <?= $size_string ?> loading="lazy" />
								</picture>
							</a>
						<?php else: ?>
							<a href="<?= $url ?>" download="<?= $a['file_original'] ?>"><?= $a['file_original'] ?></a>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
				<?php if($action == 'delete'): ?>
					<form action="" method="post" class="delete">
						<input type="hidden" name="action" value="delete" />
						<input type="hidden" name="id" value="<?= $post['id'] ?>" />
						<input type="submit" class="button alert" value="Delete this post" />
					</form>
					<?php if($error !== false): ?>
						<p class="message error"><?= $error ?></p>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
			</li>
		<?php else: ?>
			<p>No post with this ID.</p>
		<?php endif; ?>
		</ul>
	</div>
	<?php require(ROOT.DS.'snippets'.DS.'footer.snippet.php'); ?>
</body>
</html>
