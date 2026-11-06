<?php

namespace GlpiPlugin\Mailmanager;

use CommonDBTM;
use CommonGLPI;
use Html;
use Plugin;
use Session;
use User;

class MailObject extends CommonDBTM
{
    public static $rightname = Right::RIGHT_MAILOBJECT;
    public $dohistory         = true;
    public $recursive         = true;

    // Types d'objets mail
    const TYPE_MAILBOX        = 'mailbox';
    const TYPE_ALIAS          = 'alias';
    const TYPE_LIST           = 'list';
    const TYPE_SHARED_MAILBOX = 'shared';
    const TYPE_GROUP          = 'group';

    const TYPES = [
        self::TYPE_MAILBOX        => 'Boîte mail',
        self::TYPE_ALIAS          => 'Alias',
        self::TYPE_LIST           => 'Liste d\'email',
        self::TYPE_SHARED_MAILBOX => 'Boîte partagée',
        self::TYPE_GROUP          => 'Groupe mail',
    ];

    // Types cibles valides pour un alias
    const ALIAS_TARGET_TYPES = [self::TYPE_MAILBOX, self::TYPE_SHARED_MAILBOX];

    public function maybeRecursive(): bool { return true; }
    public function isEntityAssign(): bool  { return true; }
    public function canRecurs(): bool       { return Session::haveRight(self::$rightname, CREATE); }

    public static function getTypeName($nb = 0): string
    {
        return _n('Objet mail', 'Objets mail', $nb, 'mailmanager');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_mailmanager_mailobjects';
    }

    public static function getIcon(): string
    {
        return 'ti ti-mail';
    }

    public static function getFormURL($full = true): string
    {
        return Plugin::getWebDir('mailmanager', $full) . '/MailObject/form';
    }

    public static function getFormURLWithID($id = 0, $full = true): string
    {
        return self::getFormURL($full) . '?id=' . (int)$id;
    }

    public static function getSearchURL($full = true): string
    {
        return Plugin::getWebDir('mailmanager', $full) . '/MailObject';
    }

    // ------------------------------------------------------------------
    // Menu GLPI 11
    // ------------------------------------------------------------------

    public static function getMenuName(): string
    {
        return __('Boîtes mail', 'mailmanager');
    }

