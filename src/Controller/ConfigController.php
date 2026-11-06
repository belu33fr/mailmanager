<?php

namespace GlpiPlugin\Mailmanager\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Mailmanager\Config;
use GlpiPlugin\Mailmanager\Right;
use Html;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConfigController extends AbstractController
{
    #[Route("/Config", name: "mailmanager_config", methods: ["GET", "POST"])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        Session::checkRight(Right::RIGHT_CONFIG, READ);

        if ($request->isMethod('POST')) {
            Session::checkRight(Right::RIGHT_CONFIG, UPDATE);
            Session::checkCSRF($request->request->all());
            Config::save($request->request->all());
            return $this->redirectToRoute('mailmanager_config');
        }

        ob_start();
        Html::header(
            Config::getTypeName(1),
            $_SERVER['PHP_SELF'],
            'config',
            Config::class
        );
        $config = new Config();
        $config->display();
        Html::footer();
        return new Response(ob_get_clean());
    }
}
