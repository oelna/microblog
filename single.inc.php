<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');
	header('Content-Type: text/html; charset=utf-8');

	$id = (!empty(path(0))) ? (int) path(0) : 0;

	$action = 'display';
	if(mb_strtolower(path(1)) == 'delete') $action = 'delete';
	if(mb_strtolower(path(1)) == 'edit') $action = 'edit';

	// delete post
	$error = false;
	if(!empty($_POST['action']) && $_POST['action'] == 'delete') {
		$result = db_delete((int) $_POST['id']);

		if(!$result) {
			$error = 'Post could not be deleted!';
		} else {
			rebuild_feeds();

			header('Location: '.$config['url']);
			die();
		}
	}

	// edit post
	if(!empty($_POST['action']) && $_POST['action'] == 'edit') {
		$result = db_update((int) $_POST['id'], $_POST['content']);

		if(!$result) {
			$error = 'Post could not be updated!';
		} else {
			rebuild_feeds();

			header('Location: '.$config['url'].'/'.$_POST['id']);
			die();
		}
	}

	// load the actual post
	$post = db_select_post($id);

?><!DOCTYPE html>
<html lang="<?= $config['language'] ?>" class="post">
<head>
	<meta charset="utf-8" />
	<title><?= empty($config['microblog_account']) ? "" : $config['microblog_account'] . "'s " ?>micro.blog - entry #<?= $id ?></title>
	<meta name="viewport" content="width=device-width" />
	<link rel="alternate" type="application/json" title="JSON Feed" href="<?= $config['url'] ?>/feed/json" />
	<link rel="alternate" type="application/atom+xml" title="Atom Feed" href="<?= $config['url'] ?>/feed/atom" />
	<?php if($config['xmlrpc']): ?><link rel="EditURI" type="application/rsd+xml" title="RSD" href="<?= $config['url'] ?>/rsd" /><?php endif; ?>
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
		<ul class="posts">
		<?php if(!empty($post)): ?>
			<li class="single-post" data-post-id="<?= $post['id'] ?>">
			<?php if($action == 'edit'): ?>
				<form action="" method="post" class="edit">
					<textarea name="content" maxlength="<?= $config['max_characters'] ?>"><?= $post['post_content'] ?></textarea>
					<p id="count"><?= $config['max_characters'] ?></p>

					<input type="hidden" name="action" value="edit" />
					<input type="hidden" name="id" value="<?= $post['id'] ?>" />
					<input type="submit" class="button" value="Update this post" />
				</form>
			<?php else: ?>
				<?php
					$date = date_create();
					date_timestamp_set($date, $post['post_timestamp']);
					
					$datetime = date_format($date, 'Y-m-d H:i:s');
					$formatted_time = date_format($date, 'M d Y H:i');
				?>
				<span class="post-timestamp"><time datetime="<?= $datetime ?>" data-unix-time="<?= $post['post_timestamp'] ?>"><?= $formatted_time ?></time></span>
				<nav class="post-meta">
					<ul>
						<li><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/edit">Edit</a></li>
						<li><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/delete">Delete</a></li>
					</ul>
				</nav>
				<p class="post-content"><?= nl2br(autolink($post['post_content'])) ?></p>
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
	<footer>
		<nav>
			<ul>
				<li><a href="<?= $config['url'] ?>/feed/atom">ATOM Feed</a></li>
				<li><a href="<?= $config['url'] ?>/feed/json">JSON Feed</a></li>
				<?php if($config['xmlrpc']): ?><li><a href="<?= $config['url'] ?>/xmlrpc">XML-RPC</a></li><?php endif; ?>
			</ul>
		</nav>
	</footer>
</body>
</html>
