<?php

namespace GlpiPlugin\Mailmanager;

use CommonGLPI;
use Session;

/**
 * Entrée unique du plugin dans le menu : Gestion → Mail Manager.
 * Les sous-entrées (Serveurs mail, Boîtes mail, plus tard Hébergeurs)
 * sont déclarées ici, en un seul endroit.
 */
class Menu extends CommonGLPI
{
    public static $rightname = Right::RIGHT_MAILSERVER;

    /** Secteur GLPI où se trouve le menu (utilisé par setup.php et les pages front). */
    public const MENU_SECTOR = 'management';

    public static function getTypeName($nb = 0): string
    {
        return 'Mail Manager';
    }

    public static function getMenuName(): string
    {
        return 'Mail Manager';
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
        $options = [];

        if (Session::haveRight(Right::RIGHT_MAILSERVER, READ)) {
            $options['mailserver'] = [
                'title' => MailServer::getTypeName(2),
                'page'  => MailServer::getSearchURL(false),
                'icon'  => MailServer::getIcon(),
                'links' => self::links(MailServer::class, Right::RIGHT_MAILSERVER),
            ];
        }

        if (Session::haveRight(Right::RIGHT_MAILOBJECT, READ)) {
            $options['mailobject'] = [
                'title' => MailObject::getTypeName(2),
                'page'  => MailObject::getSearchURL(false),
                'icon'  => MailObject::getIcon(),
                'links' => self::links(MailObject::class, Right::RIGHT_MAILOBJECT),
            ];
        }

        if ($options === []) {
            return [];
        }

        return [
            'title'   => self::getMenuName(),
            'page'    => reset($options)['page'],
            'icon'    => self::getIcon(),
            'options' => $options,
        ];
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
