<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');

	// handle login
	if(isset($_POST['user']) && isset($_POST['pass'])) {
		if($_POST['user'] === $config['admin_user'] && $_POST['pass'] === $config['admin_pass']) {
			$domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
			setcookie('microblog_login', sha1($config['url'].$config['admin_pass']), NOW+$config['cookie_life'], '/', $domain, false);

			header('Location: '.$config['url'].'/new');
			die();
		} else {
			header('HTTP/1.0 401 Unauthorized');
			$message = array(
				'status' => 'error',
				'message' => 'You entered wrong user credentials. Please try again.'
			);
		}
	}

	header('Content-Type: text/html; charset=utf-8');

?><!DOCTYPE html>
<html lang="<?= $config['language'] ?>" class="login">
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
		<nav>
			<ul>
				<li><a href="<?= $config['url'] ?>/">Timeline</a></li>
				<li><a href="<?= $config['url'] ?>/new">New Status</a></li>
			</ul>
		</nav>
		<p>Please enter your login information.</p>
		<?php if(isset($message['status']) && isset($message['message'])): ?>
		<p class="message <?= $message['status'] ?>"><?= $message['message'] ?></p>
		<?php endif; ?>
		<form action="" method="post">
			<input type="text" name="user" placeholder="username" /><br />
			<input type="password" name="pass" placeholder="password" /><br />
			<input type="submit" name="" value="Login" />
		</form>
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
