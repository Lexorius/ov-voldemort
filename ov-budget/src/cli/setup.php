<?php
/**
 * Containerstart: Konfiguration schreiben, auf die Datenbank warten,
 * Schema einspielen und beim ersten Start den Administrator anlegen.
 *
 * Läuft absichtlich eigenständig (nur PDO, kein Bootstrap), damit ein Fehler
 * in der Konfiguration hier klar gemeldet wird und nicht erst im Browser.
 *
 * Quellen der Einstellungen, in dieser Reihenfolge:
 *   1. /data/options.json  (Optionen des Home-Assistant-Add-ons)
 *   2. Umgebungsvariablen  (OVB_* – auch das Startskript reicht so die
 *                           Zugangsdaten der mitgelieferten MariaDB herein)
 */
declare(strict_types=1);

// Wurzel der Anwendung aus der Lage dieser Datei ableiten (/app im Container)
define('APP_ROOT', dirname(__DIR__, 2));

function say(string $msg): void
{
    fwrite(STDOUT, '[setup] ' . $msg . PHP_EOL);
}

function fail(string $msg): never
{
    fwrite(STDERR, '[setup] FEHLER: ' . $msg . PHP_EOL);
    exit(1);
}

/* ------------------------------------------------------------------ */
/* Optionen einlesen                                                    */
/* ------------------------------------------------------------------ */

$options = [];
$isAddon = false;
if (is_file('/data/options.json')) {
    $raw = json_decode((string)file_get_contents('/data/options.json'), true);
    if (is_array($raw)) {
        $options = $raw;
        $isAddon = true;
    }
}

/** Wert aus Add-on-Option, sonst Umgebungsvariable, sonst Vorgabe */
function opt(string $key, string $env, string $default = ''): string
{
    global $options;
    $v = $options[$key] ?? null;
    if ($v !== null && $v !== '') {
        return is_bool($v) ? ($v ? '1' : '0') : trim((string)$v);
    }
    $v = $_ENV[$env] ?? $_SERVER[$env] ?? getenv($env);
    if ($v !== false && $v !== null && $v !== '') {
        return trim((string)$v);
    }
    return $default;
}

$db = [
    'host' => opt('db_host', 'OVB_DB_HOST'),
    'port' => (int)opt('db_port', 'OVB_DB_PORT', '3306'),
    'name' => opt('db_name', 'OVB_DB_NAME', 'ovbudget'),
    'user' => opt('db_user', 'OVB_DB_USER'),
    'pass' => opt('db_password', 'OVB_DB_PASS'),
];

/* ------------------------------------------------------------------ */
/* Lokale oder externe Datenbank?                                       */
/* ------------------------------------------------------------------ */

/*
 * Ohne gesetzten Host bringt der Container seine eigene MariaDB mit.
 * Das Startskript fragt diese Entscheidung mit --db-target ab, startet
 * gegebenenfalls den Server und reicht die Zugangsdaten als OVB_DB_*
 * wieder herein.
 */
if (($argv[1] ?? '') === '--db-target') {
    echo $db['host'] === '' ? 'local:' . $db['name'] : 'external';
    echo PHP_EOL;
    exit(0);
}

if ($db['host'] === '') {
    fail('Kein Datenbank-Host bekannt – die mitgelieferte Datenbank wurde nicht gestartet.');
}
if ($db['user'] === '') {
    fail('Kein Datenbank-Benutzer angegeben. Bei einer externen Datenbank gehören '
        . 'db_host, db_user und db_password zusammen.');
}

/* ------------------------------------------------------------------ */
/* Konfigurationsdatei schreiben                                        */
/* ------------------------------------------------------------------ */

$uploadDir = opt('upload_dir', 'OVB_UPLOAD_DIR', '/data/uploads');
$ingress = $isAddon || opt('ingress', 'OVB_INGRESS', '0') === '1';

@mkdir($uploadDir, 0770, true);
@mkdir(APP_ROOT . '/config', 0750, true);

$cfg = "<?php\n"
    . "/** Automatisch beim Containerstart erzeugt – Änderungen gehen verloren. */\n"
    . "return [\n"
    . "    'db' => [\n"
    . "        'host'    => " . var_export($db['host'], true) . ",\n"
    . "        'port'    => " . $db['port'] . ",\n"
    . "        'name'    => " . var_export($db['name'], true) . ",\n"
    . "        'user'    => " . var_export($db['user'], true) . ",\n"
    . "        'pass'    => " . var_export($db['pass'], true) . ",\n"
    . "        'charset' => 'utf8mb4',\n"
    . "    ],\n"
    . "    'base_path'    => " . var_export(rtrim(opt('base_path', 'OVB_BASE_PATH'), '/'), true) . ",\n"
    . "    'ingress'      => " . ($ingress ? 'true' : 'false') . ",\n"
    . "    'upload_dir'   => " . var_export($uploadDir, true) . ",\n"
    . "    'session_name' => 'ovbudget',\n"
    . "    'debug'        => " . (opt('debug', 'OVB_DEBUG', '0') === '1' ? 'true' : 'false') . ",\n"
    . "];\n";

