<?php
	require_once(__DIR__.DIRECTORY_SEPARATOR.'config.php');

	// check user credentials
	$config['logged_in'] = check_login();

	$config['show_edits'] = !empty($config['show_edits']) ? $config['show_edits'] : true;

	// subpages
	$template = 'timeline';
	if(is_numeric(path(0))) {
		// show a single blog post
		$template = 'single';
		require_once(ROOT.DS.'templates'.DS.'single.inc.php');

	} else {
		$page = mb_strtolower(path(0));

		// why?
		// if($page == '.well_known' && path(1) == 'webfinger') { $page = 'webfinger'; }

		switch($page) {
			case 'login':
				$template = 'login';
				require_once(ROOT.DS.'templates'.DS.'loginform.inc.php');
				break;
			case 'logout':
				$domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
				setcookie('microblog_login', '', time()-3600, '/', $domain, false);
				unset($_COOKIE['microblog_login']);

				header('Location: '.$config['url']);
				break;
			case 'new':
				$template = 'postform';
				require_once(ROOT.DS.'templates'.DS.'postform.inc.php');
				break;
			case 'rsd':
				require_once(ROOT.DS.'lib'.DS.'rsd.xml.php');
				break;
			case 'xmlrpc':
				require_once(ROOT.DS.'lib'.DS.'xmlrpc.php');
				break;
			case '.well-known':
				if(!empty(path(1)) && path(1) == 'webfinger') {
					require_once(ROOT.DS.'lib'.DS.'activitypub-webfinger.php');
				} else {
					http_response_code(404);
					die();
				}
				break;
			/*
			case 'webfinger':
				die('aaaa');
				if(!empty(path(1)) && path(1) == 'webfinger') {
					require_once(ROOT.DS.'lib'.DS.'activitypub-webfinger.php');
				} else {
					die('xxx');
					http_response_code(404);
					die();
				}
				die('abc');
				break;
			*/
			case 'actor':
				require_once(ROOT.DS.'lib'.DS.'activitypub-actor.php');
				break;
			case 'followers':
				require_once(ROOT.DS.'lib'.DS.'activitypub-followers.php');
				break;
			case 'outbox':
				require_once(ROOT.DS.'lib'.DS.'activitypub-outbox.php');
				break;
			case 'inbox':
				require_once(ROOT.DS.'lib'.DS.'activitypub-inbox.php');
				break;
			default:
				// redirect everything else to the homepage
				if(!empty(path(0)) && path(0) != 'page') {
					// die(path(0) . path(1) . 'WTF');
					header('Location: '.$config['url']);
					die();
				}

				// show the homepage
				require_once(ROOT.DS.'templates'.DS.'timeline.inc.php');
				break;
		}
	}
