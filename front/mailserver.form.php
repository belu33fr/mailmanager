<?php

include('../../../inc/includes.php');

use GlpiPlugin\Mailmanager\MailServer;
use GlpiPlugin\Mailmanager\Menu;

Session::checkLoginUser();

$mailserver = new MailServer();

if (isset($_POST['add'])) {
    Session::checkRight(MailServer::$rightname, CREATE);
    $newId = $mailserver->add($_POST);
    Html::back();

} elseif (isset($_POST['update'])) {
    Session::checkRight(MailServer::$rightname, UPDATE);
    $mailserver->update($_POST);
    Html::back();

} elseif (isset($_POST['delete'])) {
    Session::checkRight(MailServer::$rightname, DELETE);
    $mailserver->delete($_POST);
    $mailserver->redirectToList();

} elseif (isset($_POST['purge'])) {
    Session::checkRight(MailServer::$rightname, PURGE);
    $mailserver->delete($_POST, true);
    $mailserver->redirectToList();

} else {
    Session::checkRight(MailServer::$rightname, READ);

    $ID = (int)($_GET['id'] ?? 0);
    if ($ID > 0) {
        $mailserver->check($ID, READ);
    } else {
        $mailserver->check(-1, CREATE);
    }

    Html::header(
        MailServer::getTypeName(1),
        $_SERVER['PHP_SELF'],
        Menu::MENU_SECTOR,
        Menu::class,
        'mailserver'
    );

    $mailserver->display(['id' => $ID]);

    Html::footer();
}
