<?php

$request_xml = file_get_contents("php://input");

// check prerequisites
if(!function_exists('xmlrpc_server_create')) { exit('No XML-RPC support detected!'); }
if(empty($request_xml)) { exit('XML-RPC server accepts POST requests only.'); }

// load config
require_once(__DIR__.DIRECTORY_SEPARATOR.'config.php');

if(!function_exists('str_starts_with')) {
	function str_starts_with($haystack, $needle) {
		if(empty($needle)) return true;
		return mb_substr($haystack, 0, mb_strlen($needle)) === $needle;
	}
}

function check_credentials($username, $password) {
	global $config;

	$xmlrpc_auth = $config['admin_pass'];
	if(!empty($config['app_token'])) {
		$xmlrpc_auth = $config['app_token'];
	}

	if($username == $config['admin_user'] && $password == $xmlrpc_auth) {
		return true;
	} else {
		return false;
	}
}

function say_hello($method_name, $args) {
	return 'Hello';
}

function make_post($post, $method='metaWeblog') {
	global $config;

	$date_created = date('Y-m-d\TH:i:s', $post['post_timestamp']).$config['local_time_offset'];
	$date_created_gmt = gmdate('Y-m-d\TH:i:s', $post['post_timestamp']).'Z';
	if(!empty($post['post_edited']) && !is_null($post['post_edited'])) {
		$date_modified = date('Y-m-d\TH:i:s', $post['post_edited']).$config['local_time_offset'];
		$date_modified_gmt = gmdate('Y-m-d\TH:i:s', $post['post_edited']).'Z';
	} else {
		$date_modified = date('Y-m-d\TH:i:s', 0).$config['local_time_offset'];
		$date_modified = null;
		$date_modified_gmt = gmdate('Y-m-d\TH:i:s', 0).'Z';
	}

	@xmlrpc_set_type($date_created, 'datetime');
	@xmlrpc_set_type($date_created_gmt, 'datetime');
	@xmlrpc_set_type($date_modified, 'datetime');
	@xmlrpc_set_type($date_modified_gmt, 'datetime');

	if(str_starts_with($method, 'microblog')) {
		// convert the post format to a microblog post
		// similar to metaWeblog.recentPosts but with offset parameter for paging,
		// consistent field names
		$mb_post = [
			'id' => (int) $post['id'],
			'date_created' => $date_created,
			'date_modified' => $date_modified,
			'permalink' => $config['url'].'/'.$post['id'],
			'title' => '',
			'description' => ($post['post_content']),
			'categories' => [],
			'post_status' => 'published',
			'author' => [
				'name' => $config['microblog_account'],
				'username' => $config['admin_user']
			]
		];

		return $mb_post;
	} else {
		// convert the post format to a standard metaWeblog post
		$mw_post = [
			'postid' => (int) $post['id'],
			'title' => '',
			'description' => ($post['post_content']), // Post content
			'link' => $config['url'].'/'.$post['id'], // Post URL
			// string userid†: ID of post author.
			'dateCreated' => $date_created,
			'date_created_gmt' => $date_created_gmt,
			'date_modified' => $date_modified,
			'date_modified_gmt' => $date_modified_gmt,
			// string wp_post_thumbnail†
			'permalink' => $config['url'].'/'.$post['id'], // Post URL, equivalent to link.
			'categories' => [], // Names of categories assigned to the post.
			'mt_keywords' => '', // Names of tags assigned to the post.
			'mt_excerpt' => '',
			'mt_text_more' => '', // Post "Read more" text.
		];

		return $mw_post;
	}
}

function mw_get_recent_posts($method_name, $args) {

	list($_, $username, $password, $amount) = $args;
	$offset = 0;
	if($method_name == 'microblog.getPosts' && !empty($args[4])) {
		$offset = $args[4];
	}

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	if(!$amount) $amount = 25;
	$amount = min($amount, 200); // cap the max available posts at 200 (?)

	$posts = db_select_posts(null, $amount, 'asc', $offset);
	var_dump($posts);
	if(empty($posts)) return [];

	if($method_name == 'microblog.getPosts') {
		// currently the same as metaWeblog
		/*
		$mw_posts = array_map(
			function($posts) {
				return make_post($posts, 'microblog');
			},
			$posts
		);
		*/
		// https://stackoverflow.com/a/22735187/3625228
		$mw_posts = array_map('make_post', $posts, array_fill(0, count($posts), $method_name));
	} else {
		$mw_posts = array_map('make_post', $posts);
	}

	return $mw_posts;
}

