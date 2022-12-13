<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');

	// never cache the timeline (?)
	header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: post-check=0, pre-check=0', FALSE);
	header('Pragma: no-cache');

	// pagination
	$current_page = (path(0) == 'page' && is_numeric(path(1))) ? (int) path(1) : 1;
	$posts_count = db_posts_count();
	$total_pages = ceil($posts_count / $config['posts_per_page']);
	$offset = ($current_page-1)*$config['posts_per_page'];

	// get posts
	$posts = db_select_posts(NOW, $config['posts_per_page'], 'desc', $offset);

	header('Content-Type: text/html; charset=utf-8');

?><!DOCTYPE html>
<html lang="<?= $config['language'] ?>" class="timeline">
<head>
	<meta charset="utf-8" />
	<title><?= empty($config['microblog_account']) ? "" : $config['microblog_account'] . "'s "; ?>micro.blog</title>
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
				<?php if($config['logged_in']): ?><li><a class="button" href="<?= $config['url'] ?>/new">New Status</a></li><?php endif; ?>
				<?php if(!$config['logged_in']): ?><li><a class="button" href="<?= $config['url'] ?>/login">Login</a></li><?php endif; ?>
			</ul>
		</nav>
		<ul class="posts">
		<?php if(!empty($posts)): ?>
			<?php foreach($posts as $post): ?>
			<li data-post-id="<?= $post['id'] ?>">
				<?php
					$date = date_create();
					date_timestamp_set($date, $post['post_timestamp']);
					
					$datetime = date_format($date, 'Y-m-d H:i:s');
					$formatted_time = date_format($date, 'M d Y H:i');
				?>
				<a class="post-timestamp" href="<?= $config['url'] ?>/<?= $post['id'] ?>"><time datetime="<?= $datetime ?>" data-unix-time="<?= $post['post_timestamp'] ?>"><?= $formatted_time ?></time></a>
				<nav class="post-meta">
					<?php if($config['logged_in']): ?><ul>
						<li><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/edit">Edit</a></li>
						<li><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/delete">Delete</a></li>
					</ul><?php endif; ?>
				</nav>
				<div class="post-content"><?= nl2br(autolink($post['post_content'])) ?></div>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php else: ?>
		<p>No posts found.</p>
		<?php endif; ?>
		<div class="pagination">
			<?php if ($current_page > 1): ?><a href="<?= $config['url'] ?>/page/<?= $current_page - 1 ?>" class="previous">newer updates</a><?php endif; ?>
			<?php if ($current_page < $total_pages): ?><a href="<?= $config['url'] ?>/page/<?= $current_page + 1 ?>" class="next">older updates</a><?php endif; ?>
		</div>
	</div>
	<footer>
		<nav>
			<ul>
				<li><a href="<?= $config['url'] ?>/feed/atom">ATOM Feed</a></li>
				<li><a href="<?= $config['url'] ?>/feed/json">JSON Feed</a></li>
				<?php if($config['xmlrpc']): ?><li><a href="<?= $config['url'] ?>/xmlrpc">XML-RPC</a></li><?php endif; ?>
				<?php if($config['logged_in']): ?><li><a href="<?= $config['url'] ?>/logout">Logout</a></li><?php endif; ?>
			</ul>
		</nav>
	</footer>
</body>
</html>
