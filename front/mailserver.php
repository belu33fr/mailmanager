<?php

include('../../../inc/includes.php');

use GlpiPlugin\Mailmanager\MailServer;
use GlpiPlugin\Mailmanager\Menu;

Session::checkLoginUser();
Session::checkRight(MailServer::$rightname, READ);

$mailserver = new MailServer();

Html::header(
    MailServer::getTypeName(2),
    $_SERVER['PHP_SELF'],
    Menu::MENU_SECTOR,
    Menu::class,
    'mailserver'
);

Search::show(MailServer::class);

Html::footer();
