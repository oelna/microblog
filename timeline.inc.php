<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');
	header('Content-Type: text/html; charset=utf-8');

	// check user credentials
	if(isset($_COOKIE['microblog_login']) && $_COOKIE['microblog_login'] === sha1($config['url'].$config['admin_pass'])) {
		// correct auth data, extend cookie life
		$domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
		setcookie('microblog_login', sha1($config['url'].$config['admin_pass']), NOW+$config['cookie_life'], '/', $domain, false);
	}

	// pagination
	$current_page = (path(0) == 'page' && is_numeric(path(1))) ? (int) path(1) : 1;
	$posts_count = db_posts_count();
	$total_pages = ceil($posts_count / $config['posts_per_page']);

	// get posts
	$posts = db_select_posts(NOW, $config['posts_per_page'], 'desc', $current_page);

?><!DOCTYPE html>
<html lang="<?= $config['language'] ?>" class="timeline">
<head>
	<title><?= empty($config['microblog_account']) ? "" : $config['microblog_account'] . "'s "; ?>micro.blog</title>
	<meta name="viewport" content="width=device-width" />
	<link rel="alternate" type="application/json" title="JSON Feed" href="<?= $config['url'] ?>/feed.json" />
	<link rel="stylesheet" href="<?= $config['url'] ?>/microblog.css" />
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
					<ul>
						<li><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/edit">Edit</a></li>
						<li><a href="<?= $config['url'] ?>/<?= $post['id'] ?>/delete">Delete</a></li>
					</ul>
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
</body>
</html>
