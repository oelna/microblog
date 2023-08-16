<?php

	$title_suffix = isset($title_suffix) ? ' - ' . $title_suffix : '';
	$css = 'microblog'; // the default
	if(!empty($config['theme']) && file_exists(ROOT.DS.'css'.DS.$config['theme'].DS.$config['theme'].'.css')) {
		$css = $config['theme'];
	}

	header('Content-Type: text/html; charset=utf-8');

?><!DOCTYPE html>
<html lang="<?= $config['language'] ?>" class="no-js <?= $template ?>">
<head>
	<meta charset="utf-8" />
	
	<title><?= empty($config['microblog_account']) ? "" : $config['microblog_account'] . "'s "; ?>micro.blog<?= $title_suffix ?></title>
	
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<link rel="apple-touch-icon" href="<?= $config['url'] ?>/favicon-large.png" />
	<!-- <link rel="apple-touch-startup-image" href="launch.png"> -->
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
	<meta name="apple-mobile-web-app-title" content="<?= $config['site_title'] ?>">

	<link rel="alternate" type="application/json" title="JSON Feed" href="<?= $config['url'] ?>/feed/json" />
	<link rel="alternate" type="application/atom+xml" title="Atom Feed" href="<?= $config['url'] ?>/feed/atom" />
	<?php if($config['xmlrpc']): ?><link rel="EditURI" type="application/rsd+xml" title="RSD" href="<?= $config['url'] ?>/rsd" /><?php endif; ?>

	<link rel="authorization_endpoint" href="https://micro.blog/indieauth/auth" />
	<link rel="token_endpoint" href="https://micro.blog/indieauth/token" />

	<?php if(!empty($config['microblog_account'])): ?>
	<link href="https://micro.blog/<?= ltrim($config['microblog_account'], '@') ?>" rel="me" />
	<?php endif; ?>

	<link rel="icon" href="<?= $config['url'] ?>/favicon.ico" />

	<link rel="stylesheet" href="<?= $config['url'] ?>/css/<?= $css ?>/<?= $css ?>.css" />
	
	<script src="<?= $config['url'] ?>/js/microblog.js" type="module" defer></script>
</head>
