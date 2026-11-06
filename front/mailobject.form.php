<?php

include('../../../inc/includes.php');

use GlpiPlugin\Mailmanager\MailObject;
use GlpiPlugin\Mailmanager\Menu;

Session::checkLoginUser();

$mailobject = new MailObject();

if (isset($_POST['add'])) {
    Session::checkRight(MailObject::$rightname, CREATE);
    $newId = $mailobject->add($_POST);
    Html::back();

} elseif (isset($_POST['update'])) {
    Session::checkRight(MailObject::$rightname, UPDATE);
    $mailobject->update($_POST);
    Html::back();

} elseif (isset($_POST['delete'])) {
    Session::checkRight(MailObject::$rightname, DELETE);
    $mailobject->delete($_POST);
    $mailobject->redirectToList();

} elseif (isset($_POST['purge'])) {
    Session::checkRight(MailObject::$rightname, PURGE);
    $mailobject->delete($_POST, true);
    $mailobject->redirectToList();

} else {
    Session::checkRight(MailObject::$rightname, READ);

    $ID = (int)($_GET['id'] ?? 0);
    if ($ID > 0) {
        $mailobject->check($ID, READ);
    } else {
        $mailobject->check(-1, CREATE);
    }

    Html::header(
        MailObject::getTypeName(1),
        $_SERVER['PHP_SELF'],
        Menu::MENU_SECTOR,
        'mailobject'
    );

    $mailobject->display(['id' => $ID]);

    Html::footer();
}
