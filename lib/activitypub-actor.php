<?php

	if(!$config['activitypub']) exit('ActivityPub is disabled via config file.');

	$public_key = activitypub_get_key('public');

	// generate a key pair, if neccessary
	if(!$public_key) {
		$key = activitypub_new_key('sha512', 4096, 'RSA');

		if(!empty($key)) exit('Fatal error: Could not generate a new key!');
		$public_key = $key['key_public'];
	}

	/*
	// old, file-based key system
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
	*/

	if(strpos($_SERVER['HTTP_ACCEPT'], 'application/activity+json') !== false):

		header('Content-Type: application/ld+json');

?>{
	"@context": [
		"https://www.w3.org/ns/activitystreams",
		"https://w3id.org/security/v1"
	],
	"id": "<?= $config['url'] ?>/actor",
	"type": "Person",
	"name": "<?= trim($config['site_title']) ?>",
	"summary": "<?= trim($config['site_claim']) ?>",
	"preferredUsername": "<?= ltrim($config['microblog_account'], '@') ?>",
	"manuallyApprovesFollowers": false,
	"discoverable": true,
	"publishedDate": "2023-01-01T00:00:00Z",
	"icon": {
		"url": "<?= $config['url'] ?>/favicon-large.png",
		"mediaType": "image/png",
		"type": "Image"
	},
	"inbox": "<?= $config['url'] ?>/inbox",
	"outbox": "<?= $config['url'] ?>/outbox",
	"followers": "<?= $config['url'] ?>/followers",
	"publicKey": {
		"id": "<?= $config['url'] ?>/actor#main-key",
		"owner": "<?= $config['url'] ?>/actor",
		"publicKeyPem": "<?= preg_replace('/\n/', '\n', $public_key) ?>"
	}
}
<?php
	else:
		 // this is for people who click through to the profile URL in their mastodon client
		header('Location: '.$config['url']);
		exit();
	endif;
?>
