<?php
declare(strict_types=1);

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name((string)app_config('session_name', 'ovbudget'));
    session_set_cookie_params([
        'lifetime' => 0,
        // Bewusst '/': hinter dem Ingress von Home Assistant wechselt der
        // Pfad je Sitzung, ein pfadgebundenes Cookie ginge dabei verloren.
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        return null;
    }

    // Sitzungslaufzeit prüfen
    $lifetime = setting_int('session_lifetime', 43200);
    if ($lifetime > 0 && (time() - (int)($_SESSION['last_seen'] ?? 0)) > $lifetime) {
        auth_logout();
        return null;
    }
    $_SESSION['last_seen'] = time();

    $user = db_row('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
    if (!$user) {
        auth_logout();
        return null;
    }
    $user['functions'] = array_map(
        static fn($r) => (int)$r['function_id'],
        db_all('SELECT function_id FROM user_functions WHERE user_id = ?', [$id])
    );
    return $user;
}

function user_functions(int $userId): array
{
    return array_map(
        static fn($r) => (int)$r['function_id'],
        db_all('SELECT function_id FROM user_functions WHERE user_id = ?', [$userId])
    );
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = current_url();
        redirect_route('login');
    }
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        render('error', ['title' => 'Kein Zugriff', 'message' => 'Für diesen Bereich fehlen dir die Rechte.']);
        exit;
    }
    return $u;
}

/* ---------------- Rechte ---------------- */

function can(string $what, mixed $ctx = null): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    $role = $u['role'];
    $admin = $role === 'admin';
    $leitung = $admin || $role === 'leitung';

    return match ($what) {
        'admin'          => $admin,
        'manage_users'   => $admin,
        'manage_budget'  => $leitung,
        'manage_wishes'  => $leitung,   // Status, Priorisierung, fremde Wünsche bearbeiten
        'manage_todos'   => $leitung,
        'create_wish'    => true,
        'vote'           => setting_bool('wunsch_voting_aktiv', true),
        'change_status'  => $leitung || setting_bool('wunsch_user_darf_status', false),
        'create_todo'    => $leitung || setting_bool('todo_user_darf_anlegen', true),

        'edit_wish'      => $leitung || (is_array($ctx) && (int)($ctx['created_by'] ?? 0) === (int)$u['id']
                              && (int)(list_item((int)($ctx['status_id'] ?? 0))['is_final'] ?? 0) === 0),
        'delete_wish'    => $leitung || (is_array($ctx) && (int)($ctx['created_by'] ?? 0) === (int)$u['id']),

        'edit_todo'      => $leitung || (is_array($ctx) && (
                              (int)($ctx['created_by'] ?? 0) === (int)$u['id'] || todo_is_mine($ctx, $u)
                            )),
        default          => false,
    };
}

/** Gehört die Aufgabe zum Zuständigkeitsbereich des Benutzers? */
function todo_is_mine(array $todo, ?array $u = null): bool
{
    $u ??= current_user();
    if (!$u) {
        return false;
    }
    return match ($todo['target_type']) {
        'ov'         => true,
        'fachgruppe' => (int)$todo['target_id'] === (int)($u['fachgruppe_id'] ?? 0),
        'funktion'   => in_array((int)$todo['target_id'], $u['functions'] ?? user_functions((int)$u['id']), true),
        'user'       => (int)$todo['target_id'] === (int)$u['id'],
        default      => false,
    };
}

/* ---------------- Anmeldung ---------------- */

function auth_attempt(string $username, string $password): array
{
    $username = trim($username);
    $maxTries = setting_int('login_max_versuche', 8);
    $blockMin = setting_int('login_sperre_minuten', 15);

    if ($maxTries > 0) {
        $fails = (int)db_val(
            'SELECT COUNT(*) FROM login_attempts WHERE username = ? AND created_at > (NOW() - INTERVAL ? MINUTE)',
            [$username, $blockMin],
            0
        );
        if ($fails >= $maxTries) {
            return [false, sprintf('Zu viele Fehlversuche. Bitte in %d Minuten erneut probieren.', $blockMin)];
        }
    }

    $user = db_row('SELECT * FROM users WHERE username = ?', [$username]);
    $ok = $user && password_verify($password, $user['password_hash']);

    if (!$ok) {
        db_exec('INSERT INTO login_attempts (username, ip) VALUES (?,?)', [
            mb_substr($username, 0, 60),
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
        return [false, 'Benutzername oder Passwort ist falsch.'];
    }

    if ((int)$user['is_active'] !== 1) {
        return [false, 'Dieser Zugang ist deaktiviert. Bitte an die OV-Leitung wenden.'];
    }

    // Hash bei Bedarf modernisieren
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['id'];
    $_SESSION['last_seen'] = time();
    db_exec('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
    db_exec('DELETE FROM login_attempts WHERE username = ?', [$username]);

    return [true, ''];
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function password_problem(string $pw): ?string
{
    $min = setting_int('passwort_min_laenge', 10);
    if (mb_strlen($pw) < $min) {
        return sprintf('Das Passwort muss mindestens %d Zeichen lang sein.', $min);
    }
    return null;
}
