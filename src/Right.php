<?php

namespace GlpiPlugin\Mailmanager;

use CommonGLPI;
use DbUtils;
use Profile;
use ProfileRight;
use Session;

class Right extends Profile
{
    public static $rightname = 'profile';

    const RIGHT_MAILSERVER = 'plugin_mailmanager_mailserver';
    const RIGHT_MAILOBJECT = 'plugin_mailmanager_mailobject';
    const RIGHT_CONFIG     = 'plugin_mailmanager_config';

    public static function getTypeName($nb = 0): string
    {
        return 'Mail Manager';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() === 'Profile' && $item->getField('interface') === 'central') {
            return self::createTabEntry(__('Mail Manager', 'mailmanager'));
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item->getType() === 'Profile') {
            $ID           = $item->getID();
            $prof         = new self();
            $profileRight = new ProfileRight();
            $dbu          = new DbUtils();

            foreach ([self::RIGHT_MAILSERVER, self::RIGHT_MAILOBJECT, self::RIGHT_CONFIG] as $right) {
                if ($dbu->countElementsInTable('glpi_profilerights', ['profiles_id' => $ID, 'name' => $right]) === 0) {
                    $profileRight->add(['profiles_id' => $ID, 'name' => $right, 'rights' => 0]);
                }
            }

            $prof->showForm($ID);
        }
        return true;
    }

    public function showForm($profiles_id = 0, $openform = true, $closeform = true)
    {
        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        $profile = new Profile();
        $profile->getFromDB($profiles_id);

        ob_start();
        $profile->displayRightsChoiceMatrix(self::getAllRights(), [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('Mail Manager', 'mailmanager'),
        ]);
        $rights_matrix = ob_get_clean();

        \Glpi\Application\View\TemplateRenderer::getInstance()->display(
            '@mailmanager/profile.html.twig',
            [
                'canedit'          => $canedit,
                'profile_form_url' => $profile->getFormURL(),
                'rights_matrix'    => $rights_matrix,
                'profiles_id'      => $profiles_id,
            ]
        );

        return true;
    }

    public static function getAllRights(): array
    {
        return [
            [
                'itemtype' => MailServer::class,
                'label'    => __('Serveurs mail', 'mailmanager'),
                'field'    => self::RIGHT_MAILSERVER,
            ],
            [
                'itemtype' => MailObject::class,
                'label'    => __('Boîtes mail / Objets mail', 'mailmanager'),
                'field'    => self::RIGHT_MAILOBJECT,
            ],
            [
                'itemtype' => Config::class,
                'label'    => __('Configuration', 'mailmanager'),
                'field'    => self::RIGHT_CONFIG,
                'rights'   => [READ => __('Lire'), UPDATE => __('Modifier')],
            ],
        ];
    }

    public static function addDefaultProfileRights(): void
    {
        global $DB;

        $defaults = [
            'Super-Admin' => [
                self::RIGHT_MAILSERVER => ALLSTANDARDRIGHT,
                self::RIGHT_MAILOBJECT => ALLSTANDARDRIGHT,
                self::RIGHT_CONFIG     => READ | UPDATE,
            ],
            'Admin' => [
                self::RIGHT_MAILSERVER => ALLSTANDARDRIGHT,
                self::RIGHT_MAILOBJECT => ALLSTANDARDRIGHT,
                self::RIGHT_CONFIG     => 0,
            ],
            'Observer' => [
                self::RIGHT_MAILSERVER => READ,
                self::RIGHT_MAILOBJECT => READ,
                self::RIGHT_CONFIG     => 0,
            ],
        ];

        $profileRight = new ProfileRight();
        $dbu          = new DbUtils();

        foreach ($DB->request(['FROM' => 'glpi_profiles']) as $profile) {
            $profileId = (int)$profile['id'];
            $rights    = $defaults[$profile['name']] ?? [
                self::RIGHT_MAILSERVER => 0,
                self::RIGHT_MAILOBJECT => 0,
                self::RIGHT_CONFIG     => 0,
            ];

            foreach ($rights as $rightName => $value) {
                if ($dbu->countElementsInTable('glpi_profilerights', ['profiles_id' => $profileId, 'name' => $rightName]) === 0) {
                    $profileRight->add(['profiles_id' => $profileId, 'name' => $rightName, 'rights' => $value]);
                } else {
                    // Mettre à jour si les droits sont à 0 (installation fraîche ou réinstallation)
                    $DB->update('glpi_profilerights',
                        ['rights' => $value],
                        ['profiles_id' => $profileId, 'name' => $rightName, 'rights' => 0]
                    );
                }
            }
        }
    }

    public static function removeProfileRights(): void
    {
        $rights = array_column(self::getAllRights(), 'field');
        ProfileRight::deleteProfileRights($rights);
    }

    public static function initProfile(): void
    {
        global $DB;

        foreach (self::getAllRights() as $data) {
            $dbu = new DbUtils();
            if ($dbu->countElementsInTable('glpi_profilerights', ['name' => $data['field']]) === 0) {
                ProfileRight::addProfileRights([$data['field']]);
            }
        }

        $profileId = $_SESSION['glpiactiveprofile']['id'] ?? 0;
        if ($profileId) {
            foreach ($DB->request([
                'FROM'  => 'glpi_profilerights',
                'WHERE' => ['profiles_id' => $profileId, 'name' => ['LIKE', '%plugin_mailmanager%']],
            ]) as $prof) {
                $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
            }
        }
    }

    public static function canConfig(): bool
    {
        return Session::haveRight(self::RIGHT_CONFIG, UPDATE);
    }
}
