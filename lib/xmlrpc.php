<?php

$request_xml = isset($request_xml) ? $request_xml : file_get_contents("php://input");

// check prerequisites
if(!$config['xmlrpc']) { exit('No XML-RPC support detected!'); }
if(empty($request_xml) && !isset($_GET['test'])) { exit('XML-RPC server accepts POST requests only.'); }

$logfile = ROOT.DS.'log.txt';

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

	// format file attachments
	if(!empty($post['post_attachments'])) {
		foreach($post['post_attachments'] as $attachment) {
			// todo: handle attachments other than images
			$attachment_url = get_file_url($attachment);
			$post['post_content'] .= PHP_EOL.'<img src="'.$attachment_url.'" alt="" />';
		}
	}

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
			'description' => $post['post_content'],
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
			'description' => $post['post_content'], // Post content
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
			/*
			[
				'id' => '1',
				'name' => 'default',
			]
			*/
		];
	} else {
		$categories = [
			[
				'categoryId' => '1',
				'parentId' => '0',
				'categoryName' => 'default',
				'categoryDescription' => 'The default category',
				'description' => 'default',
				'htmlUrl' => '/',
				'rssUrl' => '/'
			]
			/*
			[
				'description' => 'Default',
				'htmlUrl' => '',
				'rssUrl' => '',
				'title' => 'default',
				'categoryid' => '1',
			]
			*/
		];
	}

	return $categories;
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

	$posts = db_select_posts(null, $amount, 'desc', $offset);
	if(empty($posts)) return [];

	// get attached files
	$ids = array_column($posts, 'id');
	$attached_files = db_get_attached_files($ids);

	for ($i=0; $i < count($posts); $i++) { 
		if(!empty($attached_files[$posts[$i]['id']])) {
			$posts[$i]['post_attachments'] = $attached_files[$posts[$i]['id']];
		}
	}

	// call make_post() on all items
	$mw_posts = array_map('make_post', $posts, array_fill(0, count($posts), $method_name));

	return $mw_posts;
}

