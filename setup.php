<?php

/**
 * MailManager — Plugin GLPI 11 de gestion des boîtes mail et serveurs mail
 */

define('PLUGIN_MAILMANAGER_VERSION', '0.1.0');
define('PLUGIN_MAILMANAGER_MIN_GLPI', '11.0.0');
define('PLUGIN_MAILMANAGER_MAX_GLPI', '12.0.0');

use Glpi\Plugin\Hooks;
use GlpiPlugin\Mailmanager\MailServer;
use GlpiPlugin\Mailmanager\MailObject;
use GlpiPlugin\Mailmanager\Menu;
use GlpiPlugin\Mailmanager\Right;

function plugin_init_mailmanager(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['mailmanager'] = true;

    if (!Session::getLoginUserID()) {
        return;
    }

    // Menu principal « Mail Manager » : nouveau secteur, entrées déclarées dans Menu::getMenuContent.
    // La valeur doit être une liste de classes (tableau) pour que GLPI crée le secteur.
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['mailmanager'] = [
        Menu::MENU_SECTOR => [Menu::class],
    ];

    // Actions massives
    $PLUGIN_HOOKS[Hooks::USE_MASSIVE_ACTION]['mailmanager'] = 1;

    // Enregistrement des classes
    Plugin::registerClass(MailServer::class, ['dropdown_itemtypes' => true]);
    Plugin::registerClass(MailObject::class, ['dropdown_itemtypes' => true]);

    // Alias compatibilité MassiveAction GLPI 11
    if (!class_exists('PluginMailmanagerMailServer', false)) {
        class_alias(MailServer::class, 'PluginMailmanagerMailServer');
    }
    if (!class_exists('PluginMailmanagerMailObject', false)) {
        class_alias(MailObject::class, 'PluginMailmanagerMailObject');
    }

    // Onglet "Mail" dans les profils
    Plugin::registerClass(Right::class, ['addtabon' => 'Profile']);

    // Initialiser les droits dans la session courante
    Right::initProfile();

    // Page de configuration
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['mailmanager'] = 'front/config.php';

    // JS du formulaire boîte mail (affichage conditionnel par type)
    if (strpos($_SERVER['REQUEST_URI'] ?? '', 'mailmanager/front/mailobject.form.php') !== false) {
        $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['mailmanager'][] = 'public/mailobject.form.js';
    }
}

function plugin_version_mailmanager(): array
{
    return [
        'name'         => 'Mail Manager',
        'version'      => PLUGIN_MAILMANAGER_VERSION,
        'author'       => 'L. Berthaud, Claude (Anthropic)',
        'license'      => 'GPL v2+',
        'homepage'     => 'https://github.com/belu33fr/mailmanager',
        'bugtracker'   => 'https://github.com/belu33fr/mailmanager/issues',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_MAILMANAGER_MIN_GLPI,
                'max' => PLUGIN_MAILMANAGER_MAX_GLPI,
            ],
            'php'  => [
                'min'  => '8.1',
                'exts' => ['openssl', 'json'],
            ],
        ],
    ];
}

function plugin_mailmanager_check_prerequisites(): bool
{
    if (!extension_loaded('openssl')) {
        echo "Extension PHP openssl requise.<br/>";
        return false;
    }
    return true;
}

function plugin_mailmanager_check_config(): bool
{
    return true;
}
