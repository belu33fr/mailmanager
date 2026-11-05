<?php

namespace GlpiPlugin\Mailmanager;

use CommonDBTM;

/**
 * Relation N-N entre MailServer et les Domaines GLPI natifs.
 */
class MailServerDomain extends CommonDBTM
{
    public static $rightname = Right::RIGHT_MAILSERVER;

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_mailmanager_mailserver_domains';
    }

    /**
     * Retourne les IDs de domaines liés à un serveur.
     */
    public static function getDomainsForServer(int $serverId): array
    {
        global $DB;
        $result = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['mailservers_id' => $serverId],
        ]) as $row) {
            $result[] = (int)$row['domains_id'];
        }
        return $result;
    }

    /**
     * Retourne les IDs de serveurs liés à un domaine.
     */
    public static function getServersForDomain(int $domainId): array
    {
        global $DB;
        $result = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['domains_id' => $domainId],
        ]) as $row) {
            $result[] = (int)$row['mailservers_id'];
        }
        return $result;
    }

    /**
     * Synchronise les liaisons d'un serveur avec la liste fournie.
     */
    public static function syncForServer(int $serverId, array $domainIds): void
    {
        global $DB;

        $existing = self::getDomainsForServer($serverId);

        // Supprimer les liaisons retirées
        $toDelete = array_diff($existing, $domainIds);
        foreach ($toDelete as $domainId) {
            $DB->delete(self::getTable(), [
                'mailservers_id' => $serverId,
                'domains_id'     => $domainId,
            ]);
        }

        // Ajouter les nouvelles liaisons
        $toAdd = array_diff($domainIds, $existing);
        foreach ($toAdd as $domainId) {
            if ($domainId <= 0) continue;
            $DB->insert(self::getTable(), [
                'mailservers_id' => $serverId,
                'domains_id'     => $domainId,
            ]);
        }
    }
}
