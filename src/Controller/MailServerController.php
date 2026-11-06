<?php

namespace GlpiPlugin\Mailmanager\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Mailmanager\MailServer;
use Html;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MailServerController extends AbstractController
{
    #[Route("/MailServer", name: "mailmanager_mailserver_list", methods: ["GET"])]
    public function list(Request $request): Response
    {
        Session::checkLoginUser();
        Session::checkRight(MailServer::$rightname, READ);

        ob_start();
        Html::header(
            MailServer::getTypeName(2),
            $_SERVER['PHP_SELF'],
            'tools',
            MailServer::class
        );
        \Search::show(MailServer::class);
        Html::footer();
        return new Response(ob_get_clean());
    }

    #[Route("/MailServer/form", name: "mailmanager_mailserver_form", methods: ["GET", "POST"])]
    public function form(Request $request): Response
    {
        Session::checkLoginUser();

        $mailserver = new MailServer();

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['add'])) {
                Session::checkRight(MailServer::$rightname, CREATE);
                $mailserver->add($post);
                return $this->redirectToRoute('mailmanager_mailserver_list');
            }
            if (isset($post['update'])) {
                Session::checkRight(MailServer::$rightname, UPDATE);
                $mailserver->update($post);
                return $this->redirectToRoute('mailmanager_mailserver_form', ['id' => $post['id']]);
            }
            if (isset($post['delete'])) {
                Session::checkRight(MailServer::$rightname, DELETE);
                $mailserver->delete($post);
                return $this->redirectToRoute('mailmanager_mailserver_list');
            }
            if (isset($post['purge'])) {
                Session::checkRight(MailServer::$rightname, PURGE);
                $mailserver->delete($post, true);
                return $this->redirectToRoute('mailmanager_mailserver_list');
            }
        }

        $ID = (int)($request->query->get('id', 0));
        if ($ID > 0) {
            $mailserver->check($ID, READ);
        } else {
            $mailserver->check(-1, CREATE);
        }

        ob_start();
        Html::header(
            MailServer::getTypeName(1),
            $_SERVER['PHP_SELF'],
            'tools',
            MailServer::class
        );
        $mailserver->display(['id' => $ID]);
        Html::footer();
        return new Response(ob_get_clean());
    }
}
