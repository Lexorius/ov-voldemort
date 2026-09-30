<?php
declare(strict_types=1);
/*
 * Sicherung: Paket packen und lesen, SQL zerlegen, Pfade prüfen, Namen,
 * Ausdünnen und die Seite. Ohne echte Datenbank – der Auszug wird gestellt.
 */
ob_start();
session_start();
$tmp = sys_get_temp_dir() . '/ovb-sicherung-test';
$leeren = static function (string $o) use (&$leeren): void {
    foreach (glob($o . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
        if (in_array(basename($f), ['.', '..'], true)) { continue; }
        if (is_dir($f)) { $leeren($f); @rmdir($f); } else { @unlink($f); }
    }
};
@mkdir($tmp, 0777, true);
$leeren($tmp);

$GLOBALS['settings'] = ['ov_name' => 'OV Test', 'backup_automatisch_tage' => '0', 'backup_aufheben_anzahl' => '2'];
$GLOBALS['state'] = [];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $GLOBALS['state'][$p[0] ?? ''] ?? $d; }
function db_exec(string $sql, array $p = []): int { if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = $p[1]; } return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function db_lock(string $n, int $w = 0): bool { return true; }
function db_unlock(string $n): void {}
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'admin']; }
function require_role(string ...$r): array { return ['id' => 1, 'role' => 'admin', 'password_hash' => password_hash('geheim1234', PASSWORD_DEFAULT), 'display_name' => 'Admin']; }

