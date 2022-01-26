<?php
	require_once(__DIR__.DIRECTORY_SEPARATOR.'config.php');

	if(is_numeric(path(0))) {
		// show a single blog post
		require_once(ROOT.DS.'single.inc.php');
	} elseif(mb_strtolower(path(0)) === 'login') {
			// show login form
			require_once(ROOT.DS.'loginform.inc.php');
	} elseif(mb_strtolower(path(0)) === 'new') {
		if(isset($_COOKIE['microblog_login']) && $_COOKIE['microblog_login'] === sha1($config['url'].$config['admin_pass'])) {
			// show the post form
			require_once(ROOT.DS.'postform.inc.php');
		} else {
			header('Location: '.$config['url'].'/login');
			die();
		}
	} else {
		// redirect everything else to the homepage
		if(!empty(path(0)) && path(0) != 'page') {
			header('Location: '.$config['url']);
			die();
		}

		// show the homepage
		require_once(ROOT.DS.'timeline.inc.php');
	}
