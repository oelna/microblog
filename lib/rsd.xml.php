<?php
  require_once(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'config.php');

  header('Content-Type: text/xml; charset=utf-8');
?><rsd xmlns="http://archipelago.phrasewise.com/rsd" version="1.0">
  <service>
    <engineName>oelna/microblog</engineName>
    <engineLink>https://github.com/oelna/microblog</engineLink>
    <homePageLink><?= $config['url'] ?></homePageLink>
    <apis>
      <api name="Micro.blog" blogID="1" preferred="true" apiLink="<?= $config['url'] ?>/xmlrpc" />
      <api name="MetaWeblog" blogID="1" preferred="false" apiLink="<?= $config['url'] ?>/xmlrpc" />
    </apis>
  </service>
</rsd>
