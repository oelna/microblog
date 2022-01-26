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
	<title>micro.blog</title>
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
</body>
</html>
