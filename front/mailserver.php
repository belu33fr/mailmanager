<?php

include('../../../inc/includes.php');

use GlpiPlugin\Mailmanager\MailServer;

Session::checkLoginUser();
Session::checkRight(MailServer::$rightname, READ);

$mailserver = new MailServer();

Html::header(
    MailServer::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'tools',
    MailServer::class,
    'mailserver'
);

Search::show(MailServer::class);

Html::footer();
