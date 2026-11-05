<?php

namespace GlpiPlugin\Mailmanager;

use CommonDBTM;

/**
 * Relation N-N générique entre objets mail.
 *
 * relation_type peut être :
 *   - 'list_member'       : membre d'une liste d'email
 *   - 'list_admin'        : administrateur d'une liste d'email
 *   - 'shared_member'     : membre (accès) d'une boîte partagée
 *   - 'shared_read'       : autorisation lecture/gestion
 *   - 'shared_send_as'    : autorisation "Envoyer en tant que"
 *   - 'shared_send_behalf': autorisation "Envoyer de la part de"
 */
class MailObjectRelation extends CommonDBTM
{
    public static $rightname = Right::RIGHT_MAILOBJECT;

    // Types de relations valides
    const TYPES = [
        'list_member',
        'list_admin',
        'shared_member',
        'shared_read',
        'shared_send_as',
        'shared_send_behalf',
    ];

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_mailmanager_mailobject_relations';
    }

    /**
     * Retourne les IDs des objets liés à $mailobjectId pour un type de relation.
     */
    public static function getRelated(int $mailobjectId, string $relationType): array
    {
        global $DB;
        $result = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'mailobjects_id' => $mailobjectId,
                'relation_type'  => $relationType,
            ],
        ]) as $row) {
            $result[] = (int)$row['related_id'];
        }
        return $result;
    }

    /**
     * Retourne les IDs des objets dont $mailobjectId est membre (relation inverse).
     * Ex: de quelles listes cette boîte fait-elle partie ?
     */
    public static function getParents(int $mailobjectId, string $relationType): array
    {
        global $DB;
        $result = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'related_id'    => $mailobjectId,
                'relation_type' => $relationType,
            ],
        ]) as $row) {
            $result[] = (int)$row['mailobjects_id'];
        }
        return $result;
    }

    /**
     * Synchronise les relations d'un objet pour un type donné.
     */
    public static function syncRelations(int $mailobjectId, string $relationType, array $relatedIds): void
    {
        global $DB;

        $existing = self::getRelated($mailobjectId, $relationType);

        $toDelete = array_diff($existing, $relatedIds);
        foreach ($toDelete as $relId) {
            $DB->delete(self::getTable(), [
                'mailobjects_id' => $mailobjectId,
                'related_id'     => $relId,
                'relation_type'  => $relationType,
            ]);
        }

        $toAdd = array_diff($relatedIds, $existing);
        foreach ($toAdd as $relId) {
            if ($relId <= 0) continue;
            $DB->insert(self::getTable(), [
                'mailobjects_id' => $mailobjectId,
                'related_id'     => $relId,
                'relation_type'  => $relationType,
            ]);
        }
    }

    /**
     * Supprime toutes les relations d'un objet (utile à la suppression).
     */
    public static function deleteAllForObject(int $mailobjectId): void
    {
        global $DB;
        $DB->delete(self::getTable(), ['mailobjects_id' => $mailobjectId]);
        $DB->delete(self::getTable(), ['related_id' => $mailobjectId]);
    }
}
