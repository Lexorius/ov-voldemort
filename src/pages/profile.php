<?php
declare(strict_types=1);

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'password') {
        $alt = (string)post('alt', '');
        $neu = (string)post('neu', '');
        $neu2 = (string)post('neu2', '');

        if (!password_verify($alt, $user['password_hash'])) {
            $errors[] = 'Das aktuelle Passwort stimmt nicht.';
        }
        if ($neu !== $neu2) {
            $errors[] = 'Die beiden neuen Passwörter stimmen nicht überein.';
        }
        if ($p = password_problem($neu)) {
            $errors[] = $p;
        }
        if (!$errors) {
            db_update('users', [
                'password_hash'  => password_hash($neu, PASSWORD_DEFAULT),
                'must_change_pw' => 0,
            ], 'id = ?', [$user['id']]);
            audit('passwort.geaendert', 'user', (int)$user['id']);
            flash('success', 'Passwort geändert.');
            redirect_route('profile');
        }
    } else {
        db_update('users', [
            'display_name' => mb_substr(post_str('display_name'), 0, 150),
            'email'        => mb_substr(post_str('email'), 0, 150),
            'phone'        => mb_substr(post_str('phone'), 0, 60),
        ], 'id = ?', [$user['id']]);
        flash('success', 'Profil gespeichert.');
        redirect_route('profile');
    }
}

render('profile', [
    'title'  => 'Mein Profil',
    'user'   => $user,
    'errors' => $errors,
    'meine'  => db_all(
        'SELECT li.label FROM user_functions uf
         JOIN list_items li ON li.id = uf.function_id
         WHERE uf.user_id = ? ORDER BY li.sort_order',
        [$user['id']]
    ),
]);
