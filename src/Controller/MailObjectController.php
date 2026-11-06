<?php

namespace GlpiPlugin\Mailmanager\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Mailmanager\MailObject;
use Html;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MailObjectController extends AbstractController
{
    #[Route("/MailObject", name: "mailmanager_mailobject_list", methods: ["GET"])]
    public function list(Request $request): Response
    {
        Session::checkLoginUser();
        Session::checkRight(MailObject::$rightname, READ);

        ob_start();
        Html::header(
            MailObject::getTypeName(2),
            $_SERVER['PHP_SELF'],
            'management',
            MailObject::class
        );
        \Search::show(MailObject::class);
        Html::footer();
        return new Response(ob_get_clean());
    }

    #[Route("/MailObject/form", name: "mailmanager_mailobject_form", methods: ["GET", "POST"])]
    public function form(Request $request): Response
    {
        Session::checkLoginUser();

        $mailobject = new MailObject();

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['add'])) {
                Session::checkRight(MailObject::$rightname, CREATE);
                $mailobject->add($post);
                return $this->redirectToRoute('mailmanager_mailobject_list');
            }
            if (isset($post['update'])) {
                Session::checkRight(MailObject::$rightname, UPDATE);
                $mailobject->update($post);
                return $this->redirectToRoute('mailmanager_mailobject_form', ['id' => $post['id']]);
            }
            if (isset($post['delete'])) {
                Session::checkRight(MailObject::$rightname, DELETE);
                $mailobject->delete($post);
                return $this->redirectToRoute('mailmanager_mailobject_list');
            }
            if (isset($post['purge'])) {
                Session::checkRight(MailObject::$rightname, PURGE);
                $mailobject->delete($post, true);
                return $this->redirectToRoute('mailmanager_mailobject_list');
            }
        }

        $ID = (int)($request->query->get('id', 0));
        if ($ID > 0) {
            $mailobject->check($ID, READ);
        } else {
            $mailobject->check(-1, CREATE);
        }

        ob_start();
        Html::header(
            MailObject::getTypeName(1),
            $_SERVER['PHP_SELF'],
            'management',
            MailObject::class
        );
        $mailobject->display(['id' => $ID]);
        Html::footer();
        return new Response(ob_get_clean());
    }
}
