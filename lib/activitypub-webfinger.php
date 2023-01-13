<?php

	// todo: handle empty usernames

	$actor = ltrim($config['microblog_account'], '@');
	$url = parse_url($config['url']);
	$domain = $url['host'];
	if(!empty($url['path'])) $domain .= rtrim($url['path'], '/');

	$data = [
		'subject' => 'acct:'.$actor.'@'.$domain,
		'links' => [
			[
				'rel' => 'self',
				'type' => 'application/activity+json',
				'href' => $config['url'].'/actor'
			]
		]
	];

	header('Content-Type: application/ld+json');
	echo(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
