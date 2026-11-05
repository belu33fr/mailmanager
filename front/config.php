<?php

include('../../../inc/includes.php');

use GlpiPlugin\Mailmanager\Config;
use GlpiPlugin\Mailmanager\Right;

Session::checkLoginUser();
Session::checkRight(Right::RIGHT_CONFIG, READ);

if (isset($_POST['update'])) {
    Session::checkRight(Right::RIGHT_CONFIG, UPDATE);
    Config::save($_POST);
    Html::back();
}

Html::header(
    __('Configuration Mail Manager', 'mailmanager'),
    $_SERVER['PHP_SELF'],
    'config',
    Config::class
);

$config = new Config();
$config->display([]);

Html::footer();
