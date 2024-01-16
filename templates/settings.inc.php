<?php
	if(!defined('ROOT')) die('Don\'t call this directly.');

	if(!$is_setup && !$config['logged_in']) {
		// wrong data, kick user to login page
		header('HTTP/1.0 401 Unauthorized');
		header('Location: '.$config['url'].'/login');
		die();
	}

	if($is_setup) {
		// allow the user in
		$config['logged_in'] = check_login(true);

		// generate some values
		if(empty($settings['app_token'])) {
			$settings['app_token'] = bin2hex(random_bytes(16));
		}
		if(empty($settings['admin_pass'])) {
			$settings['admin_pass'] = suggest_password();
		}

		$config = array_merge($config, $settings);
	}

	$message = array();
	if(isset($_POST['settings'])) {
		// todo: clean up values such as paths, etc?
		$settings_clean = $_POST['s'];

		if(!empty($settings_clean['admin_pass'])) {
			// update password
			$settings_clean['admin_pass'] = password_hash($settings_clean['admin_pass'], PASSWORD_DEFAULT);
		} else unset($settings_clean['admin_pass']);

		$update = [
			'key' => null,
			'value' => null,
			'updated' => time()
		];

		try {
			// backup all old values (todo: handle `admin_pass`)
			// does not work as INSERT replaces `previous` column with NULL
			//$statement = $db->prepare('UPDATE settings SET settings_value_previous = settings_value;');
			//$statement->execute();

			$statement = $db->prepare('INSERT OR REPLACE INTO settings (id, settings_key, settings_value, settings_updated) VALUES ((SELECT id FROM settings WHERE settings_key = :skey), :skey, :sval, :supdate);');

			$statement->bindParam(':skey', $update['key'], PDO::PARAM_STR);
			$statement->bindParam(':sval', $update['value'], PDO::PARAM_STR);
			$statement->bindParam(':supdate', $update['updated'], PDO::PARAM_INT);

			foreach ($settings_clean as $key => $value) {
				$update['key'] = mb_strtolower($key);
				$update['value'] = ($value !== '') ? $value : null;

				$statement->execute();
			}
		} catch(PDOException $e) {
			print 'Exception: Could not save settings. '.$e->getMessage();
			return false;
		}

		header('Location: '.$config['url'].'/'.$template);
		die();
	}

	$title_suffix = 'settings';
	require(ROOT.DS.'snippets'.DS.'header.snippet.php');

