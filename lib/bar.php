<?php

	function bar_list() {

		$files_dir = ROOT.DS.'files';

		if(!is_dir($files_dir)) {
			mkdir($files_dir, 0755);
		}

		if(!is_dir($files_dir.DS.'bar')) {
			mkdir($files_dir.DS.'bar', 0755);
		}

		$files = array_filter(glob($files_dir.DS.'bar'.DS.'*.{zip,bar}', GLOB_BRACE), 'is_file');
		sort($files);

		return $files;
	}

	function bar_remove($archive_file) {
		$abs = ROOT.DS.'files'.DS.'bar'.DS.basename($archive_file); // so no path ever gets in
		if(file_exists($abs)) {
			unlink($abs);
		}
	}

	function bar_is_zip($file) { // https://stackoverflow.com/a/63255883/3625228
		$f = fopen($file, 'r');
		$bytes = fread($f, 4);
		fclose($fh);
		return ('504b0304' === bin2hex($bytes));
	}

	function bar_create_zip() { // https://stackoverflow.com/a/64479856/3625228
		global $db;

		$files_dir = ROOT.DS.'files';

		if(!is_dir($files_dir)) {
			mkdir($files_dir, 0755);
		}

		if(!is_dir($files_dir.DS.'bar')) {
			mkdir($files_dir.DS.'bar', 0755);
		}

		// collect all posts and attachments
		$sql = "SELECT p.id, p.post_content, p.post_guid, p.post_timestamp,
			GROUP_CONCAT(f.file_dir || '/' || f.file_subdir || '/' || f.file_filename || '.' || f.file_extension) as post_attachments
			FROM posts p
			LEFT JOIN file_to_post f2p ON p.id = f2p.post_id
			LEFT JOIN files f ON f.id = f2p.file_id
			WHERE post_deleted IS NULL
			GROUP BY p.id
			ORDER BY post_timestamp DESC";
		// alternative with subquery
		/*
		$sql = "SELECT p.id, p.post_content, p.post_guid, p.post_timestamp, (
					SELECT GROUP_CONCAT(f.file_dir || '/' || f.file_subdir || '/' || f.file_filename || '.' || f.file_extension)
					FROM files f
					LEFT JOIN file_to_post f2p ON p.id = f2p.post_id
					WHERE f.id = f2p.file_id
				) as post_attachments
				FROM posts p
				WHERE post_deleted IS NULL
				GROUP BY p.id
				ORDER BY post_timestamp DESC";
		*/
		$statement = $db->query($sql);
		$posts = $statement->fetchAll(PDO::FETCH_ASSOC);

		$json = rebuild_json_feed($posts, true, true); // return as string, relative paths
		$html = bar_generate_hfeed($posts);

		$zip = new ZipArchive;
		$archive_filename = 'mb-'.time().'-'.bin2hex(random_bytes(4)).'.zip'; // make unguessable

		if ($zip->open($files_dir.DS.'bar'.DS.$archive_filename, ZipArchive::CREATE) === TRUE) {
			$zip->addFromString('index.html', $html);
			$zip->addFromString('feed.json', $json);

			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($files_dir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ($files as $file) {
				if (!$file->isDir()) {
					$filePath = $file->getRealPath();
					$relativePath = substr($filePath, strlen($files_dir) + 1);
					list($subdir) = explode(DS, $relativePath);

					if(is_numeric($subdir)) { // only add year subdirs
						$zip->addFile($filePath, 'files/'.$relativePath);
					}
				}
			}

			$zip->close();
		} else return false;

		return true;
	}

	function bar_generate_hfeed($posts=[]) {
		global $settings;

		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$html = $doc->appendChild($doc->createElement('html'));
		$head = $html->appendChild($doc->createElement('head'));

		$meta = array(
			array('charset' => 'utf-8'),
		);

		foreach ($meta as $attributes) {
			$node = $head->appendChild($doc->createElement('meta'));
			foreach ($attributes as $key => $value) {
				$node->setAttribute($key, $value);
			}
		}

		$title = $head->appendChild($doc->createElement('title'));
		$title->nodeValue = $settings['site_title'];

		$body = $html->appendChild($doc->createElement('body'));
		$hfeed = $body->appendChild($doc->createElement('section'));
		$hfeed->setAttribute('class', 'h-feed');

		$h1 = $hfeed->appendChild($doc->createElement('h1'));
		$h1->setAttribute('class', 'p-name site-title');

		$claim = $hfeed->appendChild($doc->createElement('p'));
		$claim->setAttribute('class', 'p-summary site-description');
		$claim->nodeValue = $settings['site_claim'];

		$node = $h1->appendChild($doc->createElement('a'));
		$node->setAttribute('class', 'u-url');
		$node->setAttribute('href', $settings['url']);
		$node->nodeValue = $settings['site_title'];

		foreach ($posts as $post) {
			$node = $hfeed->appendChild($doc->createElement('article'));
			$node->setAttribute('class', 'h-entry hentry');
			$node->setAttribute('data-id', $post['id']);

			$permalink = $node->appendChild($doc->createElement('a'));
			$permalink->setAttribute('class', 'u-url u-uid');
			$permalink->setAttribute('href', $settings['url'].'/'.$post['id']);

			$time = $permalink->appendChild($doc->createElement('time'));
			$time->setAttribute('class', 'dt-published published');
			$time->setAttribute('datetime', date('Y-m-d H:i:s', $post['post_timestamp']));
			$time->nodeValue = date('Y-m-d H:i:s', $post['post_timestamp']);

			if(!empty($post['post_edited'])) {
				$edittime = $permalink->appendChild($doc->createElement('time'));
				$edittime->setAttribute('class', 'dt-updated');
				$edittime->setAttribute('datetime', date('Y-m-d H:i:s', $post['post_edited']));
				$edittime->nodeValue = date('Y-m-d H:i:s', $post['post_edited']);
			}

			$author = $node->appendChild($doc->createElement('p'));
			$author->setAttribute('class', 'p-author author h-card vcard');
			$author->nodeValue = $settings['microblog_account'];

			$content = $node->appendChild($doc->createElement('div'));
			$content->setAttribute('class', 'e-content');

			$p = $content->appendChild($doc->createElement('p'));
			$fragment = $p->ownerDocument->createDocumentFragment();
			$fragment->appendXML(autolink($post['post_content'])); // fragile?
			$p->appendChild($fragment);
			// $p->nodeValue = autolink($post['post_content']);

			if(!empty($post['post_attachments'])) {
				// todo: distinguish between images and other file types
				// tricky, because has to check in DB
				$images = explode(',', $post['post_attachments']);
				foreach ($images as $img) {
					$image = $content->appendChild($doc->createElement('img'));
					$image->setAttribute('class', 'u-photo');
					$image->setAttribute('src', './files/'.$img);
				}
			}
		}

		$doc->formatOutput = true;
		$markup = $doc->saveXML(); // saveHTML has fewer options

		// spaces to tabs (?) and cleanup
		$markup = preg_replace_callback('/^( +)</m', function($a) {
			return str_repeat("\t", intval(strlen($a[1]) / 2)).'<';
		}, $doc->saveXML());
		$markup = str_replace('<?xml version="1.0"?>', '<!DOCTYPE html>', $markup); // LIBXML_NOXMLDECL is PHP 8.3+

		file_put_contents(ROOT.DS.'feed'.DS.'feed.html', $markup);
		// echo($markup);

		return $markup;
	}
