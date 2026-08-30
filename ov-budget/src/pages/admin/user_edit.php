<?php
declare(strict_types=1);

$me = require_role('admin');

$id = get_int('id');
$edit = $id ? db_row('SELECT * FROM users WHERE id = ?', [$id]) : null;

if ($id && !$edit) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Benutzer gibt es nicht (mehr).']);
    return;
}

$errors = [];
$neuesPasswort = null;
$funktionen = $edit ? user_functions((int)$edit['id']) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action');

    if ($action === 'delete' && $edit) {
        if ((int)$edit['id'] === (int)$me['id']) {
            flash('error', 'Der eigene Zugang kann nicht gelöscht werden.');
        } else {
            db_exec('DELETE FROM users WHERE id = ?', [$edit['id']]);
            audit('benutzer.geloescht', 'user', (int)$edit['id'], $edit['username']);
            flash('success', 'Benutzer gelöscht.');
            redirect_route('admin_users');
        }
    }

    if ($action === 'reset_pw' && $edit) {
        $neu = (string)post('neues_passwort', '');
        if ($neu === '') {
            $neu = bin2hex(random_bytes(6));
        }
        if ($p = password_problem($neu)) {
            $errors[] = $p;
        } else {
            db_update('users', [
                'password_hash'  => password_hash($neu, PASSWORD_DEFAULT),
                'must_change_pw' => 1,
            ], 'id = ?', [$edit['id']]);
            db_exec('DELETE FROM login_attempts WHERE username = ?', [$edit['username']]);
            audit('benutzer.passwort', 'user', (int)$edit['id'], $edit['username']);
            $neuesPasswort = $neu;
            flash('success', 'Neues Passwort gesetzt: <strong class="mono">' . e($neu)
                . '</strong> – bitte sicher weitergeben. Der Benutzer wird zur Änderung aufgefordert.');
        }
    }

    if ($action === '' || $action === 'save') {
        $username = strtolower(post_str('username'));
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
            $errors[] = 'Der Benutzername darf nur Kleinbuchstaben, Ziffern, Punkt, Bindestrich und Unterstrich enthalten (3–60 Zeichen).';
        }
        $dup = db_val('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, (int)($edit['id'] ?? 0)]);
        if ($dup) {
            $errors[] = 'Dieser Benutzername ist bereits vergeben.';
        }

        $role = post_str('role', 'user');
        if (!in_array($role, ['admin', 'leitung', 'user'], true)) {
            $role = 'user';
        }
        $aktiv = post_bool('is_active');

        // Sich selbst nicht aussperren
        if ($edit && (int)$edit['id'] === (int)$me['id']) {
            if ($role !== 'admin' || !$aktiv) {
                $errors[] = 'Du kannst dir selbst die Administrationsrechte nicht entziehen.';
                $role = 'admin';
                $aktiv = 1;
            }
        }

        $pw = (string)post('passwort', '');
        if (!$edit) {
            if ($pw === '') {
                $pw = bin2hex(random_bytes(6));
                $neuesPasswort = $pw;
            }
            if ($p = password_problem($pw)) {
                $errors[] = $p;
            }
        }

        if (!$errors) {
            $data = [
                'username'      => $username,
                'display_name'  => mb_substr(post_str('display_name'), 0, 150),
                'email'         => mb_substr(post_str('email'), 0, 150),
                'phone'         => mb_substr(post_str('phone'), 0, 60),
                'role'          => $role,
                'fachgruppe_id' => post_int('fachgruppe_id'),
                'is_active'     => $aktiv,
            ];

            if ($edit) {
                db_update('users', $data, 'id = ?', [$edit['id']]);
                $uid = (int)$edit['id'];
                audit('benutzer.bearbeitet', 'user', $uid, $username);
            } else {
                $data['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
                $data['must_change_pw'] = 1;
                $uid = db_insert('users', $data);
                audit('benutzer.angelegt', 'user', $uid, $username);
            }

            db_exec('DELETE FROM user_functions WHERE user_id = ?', [$uid]);
            foreach ((array)post('funktionen', []) as $fid) {
                if ((int)$fid > 0) {
                    db_exec('INSERT IGNORE INTO user_functions (user_id, function_id) VALUES (?,?)', [$uid, (int)$fid]);
                }
            }

            if ($neuesPasswort !== null) {
                flash('success', 'Benutzer angelegt. Startpasswort: <strong class="mono">'
                    . e($neuesPasswort) . '</strong> – bitte sicher weitergeben.');
            } else {
                flash('success', 'Benutzer gespeichert.');
            }
            redirect_route('admin_user_edit', ['id' => $uid]);
        }

        // Bei Fehlern die Eingaben stehen lassen, statt sie neu aus der Datenbank zu holen
        $edit = array_merge($edit ?? [], $_POST, ['id' => $edit['id'] ?? null]);
        $funktionen = array_map('intval', (array)post('funktionen', []));
    }
}

if (!$edit) {
    $edit = [
        'id' => null, 'username' => '', 'display_name' => '', 'email' => '', 'phone' => '',
        'role' => 'user', 'fachgruppe_id' => null, 'is_active' => 1, 'must_change_pw' => 0,
        'last_login' => null, 'created_at' => null,
    ];
}

render('admin/user_edit', [
    'title'      => $edit['id'] ? 'Benutzer bearbeiten' : 'Benutzer anlegen',
    'edit'       => $edit,
    'errors'     => $errors,
    'funktionen' => $funktionen,
    'istIchSelbst' => (int)($edit['id'] ?? 0) === (int)$me['id'],
]);