?><body ontouchstart="">
	<div class="wrap">
		<?php require(ROOT.DS.'snippets'.DS.'nav.snippet.php'); ?>
		<?php if(isset($message['status']) && isset($message['message'])): ?>
		<p class="message <?= $message['status'] ?>"><?= $message['message'] ?></p>
		<?php endif; ?>
		<form action="" method="post" enctype="multipart/form-data" id="post-settings-form" data-redirect="<?= $config['url'] ?>/settings">
			<?php if($is_setup): ?><p class="message">First time setup! Please make sure to choose a password!</p><?php endif; ?>
			<fieldset>
				<legend>General</legend>

				<?php if($is_setup): ?><input name="s[do_setup]" type="hidden" value="0" /><?php endif; ?>

				<dl>
					<dt><label for="s-url">URL</label></dt>
					<dd><input id="s-url" name="s[url]" type="text" value="<?= $is_setup ? $config['url_detected'] : ($settings['url'] ?? '') ?>" placeholder="The URL of your microblog" /></dd>

					<!--<dt><label for="s-root">Path (auto-detect if empty)</label></dt>
					<dd><input id="s-root" name="s[root]" type="text" value="<?= $settings['root'] ?? '' ?>" placeholder="<?= realpath(ROOT) ?>" readonly /></dd>-->

					<dt><label for="s-admin-user">Login username</label></dt>
					<dd><input id="s-admin-user" name="s[admin_user]" type="text" value="<?= $settings['admin_user'] ?? '' ?>" placeholder="Set a login username" /></dd>

					<dt><label for="s-admin-pass">Login password</label></dt>
					<dd><input id="s-admin-pass" name="s[admin_pass]" type="text" value="<?= $is_setup ? $settings['admin_pass'] : '' ?>" placeholder="Set a login password" autocomplete="new-password" /></dd>

					<?php if(!$is_setup): ?><dt><label for="s-admin-passkey">Login Passkey</label></dt>
					<dd><?php if(empty($config['passkey'])): ?><button class="button hidden" id="passkey-create">Create Passkey</button><?php endif; ?><span id="passkey-status"><?= !empty($config['passkey']) ? 'Passkey is set (<a href="/pk/revoke" id="passkey-revoke">revoke</a>)' : 'No passkey set' ?></span></dd><?php endif; ?>

					<dt><label for="s-app-token">App Token</label></dt>
					<dd><input id="s-app-token" name="s[app_token]" type="text" value="<?= $settings['app_token'] ?? '' ?>" placeholder="A seperate password used for XMLRPC" /></dd>

					<dt><label for="s-admin-email">Recovery Email</label></dt>
					<dd><input id="s-admin-email" name="s[admin_email]" type="text" value="<?= $settings['admin_email'] ?? '' ?>" placeholder="Set an email address for password recovery" /></dd>
				</dl>
			</fieldset>

			<fieldset>
				<legend>Configuration</legend>

				<dl>
					<dt><label for="s-theme">Theme</label></dt>
					<dd>
						<select id="s-theme" name="s[theme]"><?php
							// dynamic theme selector
							$themes_dir = __DIR__.DS.'..'.DS.'css';
							$themes = scandir($themes_dir);
							foreach ($themes as $theme) {
								if(is_dir($themes_dir.DS.$theme)) {
									if($theme[0] == '.') continue;
									$active = (($settings['theme'] ?? '') == $theme) ? ' selected' : '';
									echo('<option value="'.$theme.'"'.$active.'>'.$theme.'</option>'.NL);
								}
							}
						?></select>
					</dd>

					<dt><label for="s-language">Language (2 character code)</label></dt>
					<dd><input id="s-language" name="s[language]" type="text" value="<?= $settings['language'] ?? '' ?>" placeholder="en" /></dd>

					<dt><label for="s-max-characters">Character limit in posts</label></dt>
					<dd><input id="s-max-characters" name="s[max_characters]" type="text" value="<?= $settings['max_characters'] ?? '' ?>" placeholder="280" /></dd>

					<dt><label for="s-posts-per-page">Posts per page</label></dt>
					<dd><input id="s-posts-per-page" name="s[posts_per_page]" type="text" value="<?= $settings['posts_per_page'] ?? '' ?>" placeholder="10" /></dd>

					<dt><label>Show edits</label></dt>
					<dd>
						<label><input id="s-show-edits-1" name="s[show_edits]" type="radio" value="1"<?= ($settings['show_edits'] ?? 0) == 1 ? ' checked' : '' ?> /> Yes</label>
						<label><input id="s-show-edits-2" name="s[show_edits]" type="radio" value="0"<?= ($settings['show_edits'] ?? 0) == 0 ? ' checked' : '' ?> /> No</label>
					</dd>

					<dt><label for="s-cookie-life">Cookie lifetime in seconds</label></dt>
					<dd><input id="s-cookie-life" name="s[cookie_life]" type="text" value="<?= $settings['cookie_life'] ?? '' ?>" placeholder="2419200" /></dd>
					
					<dt><label for="s-local_timezone">Local Time Zone</label></dt>
					<dd><select id="s-local_timezone" name="s[local_timezone]"><?php 
					$timezones= DateTimeZone::listIdentifiers();
					foreach ($timezones as $timezone) {
							$active = (($settings['local_timezone'] ?? '') == $timezone) ? ' selected' : '';
							echo('<option value="'.$timezone.'"'.$active.'>'.$timezone.'</option>'.NL);
					}
					?></select>
					</dd>


				</dl>
			</fieldset>

			<fieldset>
				<legend>ActivityPub</legend>
				<dl>
					<dt><label>ActivityPub support</label></dt>
					<dd>
						<label><input id="s-activitypub" name="s[activitypub]" type="radio" value="1"<?= ($settings['activitypub'] ?? 0) == 1 ? ' checked' : '' ?> /> Active</label>
						<label><input id="s-activitypub" name="s[activitypub]" type="radio" value="0"<?= ($settings['activitypub'] ?? 0) == 0 ? ' checked' : '' ?> /> Inactive</label>
					</dd>

					<dt><label for="s-ap-username">Microblog account,<br />ActivityPub actor username</label></dt>
					<dd><input id="s-ap-username" name="s[microblog_account]" type="text" value="<?= $settings['microblog_account'] ?? '' ?>" placeholder="@username" /></dd>

					<dt><label for="s-site-title">Site title,<br />ActivityPub actor full name</label></dt>
					<dd><input id="s-site-title" name="s[site_title]" type="text" value="<?= $settings['site_title'] ?? '' ?>" placeholder="Jane Doe" /></dd>

					<dt><label for="s-site-claim">Site claim,<br />ActivityPub actor summary</label></dt>
					<dd><input id="s-site-claim" name="s[site_claim]" type="text" value="<?= $settings['site_claim'] ?? '' ?>" placeholder="This is an automated account. Don't mention or reply please." /></dd>

					<dt><label for="s-profile-image">ActivityPub actor profile image</label></dt>
					<dd><input id="s-profile-image" name="s[site_image]" type="text" value="<?= $settings['site_image'] ?? '' ?>" placeholder="<?= $config['url_detected'].'/favicon-large.png' ?>" /></dd>

					<dt><label>Ping Micro.blog on new post</label></dt>
					<dd>
						<label><input id="s-ping-1" name="s[ping]" type="radio" value="1"<?= ($settings['ping'] ?? 0) == 1 ? ' checked' : '' ?> /> Yes</label>
						<label><input id="s-ping-2" name="s[ping]" type="radio" value="0"<?= ($settings['ping'] ?? 0) == 0 ? ' checked' : '' ?> /> No</label>
					</dd>
				</dl>
			</fieldset>

			<fieldset>
				<legend>Bluesky/AT Protocol support</legend>
				<dl>
					<dt><label>Crosspost to Bluesky</label></dt>
					<dd>
						<label><input id="s-at-enabled" name="s[at_enabled]" type="radio" value="1"<?= ($settings['at_enabled'] ?? 0) == 1 ? ' checked' : '' ?> /> Active</label>
						<label><input id="s-at-enabled" name="s[at_enabled]" type="radio" value="0"<?= ($settings['at_enabled'] ?? 0) == 0 ? ' checked' : '' ?> /> Inactive</label>
					</dd>

					<dt><label for="s-at-username">Bluesky user handle</label></dt>
					<dd><input id="s-at-username" name="s[at_handle]" type="text" value="<?= $settings['at_handle'] ?? '' ?>" placeholder="@user.bsky.social" autocomplete="off" /></dd>

					<dt><label for="s-at-password">Bluesky app password</label></dt>
					<dd><input id="s-at-password" name="s[at_password]" type="password" value="<?= $settings['at_password'] ?? '' ?>" placeholder="Create app password in Bluesky settings" autocomplete="off" /></dd>
				</dl>
			</fieldset>

			<div class="post-nav">
				<input type="submit" name="settings" value="Save" class="button" />
			</div>

			<details>
				<summary>Debug info</summary>
			<fieldset>
				<legend>Server</legend>
				<dl>
					<dt><label>Detected URL</label></dt>
					<dd><?= $config['url_detected'] ?></dd>

					<dt><label>Path</label></dt>
					<dd><?= realpath(ROOT) ?></dd>

					<dt><label>Timezone offset</label></dt>
					<dd><?= $config['local_time_offset'] ?></dd>

					<dt><label>XML-RPC support</label></dt>
					<dd><?= $config['xmlrpc'] == 1 ? 'Yes' : 'No' ?></dd>

					<dt><label>Installed on domain root</label></dt>
					<dd><?= $config['subdir_install'] != 1 ? 'Yes' : 'No' ?></dd>
				</dl>
			</fieldset>
			</details>
		</form>
	</div>
	<?php require(ROOT.DS.'snippets'.DS.'footer.snippet.php'); ?>
</body>
</html>
