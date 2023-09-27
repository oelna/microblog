<footer>
	<nav>
		<ul>
			<li><a href="<?= $config['url'] ?>/feed/atom">ATOM Feed</a></li>
			<li><a href="<?= $config['url'] ?>/feed/json">JSON Feed</a></li>
			<?php if($config['xmlrpc']): ?><li><a href="<?= $config['url'] ?>/xmlrpc">XML-RPC</a></li><?php endif; ?>
			<?php if($config['logged_in']): ?><li><a href="<?= $config['url'] ?>/settings">Settings</a></li><?php endif; ?>
			<?php if($config['logged_in']): ?><li><a href="<?= $config['url'] ?>/logout">Logout</a></li><?php else: ?><li><a href="<?= $config['url'] ?>/login">Login</a></li><?php endif; ?>
		</ul>
	</nav>
</footer>
<script>
	window.mb = {
		'url': new URL('<?= $config['url'] ?>'),
		'passkeys': false
	};
	// mostly used for passkey management
</script>