function mw_get_post($method_name, $args) {
	// todo: find a way to represent media attachments to editors

	list($post_id, $username, $password) = $args;

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	$post = db_select_post($post_id);

	// get attached files
	$attached_files = db_get_attached_files($post_id);

	if(!empty($attached_files[$post_id])) {
		$post['post_attachments'] = $attached_files[$post_id];
	}

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

function mw_new_post($method_name, $args) {
	global $config;

	if($method_name == 'blogger.newPost') {
		// app_key (unused), blog_id, user, pass, array of post content, publish/draft

		list($_, $blog_id, $username, $password, $content, $publish_flag) = $args;
	} else {
		// blog_id, user, pass, array of post content, unknown
		list($blog_id, $username, $password, $content, $_) = $args;
	}

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
	} elseif($method_name == 'blogger.newPost') {
		// support eg. micro.blog iOS app
		$post = [
			'post_content' => $content,
			'post_timestamp' => time()
		];
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

	// clean up image tags and get references to image files
	$image_urls = images_from_html($post['post_content']);

	// remove image tags
	$post['post_content'] = strip_img_tags($post['post_content']);

	$insert_id = db_insert($post['post_content'], $post['post_timestamp']);
	if($insert_id && $insert_id > 0) {
		// create references to file attachments
		if(!empty($image_urls)) {
			foreach ($image_urls as $url) {
				if(str_contains($url, $config['url'])) {
					$filename = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);

					// todo: match by hash instead of filename??

					$file = db_select_file($filename, 'filename');
					if($file) db_link_file($file['id'], $insert_id);
				}
			}
		}

		// success
		rebuild_feeds();
		if($config['activitypub'] == true) activitypub_notify_followers($insert_id);

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
	// todo: make this work with different hash algorithms, as stored in the DB
	global $config;
	global $db;

	// post_id, user, password, array of post content
	list($post_id, $username, $password, $content) = $args;

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

		if(!empty($content['dateCreated'])) {
			$post['post_timestamp'] = $content['dateCreated']->timestamp;
		}
	}

	// compare old and new image attachments:

	// load existing attachments
	$attached_files = db_get_attached_files($post_id);

	// extract new attachments from images
	$image_urls = images_from_html($post['post_content']);

var_dump($image_urls);
	// compare via file hash
	$hashes_of_existing_links = array_column(reset($attached_files), 'file_hash');
	$hashes_of_edited_post = [];

	foreach($image_urls as $url) {
		$file_path = ROOT.parse_url($url, PHP_URL_PATH); // fragile?

		if(file_exists($file_path)) {
			var_dump($file_path);
			$hashes_of_edited_post[] = make_file_hash($file_path);
		}
	}
	// $hashes_of_edited_post = array_unique($hashes_of_edited_post);

	$hashes_to_link = array_diff($hashes_of_edited_post, $hashes_of_existing_links);
	$hashes_to_unlink = array_diff($hashes_of_existing_links, $hashes_of_edited_post);

	
	var_dump([
		'post_id' => $post_id,
		'existing' => $hashes_of_existing_links,
		'new' => $hashes_of_edited_post,
		'to_link' => $hashes_to_link,
		'to_unlink' => $hashes_to_unlink
	]);
	

	try {
		$statement = $db->prepare('INSERT OR REPLACE INTO file_to_post (file_id, post_id, deleted) VALUES ((SELECT id FROM files WHERE file_hash = :file_hash LIMIT 1), :post_id, :deleted)');

		$attachment = [
			'hash' => null,
			'post' => $post_id
		];

		$statement->bindParam(':file_hash', $attachment['hash'], PDO::PARAM_STR);
		$statement->bindParam(':post_id', $attachment['post'], PDO::PARAM_INT);

		// link
		foreach ($hashes_to_link as $hash) {
			$attachment['hash'] = $hash;
			$statement->bindValue(':deleted', null, PDO::PARAM_NULL);
			$statement->execute();
		}

		// unlink
		foreach ($hashes_to_unlink as $hash) {
			$attachment['hash'] = $hash;
			$statement->bindValue(':deleted', time(), PDO::PARAM_INT);
			$statement->execute();
		}

	} catch(PDOException $e) {
		return [
			'faultCode' => 400,
			'faultString' => 'Post update SQL error: '.$e->getMessage()
		];
	}

	// remove html img tags
	$post['post_content'] = strip_img_tags($post['post_content']);

	$update = db_update($post_id, $post['post_content'], $post['post_timestamp']);
	if($update && $update > 0) {
		// success
		rebuild_feeds();
		// todo: does this have to notify activitypub followers too?

		return true;
	} else {
		return [
			'faultCode' => 400,
			'faultString' => 'Could not write post update.'
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
		// todo: does this have to notify activitypub followers too?

		return true;
	} else {
		return [
			'faultCode' => 400,
			'faultString' => 'Could not delete post.'
		];
	}
}

function mw_new_media_object($method_name, $args) {
	global $config;

	list($blog_id, $username, $password, $data) = $args;

	if(!check_credentials($username, $password)) {
		return [
			'faultCode' => 403,
			'faultString' => 'Incorrect username or password.'
		];
	}

	$new_ext = pathinfo($data['name'], PATHINFO_EXTENSION);
	if(!empty($data['type'])) {
		$new_ext = mime_to_extension($data['type']);
	}

	// file_put_contents(ROOT.DS.'test.txt', json_encode([$data['type'], pathinfo($data['name'], PATHINFO_EXTENSION), $new_ext]));

	$media_object = $data['bits']->scalar;

	// save the file to a temporary location
	$tmp_file = tempnam(sys_get_temp_dir(), 'microblog_');
	file_put_contents($tmp_file, $media_object);

	// make a DB entry for the file
	$new_file_id = save_file($data['name'], $new_ext, $tmp_file, 0, $data['type']);

	// get file info
	$file = db_select_file($new_file_id, 'id');
	// $file_path = get_file_path($file);

	$url = get_file_url($file);

	$success = ($new_file_id) ? 1 : 0;

	if($success > 0) {
		// If newMediaObject succeeds, it returns a struct, which must contain at least one element, url, which is the url through which the object can be accessed. It must be either an FTP or HTTP url.
		return [
			// 'url' => 'https://microblog.oelna.de/files/this-is-a-test.jpg'
			'url' => $url,
			'title' => $file['file_original'],
			'type' => $data['type'] ? $data['type'] : 'image/jpg' // is this reasonable?
		];
	} else {
		// If newMediaObject fails, it throws an error.
		return [
			'faultCode' => 400,
			'faultString' => 'Could not store media object.'
		];
	}
}

// https://codex.wordpress.org/XML-RPC_MetaWeblog_API
// https://community.devexpress.com/blogs/theprogressbar/metablog.ashx
// idea: http://www.hixie.ch/specs/pingback/pingback#TOC3
$server = xmlrpc_server_create();
xmlrpc_server_register_method($server, 'demo.sayHello', 'say_hello');

// http://nucleuscms.org/docs//item/204
xmlrpc_server_register_method($server, 'blogger.newPost', 'mw_new_post');
xmlrpc_server_register_method($server, 'blogger.editPost', 'mw_edit_post');
xmlrpc_server_register_method($server, 'blogger.getPost', 'mw_get_post');
xmlrpc_server_register_method($server, 'blogger.deletePost', 'mw_delete_post');
xmlrpc_server_register_method($server, 'blogger.getUsersBlogs', 'mw_get_users_blogs');
xmlrpc_server_register_method($server, 'blogger.getRecentPosts', 'mw_get_recent_posts');
xmlrpc_server_register_method($server, 'blogger.getUserInfo', 'mw_get_user_info');
xmlrpc_server_register_method($server, 'blogger.newMediaObject', 'mw_new_media_object');

xmlrpc_server_register_method($server, 'metaWeblog.getCategories', 'mw_get_categories');
xmlrpc_server_register_method($server, 'metaWeblog.getRecentPosts', 'mw_get_recent_posts');
xmlrpc_server_register_method($server, 'metaWeblog.newPost', 'mw_new_post');
xmlrpc_server_register_method($server, 'metaWeblog.editPost', 'mw_edit_post');
xmlrpc_server_register_method($server, 'metaWeblog.getPost', 'mw_get_post');
xmlrpc_server_register_method($server, 'metaWeblog.newMediaObject', 'mw_new_media_object');

// non-standard convenience?
xmlrpc_server_register_method($server, 'metaWeblog.getPosts', 'mw_get_recent_posts');
xmlrpc_server_register_method($server, 'metaWeblog.deletePost', 'mw_delete_post');

// micro.blog API methods (currently just using the metaWeblog functions)
// https://help.micro.blog/t/micro-blog-xml-rpc-api/108
xmlrpc_server_register_method($server, 'microblog.getCategories', 'mw_get_categories');
xmlrpc_server_register_method($server, 'microblog.getPosts', 'mw_get_recent_posts');
xmlrpc_server_register_method($server, 'microblog.getPost', 'mw_get_post');
xmlrpc_server_register_method($server, 'microblog.newPost', 'mw_new_post');
xmlrpc_server_register_method($server, 'microblog.editPost', 'mw_edit_post');
xmlrpc_server_register_method($server, 'microblog.deletePost', 'mw_delete_post');
xmlrpc_server_register_method($server, 'microblog.newMediaObject', 'mw_new_media_object');

// micro.blog pages are not supported
/*
xmlrpc_server_register_method($server, 'microblog.getPages', 'say_hello');
xmlrpc_server_register_method($server, 'microblog.getPage', 'say_hello');
xmlrpc_server_register_method($server, 'microblog.newPage', 'say_hello');
xmlrpc_server_register_method($server, 'microblog.editPage', 'say_hello');
xmlrpc_server_register_method($server, 'microblog.deletePage', 'say_hello');
*/

if(!isset($_GET['test'])) {
	// https://docstore.mik.ua/orelly/webprog/pcook/ch12_08.htm
	$response = xmlrpc_server_call_method($server, $request_xml, null, [
		'escaping' => 'markup',
		'encoding' => 'UTF-8'
	]);

	if($response) {
		header('Content-Type: text/xml; charset=utf-8');
		// todo: make error logging a config option
		// error_log(date('Y-m-d H:i:s')."\n".$request_xml."\n\n".$response."\n\n", 3, $logfile);
		echo($response);
	}
}
