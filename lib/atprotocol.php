<?php

	function at_parse_handle($handle) {
		$return = [
			'user' => '',
			'host' => ''
		];

		if(empty($handle)) return $return;
		list($return['user'], $return['host']) = explode('.', ltrim($handle, '@'), 2);

		return $return;
	}

	function at_datetime($timestamp=false) {
		// format: 2023-10-08T00:31:12.156888Z
		if(!$timestamp) $timestamp = microtime(true);

		$d = DateTime::createFromFormat('U.u', $timestamp);
		if(!$d) {
			$d = DateTime::createFromFormat('U', $timestamp);
		}
		// $d->setTimezone(new DateTimeZone('UTC'));

		return $d->format('Y-m-d\TH:i:s.u\Z');
	}

	function at_get_token($handle, $password, $curl=false) {
		$data = at_parse_handle($handle);

		$ch = $curl ? $curl : curl_init();

		$url = sprintf('https://%s/xrpc/com.atproto.server.createSession', $data['host']);
		$post_data = [
			'identifier' => ltrim($handle, '@'),
			'password' => $password
		];

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json'
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$server_output = curl_exec($ch);
		if(!$curl) curl_close($ch);

		$auth_data = json_decode($server_output, true);

		return $auth_data;
	}

	function at_parse_urls($text) {
		$spans = [];
		$pattern = regex_patterns('url');

		preg_match_all("#$pattern#i", $text, $matches, PREG_OFFSET_CAPTURE);

		if(!empty($matches) && !empty($matches[0])) {
			for ($i=0; $i<count($matches[0]); $i++) {
				$matches[0][$i][2] = $matches[0][$i][1] + mb_strlen($matches[0][$i][0]);
			}
			return $matches[0];
		}
		return [];
	}

	function at_urls_to_facets($text) {
		$facets = [];
		$offsets = at_parse_urls($text);

		foreach ($offsets as $uri) {
			$facets[] = [
				'index' => [
					'byteStart' => $uri[1],
					'byteEnd' => $uri[2],
				],
				'features' => [
					[
						'$type' => "app.bsky.richtext.facet#link",
						'uri' => $uri[0], # NOTE: URI ("I") not URL ("L")
					]
				]
			];
		}
		return $facets;
	}

	function at_new_blob($handle, $password, $token, $image, $curl=false) {
		$data = at_parse_handle($handle);

		$ch = $curl ? $curl : curl_init();

		$url = sprintf('https://%s/xrpc/com.atproto.repo.uploadBlob', $data['host']);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($image));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: image/jpeg',
			'Authorization: Bearer '.$token
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$server_output = curl_exec($ch);

		if(!$curl) curl_close($ch);

		$response = json_decode($server_output, true);

		return $response['blob'];
	}

	function at_new_post($handle, $password, $text, $images=[]) {
		$data = at_parse_handle($handle);

		$ch = curl_init();

		$auth_data = at_get_token($handle, $password, $ch);

		// IMAGES
		$embeds = [];
		$images = array_slice($images, 0, 4); // limit to max 4 images
		foreach ($images as $image) {
			$type = mime_content_type($image);

			// todo: support PNG!
			// this size limit is specified in the app.bsky.embed.images lexicon
			if(filesize($image) < 100000 && ($type == 'image/jpeg' || $type == 'image/jpg')) {
				// these images are good to go
				$path = $image;
			} else {
				// scale down and save to temp
				// hope to fit withing bluesky 1MB limit
				$max_dimensions = 2000;
				$quality = 65;

				list($width, $height) = getimagesize($image);
				$ratio = $width/$height;

				// do we need to scale down?
				$scale = false;
				if($width > $max_dimensions || $height > $max_dimensions) {
					$scale = true;
					if($ratio > 1) {
						$new_width = $max_dimensions;
						$new_height = floor($max_dimensions/$ratio);
					} else {
						$new_width = floor($max_dimensions*$ratio);
						$new_height = $max_dimensions;
					}
				}

				if(class_exists('Imagick')) {
					// use Imagick to handle image
					$img = new Imagick($image);
					$profiles = $img->getImageProfiles('icc', true);

					// bake in EXIF orientation
					$orientation = $img->getImageOrientation();

					switch($orientation) {
						case imagick::ORIENTATION_BOTTOMRIGHT: 
							$img->rotateimage('#000', 180); // rotate 180 degrees
							break;

						case imagick::ORIENTATION_RIGHTTOP:
							$img->rotateimage('#000', 90); // rotate 90 degrees CW
							break;

						case imagick::ORIENTATION_LEFTBOTTOM: 
							$img->rotateimage('#000', -90); // rotate 90 degrees CCW
							break;
					}

					$img->setImageCompression(imagick::COMPRESSION_JPEG);
					$img->setImageCompressionQuality($quality);

					if($scale == true) {
						$img->resizeImage(
							$new_width,
							$new_height,
							imagick::FILTER_CATROM,
							1,
							true
						);
					}

					$img->stripImage();

					// reset orientation info
					$img->setImageOrientation(imagick::ORIENTATION_TOPLEFT);

					if(!empty($profiles)) {
						$img->profileImage('icc', $profiles['icc']);
					}

					$tmp = tmpfile();
					$path = stream_get_meta_data($tmp)['uri'];

					$img->writeImage('jpg:'.$path);
					// $img->writeImage('jpg:'.ROOT.DS.'test-'.microtime(true).'.jpg');
					$img->clear();
				} else {
					// use GD to handle image
					$res = imagecreatefromstring(file_get_contents($image));

					$tmp = tmpfile();
					$path = stream_get_meta_data($tmp)['uri'];

					if($scale == true) {
						$resized = imagecreatetruecolor($new_width, $new_height);

						// todo: do we need to fix orientation here, too?

						imagecopyresampled($resized, $res, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
						imagejpeg($resized, $tmp, $quality);
						// imagejpeg($resized, ROOT.DS.'test-'.microtime(true).'.jpg', $quality);

						imagedestroy($resized);
						imagedestroy($res);
					} else {
						imagejpeg($res, $tmp, $quality);
						imagedestroy($res);
					}
				}
			}

			$blob = at_new_blob($handle, $password, $auth_data['accessJwt'], $path, $ch);

			$embeds[] = [
				"alt" => '',
				"image" => $blob
			];
		}

		// URLs
		$facets = at_urls_to_facets($text);

		$new_post = [
			'$type' => 'app.bsky.feed.post',
			'text' => $text,
			'createdAt' => at_datetime(microtime(true)),
			// 'langs' => [ 'en' ],
		];
		if(!empty($embeds)) $new_post['embed'] = [
			'$type' => "app.bsky.embed.images",
			'images' => $embeds
		];
		if(!empty($facets)) $new_post['facets'] = $facets;

		// var_dump(json_encode($new_post));

		$post_data = [
			"repo" => $auth_data['did'],
			"collection" => 'app.bsky.feed.post',
			"record" => $new_post
		];

		$url = sprintf('https://%s/xrpc/com.atproto.repo.createRecord', $data['host']);

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: Bearer '.$auth_data['accessJwt']
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		$server_output = curl_exec($ch);
		curl_close($ch);

		$status = json_decode($server_output, true);
		//var_dump($status);

		return $status;
	}

	function at_post_status($post_id) {
		global $config;

		// $post_id = 40; // testing only!
		$post = db_select_post($post_id);

		$handle = $config['at_handle'];
		$app_password = $config['at_password'];

		// get image attachments
		$files = db_get_attached_files($post['id']);
		$images = [];
		if(!empty($files)) {
			$files = array_values($files)[0];
			$images = array_map(function ($file) {
				if(strpos($file['file_mime_type'], 'image') !== 0) return false; // skip non-image files
				return realpath(ROOT .'/'. get_file_path($file));
			}, $files);
		}

		if(1 == 1) {
			// add a permalink to bluesky posts
			$post['post_content'] = $post['post_content'] . "\n\n" . $config['url'] . '/' . $post['id'];
		}

		$status = at_new_post($handle, $app_password, $post['post_content'], $images);

		return $status; // todo: save this as post meta?
	}
