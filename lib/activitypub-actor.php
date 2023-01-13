<?php

	if(!file_exists(ROOT.DS.'keys'.DS.'id_rsa')) {
		if(!is_dir(ROOT.DS.'keys')) {
			mkdir(ROOT.DS.'keys');
		}

		// generate a key pair, if neccessary

		$rsa = openssl_pkey_new([ 
			'digest_alg' => 'sha512',
			'private_key_bits' => 4096,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);
		openssl_pkey_export($rsa, $private_key);
		$public_key = openssl_pkey_get_details($rsa)['key'];

		file_put_contents(ROOT.DS.'keys'.DS.'id_rsa', $private_key);
		file_put_contents(ROOT.DS.'keys'.DS.'id_rsa.pub', $public_key);
	} else {
		$public_key = file_get_contents(ROOT.DS.'keys'.DS.'id_rsa.pub');
	}

	

	/*

	$data = [
		'subject' => 'acct:'.$actor.'@'.$domain,
		'links' => [
			'rel' => 'self',
			'type' => 'application/activity+json',
			'href' => $config['url'].'/actor'
		]
	];
	*/

	header('Content-Type: application/ld+json');
	// echo(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
?>{
	"@context": [
		"https://www.w3.org/ns/activitystreams",
		"https://w3id.org/security/v1"
	],

	"id": "<?= $config['url'] ?>/actor",
	"type": "Person",
	"preferredUsername": "<?= ltrim($config['microblog_account'], '@') ?>",
	"inbox": "<?= $config['url'] ?>/inbox",

	"publicKey": {
		"id": "<?= $config['url'] ?>/actor#main-key",
		"owner": "<?= $config['url'] ?>/actor",
		"publicKeyPem": "<?= preg_replace('/\n/', '\n', $public_key) ?>"
	}
}
