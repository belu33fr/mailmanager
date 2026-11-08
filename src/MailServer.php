<?php

namespace GlpiPlugin\Mailmanager;

use CommonDBTM;
use CommonGLPI;
use Html;
use Plugin;
use Session;

class MailServer extends CommonDBTM
{
    public static $rightname = Right::RIGHT_MAILSERVER;
    public $dohistory         = true;
    public $recursive         = true;

    // Types de serveurs disponibles
    const TYPES = [
        'pop3'     => 'POP3',
        'imap'     => 'IMAP',
        'smtp'     => 'SMTP',
        'exchange' => 'Exchange',
        'ms365'    => 'MS365',
        'other'    => 'Autre',
    ];

    // Types de chiffrement disponibles
    const ENCRYPTIONS = [
        'none'     => 'Aucun',
        'ssl'      => 'SSL',
        'tls'      => 'TLS',
        'ssl_tls'  => 'SSL/TLS',
        'starttls' => 'STARTTLS',
    ];

    // Ports par défaut selon type + chiffrement
    const DEFAULT_PORTS = [
        'pop3'  => ['none' => 110, 'ssl' => 995, 'tls' => 995, 'ssl_tls' => 995, 'starttls' => 110],
        'imap'  => ['none' => 143, 'ssl' => 993, 'tls' => 993, 'ssl_tls' => 993, 'starttls' => 143],
        'smtp'  => ['none' => 25,  'ssl' => 465, 'tls' => 587, 'ssl_tls' => 465, 'starttls' => 587],
    ];

    // Types pour lesquels "Password In=Out" est pertinent
    const TYPES_WITH_PASSWORD_IN_OUT = ['smtp'];

    public function maybeRecursive(): bool { return true; }
    public function isEntityAssign(): bool  { return true; }
    public function canRecurs(): bool       { return Session::haveRight(self::$rightname, CREATE); }

    public static function getTypeName($nb = 0): string
    {
        return _n('Serveur mail', 'Serveurs mail', $nb, 'mailmanager');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_mailmanager_mailservers';
    }

    public static function getIcon(): string
    {
        return 'ti ti-server';
    }

    public static function getFormURL($full = true): string
    {
        return Plugin::getWebDir('mailmanager', $full) . '/front/mailserver.form.php';
    }

    public static function getFormURLWithID($id = 0, $full = true): string
    {
        return self::getFormURL($full) . '?id=' . (int)$id;
    }

    public static function getSearchURL($full = true): string
    {
        return Plugin::getWebDir('mailmanager', $full) . '/front/mailserver.php';
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

        // Domaines liés à ce serveur (IDs uniquement pour la sélection)
        $linkedDomains = [];
        if ($ID > 0) {
            $linkedDomains = MailServerDomain::getDomainsForServer($ID);
        }

        // Tous les domaines disponibles pour le <select multiple>
        global $DB;
        $allDomains = [];
        foreach ($DB->request(['FROM' => 'glpi_domains', 'ORDER' => 'name']) as $row) {
            $allDomains[] = ['id' => $row['id'], 'name' => $row['name']];
        }

        \Glpi\Application\View\TemplateRenderer::getInstance()->display(
            '@mailmanager/mailserver.form.html.twig',
            [
                'item'           => $this,
                'params'         => $options,
                'server_types'   => self::TYPES,
                'encryptions'    => self::ENCRYPTIONS,
                'default_ports'  => json_encode(self::DEFAULT_PORTS),
                'password_in_out_types' => self::TYPES_WITH_PASSWORD_IN_OUT,
                'accounts_class' => $accountsClass,
                'linked_domains' => $linkedDomains,
                'all_domains'    => $allDomains,
                'webdir'         => Plugin::getWebDir('mailmanager'),
                'ajax_url'       => Plugin::getWebDir('mailmanager') . '/ajax/mailmanager.php',
            ]
        );

        return true;
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
            'field'         => 'name',
            'name'          => __('Nom', 'mailmanager'),
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
            'field'    => 'server_type',
            'name'     => __('Type', 'mailmanager'),
            'datatype' => 'string',
            'massiveaction' => true,
        ];