function mw_get_post($method_name, $args) {

	list($post_id, $username, $password) = $args;

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	$post = db_select_post($post_id);
	if($post) {
		if($method_name == 'microblog.getPost') {
			$mw_post = make_post($post, $method_name);
		} else {
			$mw_post = make_post($post);
		}
		
		return $mw_post;
	} else {
		return [
			'faultCode' => 400,
			'faultString' => 'Could not fetch post.'
		];
	}
}

function mw_delete_post($method_name, $args) {

	if($method_name == 'microblog.deletePost') {
		list($post_id, $username, $password) = $args;
	} else {
		// blogger.deletePost
		list($_, $post_id, $username, $password, $_) = $args;
	}

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	$success = db_delete($post_id);
	if($success > 0) {
		rebuild_feeds();

		return true;
	} else {
		return [
			'faultCode' => 400,
			'faultString' => 'Could not delete post.'
		];
	}
}

function mw_new_post($method_name, $args) {

	// blog_id, unknown, unknown, array of post content, unknown
	list($blog_id, $username, $password, $content, $_) = $args;

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	if($method_name == 'microblog.newPost') {
		$post = [
			// 'post_title' => $content['title'],
			'post_content' => $content['description'],
			'post_timestamp' => time(),
			// 'post_categories' => $content['categories'],
			// 'post_status' => $content['post_status'],
		];

		// use a specific timestamp, if provided
		if(isset($content['date_created'])) {
			$post['post_timestamp'] = $content['date_created']->timestamp;
		}
	} else {
		$post = [
			// 'post_hp' => $content['flNotOnHomePage'],
			'post_timestamp' => time(),
			// 'post_title' => $content['title'],
			'post_content' => $content['description'],
			// 'post_url' => $content['link'],
		];

		// use a specific timestamp, if provided
		if(isset($content['dateCreated'])) {
			$post['post_timestamp'] = $content['dateCreated']->timestamp;
		}
	}

	$insert_id = db_insert($post['post_content'], $post['post_timestamp']);
	if($insert_id && $insert_id > 0) {
		// success
		rebuild_feeds();

		return (int) $insert_id;
	} else {
		// insert failed
		// error codes: https://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php
		// more error codes? https://github.com/zendframework/zend-xmlrpc/blob/master/src/Fault.php
		return [
			'faultCode' => 400,
			'faultString' => 'Could not create post.'
		];
	}
}

function mw_edit_post($method_name, $args) {

	// post_id, unknown, unknown, array of post content
	list($post_id, $username, $password, $content, $_) = $args;

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	if($method_name == 'microblog.editPost') {
		$post = [
			// 'post_title' => $content['title'],
			'post_content' => $content['description'],
			'post_timestamp' => null,
			// 'post_categories' => $content['categories'],
			// 'post_status' => $content['post_status'],
		];

		if(!empty($content['date_created'])) {
			$post['post_timestamp'] = $content['date_created']->timestamp;
		}
	} else {
		$post = [
			// 'post_hp' => $content['flNotOnHomePage'],
			// 'post_title' => $content['title'],
			'post_timestamp' => null,
			'post_content' => $content['description'],
			// 'post_url' => $content['link'],
		];

		if(empty($content['dateCreated'])) {
			$post['post_timestamp'] = $content['dateCreated']->timestamp;
		}
	}

	$update = db_update($post_id, $post['post_content'], $post['post_timestamp']);
	if($update && $update > 0) {
		// success
		rebuild_feeds();

		return true;
	} else {
		return [
			'faultCode' => 400,
			'faultString' => 'Could not write post update.'
		];
	}
}

function mw_get_categories($method_name, $args) {

	list($_, $username, $password) = $args;

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	// we don't support categories, so only return a fake one
	if($method_name == 'microblog.getCategories') {
		$categories = [
			[
				'id' => '1',
				'name' => 'default',
			]
		];
	} else {
		$categories = [
			[
				'description' => 'Default',
				'htmlUrl' => '',
				'rssUrl' => '',
				'title' => 'default',
				'categoryid' => '1',
			]
		];
	}

	return $categories;
}

