<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');
	header('Content-Type: text/html; charset=utf-8');

	$id = (!empty(path(0))) ? (int) path(0) : 0;
	$post = db_select_post($id);

?><!DOCTYPE html>
<html lang="<?= $config['language'] ?>" class="post">
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
		<?php if(!empty($post)): ?>
		<?php
			$datetime = strftime('%Y-%m-%d %H:%M:%S', $post['post_timestamp']);
			$formatted_time = strftime('%b %d %Y %H:%M', $post['post_timestamp']);
		?>
		<time class="post-timestamp" datetime="<?= $datetime ?>" data-unix-time="<?= $post['post_timestamp'] ?>"><?= $formatted_time ?></time>
		<p class="post-message"><?= autolink($post['post_content']) ?></p>
		<?php else: ?>
		<p>No post with this ID.</p>
		<?php endif; ?>
	</div>
</body>
</html>

