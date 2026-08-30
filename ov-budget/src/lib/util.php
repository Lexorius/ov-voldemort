<?php
declare(strict_types=1);

/** HTML-sicher ausgeben */
function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Verzeichnis, unter dem die Anwendung erreichbar ist.
 * Hinter dem Ingress von Home Assistant wechselt der Pfad je Sitzung – er
 * steht dann im Header X-Ingress-Path und wird nur dann ausgewertet, wenn
 * die Konfiguration den Ingress-Betrieb ausdrücklich erlaubt.
 */
function base_path(): string
{
    static $bp = null;
    if ($bp !== null) {
        return $bp;
    }

    $bp = rtrim((string)app_config('base_path', ''), '/');

    if (app_config('ingress', false)) {
        $header = (string)($_SERVER['HTTP_X_INGRESS_PATH'] ?? '');
        if ($header !== ''
            && !str_contains($header, '..')
            && preg_match('#^/[A-Za-z0-9_./-]*$#', $header) === 1
        ) {
            $bp = rtrim($header, '/');
        }
    }

    return $bp;
}

/**
 * Aktuell aufgerufene URL als Pfad – hinter dem Ingress entfernt der
 * Supervisor sein Präfix, für Rücksprünge muss es wieder davor.
 */
function current_url(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($uri === '' || $uri[0] !== '/') {
        return url('dashboard');
    }
    $bp = base_path();
    if ($bp !== '' && !str_starts_with($uri, $bp . '/')) {
        $uri = $bp . $uri;
    }
    return $uri;
}

/** Interne URL bauen: url('wishes', ['id' => 3]) */
function url(string $route = '', array $params = []): string
{
    $base = base_path() . '/index.php';
    if ($route !== '') {
        $params = array_merge(['p' => $route], $params);
    }
    return $params ? $base . '?' . http_build_query($params) : $base;
}

/** Asset-URL */
function asset(string $path): string
{
    return base_path() . '/assets/' . ltrim($path, '/');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function redirect_route(string $route, array $params = []): never
{
    redirect(url($route, $params));
}

/**
 * Wert aus config/config.php – Umgebungsvariablen (OVB_*) haben Vorrang,
 * damit die Anwendung auch ohne Konfigurationsdatei im Container läuft.
 */
function app_config(string $key, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $file = dirname(__DIR__, 2) . '/config/config.php';
        if (is_file($file) && !is_readable($file)) {
            throw new RuntimeException(
                'config/config.php ist vorhanden, aber für den Webserver-Benutzer nicht lesbar. '
                . 'Bitte Eigentümer und Rechte der Datei prüfen.'
            );
        }
        $cfg = is_file($file) ? require $file : [];
        $env = env_config();
        $cfg = $env ? array_replace_recursive($cfg, $env) : $cfg;
    }
    return $cfg[$key] ?? $default;
}

/** Konfiguration aus Umgebungsvariablen (Docker / Home-Assistant-Add-on) */
function env_config(): array
{
    $env = static function (string $name): ?string {
        $v = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        return ($v === false || $v === null || $v === '') ? null : (string)$v;
    };

    $db = array_filter([
        'host' => $env('OVB_DB_HOST'),
        'port' => $env('OVB_DB_PORT') !== null ? (int)$env('OVB_DB_PORT') : null,
        'name' => $env('OVB_DB_NAME'),
        'user' => $env('OVB_DB_USER'),
        'pass' => $env('OVB_DB_PASS'),
    ], static fn($v) => $v !== null);

    $cfg = [];
    if ($db) {
        $cfg['db'] = $db + ['charset' => 'utf8mb4'];
    }
    foreach ([
        'base_path'    => 'OVB_BASE_PATH',
        'upload_dir'   => 'OVB_UPLOAD_DIR',
        'session_name' => 'OVB_SESSION_NAME',
    ] as $key => $name) {
        if (($v = $env($name)) !== null) {
            $cfg[$key] = $v;
        }
    }
    foreach (['ingress' => 'OVB_INGRESS', 'debug' => 'OVB_DEBUG'] as $key => $name) {
        if (($v = $env($name)) !== null) {
            $cfg[$key] = in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
        }
    }
    return $cfg;
}

/* ---------------- Flash-Nachrichten ---------------- */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $message];
}

