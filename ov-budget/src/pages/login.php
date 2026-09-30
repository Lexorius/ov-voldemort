<?php
declare(strict_types=1);

if (is_logged_in()) {
    redirect_route('dashboard');
}

$error = '';
$username = '';
$zweiterFaktor = auth_zweiter_faktor_offen();

$weiter = static function (): never {
    audit('login', 'user', (int)($_SESSION['uid'] ?? 0) ?: null);
    $to = $_SESSION['after_login'] ?? null;
    unset($_SESSION['after_login']);
    if ($to && url_ist_intern((string)$to)) {
        redirect((string)$to);
    }
    redirect_route('dashboard');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'totp_abbrechen') {
    unset($_SESSION['totp_pending']);
    redirect_route('login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'totp') {
    [$ok, $msg, $art] = auth_zweiter_faktor((string)post('code', ''));
    if ($ok) {
        if ($art === 'backup') {
            $rest = totp_status(current_user() ?? [])['backup_rest'];
            flash('warn', sprintf('Du hast dich mit einem Backup-Code angemeldet – es bleiben %d. Neue Codes gibt es im Profil.', $rest));
            audit('login.backup_code', 'user', (int)($_SESSION['uid'] ?? 0) ?: null);
        }
        $weiter();
    }
    $error = $msg;
    $zweiterFaktor = auth_zweiter_faktor_offen();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post_str('username');
    [$ok, $msg, $zweiterFaktor] = auth_attempt($username, (string)post('password', ''));
    if ($ok && !$zweiterFaktor) {
        $weiter();
    }
    $error = $msg;
}

render('login', ['title' => 'Anmeldung', 'error' => $error, 'username' => $username, 'zweiterFaktor' => $zweiterFaktor]);
