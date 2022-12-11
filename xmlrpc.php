<?php

$request_xml = file_get_contents("php://input");

// check prerequisites
if(!function_exists('xmlrpc_server_create')) { exit('No XML-RPC support detected!'); }
if(empty($request_xml)) { exit('XML-RPC server accepts POST requests only.'); }

// load config
require_once(__DIR__.DIRECTORY_SEPARATOR.'config.php');

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

function mw_make_post($post) {
	global $config;

	$date_created = date('Y-m-d\TH:i:s', $post['post_timestamp']).$config['local_time_offset'];
	$date_created_gmt = gmdate('Y-m-d\TH:i:s', $post['post_timestamp']).'Z';
	if(!empty($post['post_edited']) && $post['post_edited'] > 0) {
		$date_modified = date('Y-m-d\TH:i:s', $post['post_edited']).$config['local_time_offset'];
		$date_modified_gmt = gmdate('Y-m-d\TH:i:s', $post['post_edited']).'Z';
	} else {
		$date_modified = date('Y-m-d\TH:i:s', 0).$config['local_time_offset'];
		$date_modified_gmt = gmdate('Y-m-d\TH:i:s', 0).'Z';
	}

	@xmlrpc_set_type($date_created, 'datetime');
	@xmlrpc_set_type($date_created_gmt, 'datetime');
	@xmlrpc_set_type($date_modified, 'datetime');
	@xmlrpc_set_type($date_modified_gmt, 'datetime');

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

function mw_get_recent_posts($method_name, $args) {

	list($_, $username, $password, $amount) = $args;

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	if(!$amount) $amount = 25;

	$posts = db_select_posts(time()+10, $amount);
	$mw_posts = array_map('mw_make_post', $posts);

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
		$mw_post = mw_make_post($post);
		return $mw_post;
	} else {
		return [
			'faultCode' => 400,
			'faultString' => 'Could not fetch post.'
		];
	}
}

function mw_delete_post($method_name, $args) {

	if($method_name == 'blogger.deletePost') {
		list($_, $post_id, $username, $password, $_) = $args;
	} else {
		list($post_id, $username, $password) = $args;
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

	// post_id, unknown, unknown, array of post content, unknown
	list($post_id, $username, $password, $content, $_) = $args;

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	$post = [
		// 'post_hp' => $content['flNotOnHomePage'],
		'post_timestamp' => $content['dateCreated']->timestamp,
		// 'post_title' => $content['title'],
		'post_content' => $content['description'],
		// 'post_url' => $content['link'],
	];

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
	$categories = [
		[
			'description' => 'Default',
			'htmlUrl' => '',
			'rssUrl' => '',
			'title' => 'default',
			'categoryid' => '1',
		]
	];

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

// https://codex.wordpress.org/XML-RPC_MetaWeblog_API
// https://community.devexpress.com/blogs/theprogressbar/metablog.ashx
// idea: http://www.hixie.ch/specs/pingback/pingback#TOC3
$server = xmlrpc_server_create();
xmlrpc_server_register_method($server, 'demo.sayHello', 'say_hello');
xmlrpc_server_register_method($server, 'blogger.getUsersBlogs', 'mw_get_users_blogs');
xmlrpc_server_register_method($server, 'blogger.getUserInfo', 'mw_get_user_info');
xmlrpc_server_register_method($server, 'metaWeblog.getCategories', 'mw_get_categories');
xmlrpc_server_register_method($server, 'metaWeblog.getRecentPosts', 'mw_get_recent_posts');
xmlrpc_server_register_method($server, 'metaWeblog.newPost', 'mw_new_post');
xmlrpc_server_register_method($server, 'metaWeblog.editPost', 'mw_edit_post');
xmlrpc_server_register_method($server, 'metaWeblog.getPost', 'mw_get_post');
xmlrpc_server_register_method($server, 'blogger.deletePost', 'mw_delete_post');
xmlrpc_server_register_method($server, 'metaWeblog.deletePost', 'mw_delete_post'); // does this exist?
// xmlrpc_server_register_method($server, 'metaWeblog.newMediaObject', 'say_hello'); // todo

// https://docstore.mik.ua/orelly/webprog/pcook/ch12_08.htm
$response = xmlrpc_server_call_method($server, $request_xml, null, [
	'escaping' => 'markup',
	'encoding' => 'UTF-8'
]);

if($response) {
	header('Content-Type: text/xml; charset=utf-8');
	echo($response);
}
