<?php

if(!$config['activitypub']) exit('ActivityPub is disabled via config file.');

$posts_per_page = 20; // 20 is mastodon default?
$posts_total = db_posts_count(); // get total amount of posts
$total_pages = ceil($posts_total / $posts_per_page);

if(!isset($_GET['page'])):

	$output = [
		'@context' => 'https://www.w3.org/ns/activitystreams',
		'id' => $config['url'].'/outbox',
		'type' => 'OrderedCollection',
		'totalItems' => $posts_total,
		'first' => $config['url'].'/outbox?page=1',
		'last' => $config['url'].'/outbox?page='.$total_pages
	];

	header('Content-Type: application/ld+json');
	echo(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
else:

	// pagination
	$current_page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int) $_GET['page'] : 1;
	$offset = ($current_page-1)*$posts_per_page;

	if($current_page < 1 || $current_page > $total_pages) {
		http_response_code(404);
		header('Content-Type: application/ld+json');
		die('{}');
	}

	$posts = db_select_posts(NOW, $posts_per_page, 'desc', $offset);

	$ordered_items = [];
	if(!empty($posts)) {
		foreach ($posts as $post) {

			$item = [];
			$item['id'] = $config['url'].'/'.$post['id'].'/json';
			$item['type'] = 'Create';
			$item['actor'] = $config['url'].'/actor';
			$item['published'] = gmdate('Y-m-d\TH:i:s\Z', $post['post_timestamp']);
			$item['to'] = ['https://www.w3.org/ns/activitystreams#Public'];
			$item['cc'] = [$config['url'].'/followers'];
			$item['object'] = $config['url'].'/'.$post['id'].'/';

			$ordered_items[] = $item;
		}
	}

	$output = [
		'@context' => 'https://www.w3.org/ns/activitystreams',
		'id' => $config['url'].'/outbox?page='.$current_page,
		'type' => 'OrderedCollectionPage',
		'partOf' => $config['url'].'/outbox'
	];

	if($current_page > 1) {
		$output['prev'] = $config['url'].'/outbox?page='.($current_page-1);
	}

	if($current_page < $total_pages) {
		$output['next'] = $config['url'].'/outbox?page='.($current_page+1);
	}

	$output['orderedItems'] = $ordered_items;

	header('Content-Type: application/ld+json');
	echo(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
endif;
