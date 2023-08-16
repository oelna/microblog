<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');

	// handle login
	if(isset($_POST['user']) && isset($_POST['pass'])) {
		if($_POST['user'] === $config['admin_user'] && $_POST['pass'] === $config['admin_pass']) {
			$domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
			setcookie('microblog_login', sha1($config['url'].$config['admin_pass']), NOW+$config['cookie_life'], '/', $domain, false);

			header('Location: '.$config['url']);
			die();
		} else {
			header('HTTP/1.0 401 Unauthorized');
			$message = array(
				'status' => 'error',
				'message' => 'You entered wrong user credentials. Please try again.'
			);
		}
	}

	$title_suffix = 'login';
	require(ROOT.DS.'snippets'.DS.'header.snippet.php');

?><body ontouchstart="">
	<div class="wrap">
		<?php require(ROOT.DS.'snippets'.DS.'nav.snippet.php'); ?>
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
	<?php require(ROOT.DS.'snippets'.DS.'footer.snippet.php'); ?>
</body>
</html>
