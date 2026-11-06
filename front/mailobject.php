<?php

include('../../../inc/includes.php');

use GlpiPlugin\Mailmanager\MailObject;
use GlpiPlugin\Mailmanager\Menu;

Session::checkLoginUser();
Session::checkRight(MailObject::$rightname, READ);

$mailobject = new MailObject();

Html::header(
    MailObject::getTypeName(2),
    $_SERVER['PHP_SELF'],
    Menu::MENU_SECTOR,
    Menu::class,
    'mailobject'
);

Search::show(MailObject::class);

Html::footer();
