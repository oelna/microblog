<?php
	$config_file = __DIR__.DIRECTORY_SEPARATOR.'config.php';
	$config_file_default = __DIR__.DIRECTORY_SEPARATOR.'config-dist.php';
	if(!include_once($config_file)) {
		if(file_exists($config_file_default)) {
			copy($config_file_default, $config_file);
			chmod($config_file, 0644);
			header('Refresh:1');
			exit();
		}
	}

	// check if we are running for the first time
	$is_setup = (isset($settings) && !empty($settings['do_setup']) && $settings['do_setup'] == 1) ? true : false;

	if($is_setup && path(0) !== 'settings') {
		// first time setup
		header('Location: '.$config['url_detected'].'/settings');
		die();
	}

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

		switch($page) {
			case 'login':
				$template = 'login';
				require_once(ROOT.DS.'templates'.DS.'loginform.inc.php');
				break;
			case 'logout':
				$host = get_host(false); // cookies are port-agnostic
				$domain = ($host != 'localhost') ? $host : false;
				setcookie('microblog_login', '', time()-3600, '/', $domain, false);
				unset($_COOKIE['microblog_login']);

				header('Location: '.$config['url']);
				break;
			case 'new':
				$template = 'postform';
				require_once(ROOT.DS.'templates'.DS.'postform.inc.php');
				break;
			case 'settings':
				$template = 'settings';
				require_once(ROOT.DS.'templates'.DS.'settings.inc.php');
				break;
			case 'rsd':
				require_once(ROOT.DS.'lib'.DS.'rsd.xml.php');
				break;
			case 'xmlrpc':
				require_once(ROOT.DS.'lib'.DS.'xmlrpc.php');
				break;
			case 'pk':
				require_once(ROOT.DS.'lib'.DS.'passkeys.php');
				break;
			case '.well-known':
				if(!empty(path(1)) && path(1) == 'webfinger') {
					require_once(ROOT.DS.'lib'.DS.'activitypub-webfinger.php');
				} else {
					http_response_code(404);
					die();
				}
				break;
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
			case 'recovery':
				// password recovery via email
				if(!empty($config['admin_email'])) {

					if(!empty(path(1))) {
						// get magic link
						list('settings_value' => $bytes, 'settings_updated' => $age) = db_get_setting('magic_url', true);
						if(empty($bytes)) exit('Invalid URL');

						// validate
						if(path(1) === $bytes) {

							// check link age (valid for 1h)
							if($age > NOW - 3600) {
								$config['logged_in'] = check_login(true); // force entry!

								header('Location: '.$config['url'].'/settings');
								exit('Success');
							}

							exit('Link has expired');
						} else {
							exit('Invalid URL');
						}
					} else {
						// send a recovery email with link
						$bytes = bin2hex(random_bytes(16));
						$magic_link = $config['url'].'/recovery/'.$bytes;

						db_set_setting('magic_url', $bytes);

						$mailtext  = 'Your recovery link for Microblog:'.NL;
						$mailtext .= $magic_link.NL;

						$host = parse_url($config['url'], PHP_URL_HOST);
						$headers = array(
							'From' => 'admin@'.$host,
							'Reply-To' => 'admin@'.$host,
							'X-Mailer' => 'PHP/' . phpversion()
						);

						if(mail(trim($config['admin_email']), 'Your Microblog recovery link', $mailtext, $headers)) {
							// var_dump($mailtext);
							header('Location: '.$config['url'].'/login/recovery');
						} else {
							exit('Could not send email with recovery link!');
						}
					}
				}

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
