<?php

/**
 * MailManager — endpoint AJAX des formulaires (serveur et boîte mail).
 *
 * Lecture (GET) :
 *   action=account_user&accounts_id=N   utilisateur GLPI rattaché à un compte (plugin Accounts)
 *   action=user_info&users_id=N         données d'un utilisateur pour pré-remplir la boîte
 *   action=user_by_email&email=...      utilisateur GLPI portant cette adresse
 * Écriture (POST, jeton CSRF en en-tête X-Glpi-Csrf-Token) :
 *   action=user_create                  création rapide d'un utilisateur
 *   action=user_addemail                ajout d'une adresse à la fiche d'un utilisateur
 */

include('../../../inc/includes.php');

use GlpiPlugin\Mailmanager\Right;

Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');

/** Libellé lisible d'un utilisateur GLPI. */
function mm_user_label(int $id): string
{
    $user = new User();
    if ($id <= 0 || !$user->getFromDB($id)) {
        return '';
    }
    $full = trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? ''));
    return $full !== '' ? $full . ' (' . $user->fields['name'] . ')' : (string) $user->fields['name'];
}

/** Adresses d'un utilisateur : [adresse par défaut, toutes les adresses]. */
function mm_user_emails(int $id): array
{
    global $DB;
    $all     = [];
    $default = '';
    foreach ($DB->request([
        'FROM'  => 'glpi_useremails',
        'WHERE' => ['users_id' => $id],
        'ORDER' => ['is_default DESC', 'id ASC'],
    ]) as $row) {
        $all[] = $row['email'];
        if ($default === '' && $row['email'] !== '') {
            $default = $row['email'];
        }
    }
    return [$default, $all];
}

/** Messages GLPI en attente (erreurs d'un add/update), aplatis en texte. */
function mm_pop_messages(): string
{
    $out = [];
    foreach (($_SESSION['MESSAGE_AFTER_REDIRECT'] ?? []) as $messages) {
        foreach ((array) $messages as $m) {
            $out[] = trim(strip_tags(html_entity_decode((string) $m)));
        }
    }
    unset($_SESSION['MESSAGE_AFTER_REDIRECT']);
    return implode(' ', array_filter($out));
}

function mm_json(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    !Session::haveRight(Right::RIGHT_MAILSERVER, READ)
    && !Session::haveRight(Right::RIGHT_MAILOBJECT, READ)
) {
    mm_json(['error' => 'forbidden'], 403);
}

global $DB;
$action = (string) ($_REQUEST['action'] ?? '');

