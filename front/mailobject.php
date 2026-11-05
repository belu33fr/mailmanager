<?php

include('../../../inc/includes.php');

use GlpiPlugin\Mailmanager\MailObject;

Session::checkLoginUser();
Session::checkRight(MailObject::$rightname, READ);

$mailobject = new MailObject();

Html::header(
    MailObject::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'management',
    MailObject::class
);

Search::show(MailObject::class);

Html::footer();
