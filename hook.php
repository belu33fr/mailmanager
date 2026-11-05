<?php

use GlpiPlugin\Mailmanager\MailServer;
use GlpiPlugin\Mailmanager\MailObject;
use GlpiPlugin\Mailmanager\MailServerDomain;
use GlpiPlugin\Mailmanager\MailObjectRelation;
use GlpiPlugin\Mailmanager\Right;
use GlpiPlugin\Mailmanager\Config;

function plugin_mailmanager_install(): bool
{
    global $DB;

    $default_charset   = \DBConnection::getDefaultCharset();
    $default_collation = \DBConnection::getDefaultCollation();

    // ----------------------------------------------------------------
    // Table des serveurs mail
    // ----------------------------------------------------------------
    $table = MailServer::getTable();
    if (!$DB->tableExists($table)) {
        $DB->doQuery("
            CREATE TABLE `$table` (
                `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`              VARCHAR(255) NOT NULL DEFAULT '',
                `comment`           TEXT,
                `server_type`       VARCHAR(50)  NOT NULL DEFAULT '',
                `fqdn`              VARCHAR(255) NOT NULL DEFAULT '',
                `port`              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `encryption`        VARCHAR(20)  NOT NULL DEFAULT 'none',
                `password_in_out`   TINYINT(1)   NOT NULL DEFAULT 0,
                `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
                `states_id`         INT UNSIGNED NOT NULL DEFAULT 0,
                `entities_id`       INT UNSIGNED NOT NULL DEFAULT 0,
                `is_recursive`      TINYINT(1)   NOT NULL DEFAULT 0,
                `groups_id`         INT UNSIGNED NOT NULL DEFAULT 0,
                `groups_id_tech`    INT UNSIGNED NOT NULL DEFAULT 0,
                `plugin_accounts_accounts_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `date_creation`     DATETIME DEFAULT NULL,
                `date_mod`          DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name` (`name`),
                KEY `entities_id` (`entities_id`),
                KEY `is_active` (`is_active`),
                KEY `states_id` (`states_id`),
                KEY `server_type` (`server_type`)
            ) ENGINE=InnoDB
            DEFAULT CHARSET={$default_charset}
            COLLATE={$default_collation}
        ");
    }

    // ----------------------------------------------------------------
    // Table de liaison Serveur <-> Domaines (N-N)
    // ----------------------------------------------------------------
    $table = MailServerDomain::getTable();
    if (!$DB->tableExists($table)) {
        $DB->doQuery("
            CREATE TABLE `$table` (
                `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `mailservers_id`  INT UNSIGNED NOT NULL,
                `domains_id`      INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_server_domain` (`mailservers_id`, `domains_id`),
                KEY `domains_id` (`domains_id`)
            ) ENGINE=InnoDB
            DEFAULT CHARSET={$default_charset}
            COLLATE={$default_collation}
        ");
    }

    // ----------------------------------------------------------------
    // Table des objets mail (boîtes, alias, listes, boîtes partagées…)
    // ----------------------------------------------------------------
    $table = MailObject::getTable();
    if (!$DB->tableExists($table)) {
        $DB->doQuery("
            CREATE TABLE `$table` (
                `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `mail_type`             VARCHAR(30)  NOT NULL DEFAULT 'mailbox',
                `email`                 VARCHAR(255) NOT NULL DEFAULT '',
                `domains_id`            INT UNSIGNED NOT NULL DEFAULT 0,
                `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
                `states_id`             INT UNSIGNED NOT NULL DEFAULT 0,
                `entities_id`           INT UNSIGNED NOT NULL DEFAULT 0,
                `is_recursive`          TINYINT(1)   NOT NULL DEFAULT 0,
                `comment`               TEXT,

                -- Boîte mail
                `users_id`              INT UNSIGNED NOT NULL DEFAULT 0,
                `display_name`          VARCHAR(255) NOT NULL DEFAULT '',
                `plugin_accounts_accounts_id_in`  INT UNSIGNED NOT NULL DEFAULT 0,
                `mailservers_id_in`     INT UNSIGNED NOT NULL DEFAULT 0,
                `mailservers_id_out`    INT UNSIGNED NOT NULL DEFAULT 0,
                `plugin_accounts_accounts_id_out` INT UNSIGNED NOT NULL DEFAULT 0,
                `rescue_email`          VARCHAR(255) NOT NULL DEFAULT '',
                `has_forward`           TINYINT(1)   NOT NULL DEFAULT 0,
                `forward_to`            VARCHAR(255) NOT NULL DEFAULT '',
                `has_autoreply`         TINYINT(1)   NOT NULL DEFAULT 0,
                `softwarelicenses_id`   INT UNSIGNED NOT NULL DEFAULT 0,
                `job_title`             VARCHAR(255) NOT NULL DEFAULT '',
                `department`            VARCHAR(255) NOT NULL DEFAULT '',
                `phone`                 VARCHAR(50)  NOT NULL DEFAULT '',
                `phone_mobile`          VARCHAR(50)  NOT NULL DEFAULT '',
                `postal_code`           VARCHAR(20)  NOT NULL DEFAULT '',
                `city`                  VARCHAR(255) NOT NULL DEFAULT '',
                `country`               VARCHAR(255) NOT NULL DEFAULT '',

                -- Alias
                `alias_target_id`       INT UNSIGNED NOT NULL DEFAULT 0,

                -- Boîte partagée
                `shared_name`           VARCHAR(255) NOT NULL DEFAULT '',
                `shared_has_forward`    TINYINT(1)   NOT NULL DEFAULT 0,
                `shared_forward_to`     VARCHAR(255) NOT NULL DEFAULT '',
                `shared_has_autoreply`  TINYINT(1)   NOT NULL DEFAULT 0,

                `date_creation`         DATETIME DEFAULT NULL,
                `date_mod`              DATETIME DEFAULT NULL,

                PRIMARY KEY (`id`),
                KEY `mail_type` (`mail_type`),
                KEY `email` (`email`),
                KEY `entities_id` (`entities_id`),
                KEY `users_id` (`users_id`),
                KEY `domains_id` (`domains_id`),
                KEY `states_id` (`states_id`)
            ) ENGINE=InnoDB
            DEFAULT CHARSET={$default_charset}
            COLLATE={$default_collation}
        ");
    }

    // ----------------------------------------------------------------
    // Table des relations N-N génériques entre objets mail
    // (liste↔membres, liste↔admins, boîte partagée↔membres, etc.)
    // ----------------------------------------------------------------
    $table = MailObjectRelation::getTable();
    if (!$DB->tableExists($table)) {
        $DB->doQuery("
            CREATE TABLE `$table` (
                `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `mailobjects_id`    INT UNSIGNED NOT NULL,
                `related_id`        INT UNSIGNED NOT NULL,
                `relation_type`     VARCHAR(50)  NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_relation` (`mailobjects_id`, `related_id`, `relation_type`),
                KEY `related_id` (`related_id`),
                KEY `relation_type` (`relation_type`)
            ) ENGINE=InnoDB
            DEFAULT CHARSET={$default_charset}
            COLLATE={$default_collation}
        ");
    }

    // ----------------------------------------------------------------
    // Table de configuration du plugin
    // ----------------------------------------------------------------
    $table = Config::getTable();
    if (!$DB->tableExists($table)) {
        $DB->doQuery("
            CREATE TABLE `$table` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `config_key`   VARCHAR(100) NOT NULL DEFAULT '',
                `config_value` TEXT,
                PRIMARY KEY (`id`),
                UNIQUE KEY `config_key` (`config_key`)
            ) ENGINE=InnoDB
            DEFAULT CHARSET={$default_charset}
            COLLATE={$default_collation}
        ");
    }

    // Droits par défaut
    Right::addDefaultProfileRights();

    return true;
}

function plugin_mailmanager_uninstall(): bool
{
    global $DB;

    $tables = [
        MailObjectRelation::getTable(),
        MailObject::getTable(),
        MailServerDomain::getTable(),
        MailServer::getTable(),
        Config::getTable(),
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    Right::removeProfileRights();

    return true;
}

function plugin_mailmanager_activate(): void
{
    Right::addDefaultProfileRights();
}
