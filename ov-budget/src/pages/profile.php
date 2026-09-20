<?php
declare(strict_types=1);

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'push_test') {
    // Testnachricht an die eigenen Browser
    $res = push_to_user((int)$user['id'], 'OV-Budget',
        'Testnachricht – die Benachrichtigungen in diesem Browser funktionieren.', notify_url('?p=dashboard'));
    if ($res['gesendet'] > 0) {
        flash('success', sprintf('An %d Browser geschickt%s. Die Meldung sollte gleich erscheinen.',
            $res['gesendet'],
            $res['entfernt'] ? sprintf(', %d abgelaufene Anmeldung(en) entfernt', $res['entfernt']) : ''));
    } elseif ($res['entfernt'] > 0) {
        flash('warn', 'Die Anmeldung dieses Browsers war abgelaufen und wurde entfernt. '
            . 'Bitte noch einmal anmelden.');
    } else {
        flash('error', $res['fehler'] !== ''
            ? 'Der Push-Dienst hat die Nachricht nicht angenommen: ' . e($res['fehler'])
            : 'Für dich ist noch kein Browser angemeldet.');
    }
    audit('push.test', 'push', (int)$user['id'], sprintf('%d gesendet', $res['gesendet']));
    redirect_route('profile');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'notify_test') {
    // Testnachricht über Home Assistant an die Companion-App
    $ziel = trim((string)($user['ha_notify'] ?? ''));
    try {
        if ($ziel === '') {
            throw new RuntimeException('Es ist kein Ziel hinterlegt. Bitte erst eintragen und speichern.');
        }
        ha_notify_send($ziel, 'OV-Budget', 'Testnachricht – die Benachrichtigungen funktionieren.',
            notify_url('?p=dashboard'));
        flash('success', 'Testnachricht an ' . e($ziel) . ' geschickt.');
    } catch (Throwable $ex) {
        flash('error', 'Hat nicht geklappt: ' . e($ex->getMessage()));
    }
    redirect_route('profile');
}

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
        $ziel = trim(post_str('ha_notify'));
        if ($ziel !== '' && !preg_match('/^[a-z0-9_]+$/', $ziel)) {
            $errors[] = 'Das Benachrichtigungsziel darf nur Kleinbuchstaben, Ziffern und _ enthalten '
                . '(z. B. mobile_app_pixel_8).';
        }
        if (!$errors) {
            db_update('users', [
                'display_name' => mb_substr(post_str('display_name'), 0, 150),
                'email'        => mb_substr(post_str('email'), 0, 150),
                'phone'        => mb_substr(phone_human(post_str('phone')), 0, 60),
                'ha_notify'    => mb_substr($ziel, 0, 120),
                'notify_aktiv' => post_bool('notify_aktiv'),
            ], 'id = ?', [$user['id']]);
            flash('success', 'Profil gespeichert.');
            redirect_route('profile');
        }
    }
}

$dienste = [];
if (setting_bool('ha_benachrichtigung_aktiv', false)) {
    try {
        $dienste = ha_notify_dienste();
    } catch (Throwable $ex) {
        $dienste = [];   // ohne Home Assistant bleibt das Feld ein freies Textfeld
    }
}

render('profile', [
    'title'  => 'Mein Profil',
    'push'      => webpush_enabled(),
    'pushAbos'  => webpush_enabled() ? push_subscriptions((int)$user['id']) : [],
    'dienste' => $dienste,
    'user'   => $user,
    'errors' => $errors,
    'meine'  => db_all(
        'SELECT li.label FROM user_functions uf
         JOIN list_items li ON li.id = uf.function_id
         WHERE uf.user_id = ? ORDER BY li.sort_order',
        [$user['id']]
    ),
]);
