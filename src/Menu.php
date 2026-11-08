<?php

namespace GlpiPlugin\Mailmanager;

use CommonGLPI;
use Session;

/**
 * Menu principal « Gestion Mails » (secteur propre, à côté de Gestion, Outils…).
 * GLPI n'a que deux niveaux (secteur → entrées) : les entrées Serveurs mail,
 * Boîtes mail (et plus tard Hébergeurs mail) sont déclarées ici en mode
 * « multi-entrées », en un seul endroit.
 */
class Menu extends CommonGLPI
{
    public static $rightname = Right::RIGHT_MAILSERVER;

    /** Secteur GLPI où se trouve le menu (utilisé par setup.php et les pages front). */
    public const MENU_SECTOR = 'mailmanager';

    public static function getTypeName($nb = 0): string
    {
        return 'Gestion Mails';
    }

    public static function getMenuName(): string
    {
        return 'Gestion Mails';
    }

    public static function getIcon(): string
    {
        return 'ti ti-mailbox';
    }

    public static function canView(): bool
    {
        return Session::haveRight(Right::RIGHT_MAILSERVER, READ)
            || Session::haveRight(Right::RIGHT_MAILOBJECT, READ);
    }

    public static function getMenuContent(): array
    {
        $entries = [];

        if (Session::haveRight(Right::RIGHT_MAILSERVER, READ)) {
            $entries['mailserver'] = [
                'title' => MailServer::getTypeName(2),
                'page'  => MailServer::getSearchURL(false),
                'icon'  => MailServer::getIcon(),
                'links' => self::links(MailServer::class, Right::RIGHT_MAILSERVER),
            ];
        }

        if (Session::haveRight(Right::RIGHT_MAILOBJECT, READ)) {
            $entries['mailobject'] = [
                'title' => MailObject::getTypeName(2),
                'page'  => MailObject::getSearchURL(false),
                'icon'  => MailObject::getIcon(),
                'links' => self::links(MailObject::class, Right::RIGHT_MAILOBJECT),
            ];
        }

        if ($entries === []) {
            return [];
        }

        // 'title' et 'icon' définissent le secteur lui-même ; 'is_multi_entries'
        // demande à GLPI de fusionner nos entrées dans le menu (cf. Html::generateMenuSession).
        return [
            'title'            => self::getMenuName(),
            'icon'             => self::getIcon(),
            'default'          => reset($entries)['page'],
            'is_multi_entries' => true,
        ] + $entries;
    }

    private static function links(string $class, string $right): array
    {
        $links = ['search' => $class::getSearchURL(false)];
        if (Session::haveRight($right, CREATE)) {
            $links['add'] = $class::getFormURL(false);
        }
        return $links;
    }
}