        $tab[] = [
            'id'       => 11,
            'table'    => self::getTable(),
            'field'    => 'fqdn',
            'name'     => __('FQDN', 'mailmanager'),
            'datatype' => 'string',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 12,
            'table'    => self::getTable(),
            'field'    => 'port',
            'name'     => __('Port', 'mailmanager'),
            'datatype' => 'number',
            'massiveaction' => true,
        ];

        $tab[] = [
            'id'       => 13,
            'table'    => self::getTable(),
            'field'    => 'encryption',
            'name'     => __('Chiffrement', 'mailmanager'),
            'datatype' => 'string',
            'massiveaction' => true,
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
            'id'            => 16,
            'table'         => 'glpi_groups',
            'field'         => 'name',
            'name'          => __('Groupe'),
            'datatype'      => 'dropdown',
            'massiveaction' => true,
            'joinparams'    => ['jointype' => 'field', 'linkfield' => 'groups_id'],
        ];

        $tab[] = [
            'id'       => 17,
            'table'    => self::getTable(),
            'field'    => 'comment',
            'name'     => __('Commentaire', 'mailmanager'),
            'datatype' => 'text',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 18,
            'table'         => 'glpi_users',
            'field'         => 'name',
            'name'          => __('Contact technique', 'mailmanager'),
            'datatype'      => 'dropdown',
            'right'         => 'all',
            'massiveaction' => true,
            'joinparams'    => ['jointype' => 'field', 'linkfield' => 'users_id_tech'],
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

    public static function getEncryptionLabel(string $enc): string
    {
        return self::ENCRYPTIONS[$enc] ?? $enc;
    }

    /**
     * Retourne tous les serveurs actifs accessibles depuis l'entité courante.
     */
    public static function getActiveServers(): array
    {
        global $DB;
        $servers = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['is_active' => 1],
            'ORDER' => 'name ASC',
        ]) as $row) {
            $servers[] = $row;
        }
        return $servers;
    }

    // ------------------------------------------------------------------
    // Validation — le contact technique est obligatoire
    // ------------------------------------------------------------------

    public function prepareInputForAdd($input)
    {
        $input = parent::prepareInputForAdd($input);
        if ($input === false) {
            return false;
        }
        if ((int) ($input['users_id_tech'] ?? 0) <= 0) {
            return $this->refuseMissingTechContact();
        }
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $input = parent::prepareInputForUpdate($input);
        if ($input === false) {
            return false;
        }
        // Mise à jour partielle (action de masse, etc.) : on ne contrôle que si le champ est envoyé
        if (array_key_exists('users_id_tech', $input) && (int) $input['users_id_tech'] <= 0) {
            return $this->refuseMissingTechContact();
        }
        return $input;
    }

    private function refuseMissingTechContact(): bool
    {
        Session::addMessageAfterRedirect(
            __('Le contact technique est obligatoire.', 'mailmanager'),
            false,
            ERROR
        );
        return false;
    }

    // ------------------------------------------------------------------
    // Sauvegarde — liaison domaines
    // ------------------------------------------------------------------

    public function post_addItem()
    {
        $this->saveDomainLinks((int)$this->fields['id']);
    }

    public function post_updateItem($history = true)
    {
        $this->saveDomainLinks((int)$this->fields['id']);
    }

    private function saveDomainLinks(int $serverId): void
    {
        // Le formulaire envoie toujours _linked_domains_defined : permet de vider la liste
        if (!isset($_POST['linked_domains_ids']) && !isset($_POST['_linked_domains_defined'])) {
            return;
        }
        $domainIds = array_map('intval', (array)($_POST['linked_domains_ids'] ?? []));
        MailServerDomain::syncForServer($serverId, $domainIds);
    }
}