try {
    switch ($action) {
        // --------------------------------------------------------------
        case 'account_user':
            $accountId = (int) ($_GET['accounts_id'] ?? 0);
            $table     = 'glpi_plugin_accounts_accounts';
            $uid       = 0;
            $source    = '';

            if ($accountId > 0 && $DB->tableExists($table)) {
                $row = $DB->request(['FROM' => $table, 'WHERE' => ['id' => $accountId]])->current();
                if ($row) {
                    // 1. technicien responsable, sinon utilisateur renseigné sur le compte
                    foreach (['users_id_tech', 'users_id'] as $col) {
                        if ((int) ($row[$col] ?? 0) > 0) {
                            $uid    = (int) $row[$col];
                            $source = 'account';
                            break;
                        }
                    }
                    // 2. sinon, identifiant ou nom du compte = identifiant ou adresse d'un utilisateur
                    if ($uid === 0) {
                        foreach (['login', 'name'] as $col) {
                            $val = trim((string) ($row[$col] ?? ''));
                            if ($val === '') {
                                continue;
                            }
                            $u = $DB->request([
                                'SELECT' => ['id'],
                                'FROM'   => 'glpi_users',
                                'WHERE'  => ['name' => $val, 'is_deleted' => 0],
                            ])->current();
                            if ($u) {
                                $uid    = (int) $u['id'];
                                $source = 'login';
                                break;
                            }
                            if (str_contains($val, '@')) {
                                $e = $DB->request([
                                    'SELECT' => ['users_id'],
                                    'FROM'   => 'glpi_useremails',
                                    'WHERE'  => ['email' => $val],
                                ])->current();
                                if ($e) {
                                    $uid    = (int) $e['users_id'];
                                    $source = 'email';
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            mm_json(['users_id' => $uid, 'name' => mm_user_label($uid), 'source' => $source]);

        // --------------------------------------------------------------
        case 'user_info':
            $id   = (int) ($_GET['users_id'] ?? 0);
            $user = new User();
            if ($id <= 0 || !$user->getFromDB($id)) {
                mm_json(['found' => false]);
            }
            $f = $user->fields;
            [$default, $all] = mm_user_emails($id);

            $jobTitle = '';
            if ((int) ($f['usercategories_id'] ?? 0) > 0) {
                $jobTitle = (string) Dropdown::getDropdownName('glpi_usercategories', (int) $f['usercategories_id']);
            }
            $department = '';
            if ((int) ($f['groups_id'] ?? 0) > 0) {
                $department = (string) Dropdown::getDropdownName('glpi_groups', (int) $f['groups_id']);
            }
            $postal = $city = $country = '';
            if ((int) ($f['locations_id'] ?? 0) > 0) {
                $loc = $DB->request([
                    'FROM'  => 'glpi_locations',
                    'WHERE' => ['id' => (int) $f['locations_id']],
                ])->current();
                if ($loc) {
                    $postal  = (string) ($loc['postcode'] ?? '');
                    $city    = (string) ($loc['town'] ?? '');
                    $country = (string) ($loc['country'] ?? '');
                }
            }
            $display = trim(($f['firstname'] ?? '') . ' ' . ($f['realname'] ?? ''));

            mm_json([
                'found'        => true,
                'label'        => mm_user_label($id),
                'display_name' => $display,
                'email'        => $default,
                'emails'       => $all,
                'fields'       => [
                    'phone'        => (string) ($f['phone'] ?? ''),
                    'phone_mobile' => (string) ($f['mobile'] ?? ''),
                    'job_title'    => $jobTitle,
                    'department'   => $department,
                    'postal_code'  => $postal,
                    'city'         => $city,
                    'country'      => $country,
                ],
            ]);

        // --------------------------------------------------------------
        case 'user_by_email':
            $email = trim((string) ($_GET['email'] ?? ''));
            $uid   = 0;
            if ($email !== '') {
                $e = $DB->request([
                    'SELECT' => ['users_id'],
                    'FROM'   => 'glpi_useremails',
                    'WHERE'  => ['email' => $email],
                ])->current();
                if ($e) {
                    $uid = (int) $e['users_id'];
                }
            }
            mm_json(['users_id' => $uid, 'name' => mm_user_label($uid)]);

        // --------------------------------------------------------------
        case 'user_create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                mm_json(['error' => 'POST required'], 405);
            }
            if (!Session::haveRight('user', CREATE)) {
                mm_json(['error' => __('Vous n\'avez pas le droit de créer des utilisateurs.', 'mailmanager')], 403);
            }
            $realname  = trim((string) ($_POST['realname'] ?? ''));
            $firstname = trim((string) ($_POST['firstname'] ?? ''));
            $email     = trim((string) ($_POST['email'] ?? ''));
            $login     = trim((string) ($_POST['login'] ?? ''));
            if ($realname === '' && $firstname === '') {
                mm_json(['error' => __('Renseignez au moins le nom ou le prénom.', 'mailmanager')], 422);
            }
            if ($login === '') {
                $login = $email !== '' && str_contains($email, '@')
                    ? strtolower(strstr($email, '@', true))
                    : strtolower(trim($firstname . '.' . $realname, '.'));
                $login = preg_replace('/[^a-z0-9._-]+/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $login) ?: $login);
            }
            if ($login === '') {
                mm_json(['error' => __('Identifiant de connexion impossible à déterminer.', 'mailmanager')], 422);
            }

            $user  = new User();
            $newId = $user->add([
                'name'        => $login,
                'realname'    => $realname,
                'firstname'   => $firstname,
                'phone'       => trim((string) ($_POST['phone'] ?? '')),
                'mobile'      => trim((string) ($_POST['mobile'] ?? '')),
                'is_active'   => 1,
                'authtype'    => Auth::DB_GLPI,
                'entities_id' => $_SESSION['glpiactive_entity'] ?? 0,
            ]);
            if (!$newId) {
                $msg = mm_pop_messages();
                mm_json(['error' => $msg !== '' ? $msg : __('Création de l\'utilisateur impossible.', 'mailmanager')], 422);
            }
            mm_pop_messages();

            if ($email !== '') {
                (new UserEmail())->add(['users_id' => $newId, 'email' => $email, 'is_default' => 1]);
                mm_pop_messages();
            }
            mm_json(['id' => (int) $newId, 'label' => mm_user_label((int) $newId)]);

        // --------------------------------------------------------------
        case 'user_addemail':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                mm_json(['error' => 'POST required'], 405);
            }
            $uid   = (int) ($_POST['users_id'] ?? 0);
            $email = trim((string) ($_POST['email'] ?? ''));
            $user  = new User();
            if ($uid <= 0 || $email === '' || !$user->getFromDB($uid) || !$user->canUpdateItem()) {
                mm_json(['error' => __('Ajout de l\'adresse impossible (droits ou données).', 'mailmanager')], 403);
            }
            [$default, $all] = mm_user_emails($uid);
            if (!in_array(strtolower($email), array_map('strtolower', $all), true)) {
                (new UserEmail())->add([
                    'users_id'   => $uid,
                    'email'      => $email,
                    'is_default' => $default === '' ? 1 : 0,
                ]);
                mm_pop_messages();
            }
            mm_json(['ok' => true]);

        // --------------------------------------------------------------
        default:
            mm_json(['error' => 'unknown action'], 400);
    }
} catch (\Throwable $e) {
    mm_json(['error' => $e->getMessage()], 500);
}
