<nav class="main">
	<ul>
		<li><a class="button" href="<?= $config['url'] ?>/">Timeline</a></li>
		<?php if($config['logged_in']): ?><li><a class="button" href="<?= $config['url'] ?>/new">New Status</a></li><?php endif; ?>
		<?php if(!$config['logged_in']): ?><li><a class="button" href="<?= $config['url'] ?>/login">Login</a></li><?php endif; ?>
	</ul>
</nav>