$app = dirname(__DIR__);
function render(string $v, array $vars = []): void { $GLOBALS['gerendert'] = $vars; }
foreach (['util', 'settings', 'lists', 'uploads', 'backup'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }
// Ablage auf den Testordner umbiegen
function app_config_override(): void {}
$GLOBALS['_upload_dir'] = $tmp . '/uploads';
// upload_dir() liest app_config('upload_dir') – die Konfiguration gibt es hier nicht; also die Umgebung
putenv('OVB_UPLOAD_DIR=' . $tmp . '/uploads');

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

$check('Ablage zeigt auf den Testordner', str_starts_with(upload_dir(), $tmp));
$check('zip ist da', backup_problem() === null);

/* ---------- Namen ---------- */
$name = backup_name_neu('manuell', 1700000000);
$check('Name hat das Muster', backup_name_gueltig($name) && str_ends_with($name, '-manuell.zip'));
$check('fremde Zeichen im Anlass fliegen raus', str_ends_with(backup_name_neu('Vor Wieder/herstellung!'), '-vorwiederherstellung.zip'));
$check('Pfadwechsel im Namen unmöglich', !backup_name_gueltig('../ovbudget-20260101-000000-x.zip')
    && !backup_name_gueltig('ovbudget-20260101-000000-x.zip.php') && backup_path('../../etc/passwd') === null);
$check('64M gelesen', backup_ini_bytes('64M') === 64 * 1024 * 1024 && backup_ini_bytes('2G') === 2 * 1024 ** 3
    && backup_ini_bytes('-1') === PHP_INT_MAX && backup_ini_bytes('12345') === 12345);

/* ---------- SQL zerlegen ---------- */
$sql = "-- Kommentar; mit Strichpunkt\nSET NAMES utf8mb4;\nCREATE TABLE `t` (\n  `a` INT,\n  `b` TEXT COMMENT 'hat ; drin'\n);\n"
     . "INSERT INTO `t` VALUES (1,'a;b'),(2,'zwei\\'s ; \\\\'),(3,'doppelt''s;');\nINSERT INTO `t` VALUES (4,\"x;y\");";
$teile = backup_sql_teilen($sql);
$check('vier Anweisungen', count($teile) === 4);
$check('Kommentar weg', str_starts_with($teile[0], 'SET NAMES'));
$check('Strichpunkt im Kommentar der Tabelle bleibt', str_contains($teile[1], "'hat ; drin'"));
$check('maskiertes Anführungszeichen und Backslash', str_contains($teile[2], "'zwei\\'s ; \\\\'") && str_contains($teile[2], "'doppelt''s;'"));
$check('doppelte Anführungszeichen', $teile[3] === 'INSERT INTO `t` VALUES (4,"x;y")');
$check('leer bleibt leer', backup_sql_teilen("  ;;\n-- nix\n") === []);

/* ---------- Pfade im Paket ---------- */
$check('normaler Pfad ok', backup_pfad_sicher('fahrzeuge/20260101_abc.jpg'));
$check('.. abgewiesen', !backup_pfad_sicher('../config/config.php') && !backup_pfad_sicher('a/../../x'));
$check('absolut abgewiesen', !backup_pfad_sicher('/etc/passwd'));
$check('Rückstrich abgewiesen', !backup_pfad_sicher('a\\..\\b'));
$check('Sicherungen selbst nicht', !backup_pfad_sicher('sicherungen/ovbudget-x.zip'));
$check('Nullbyte abgewiesen', !backup_pfad_sicher("a\0.php"));

/* ---------- Paket packen und lesen ---------- */
@mkdir(upload_dir() . '/fahrzeuge', 0777, true);
@mkdir(upload_dir() . '/sicherungen', 0777, true);
file_put_contents(upload_dir() . '/angebot.pdf', 'PDF');
file_put_contents(upload_dir() . '/fahrzeuge/bild.jpg', 'JPG');
file_put_contents(upload_dir() . '/sicherungen/ovbudget-20200101-000000-manuell.zip', 'alt');
$dateien = backup_dateien(upload_dir());
$check('zwei Dateien gefunden, Sicherungen ausgelassen', array_keys($dateien) === ['angebot.pdf', 'fahrzeuge/bild.jpg']);

$sqlDatei = $tmp . '/dump.sql';
file_put_contents($sqlDatei, "DROP TABLE IF EXISTS `users`;\nCREATE TABLE `users` (`id` INT);\nINSERT INTO `users` (`id`) VALUES\n(1);\n");
$ziel = backup_dir() . '/' . backup_name_neu('manuell', 1700000100);
backup_paket_schreiben($ziel, $sqlDatei, $dateien, ['programm' => 'OV-Multitool', 'fassung' => '1.44.0', 'zeit' => '2026-09-25T21:00:00+02:00', 'grund' => 'manuell', 'tabellen' => 1, 'zeilen' => ['users' => 1]]);
$check('Paket geschrieben', is_file($ziel) && !is_file($ziel . '.tmp'));

$info = backup_inspect($ziel);
$check('Paket erkannt', $info['meta']['programm'] === 'OV-Multitool' && $info['meta']['fassung'] === '1.44.0');
$check('Zahlen stimmen', $info['dateien'] === 2 && $info['tabellen'] === 1 && $info['sql_bytes'] > 20);

$zip = new ZipArchive(); $zip->open($ziel, ZipArchive::RDONLY);
$check('Datenbankauszug im Paket', str_contains((string)$zip->getFromName('datenbank.sql'), 'CREATE TABLE `users`'));
$check('Dateien unter dateien/', $zip->getFromName('dateien/fahrzeuge/bild.jpg') === 'JPG');
$zip->close();

// Fremdes ZIP, Paket ohne Auszug, Paket mit bösem Pfad
$fremd = $tmp . '/fremd.zip'; $z = new ZipArchive(); $z->open($fremd, ZipArchive::CREATE); $z->addFromString('x.txt', 'x'); $z->close();
try { backup_inspect($fremd); $check('fremdes ZIP abgewiesen', false); } catch (BackupException $e) { $check('fremdes ZIP abgewiesen', str_contains($e->getMessage(), 'keine Sicherung')); }
$boese = $tmp . '/boese.zip'; $z = new ZipArchive(); $z->open($boese, ZipArchive::CREATE);
$z->addFromString('sicherung.json', '{"programm":"OV-Multitool"}'); $z->addFromString('datenbank.sql', 'SELECT 1;'); $z->addFromString('dateien/../../public/x.php', '<?php'); $z->close();
try { backup_inspect($boese); $check('Pfadausbruch im Paket abgewiesen', false); } catch (BackupException $e) { $check('Pfadausbruch im Paket abgewiesen', str_contains($e->getMessage(), 'unzulässig')); }
$ohne = $tmp . '/ohne.zip'; $z = new ZipArchive(); $z->open($ohne, ZipArchive::CREATE); $z->addFromString('sicherung.json', '{"programm":"OV-Multitool"}'); $z->close();
try { backup_inspect($ohne); $check('ohne Auszug abgewiesen', false); } catch (BackupException $e) { $check('ohne Auszug abgewiesen', str_contains($e->getMessage(), 'datenbank.sql')); }
try { backup_inspect($tmp . '/dump.sql'); $check('kein ZIP abgewiesen', false); } catch (BackupException $e) { $check('kein ZIP abgewiesen', true); }

/* ---------- Liste und Ausdünnen ---------- */
$liste = backup_list();
$check('Liste: neueste zuerst, kaputte markiert', count($liste) === 2 && $liste[0]['name'] === basename($ziel) && !$liste[0]['kaputt'] && $liste[1]['kaputt']);
foreach ([1700000200, 1700000300, 1700000400] as $t) {
    copy($ziel, backup_dir() . '/' . backup_name_neu('automatisch', $t));
}
$check('drei automatische da', count(array_filter(backup_list(), fn($b) => str_contains($b['name'], 'automatisch'))) === 3);
$check('ausgedünnt auf zwei', backup_ausduennen('automatisch', 2) === 1
    && count(array_filter(backup_list(), fn($b) => str_contains($b['name'], 'automatisch'))) === 2);
$check('die älteste ist weg', !is_file(backup_dir() . '/' . backup_name_neu('automatisch', 1700000200)));
$check('manuelle unberührt', is_file($ziel));
$check('Löschen mit Prüfung', backup_delete(basename($ziel)) && !backup_delete('../dump.sql') && !is_file($ziel));

/* ---------- Automatik ---------- */
$check('Automatik aus = nichts', backup_automatisch() === null);

/* ---------- Seite: Bestätigung und Passwort ---------- */
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['action' => 'wiederherstellen', 'name' => 'ovbudget-20260101-000000-manuell.zip', 'bestaetigung' => 'ja', 'passwort' => 'geheim1234'];
$GLOBALS['gerendert'] = null;
require $app . '/src/pages/admin/backup.php';
$check('ohne Bestätigungswort keine Wiederherstellung', str_contains((string)$GLOBALS['gerendert']['fehler'], 'WIEDERHERSTELLEN'));
$_POST['bestaetigung'] = 'WIEDERHERSTELLEN'; $_POST['passwort'] = 'falsch';
require $app . '/src/pages/admin/backup.php';
$check('falsches Passwort abgewiesen', str_contains((string)$GLOBALS['gerendert']['fehler'], 'Passwort'));
$_POST = ['action' => 'loeschen', 'name' => '../../config/config.php'];
require $app . '/src/pages/admin/backup.php';
$check('Löschen prüft den Namen', str_contains((string)$GLOBALS['gerendert']['fehler'], 'gibt es nicht'));
$_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = []; $_GET = ['datei' => '../dump.sql'];
$GLOBALS['gerendert'] = null; http_response_code(200);
require $app . '/src/pages/admin/backup.php';
$check('Herunterladen prüft den Namen', ($GLOBALS['gerendert']['title'] ?? '') === 'Nicht gefunden');

$leeren($tmp);
echo "$ok bestanden, $fail fehlgeschlagen\n";
