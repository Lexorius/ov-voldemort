<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    exit('Diese Anwendung benötigt PHP 8.1 oder neuer. Gefunden: ' . PHP_VERSION);
}

mb_internal_encoding('UTF-8');

// Zeitzone: php.ini, sonst TZ (Home Assistant setzt sie), sonst Berlin
$tz = ini_get('date.timezone') ?: (string)(getenv('TZ') ?: '');
if ($tz === '' || !@date_default_timezone_set($tz)) {
    date_default_timezone_set('Europe/Berlin');
}

require __DIR__ . '/lib/util.php';

if (app_config('debug', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/settings.php';
require __DIR__ . '/lib/lists.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/view.php';
require __DIR__ . '/lib/wishes.php';
require __DIR__ . '/lib/todos.php';
require __DIR__ . '/lib/uploads.php';
require __DIR__ . '/lib/divera.php';

/** Ist die Anwendung eingerichtet? */
function app_installed(): bool
{
    if (!is_file(dirname(__DIR__) . '/config/config.php')) {
        return false;
    }
    try {
        return (int)db_val('SELECT COUNT(*) FROM users', [], 0) > 0;
    } catch (Throwable) {
        return false;
    }
}

session_boot();
