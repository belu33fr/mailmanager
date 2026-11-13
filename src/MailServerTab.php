<?php

namespace GlpiPlugin\Mailmanager;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Plugin;
use Session;

/**
 * Onglets natifs GLPI de la fiche Serveur mail (colonne de gauche) :
 * Paramétrage, Organisation. Chaque onglet a son propre formulaire et son propre « Sauvegarder ».
 */
class MailServerTab extends CommonGLPI
{
    private const TABS = [
        1 => ['Paramétrage', 'ti ti-adjustments'],
        2 => ['Organisation', 'ti ti-building'],
    ];

    public static function getTypeName($nb = 0): string
    {
        return __('Serveur mail', 'mailmanager');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof MailServer) || $item->isNewItem()) {
            return '';
        }
        $tabs = [];
        foreach (self::TABS as $num => [$label, $icon]) {
            $tabs[$num] = self::createTabEntry(__($label, 'mailmanager'), 0, null, $icon);
        }
        return $tabs;
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!($item instanceof MailServer)) {
            return false;
        }
        $params = [
            'item'      => $item,
            'canedit'   => $item->canUpdateItem(),
            'form_url'  => MailServer::getFormURL(),
            'webdir'    => Plugin::getWebDir('mailmanager'),
        ];

        if ((int) $tabnum === 1) {
            $params += [
                'encryptions'           => MailServer::ENCRYPTIONS,
                'default_ports'         => json_encode(MailServer::DEFAULT_PORTS),
                'password_in_out_types' => MailServer::TYPES_WITH_PASSWORD_IN_OUT,
                'server_types'          => MailServer::TYPES,
                'ajax_url'              => Plugin::getWebDir('mailmanager') . '/ajax/mailmanager.php',
                'accounts_class'        => class_exists('\\GlpiPlugin\\Accounts\\Account') ? '\\GlpiPlugin\\Accounts\\Account' : null,
            ];
            TemplateRenderer::getInstance()->display('@mailmanager/mailserver.tab_params.html.twig', $params);
            return true;
        }

        if ((int) $tabnum === 2) {
            global $DB;
            $all = [];
            foreach ($DB->request(['FROM' => 'glpi_domains', 'ORDER' => 'name']) as $row) {
                $all[] = ['id' => $row['id'], 'name' => $row['name']];
            }
            $params += [
                'all_domains'    => $all,
                'linked_domains' => MailServerDomain::getDomainsForServer((int) $item->getID()),
            ];
            TemplateRenderer::getInstance()->display('@mailmanager/mailserver.tab_org.html.twig', $params);
            return true;
        }
        return false;
    }
}