if (file_put_contents(APP_ROOT . '/config/config.php', $cfg) === false) {
    fail('config/config.php konnte nicht geschrieben werden.');
}
@chmod(APP_ROOT . '/config/config.php', 0640);
say('Konfiguration geschrieben.');

/* ------------------------------------------------------------------ */
/* Auf die Datenbank warten                                             */
/* ------------------------------------------------------------------ */

function connect(array $db, bool $withDatabase): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']);
    if ($withDatabase) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
    }
    return new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
}

$pdo = null;
$letzterFehler = '';
$versuche = (int)opt('db_wait_seconds', 'OVB_DB_WAIT', '90');

for ($i = 0; $i < max(1, (int)ceil($versuche / 3)); $i++) {
    try {
        // Erst ohne Datenbanknamen: dann kann sie bei Bedarf angelegt werden
        $server = connect($db, false);
        try {
            $server->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                str_replace('`', '', $db['name'])
            ));
        } catch (PDOException $ex) {
            // Fehlende Rechte sind in Ordnung, solange die Datenbank existiert
            say('Hinweis: Datenbank konnte nicht angelegt werden (' . $ex->getMessage() . ').');
        }
        $pdo = connect($db, true);
        break;
    } catch (PDOException $ex) {
        $letzterFehler = $ex->getMessage();
        if ($i === 0) {
            say('Warte auf die Datenbank ' . $db['host'] . ':' . $db['port'] . ' ...');
        }
        sleep(3);
    }
}

if (!$pdo) {
    fail('Keine Verbindung zur Datenbank: ' . $letzterFehler);
}
say('Datenbankverbindung steht.');

/* ------------------------------------------------------------------ */
/* Schema und Grunddaten einspielen (idempotent)                        */
/* ------------------------------------------------------------------ */

function run_sql_file(PDO $pdo, string $file): void
{
    if (!is_file($file)) {
        fail('SQL-Datei fehlt: ' . $file);
    }
    $sql = (string)file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) ?: [] as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

$frischeInstallation = true;
try {
    $frischeInstallation = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
} catch (PDOException) {
    $frischeInstallation = true;
}

run_sql_file($pdo, APP_ROOT . '/sql/schema.sql');
run_sql_file($pdo, APP_ROOT . '/sql/seed.sql');
say($frischeInstallation ? 'Schema und Grunddaten eingespielt.' : 'Schema geprüft, Grunddaten vorhanden.');

/* ------------------------------------------------------------------ */
/* Erster Start: Administrator anlegen                                  */
/* ------------------------------------------------------------------ */

$anzahlBenutzer = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

if ($anzahlBenutzer === 0) {
    $benutzer = strtolower(opt('admin_username', 'OVB_ADMIN_USER', 'admin'));
    if (!preg_match('/^[a-z0-9._-]{3,60}$/', $benutzer)) {
        $benutzer = 'admin';
    }

    $passwort = opt('admin_password', 'OVB_ADMIN_PASS');
    $erzeugt = false;
    if (mb_strlen($passwort) < 10) {
        $passwort = bin2hex(random_bytes(6));
        $erzeugt = true;
    }

    $st = $pdo->prepare(
        'INSERT INTO users (username, display_name, password_hash, role, is_active, must_change_pw)
         VALUES (?,?,?,\'admin\',1,?)'
    );
    $st->execute([$benutzer, $benutzer, password_hash($passwort, PASSWORD_DEFAULT), $erzeugt ? 1 : 0]);

    say(str_repeat('=', 62));
    say('Administrator angelegt.');
    say('  Benutzername: ' . $benutzer);
    if ($erzeugt) {
        say('  Passwort:     ' . $passwort);
        say('  (zufällig erzeugt – muss bei der ersten Anmeldung geändert werden)');
    } else {
        say('  Passwort:     wie in den Optionen hinterlegt');
    }
    say(str_repeat('=', 62));
}

/* Name des Ortsverbands nur beim allerersten Start übernehmen */
$ovName = opt('ov_name', 'OVB_OV_NAME');
if ($frischeInstallation && $ovName !== '') {
    $st = $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
    $st->execute([$ovName, 'ov_name']);
    $st->execute([$ovName, 'ov_kurz']);
    say('Ortsverband gesetzt: ' . $ovName);
}

say('Bereit.');
