<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
PLEASE NOTE THAT AS OF RELEASE 2.1
THERE IS NO CONFIGURATION TO BE MADE
IN THIS FILE. PLEASE USE the /settings
PAGE TO CONFIGURE YOUR MICROBLOG.

THE CONFIG FILE MAY GO AWAY IN THE
FUTURE.
*/

DEFINE('ROOT', __DIR__);
DEFINE('DS', DIRECTORY_SEPARATOR);
DEFINE('NL', "\n");
DEFINE('BR', "<br />");
DEFINE('NOW', time());

// set up database connection
require_once(ROOT.DS.'lib'.DS.'database.php');

/* make the path easier to read */
$dir = dirname($_SERVER['SCRIPT_NAME']);
$uri = $_SERVER['REQUEST_URI'];
$uri = substr($uri, mb_strlen($dir)); // handle subdir installs
$path_fragments = parse_url($uri, PHP_URL_PATH);
$path = (empty($path_fragments)) ? [''] : explode('/', trim($path_fragments, '/'));
if(mb_strlen($path[0]) == 0) { $path = []; }

// load settings
$statement = $db->prepare('SELECT * FROM settings');
$statement->execute();
$settings_raw = $statement->fetchAll(PDO::FETCH_ASSOC);

$default_settings = array(
	'url' => '',
	'path' => __DIR__,
	'language' => 'en',
	'max_characters' => 280,
	'posts_per_page' => 10,
	'theme' => 'plain',
	'microblog_account' => '',
	'site_title' => 'Another Microblog',
	'site_claim' => 'This is an automated account. Don\'t mention or reply please.',
	'admin_user' => 'admin',
	'admin_pass' => '',
	'app_token' => '',
	'cookie_life' => 60*60*24*7*4,
	'ping' => true,
	'activitypub' => true,
	'show_edits' => true,
	'local_timezone' => 'Europe/Berlin'
);

if(!empty($settings_raw)) {
	// create config array
	$settings = array_column($settings_raw, 'settings_value', 'settings_key');
} else {
	// there were no settings in the DB. initialize!
	$settings = [];
	
	$old_config = $config ?? [];
	$settings = array_merge($default_settings, $old_config); // respect existing config file
}

$config = array_merge($default_settings, $settings); // handle fresh install case where $settings is mostly empty
$settings = $config;  

date_default_timezone_set($config['local_timezone']);
$config['path'] = $path;
$config['url_detected'] = 'http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.$_SERVER['HTTP_HOST'].rtrim($dir, '/');
$config['subdir_install'] = ($dir === '/') ? false : true;
$config['xmlrpc'] = function_exists('xmlrpc_server_create');
$config['local_time_offset'] = date('P');

unset($dir, $uri, $path_fragments, $path);

// load functions
require_once(ROOT.DS.'lib'.DS.'functions.php');
require_once(ROOT.DS.'lib'.DS.'autolink.php');
