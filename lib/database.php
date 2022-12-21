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
		$db->exec("PRAGMA `user_version` = 1;
			CREATE TABLE IF NOT EXISTS `posts` (
			`id` INTEGER PRIMARY KEY NOT NULL,
			`post_content` TEXT,
			`post_timestamp` INTEGER
		);");
		$config['db_version'] = 1;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot set up initial database table!');
	}
}

// upgrade database to v2
if($config['db_version'] == 1) {
	try {
		$db->exec("PRAGMA `user_version` = 2;
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
		$db->exec("PRAGMA `user_version` = 3;
			ALTER TABLE `posts` ADD `post_guid` TEXT;
		");
		$config['db_version'] = 3;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v3!');
	}
}

// upgrade database to v4
if($config['db_version'] == 3) {
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
		$config['db_version'] = 4;
	} catch(PDOException $e) {
		print 'Exception : '.$e->getMessage();
		die('cannot upgrade database table to v4!');
	}
}

// debug: get a list of post table columns
// var_dump($db->query("PRAGMA table_info(`posts`)")->fetchAll(PDO::FETCH_COLUMN, 1));
