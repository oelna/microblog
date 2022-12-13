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
