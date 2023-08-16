<?php

if(!$config['activitypub']) exit('ActivityPub is disabled via config file.');

// get total amount
$statement = $db->prepare('SELECT COUNT(id) as total FROM followers WHERE follower_actor IS NOT NULL');
$statement->execute();
$followers_total = $statement->fetchAll(PDO::FETCH_ASSOC);
$followers_total = (!empty($followers_total)) ? $followers_total[0]['total'] : 0;

if(!isset($_GET['page'])):

	$output = [
		'@context' => 'https://www.w3.org/ns/activitystreams',
		'id' => $config['url'].'/followers',
		'type' => 'OrderedCollection',
		'totalItems' => $followers_total,
		'first' => $config['url'].'/followers?page=1',
	];

	header('Content-Type: application/ld+json');
	echo(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
else:

	// get items
	$items_per_page = 12; // mastodon default?

	// pagination
	$current_page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int) $_GET['page'] : 1;
	$total_pages = ceil($followers_total / $items_per_page);
	$offset = ($current_page-1)*$items_per_page;

	if($current_page < 1 || $current_page > $total_pages) {
		http_response_code(404);
		header('Content-Type: application/ld+json');
		die('{}');
	}

	$statement = $db->prepare('SELECT follower_actor FROM followers WHERE follower_actor IS NOT NULL ORDER BY follower_added ASC LIMIT :limit OFFSET :page');
	$statement->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
	$statement->bindValue(':page', $offset, PDO::PARAM_INT);
	$statement->execute();
	$followers = $statement->fetchAll(PDO::FETCH_ASSOC);

	$ordered_items = [];
	if(!empty($followers)) {
		$ordered_items = array_column($followers, 'follower_actor');
	}

	$output = [
		'@context' => 'https://www.w3.org/ns/activitystreams',
		'id' => $config['url'].'/followers?page='.$current_page,
		'type' => 'OrderedCollectionPage',
		'totalItems' => $followers_total,
		'partOf' => $config['url'].'/followers'
	];

	if($current_page > 1) {
		$output['prev'] = $config['url'].'/followers?page='.($current_page-1);
	}

	if($current_page < $total_pages) {
		$output['next'] = $config['url'].'/followers?page='.($current_page+1);
	}

	$output['orderedItems'] = $ordered_items;

	header('Content-Type: application/ld+json');
	echo(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
endif;
