<?php

//connect or create the database
try {
	$db = new PDO('sqlite:'.ROOT.DS.'posts.db');
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

	$config['db_version'] = $db->query("PRAGMA user_version")->fetch(PDO::FETCH_ASSOC)['user_version'];
} catch(PDOException $e) {
	print 'Exception : '.$e->getMessage();
	die('cannot connect to or open the database');
}

// first time setup
if($config['db_version'] == 0) {
	try {
		$db->exec("CREATE TABLE IF NOT EXISTS `posts` (
			`id` integer PRIMARY KEY NOT NULL,
			`post_content` TEXT,
			`post_timestamp` INTEGER
		); PRAGMA `user_version` = 1;");
		$config['db_version'] = 1;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot set up initial database table!');
	}
}

// upgrade database to v2
if($config['db_version'] == 1) {
	try {
		$db->exec("PRAGMA user_version = 2;
			ALTER TABLE `posts` ADD `post_thread` INTEGER;
			ALTER TABLE `posts` ADD `post_edited` INTEGER;
			ALTER TABLE `posts` ADD `post_deleted` INTEGER;
		");
		$config['db_version'] = 2;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v2!');
	}
}

// upgrade database to v3
if($config['db_version'] == 2) {
	try {
		$db->exec("PRAGMA user_version = 3;
			ALTER TABLE `posts` ADD `post_guid` TEXT;
		");
		$config['db_version'] = 3;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v3!');
	}
}

// debug: get a list of post table columns
// var_dump($db->query("PRAGMA table_info(`posts`)")->fetchAll(PDO::FETCH_COLUMN, 1));
