<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

DEFINE('ROOT', __DIR__);
DEFINE('DS', DIRECTORY_SEPARATOR);
DEFINE('NL', "\n");
DEFINE('BR', "<br />");
DEFINE('NOW', time());

date_default_timezone_set('Europe/Berlin');

/* make the path easier to read */
$path_fragments = (parse_url(str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $_SERVER['REQUEST_URI']), PHP_URL_PATH));
$path = explode('/', trim($path_fragments, '/'));
if(mb_strlen($path[0]) == 0) $path = array();

// (mostly) user settings
$config = array(
	'url' => 'http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.$_SERVER['SERVER_NAME'].dirname($_SERVER['SCRIPT_NAME']),
	'path' => $path,
	'language' => 'en',
	'max_characters' => 280,
	'posts_per_page' => 10,
	'microblog_account' => '', // fill in a @username if you like
	'admin_user' => 'admin',
	'admin_pass' => 'dove-life-bird-lust',
	'cookie_life' => 60*60*24*7*4, // cookie life in seconds
	'ping' => true, // enable automatic pinging of the micro.blog service
	'crosspost_to_twitter' => false, // set this to true to automatically crosspost to a twitter account (requires app credentials, see below)
	'twitter' => array( // get your tokens over at https://dev.twitter.com/apps
		'oauth_access_token' => '',
		'oauth_access_token_secret' => '',
		'consumer_key' => '',
		'consumer_secret' => ''
	)
);

//connect or create the database and tables
try {
	$db = new PDO('sqlite:'.ROOT.DS.'posts.db');
	$db->exec("CREATE TABLE IF NOT EXISTS posts (
		id integer PRIMARY KEY NOT NULL,
		post_content TEXT,
		post_timestamp integer(128)
	);");
} catch(PDOException $e) {
	print 'Exception : '.$e->getMessage();
	die('cannot connect to or open the database');
}

// load functions
require_once(ROOT.DS.'functions.php');
require_once(ROOT.DS.'lib_autolink.php');