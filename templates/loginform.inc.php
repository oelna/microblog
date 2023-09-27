<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');

	// handle login
	if(isset($_POST['user']) && isset($_POST['pass'])) {
		if($_POST['user'] === $config['admin_user'] && password_verify($_POST['pass'], $config['admin_pass'])) {
			$host = get_host(false); // cookies are port-agnostic
			$domain = ($host != 'localhost') ? $host : false;
			$hash = hash('sha256', $config['installation_signature']);
			setcookie('microblog_login', $hash, NOW+$config['cookie_life'], '/', $domain, false);

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
		<?php if(path(1) == 'recovery'): ?>
		<p class="message success">A recovery link has been sent to your email address. (Please also check Spam!)</p>
		<?php endif; ?>
		<?php if(isset($message['status']) && isset($message['message'])): ?>
		<p class="message <?= $message['status'] ?>"><?= $message['message'] ?></p>
		<?php endif; ?>
		<form action="" method="post">
			<input type="text" name="user" placeholder="username" autocomplete="username webauthn" /><br />
			<input type="password" name="pass" placeholder="password" autocomplete="current-password" required />
			<div class="login-nav">
				<input type="submit" name="" value="Login" />
				<?php
					$passkey_json = db_get_setting('passkey');
					$passkey = null;
					if($passkey_json) {
						$passkey = json_decode($passkey_json, true);
					}
				?>
				<?php if(!empty($passkey)): ?><button class="button hidden" id="passkey-login">Use Passkey</button><?php endif; ?>
				<a href="/recovery">Forgot password</a>
			</div>
		</form>
	</div>
	<?php require(ROOT.DS.'snippets'.DS.'footer.snippet.php'); ?>
</body>
</html>
