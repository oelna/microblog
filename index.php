<?php
	require_once(__DIR__.DIRECTORY_SEPARATOR.'config.php');

	// check user credentials
	$config['logged_in'] = check_login();

	// subpages
	$template = 'timeline';
	if(is_numeric(path(0))) {
		// show a single blog post
		$template = 'single';
		require_once(ROOT.DS.'templates'.DS.'single.inc.php');

	} elseif(mb_strtolower(path(0)) === 'login') {
		$template = 'login';
		require_once(ROOT.DS.'templates'.DS.'loginform.inc.php');

	} elseif(mb_strtolower(path(0)) === 'logout') {
		$domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
		setcookie('microblog_login', '', time()-3600, '/', $domain, false);
		unset($_COOKIE['microblog_login']);

		header('Location: '.$config['url']);
		die();

	} elseif(mb_strtolower(path(0)) === 'new') {
		$template = 'postform';
		require_once(ROOT.DS.'templates'.DS.'postform.inc.php');

	} else {
		// redirect everything else to the homepage
		if(!empty(path(0)) && path(0) != 'page') {
			header('Location: '.$config['url']);
			die();
		}

		// show the homepage
		require_once(ROOT.DS.'templates'.DS.'timeline.inc.php');
	}