function mw_get_users_blogs($method_name, $args) {
	global $config;

	list($_, $username, $password) = $args;

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	$bloginfo = [
		'blogid' => '1',
		'url' => $config['url'],
		'blogName' => (empty($config['microblog_account']) ? "" : $config['microblog_account'] . "'s ").' microblog',
	];

	return $bloginfo;
}

function mw_get_user_info($method_name, $args) {
	global $config;

	list($_, $username, $password) = $args;

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	$userinfo = [
		'userid' => '1',
		'firstname' => '',
		'lastname' => '',
		'nickname' => $config['microblog_account'],
		'email' => '',
		'url' => $config['url'],
	];

	return $userinfo;
}

// micro.blog API methods
// https://help.micro.blog/t/micro-blog-xml-rpc-api/108

// currently, the functions can be reused directly (disabled)
/*
use function mw_get_recent_posts as mb_get_posts;
use function mw_get_post as mb_get_post;
use function mw_delete_post as mb_delete_post;
use function mw_get_categories as mb_get_categories;
use function mw_new_post as mb_new_post;
use function mw_edit_post as mb_edit_post;
*/
// use function mw_new_mediaobject as mb_new_mediaobject; // unsupported

// https://codex.wordpress.org/XML-RPC_MetaWeblog_API
// https://community.devexpress.com/blogs/theprogressbar/metablog.ashx
// idea: http://www.hixie.ch/specs/pingback/pingback#TOC3
$server = xmlrpc_server_create();
xmlrpc_server_register_method($server, 'demo.sayHello', 'say_hello');
xmlrpc_server_register_method($server, 'blogger.getUsersBlogs', 'mw_get_users_blogs');
xmlrpc_server_register_method($server, 'blogger.getUserInfo', 'mw_get_user_info');
xmlrpc_server_register_method($server, 'metaWeblog.getCategories', 'mw_get_categories');
xmlrpc_server_register_method($server, 'metaWeblog.getRecentPosts', 'mw_get_recent_posts');
xmlrpc_server_register_method($server, 'metaWeblog.getPosts', 'mw_get_recent_posts'); // convenience
xmlrpc_server_register_method($server, 'metaWeblog.newPost', 'mw_new_post');
xmlrpc_server_register_method($server, 'metaWeblog.editPost', 'mw_edit_post');
xmlrpc_server_register_method($server, 'metaWeblog.getPost', 'mw_get_post');
xmlrpc_server_register_method($server, 'blogger.deletePost', 'mw_delete_post');
xmlrpc_server_register_method($server, 'metaWeblog.deletePost', 'mw_delete_post'); // does this exist?
// xmlrpc_server_register_method($server, 'metaWeblog.newMediaObject', 'mw_new_mediaobject'); // todo

// micro.blog methods
xmlrpc_server_register_method($server, 'microblog.getPosts', 'mw_get_recent_posts');
xmlrpc_server_register_method($server, 'microblog.getPost', 'mw_get_post');
xmlrpc_server_register_method($server, 'microblog.newPost', 'mw_new_post');
xmlrpc_server_register_method($server, 'microblog.editPost', 'mw_edit_post');
xmlrpc_server_register_method($server, 'microblog.deletePost', 'mw_delete_post');
xmlrpc_server_register_method($server, 'microblog.getCategories', 'mw_get_categories');
// xmlrpc_server_register_method($server, 'microblog.newMediaObject', 'mb_new_mediaobject');

/*
// pages are not supported
xmlrpc_server_register_method($server, 'microblog.getPages', 'say_hello');
xmlrpc_server_register_method($server, 'microblog.getPage', 'say_hello');
xmlrpc_server_register_method($server, 'microblog.newPage', 'say_hello');
xmlrpc_server_register_method($server, 'microblog.editPage', 'say_hello');
xmlrpc_server_register_method($server, 'microblog.deletePage', 'say_hello');
*/

// https://docstore.mik.ua/orelly/webprog/pcook/ch12_08.htm
$response = xmlrpc_server_call_method($server, $request_xml, null, [
	'escaping' => 'markup',
	'encoding' => 'UTF-8'
]);

if($response) {
	header('Content-Type: text/xml; charset=utf-8');
	// error_log($request_xml."\n\n".$response."\n", 3, __DIR__.'/log.txt');
	echo($response);
}