    public static function getMenuContent(): array
    {
        $search = self::getSearchURL(false);
        $form   = self::getFormURL(false);

        return [
            'title'   => self::getMenuName(),
            'page'    => $search,
            'icon'    => self::getIcon(),
            'options' => [
                'mailobject' => [
                    'title' => self::getTypeName(2),
                    'page'  => $search,
                    'links' => [
                        'search' => $search,
                        'add'    => $form,
                    ],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Formulaire GLPI 11 (Twig)
    // ------------------------------------------------------------------

    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);

        $accountsClass = class_exists('\GlpiPlugin\Accounts\Account')
            ? '\GlpiPlugin\Accounts\Account'
            : null;

        // Listes d'email dont cette boîte est membre / admin
        $listMemberships = [];
        $listAdminships  = [];
        if ($ID > 0) {
            $listMemberships = MailObjectRelation::getParents($ID, 'list_member');
            $listAdminships  = MailObjectRelation::getParents($ID, 'list_admin');
        }

        // Relations pour boîte partagée
        $sharedMembers    = [];
        $sharedRead       = [];
        $sharedSendAs     = [];
        $sharedSendBehalf = [];
        if ($ID > 0 && $this->fields['mail_type'] === self::TYPE_SHARED_MAILBOX) {
            $sharedMembers    = MailObjectRelation::getRelated($ID, 'shared_member');
            $sharedRead       = MailObjectRelation::getRelated($ID, 'shared_read');
            $sharedSendAs     = MailObjectRelation::getRelated($ID, 'shared_send_as');
            $sharedSendBehalf = MailObjectRelation::getRelated($ID, 'shared_send_behalf');
        }

        // Relations pour liste d'email
        $listMembers = [];
        $listAdmins  = [];
        if ($ID > 0 && $this->fields['mail_type'] === self::TYPE_LIST) {
            $listMembers = MailObjectRelation::getRelated($ID, 'list_member');
            $listAdmins  = MailObjectRelation::getRelated($ID, 'list_admin');
        }

        // Pré-remplir display_name depuis l'utilisateur si nouveau
        $defaultDisplayName = '';
        if ($ID <= 0 && isset($_GET['users_id'])) {
            $user = new User();
            if ($user->getFromDB((int)$_GET['users_id'])) {
                $defaultDisplayName = trim($user->fields['firstname'] . ' ' . $user->fields['realname']);
            }
        }

        \Glpi\Application\View\TemplateRenderer::getInstance()->display(
            '@mailmanager/mailobject.form.html.twig',
            [
                'item'                => $this,
                'params'              => $options,
                'mail_types'          => self::TYPES,
                'accounts_class'      => $accountsClass,
                'list_memberships'    => $listMemberships,
                'list_adminships'     => $listAdminships,
                'shared_members'      => $sharedMembers,
                'shared_read'         => $sharedRead,
                'shared_send_as'      => $sharedSendAs,
                'shared_send_behalf'  => $sharedSendBehalf,
                'list_members'        => $listMembers,
                'list_admins'         => $listAdmins,
                'default_display_name'=> $defaultDisplayName,
                'alias_target_types'  => self::ALIAS_TARGET_TYPES,
                'webdir'              => Plugin::getWebDir('mailmanager'),
            ]
        );

        return true;
    }

    // ------------------------------------------------------------------
    // Sauvegarde — relations N-N
    // ------------------------------------------------------------------

    public function post_addItem()
    {
        $this->saveRelations((int)$this->fields['id']);
    }

    public function post_updateItem($history = true)
    {
        $this->saveRelations((int)$this->fields['id']);
    }

    public function post_purgeItem()
    {
        MailObjectRelation::deleteAllForObject((int)$this->fields['id']);
    }

    private function saveRelations(int $objectId): void
    {
        $type = $this->fields['mail_type'] ?? '';

        // Listes d'email
        if ($type === self::TYPE_LIST) {
            foreach (['list_member' => 'list_members_ids', 'list_admin' => 'list_admins_ids'] as $relType => $postKey) {
                if (isset($_POST[$postKey])) {
                    $ids = array_map('intval', (array)$_POST[$postKey]);
                    MailObjectRelation::syncRelations($objectId, $relType, $ids);
                }
            }
        }

        // Boîte partagée
        if ($type === self::TYPE_SHARED_MAILBOX) {
            $relMap = [
                'shared_member'      => 'shared_members_ids',
                'shared_read'        => 'shared_read_ids',
                'shared_send_as'     => 'shared_send_as_ids',
                'shared_send_behalf' => 'shared_send_behalf_ids',
            ];
            foreach ($relMap as $relType => $postKey) {
                if (isset($_POST[$postKey])) {
                    $ids = array_map('intval', (array)$_POST[$postKey]);
                    MailObjectRelation::syncRelations($objectId, $relType, $ids);
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // prepareInput — nettoyage
    // ------------------------------------------------------------------

    public function prepareInputForAdd($input)
    {
        $input = parent::prepareInputForAdd($input);
        return $this->cleanInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        $input = parent::prepareInputForUpdate($input);
        return $this->cleanInput($input);
    }

    private function cleanInput(array $input): array
    {
        // Forcer le type mail à une valeur connue
        if (isset($input['mail_type']) && !array_key_exists($input['mail_type'], self::TYPES)) {
            $input['mail_type'] = self::TYPE_MAILBOX;
        }

        // Nettoyer les champs non pertinents selon le type
        $type = $input['mail_type'] ?? ($this->fields['mail_type'] ?? self::TYPE_MAILBOX);

        if ($type !== self::TYPE_MAILBOX) {
            foreach (['users_id', 'mailservers_id_in', 'mailservers_id_out', 'plugin_accounts_accounts_id_in', 'plugin_accounts_accounts_id_out', 'rescue_email', 'softwarelicenses_id', 'job_title', 'department', 'phone', 'phone_mobile', 'postal_code', 'city', 'country'] as $f) {
                if (!isset($input[$f])) $input[$f] = 0;
            }
            $input['has_forward']  = 0;
            $input['forward_to']   = '';
            $input['has_autoreply']= 0;
            $input['display_name'] = '';
        }

        if ($type !== self::TYPE_ALIAS) {
            if (!isset($input['alias_target_id'])) $input['alias_target_id'] = 0;
        }

        if ($type !== self::TYPE_SHARED_MAILBOX) {
            $input['shared_name']          = '';
            $input['shared_has_forward']   = 0;
            $input['shared_forward_to']    = '';
            $input['shared_has_autoreply'] = 0;
        }

        return $input;
    }

    // ------------------------------------------------------------------
    // Options de recherche
    // ------------------------------------------------------------------

    public function rawSearchOptions(): array
    {
        $tab = [];

        $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];

        $tab[] = [
            'id'            => 1,
            'table'         => self::getTable(),
            'field'         => 'email',
            'name'          => __('Adresse mail', 'mailmanager'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 2,
            'table'    => self::getTable(),
            'field'    => 'id',
            'name'     => __('ID'),
            'datatype' => 'number',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 10,
            'table'    => self::getTable(),
            'field'    => 'mail_type',
            'name'     => __('Type', 'mailmanager'),
            'datatype' => 'string',
            'massiveaction' => true,
        ];

        $tab[] = [
            'id'       => 11,
            'table'    => self::getTable(),
            'field'    => 'display_name',
            'name'     => __('Nom à afficher', 'mailmanager'),
            'datatype' => 'string',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 12,
            'table'         => 'glpi_domains',
            'field'         => 'name',
            'name'          => __('Domaine', 'mailmanager'),
            'datatype'      => 'dropdown',
            'massiveaction' => true,
            'joinparams'    => ['jointype' => 'field', 'linkfield' => 'domains_id'],
        ];

        $tab[] = [
            'id'            => 13,
            'table'         => 'glpi_users',
            'field'         => 'name',
            'name'          => __('Utilisateur', 'mailmanager'),
            'datatype'      => 'dropdown',
            'massiveaction' => false,
            'joinparams'    => ['jointype' => 'field', 'linkfield' => 'users_id'],
        ];

        $tab[] = [
            'id'       => 14,
            'table'    => self::getTable(),
            'field'    => 'is_active',
            'name'     => __('Actif', 'mailmanager'),
            'datatype' => 'bool',
            'massiveaction' => true,
        ];

        $tab[] = [
            'id'            => 15,
            'table'         => 'glpi_states',
            'field'         => 'name',
            'name'          => __('État'),
            'datatype'      => 'dropdown',
            'massiveaction' => true,
            'joinparams'    => ['jointype' => 'field', 'linkfield' => 'states_id'],
        ];

        $tab[] = [
            'id'            => 80,
            'table'         => 'glpi_entities',
            'field'         => 'completename',
            'name'          => __('Entité'),
            'datatype'      => 'dropdown',
            'massiveaction' => false,
        ];

        return $tab;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public static function getTypeLabel(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    /**
     * Retourne les boîtes mail actives (pour dropdowns alias/partagée).
     */
    public static function getMailboxes(array $types = [self::TYPE_MAILBOX, self::TYPE_SHARED_MAILBOX]): array
    {
        global $DB;
        $result = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['mail_type' => $types, 'is_active' => 1],
            'ORDER' => 'email ASC',
        ]) as $row) {
            $result[] = $row;
        }
        return $result;
    }
}
