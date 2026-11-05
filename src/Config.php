<?php

namespace GlpiPlugin\Mailmanager;

use CommonDBTM;

class Config extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0): string
    {
        return __('Configuration Mail Manager', 'mailmanager');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_mailmanager_configs';
    }

    public static function getConfig(string $key, $default = null)
    {
        global $DB;
        $row = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['config_key' => $key],
        ])->current();
        return $row ? $row['config_value'] : $default;
    }

    public static function save(array $input): void
    {
        global $DB;
        $allowed = ['default_server_type', 'accounts_plugin_type'];
        foreach ($allowed as $key) {
            if (!isset($input[$key])) continue;
            $existing = $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => ['config_key' => $key],
            ])->current();
            if ($existing) {
                $DB->update(self::getTable(), ['config_value' => $input[$key]], ['id' => $existing['id']]);
            } else {
                $DB->insert(self::getTable(), ['config_key' => $key, 'config_value' => $input[$key]]);
            }
        }
        \Session::addMessageAfterRedirect(__('Configuration enregistrée.', 'mailmanager'), true, INFO);
    }
}
