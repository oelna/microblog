<?php

// PASSKEY MODULE FOR MICROBLOG

// a lot of the following code has been taken from
// https://github.com/craigfrancis/webauthn-tidy (BSD 3)
// Copyright 2020 Craig Francis
// with modifications by Arno Richter in 2023
// for his Microblog software

$host = parse_url($config['url'], PHP_URL_HOST);
$origin = $config['url'];
$algorithm = -7; // Elliptic curve algorithm ECDSA with SHA-256

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = !empty($_GET['q']) ? mb_strtolower(trim($_GET['q'])) : path(1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// only support the specified actions
if(!in_array($action, ['create', 'store', 'login', 'verify', 'revoke'])) {
	echo(json_encode([
		'result' => -2,
		'errors' => ['Method not supported']
	]));
	exit();
}

// Challenge
if ($method == 'GET') {
	$_SESSION['challenge'] = random_bytes(32);
}
$challenge = ($_SESSION['challenge'] ?? '');

// If submitted
$errors = [];
if ($action == 'store') {
	if(!$config['logged_in']) {
		header('HTTP/1.0 401 Unauthorized');
		echo(json_encode(['errors' => ['Unauthorized access!']]));
		exit();
	}	

	$data = file_get_contents('php://input');
	if(empty($data)) exit('{}');

	// Parse
	$webauthn_data = json_decode($data, true);

	// Client data
	$client_data_json = base64_decode($webauthn_data['response']['clientDataJSON'] ?? '');
	$client_data = json_decode($client_data_json, true);

	// Auth data
	$auth_data = base64_decode($webauthn_data['response']['authenticatorData']);

	$auth_data_relying_party_id = substr($auth_data, 0, 32); // rpIdHash
	$auth_data_flags = substr($auth_data, 32, 1);
	$auth_data_sign_count = substr($auth_data, 33, 4);
	$auth_data_sign_count = intval(implode('', unpack('N*', $auth_data_sign_count))); // 32-bit unsigned big-endian integer

	// Checks basic
	if (($webauthn_data['type'] ?? '') !== 'public-key') {
		$errors[] = 'Returned type is not a "public-key".';
	}

	if (($client_data['type'] ?? '') !== 'webauthn.create') {
		$errors[] = 'Returned type is not "webauthn.create".';
	}

	if (($client_data['origin'] ?? '') !== $origin) {
		$errors[] = 'Returned origin is not "' . $origin . '".';
	}

	if (strlen($auth_data_relying_party_id) != 32 || !hash_equals(hash('sha256', $host), bin2hex($auth_data_relying_party_id))) {
		$errors[] = 'The Relying Party ID hash is not the same.';
	}

	// Check challenge
	$response_challenge = ($client_data['challenge'] ?? '');
	$response_challenge = base64_decode(strtr($response_challenge, '-_', '+/'));

	if (!$challenge) {
		$errors[] = 'The challenge has not been stored in the session.';
	} else if (substr_compare($response_challenge, $challenge, 0) !== 0) {
		$errors[] = 'The challenge has changed.';
	}

	// Only use $challenge check for attestation

	// Get public key
	$key_der = ($webauthn_data['response']['publicKey'] ?? NULL);
	if (!$key_der) {
		$errors[] = 'No public key found.';
	}

	if (($webauthn_data['response']['publicKeyAlg'] ?? NULL) !== $algorithm) {
		$errors[] = 'Different algorithm used.';
	}

	// Store
	if (count($errors) == 0) {

		//file_put_contents(ROOT.DS.'pk-log.txt', json_encode([$key_der, $webauthn_data]));

		try {
			$statement = $db->prepare('INSERT OR REPLACE INTO settings (settings_key, settings_value, settings_updated) VALUES (:settings_key, :settings_value, :settings_updated)');
			$statement->bindValue(':settings_key', 'passkey', PDO::PARAM_STR);
			$statement->bindValue(':settings_value', json_encode($webauthn_data), PDO::PARAM_STR);
			$statement->bindValue(':settings_updated', time(), PDO::PARAM_INT);

			$statement->execute();

			$id = $db->lastInsertId() || -1;

		} catch(PDOException $e) {
			$errors[] = 'Exception : '.$e->getMessage();
			$id = -1;
		}
	}

	// Show errors
	echo(json_encode([
		'result' => $id,
		'errors' => $errors
	]));
	exit();
}

// Request
if($action == 'create') {

	$request = [
		'publicKey' => [
			'rp' => [
				'name' => 'Microblog',
				'id' => $host,
				'icon' => $config['url'].'/favicon-large.png' // is this real?
			],
			'user' => [
				'id' => 1,
				'name' => 'admin',
				'displayName' => 'admin',
			],
			'challenge' => base64_encode($challenge),
			'pubKeyCredParams' => [
				[
					'type' => "public-key",
					'alg' => $algorithm,
				]
			],
			'authenticatorSelection' => [
				'authenticatorAttachment' => 'platform' // platform, cross-platform
			],
			'timeout' => 60000, // In milliseconds
			'attestation' => 'none', // "none", "direct", "indirect"
			'excludeCredentials' => [ // Avoid creating new public key credentials (e.g. existing user who has already setup WebAuthn). This is filled in L170+
				/*
				[
					'type' => "public-key",
					'id' => $passkey['id'],
				],
				*/
			],
			'userVerification' => 'discouraged'
		],
	];

	// prevent duplicate setup
	if(!empty($config['passkey'])) {
		$passkey = json_decode($config['passkey'], true);

		$request['publicKey']['excludeCredentials'][] = [
			'type' => "public-key",
			'id' => $passkey['id'] // Base64-encoded value (?)
		];
	}

	echo(json_encode($request));
	exit();
}

if($action == 'login') {

	$passkey_json = db_get_setting('passkey');
	$passkey = null;
	if($passkey_json) {
		$passkey = json_decode($passkey_json, true);
	}

	$create_auth = $passkey_json; // Only for debugging.

	$user_key_id = ($passkey['id'] ?? '');
	$user_key_value = ($passkey['response']['publicKey'] ?? '');

	if (!$user_key_id) {
		exit('Missing user key id.');
	}

	if (!$user_key_value) {
		exit('Missing user key value.');
	}

	$request = [
		'publicKey' => [
			'rpId' => $host,
			'challenge' => base64_encode($challenge),
			'timeout' => 60000, // In milliseconds
			'allowCredentials' => [
				[
					'type' => 'public-key',
					'id' => $user_key_id,
				]
			],
			'userVerification' => 'discouraged'
		]
	];

	echo(json_encode($request));
	exit();
}

if($action == 'revoke') {
	if(!$config['logged_in']) {
		header('HTTP/1.0 401 Unauthorized');
		echo(json_encode(['errors' => ['Unauthorized access!']]));
		exit();
	}

	$result = 0;
	$errors = [];
	try {
		// $statement = $db->prepare('DELETE FROM settings WHERE settings_key = "passkey"');
		$statement = $db->prepare('UPDATE settings SET settings_value = "" WHERE settings_key = "passkey"');
		$statement->execute();

		$result = $statement->rowCount();
	} catch(PDOException $e) {
		$result = -1;
		$errors[] = 'Exception : '.$e->getMessage();
	}

	echo(json_encode([
		'result' => $result,
		'errors' => $errors
	]));
	exit();
}

if($action == 'verify') {
	$data = file_get_contents('php://input');
	$errors = [];

	$passkey_json = db_get_setting('passkey');
	$passkey = null;
	if($passkey_json) {
		$passkey = json_decode($passkey_json, true);
	}

	$webauthn_data = json_decode($data, true);

	$create_auth = $passkey_json; // Only for debugging.

	$user_key_id = ($passkey['id'] ?? '');
	$user_key_value = ($passkey['response']['publicKey'] ?? '');

	if (!$user_key_id) {
		exit('Missing user key id.');
	}

	if (!$user_key_value) {
		exit('Missing user key value.');
	}

	$client_data_json = base64_decode($webauthn_data['response']['clientDataJSON'] ?? '');
	$client_data = json_decode($client_data_json, true);

	$auth_data = base64_decode($webauthn_data['response']['authenticatorData']);

	$auth_data_relying_party_id = substr($auth_data, 0, 32); // rpIdHash
	$auth_data_flags = substr($auth_data, 32, 1);
	$auth_data_sign_count = substr($auth_data, 33, 4);
	$auth_data_sign_count = intval(implode('', unpack('N*', $auth_data_sign_count))); // 32-bit unsigned big-endian integer

	// Checks basic
	if (($webauthn_data['id'] ?? '') !== $user_key_id) {
		$errors[] = 'Returned type is not for the same id.';
	}

	if (($webauthn_data['type'] ?? '') !== 'public-key') {
		$errors[] = 'Returned type is not a "public-key".';
	}

	if (($client_data['type'] ?? '') !== 'webauthn.get') {
		$errors[] = 'Returned type is not "webauthn.get".';
	}

	if (($client_data['origin'] ?? '') !== $origin) {
		$errors[] = 'Returned origin is not "' . $origin . '".';
	}

	if (strlen($auth_data_relying_party_id) != 32 || !hash_equals(hash('sha256', $host), bin2hex($auth_data_relying_party_id))) {
		$errors[] = 'The Relying Party ID hash is not the same.';
	}

	// Check challenge
	$response_challenge = ($client_data['challenge'] ?? '');
	$response_challenge = base64_decode(strtr($response_challenge, '-_', '+/'));

	if (!$challenge) {
		$errors[] = 'The challenge has not been stored in the session.';
	} else if (substr_compare($response_challenge, $challenge, 0) !== 0) {
		$errors[] = 'The challenge has changed.';
	}

	// Check signature
	$signature = ($webauthn_data['response']['signature'] ?? '');
	if ($signature) {
		$signature = base64_decode($signature);
	}

	if (!$signature) {
		$errors[] = 'No signature returned.';
	}

	// Key
	$key_info = NULL;
	if (count($errors) == 0) {

		$user_key_pem  = '-----BEGIN PUBLIC KEY-----' . "\n";
		$user_key_pem .= wordwrap($user_key_value, 64, "\n", true) . "\n";
		$user_key_pem .= '-----END PUBLIC KEY-----';

		$key_ref = openssl_pkey_get_public($user_key_pem);

		if ($key_ref === false) {
			$errors[] = 'Public key invalid.';
		} else {
			$key_info = openssl_pkey_get_details($key_ref);

			if ($key_info['type'] == OPENSSL_KEYTYPE_EC) {
				if ($key_info['ec']['curve_oid'] != '1.2.840.10045.3.1.7') {
					$errors[] = 'Invalid public key curve identifier';
				}
			} else {
				$errors[] = 'Unknown public key type (' . $key_info['type'] . ')';
			}
		}
	}

	// Check
	$result = 0;
	if (count($errors) == 0) {

		$verify_data  = '';
		$verify_data .= $auth_data;
		$verify_data .= hash('sha256', $client_data_json, true); // Contains the $challenge

		if (openssl_verify($verify_data, $signature, $key_ref, OPENSSL_ALGO_SHA256) === 1) {
			$result = 1;

			// set the login cookie
			$config['logged_in'] = check_login(true);
		} else {
			$errors[] = 'Invalid signature.';
			$result = -1;
		}
	}

	echo(json_encode([
		'result' => $result,
		'errors' => $errors
	]));
	exit();
}
