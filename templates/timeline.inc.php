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
	if(empty($posts)) {
		header('Location: '.$config['url']);
		die();
	}

	$title_suffix = '';
	require(ROOT.DS.'snippets'.DS.'header.snippet.php');

?><body>
	<div class="wrap">
		<?php require(ROOT.DS.'snippets'.DS.'nav.snippet.php'); ?>
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
				<a class="post-timestamp" href="<?= $config['url'] ?>/<?= $post['id'] ?>">
					<time class="published" datetime="<?= $datetime ?>" data-unix-time="<?= $post['post_timestamp'] ?>"><?= $formatted_time ?></time>
					<?php if(is_numeric($post['post_edited']) && $config['show_edits']): ?>
					<time class="modified" datetime="<?= gmdate('Y-m-d\TH:i:s\Z', $post['post_edited']) ?>" data-unix-time="<?= $post['post_edited'] ?>">Edited on <?= date('M d Y H:i', $post['post_edited']) ?></time>
					<?php endif; ?>
				</a>
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
	<?php require(ROOT.DS.'snippets'.DS.'footer.snippet.php'); ?>
</body>
</html>