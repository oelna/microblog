<?php

$db_version = 0;

//connect or create the database
try {
	$db = new PDO('sqlite:'.ROOT.DS.'posts.db');
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

	$db_version = $db->query("PRAGMA user_version")->fetch(PDO::FETCH_ASSOC)['user_version'];
} catch(PDOException $e) {
	print 'Exception : '.$e->getMessage();
	die('cannot connect to or open the database');
}

// first time setup
if($db_version == 0) {
	try {
		$db->exec("PRAGMA `user_version` = 1;
			CREATE TABLE IF NOT EXISTS `posts` (
			`id` INTEGER PRIMARY KEY NOT NULL,
			`post_content` TEXT,
			`post_timestamp` INTEGER
		);");
		$db_version = 1;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot set up initial database table!');
	}
}

// upgrade database to v2
if($db_version == 1) {
	try {
		$db->exec("PRAGMA `user_version` = 2;
			ALTER TABLE `posts` ADD `post_thread` INTEGER;
			ALTER TABLE `posts` ADD `post_edited` INTEGER;
			ALTER TABLE `posts` ADD `post_deleted` INTEGER;
		");
		$db_version = 2;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v2!');
	}
}

// upgrade database to v3
if($db_version == 2) {
	try {
		$db->exec("PRAGMA `user_version` = 3;
			ALTER TABLE `posts` ADD `post_guid` TEXT;
		");
		$db_version = 3;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v3!');
	}
}

// upgrade database to v4
if($db_version == 3) {
	try {
		$db->exec("PRAGMA `user_version` = 4;
				CREATE TABLE `files` (
				`id` INTEGER PRIMARY KEY NOT NULL,
				`file_filename` TEXT NOT NULL,
				`file_extension` TEXT,
				`file_original` TEXT NOT NULL,
				`file_mime_type` TEXT,
				`file_size` INTEGER,
				`file_hash` TEXT UNIQUE,
				`file_hash_algo` TEXT,
				`file_meta` TEXT DEFAULT '{}',
				`file_dir` TEXT,
				`file_subdir` TEXT,
				`file_timestamp` INTEGER,
				`file_deleted` INTEGER
			);
			CREATE TABLE `file_to_post` (
				`file_id` INTEGER NOT NULL,
				`post_id` INTEGER NOT NULL,
				`deleted` INTEGER,
				UNIQUE(`file_id`, `post_id`) ON CONFLICT IGNORE
			);
			CREATE INDEX `posts_timestamp` ON posts (`post_timestamp`);
			CREATE INDEX `files_original` ON files (`file_original`);
			CREATE INDEX `link_deleted` ON file_to_post (`deleted`);
			CREATE UNIQUE INDEX `files_hashes` ON files (`file_hash`);
		");
		$db_version = 4;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v4!');
	}
}

// v5, update for activitypub
if($db_version == 4) {
	try {
		$db->exec("PRAGMA `user_version` = 5;
			CREATE TABLE IF NOT EXISTS `followers` (
				`id` INTEGER PRIMARY KEY NOT NULL,
				`follower_name` TEXT NOT NULL,
				`follower_host` TEXT NOT NULL,
				`follower_actor` TEXT,
				`follower_inbox` TEXT,
				`follower_shared_inbox` TEXT,
				`follower_added` INTEGER
			);
			CREATE UNIQUE INDEX `followers_users` ON followers (`follower_name`, `follower_host`);
			CREATE INDEX `followers_shared_inboxes` ON followers (`follower_shared_inbox`);
		");
		$db_version = 5;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v5!');
	}
}

// v6, update for activitypub likes and announces
if($db_version == 5) {
	try {
		$db->exec("PRAGMA `user_version` = 6;
			CREATE TABLE IF NOT EXISTS `activities` (
				`id` INTEGER PRIMARY KEY NOT NULL,
				`activity_actor_name` TEXT NOT NULL,
				`activity_actor_host` TEXT NOT NULL,
				`activity_type` TEXT NOT NULL,
				`activity_object_id` INTEGER NOT NULL,
				`activity_updated` INTEGER
			);
			CREATE INDEX `activities_objects` ON activities (`activity_object_id`);
			CREATE UNIQUE INDEX `activities_unique` ON activities (`activity_actor_name`, `activity_actor_host`, `activity_type`, `activity_object_id`);
		");
		$db_version = 6;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v6!');
	}
}

// v7, update for activitypub key storage
if($db_version == 6) {
	try {
		$db->exec("PRAGMA `user_version` = 7;
			CREATE TABLE IF NOT EXISTS `keys` (
				`id` INTEGER PRIMARY KEY NOT NULL,
				`key_private` TEXT NOT NULL,
				`key_public` TEXT NOT NULL,
				`key_algo` TEXT DEFAULT 'sha512',
				`key_bits` INTEGER DEFAULT 4096,
				`key_type` TEXT DEFAULT 'rsa',
				`key_created` INTEGER
			);
		");
		$db_version = 7;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v7!');
	}
}

// v8, update for config/settings key/value storage
if($db_version == 7) {
	try {
		$db_version += 1;
		$install_signature = bin2hex(random_bytes(16));
		$db->exec("PRAGMA `user_version` = ".$db_version.";
			CREATE TABLE IF NOT EXISTS `settings` (
				`id` INTEGER PRIMARY KEY NOT NULL,
				`settings_key` TEXT NOT NULL UNIQUE,
				`settings_value` TEXT,
				`settings_value_previous` TEXT,
				`settings_updated` INTEGER
			);
			CREATE UNIQUE INDEX `settings_keys` ON settings (`settings_key`);
			INSERT INTO `settings` (settings_key, settings_value, settings_updated) VALUES ('installation_signature', '".$install_signature."', ".NOW.");
			INSERT INTO `settings` (settings_key, settings_value, settings_updated) VALUES ('do_setup', '1', ".NOW.");
			INSERT INTO `settings` (settings_key, settings_value, settings_updated) VALUES ('passkey', '', ".NOW.");
		");
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v'.$db_version.'!');
	}
}

// debug: get a list of post table columns
// var_dump($db->query("PRAGMA table_info(`posts`)")->fetchAll(PDO::FETCH_COLUMN, 1));