function flash_take(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $sent = (string)($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Sicherheitstoken ungültig oder abgelaufen. Bitte die Seite neu laden.');
    }
}

/* ---------------- Eingaben ---------------- */

function post(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function post_str(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function post_int(string $key, ?int $default = null): ?int
{
    $v = $_POST[$key] ?? null;
    if ($v === null || $v === '') {
        return $default;
    }
    return (int)$v;
}

/** Deutsche Zahleneingabe ("1.234,56") in float wandeln */
function post_dec(string $key, float $default = 0.0): float
{
    $v = $_POST[$key] ?? null;
    if ($v === null || trim((string)$v) === '') {
        return $default;
    }
    $v = str_replace([' ', "\xc2\xa0", '€'], '', (string)$v);
    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return (float)$v;
}

function post_bool(string $key): int
{
    return !empty($_POST[$key]) ? 1 : 0;
}

function post_date(string $key): ?string
{
    $v = trim((string)($_POST[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function get_int(string $key, ?int $default = null): ?int
{
    $v = $_GET[$key] ?? null;
    return ($v === null || $v === '') ? $default : (int)$v;
}

function get_str(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/* ---------------- Formatierung ---------------- */

function money(float|string|null $v, bool $withSymbol = true): string
{
    $s = number_format((float)$v, 2, ',', '.');
    if (!$withSymbol) {
        return $s;
    }
    $cur = setting('waehrung', 'EUR');
    $sym = ['EUR' => '€', 'CHF' => 'CHF', 'USD' => '$'][$cur] ?? $cur;
    return $s . ' ' . $sym;
}

/**
 * Zahl für ein Eingabefeld aufbereiten. Kommt der Wert aus einem Formular
 * (z.B. "1.234,56" nach einem Validierungsfehler), bleibt er unverändert.
 */
function num_input(mixed $v, bool $trimZeros = false): string
{
    if ($v === null || $v === '') {
        return '';
    }
    if (is_string($v) && !is_numeric($v)) {
        return $v;
    }
    $s = number_format((float)$v, 2, ',', '.');
    return $trimZeros ? rtrim(rtrim($s, '0'), ',') : $s;
}

function de_date(?string $v): string
{
    if (!$v || str_starts_with($v, '0000')) {
        return '';
    }
    $ts = strtotime($v);
    return $ts ? date('d.m.Y', $ts) : '';
}

function de_datetime(?string $v): string
{
    if (!$v || str_starts_with($v, '0000')) {
        return '';
    }
    $ts = strtotime($v);
    return $ts ? date('d.m.Y H:i', $ts) : '';
}

function slugify(string $v): string
{
    $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue'];
    $v = strtr($v, $map);
    $v = strtolower(trim($v));
    $v = preg_replace('/[^a-z0-9]+/', '-', $v) ?? '';
    return trim($v, '-');
}

/** Kontrastfarbe (schwarz/weiß) zu einer Hintergrundfarbe */
function contrast_color(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '#ffffff';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return (($r * 299 + $g * 587 + $b * 114) / 1000) > 150 ? '#111827' : '#ffffff';
}

/** Farbiges Label (Status, Dringlichkeit ...) */
function badge(?array $item, string $fallback = '–'): string
{
    if (!$item) {
        return '<span class="badge badge--muted">' . e($fallback) . '</span>';
    }
    $bg = $item['color'] ?: '#64748b';
    return '<span class="badge" style="background:' . e($bg) . ';color:' . e(contrast_color($bg)) . '">'
        . e($item['label']) . '</span>';
}

function bytes_human(int $b): string
{
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($b >= 1024 && $i < 3) {
        $b /= 1024;
        $i++;
    }
    return round($b, $i === 0 ? 0 : 1) . ' ' . $u[$i];
}

function audit(string $action, string $entity = '', ?int $entityId = null, string $detail = ''): void
{
    try {
        db()->prepare(
            'INSERT INTO audit_log (user_id, action, entity, entity_id, detail, ip)
             VALUES (?,?,?,?,?,?)'
        )->execute([
            current_user()['id'] ?? null,
            $action,
            $entity,
            $entityId,
            mb_substr($detail, 0, 500),
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    } catch (Throwable) {
        // Audit darf den Ablauf nie stören
    }
}
