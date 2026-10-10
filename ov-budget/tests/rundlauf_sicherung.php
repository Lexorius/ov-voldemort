<?php
/*
 * Echter Rundlauf der Sicherung gegen eine MariaDB – nicht Teil von alle.php,
 * weil er eine Datenbank braucht und sie LEERT.
 *
 *   docker run -d --name ovb-rundlauf -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=ovbudget \
 *     -e MARIADB_USER=ovb -e MARIADB_PASSWORD=ovb -p 33061:3306 mariadb:11.4
 *   OVB_RUNDLAUF=ja php tests/rundlauf_sicherung.php
 *   docker rm -f ovb-rundlauf
 *
 * Verbindung über OVB_DB_HOST/PORT/NAME/USER/PASS (Vorgabe: 127.0.0.1:33061,
 * ovbudget/ovb/ovb). Ablauf: Schema + Wanderungen + Grunddaten → Daten mit
 * fiesen Werten und Dateien → Sicherung → Auszug gegen Datenbank prüfen →
 * Daten kaputt machen → Wiederherstellen → Tabellen, Zeilen, Prüfsummen,
 * AUTO_INCREMENT und Dateien vergleichen.
 */
declare(strict_types=1);
if (getenv('OVB_RUNDLAUF') !== 'ja') {
    exit("Dieser Lauf leert die angegebene Datenbank. Zum Bestätigen OVB_RUNDLAUF=ja setzen.\n");
}
$app = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/ovb-rundlauf';
@mkdir($tmp . '/uploads', 0777, true);
$env = ['OVB_DB_HOST' => '127.0.0.1', 'OVB_DB_PORT' => '33061', 'OVB_DB_NAME' => 'ovbudget', 'OVB_DB_USER' => 'ovb', 'OVB_DB_PASS' => 'ovb'];
foreach ($env as $k => $v) {
    $env[$k] = getenv($k) !== false && getenv($k) !== '' ? (string)getenv($k) : $v;
}
$env['OVB_UPLOAD_DIR'] = $tmp . '/uploads';
foreach ($env as $k => $v) {
    putenv("$k=$v");
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
}
define('APP_ROOT', $app);
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';
require $app . '/src/bootstrap.php';

$ok = 0; $fail = 0;
$check = function (string $n, bool $c, string $info = '') use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok    $n\n"; } else { $fail++; echo "  FAIL  $n" . ($info !== '' ? " – $info" : '') . "\n"; } };

/* ---------- Datenbank aufsetzen wie setup.php ---------- */
$pdo = null;
for ($i = 0; $i < 30; $i++) {
    try { $pdo = db(); $pdo->query('SELECT 1'); break; } catch (Throwable $e) { sleep(2); }
}
if (!$pdo) { exit("Keine Datenbank erreichbar\n"); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (backup_tabellen($pdo) as $t) { $pdo->exec("DROP TABLE IF EXISTS `$t`"); }
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$sqlDatei = static function (PDO $pdo, string $file): void {
    $sql = (string)file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) ?: [] as $stmt) { $stmt = trim($stmt); if ($stmt !== '') { $pdo->exec($stmt); } }
};
$sqlDatei($pdo, $app . '/sql/schema.sql');
require_once $app . '/src/cli/migrate.php';
ovb_migrate($pdo, static function (string $m): void {});
$sqlDatei($pdo, $app . '/sql/seed.sql');
settings_reset_cache();
echo 'Datenbank steht: ' . count(backup_tabellen($pdo)) . " Tabellen\n";

/* ---------- Daten mit fiesen Werten ---------- */
$fies = "O'Reilly \\ \"doppelt\" ; -- kein Kommentar\nZeile 2\tTab 😀 ä ß € 100%";
$pdo->prepare("INSERT INTO users (username, display_name, password_hash, role, is_active, must_change_pw) VALUES (?,?,?,'admin',1,0)")
    ->execute(['admin', 'Admin ' . $fies, password_hash('geheim-geheim', PASSWORD_DEFAULT)]);
$pdo->prepare("INSERT INTO users (username, display_name, password_hash, role, is_active, must_change_pw, totp_secret) VALUES (?,?,?,'user',1,0,?)")
    ->execute(['mitglied', 'Mitglied', password_hash('x', PASSWORD_DEFAULT), 'JBSWY3DPEHPK3PXP']);
$pdo->prepare("UPDATE settings SET svalue = ? WHERE skey = 'ov_name'")->execute(['OV ' . $fies]);
$pdo->prepare("INSERT INTO standorte (parent_id, typ, name, kurz, notiz, sort_order, geo_lat, geo_lng) VALUES (NULL,'halle',?,?,?,10,49.142345,9.218765)")
    ->execute(['Halle ' . $fies, 'H1', $fies]);
$halleId = (int)$pdo->lastInsertId();
for ($i = 1; $i <= 3; $i++) {
    $pdo->prepare("INSERT INTO standorte (parent_id, typ, name, sort_order, plan_x, plan_y) VALUES (?,'stellplatz',?,?,?,?)")->execute([$halleId, "Stellplatz $i", $i * 10, 10.5 * $i, 20.25]);
}
$pdo->prepare("INSERT INTO vehicles (bezeichnung, funkrufname, kennzeichen, standort_id) VALUES (?,?,?,?)")->execute(['GKW ' . $fies, 'Heros 24/51', 'THW-1', $halleId + 1]);
$pdo->prepare("INSERT INTO meters (art, name, zaehlernummer, standort, einheit, umrechnung, quelle, bereich_id) VALUES ('strom',?,?,?,'kWh',1,'manuell',?)")->execute(['Hauszähler ' . $fies, 'Z-1', 'HAR', $halleId]);
$meterId = (int)$pdo->lastInsertId();
for ($d = 1; $d <= 250; $d++) {   // mehr als eine INSERT-Portion (200)
    $pdo->prepare("INSERT INTO meter_readings (meter_id, stand, gelesen_am, quelle, melder, notiz) VALUES (?,?,?,'manuell',?,?)")
        ->execute([$meterId, 1000 + $d * 1.234, date('Y-m-d 08:00:00', strtotime('2026-01-01') + $d * 86400), 'Melder ' . $d, $d === 7 ? $fies : '']);
}
$pdo->prepare("INSERT INTO radio_groups (name, beschreibung, lagerort) VALUES (?,?,?)")->execute(['HRT-Koffer', $fies, 'Funkraum']);
audit('rundlauf.test', 'standort', $halleId, $fies);
$pdo->prepare("INSERT INTO radio_groups (name, beschreibung, lagerort) VALUES (?,?,?)")->execute(['Null', "a\0b\x1a\r\nc", '']);
echo "Daten eingetragen\n";

/* ---------- Dateien in der Ablage ---------- */
$u = $tmp . '/uploads';
foreach (['fahrzeuge', 'standorte', 'veranstaltungen', 'sicherungen', 'tief/er/pfad'] as $o) { @mkdir("$u/$o", 0777, true); }
foreach (glob("$u/sicherungen/*") ?: [] as $f) { @unlink($f); }
$dateien = ['fahrzeuge/gkw.jpg' => random_bytes(5000), 'standorte/halle.png' => random_bytes(3000), 'veranstaltungen/liste.pdf' => random_bytes(1200),
            'angebot-1.pdf' => random_bytes(700), 'tief/er/pfad/ü ä Datei mit Leerzeichen.txt' => "Umlaute ÄÖÜ\n", 'leer.txt' => ''];
foreach (backup_dateien($u) as $abs) { @unlink($abs); }
foreach ($dateien as $rel => $inhalt) { file_put_contents("$u/$rel", $inhalt); }
file_put_contents("$u/sicherungen/alt.txt", 'bleibt draussen');

/* ---------- Stand A ---------- */
$stand = static function (PDO $pdo, string $u): array {
    $out = ['tabellen' => backup_tabellen($pdo), 'zeilen' => [], 'pruefsumme' => [], 'auto' => [], 'dateien' => []];
    foreach ($out['tabellen'] as $t) {
        $out['zeilen'][$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $r = $pdo->query("CHECKSUM TABLE `$t` EXTENDED")->fetch(PDO::FETCH_NUM);
        $out['pruefsumme'][$t] = (string)$r[1];
        $st = $pdo->query("SHOW TABLE STATUS LIKE '$t'")->fetch(PDO::FETCH_ASSOC);
        $out['auto'][$t] = $st['Auto_increment'] ?? null;
    }
    foreach (backup_dateien($u) as $rel => $abs) { $out['dateien'][$rel] = sha1_file($abs); }
    return $out;
};
$a = $stand($pdo, $u);
$settingsA = $pdo->query("SELECT skey, svalue FROM settings WHERE skey <> 'backup_letzte' ORDER BY skey")->fetchAll(PDO::FETCH_KEY_PAIR);
echo 'Stand A: ' . count($a['tabellen']) . ' Tabellen, ' . array_sum($a['zeilen']) . ' Zeilen, ' . count($a['dateien']) . " Dateien\n";

/* ---------- Sichern ---------- */
echo "\n== Sichern\n";
$res = backup_create('manuell', ['display_name' => 'Rundlauf']);
$zipPfad = backup_dir() . '/' . $res['name'];
$check('Sicherung geschrieben', is_file($zipPfad) && $res['groesse'] > 10000, $res['name']);
$zip = new ZipArchive(); $zip->open($zipPfad);
$sql = (string)$zip->getFromName('datenbank.sql');
$imZip = []; for ($i = 0; $i < $zip->numFiles; $i++) { $imZip[] = $zip->getNameIndex($i); }
$zip->close();
preg_match_all('/^DROP TABLE IF EXISTS `([a-z_]+)`;$/m', $sql, $m);
$gesichert = $m[1]; sort($gesichert);
$fehlt = array_diff($a['tabellen'], $gesichert);
$check('Jede Tabelle der Datenbank steht im Auszug (' . count($a['tabellen']) . ')', $fehlt === [] && count($gesichert) === count($a['tabellen']), 'fehlt: ' . implode(', ', $fehlt));
$check('CREATE TABLE je Tabelle', substr_count($sql, 'CREATE TABLE `') === count($a['tabellen']));
$meta = $res['meta'];
$diff = [];
foreach ($a['zeilen'] as $t => $n) { if ((int)($meta['zeilen'][$t] ?? -1) !== $n) { $diff[] = "$t: $n ≠ " . ($meta['zeilen'][$t] ?? '–'); } }
$check('Zeilenzahl je Tabelle im Auszug = Datenbank', $diff === [], implode('; ', $diff));
$anw = backup_sql_teilen($sql);
$inserts = count(array_filter($anw, static fn($x) => str_starts_with($x, 'INSERT INTO')));
$check('Auszug zerlegbar: ' . count($anw) . ' Anweisungen, ' . $inserts . ' INSERT-Portionen', count($anw) > 2 * count($a['tabellen']) && $inserts >= 2);
$check('Fiese Werte stehen maskiert im Auszug', str_contains($sql, "O\\'Reilly") && str_contains($sql, '😀') && str_contains($sql, 'kein Kommentar'));
$dateiPfade = array_values(array_filter($imZip, static fn($x) => str_starts_with($x, 'dateien/')));
$check('Alle Dateien der Ablage im Paket (' . count($a['dateien']) . '), Sicherungen nicht', count($dateiPfade) === count($a['dateien']) && !in_array('dateien/sicherungen/alt.txt', $imZip, true)
    && in_array('dateien/tief/er/pfad/ü ä Datei mit Leerzeichen.txt', $imZip, true) && in_array('dateien/leer.txt', $imZip, true), implode(', ', $dateiPfade));
$check('AUTO_INCREMENT im Auszug', (bool)preg_match('/CREATE TABLE `meter_readings`.*?AUTO_INCREMENT=(\d+)/s', $sql, $ai) && (int)$ai[1] === (int)$a['auto']['meter_readings'], $ai[1] ?? '–');

/* ---------- Kaputt machen ---------- */
echo "\n== Daten verändern\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec("DELETE FROM meter_readings WHERE id = (SELECT * FROM (SELECT MAX(id) FROM meter_readings) x)");
$pdo->exec("DELETE FROM standorte WHERE parent_id IS NOT NULL");
$pdo->exec("UPDATE settings SET svalue = 'kaputt' WHERE skey = 'ov_name'");
$pdo->exec("DROP TABLE radio_groups");
$pdo->exec("CREATE TABLE fremd_tabelle (id INT PRIMARY KEY)");
$pdo->exec("INSERT INTO users (username, display_name, password_hash, role) VALUES ('eindringling','x','x','admin')");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
unlink("$u/fahrzeuge/gkw.jpg");
file_put_contents("$u/fremd.txt", 'gehört weg');
$check('Veränderung wirkt', !in_array('radio_groups', backup_tabellen($pdo), true) && in_array('fremd_tabelle', backup_tabellen($pdo), true));

/* ---------- Wiederherstellen ---------- */
echo "\n== Wiederherstellen\n";
$meldungen = [];
$ergebnis = backup_restore($res['name'], static function (string $m) use (&$meldungen): void { $meldungen[] = $m; echo "  · $m\n"; });
settings_reset_cache();
$b = $stand($pdo, $u);
$settingsB = $pdo->query("SELECT skey, svalue FROM settings WHERE skey <> 'backup_letzte' ORDER BY skey")->fetchAll(PDO::FETCH_KEY_PAIR);

echo "\n== Vergleich\n";
$check('Tabellen gleich, Fremdtabelle weg', $a['tabellen'] === $b['tabellen'], 'diff: ' . implode(',', array_merge(array_diff($a['tabellen'], $b['tabellen']), array_diff($b['tabellen'], $a['tabellen']))));
$diff = []; foreach ($a['zeilen'] as $t => $n) { if (($b['zeilen'][$t] ?? -1) !== $n) { $diff[] = "$t: $n → " . ($b['zeilen'][$t] ?? '–'); } }
$check('Zeilenzahlen gleich', $diff === [], implode('; ', $diff));
$diff = []; foreach ($a['pruefsumme'] as $t => $p) { if ($t !== 'settings' && ($b['pruefsumme'][$t] ?? '') !== $p) { $diff[] = $t; } }
$check('Prüfsummen aller Tabellen gleich (außer settings)', $diff === [], implode(', ', $diff));
$check('settings gleich bis auf backup_letzte', $settingsA === $settingsB, implode(', ', array_keys(array_diff_assoc($settingsA, $settingsB))));
// Grunddaten-Tabellen (list_items, bestell_rechte) springen im Zähler, weil seed.sql nach dem
// Einspielen mit INSERT IGNORE läuft – Zeilen unverändert, nur Lücken in künftigen Nummern
$diff = []; foreach ($a['auto'] as $t => $v) { if (!in_array($t, ['list_items', 'bestell_rechte', 'settings'], true) && ($b['auto'][$t] ?? null) !== $v) { $diff[] = "$t: $v → " . ($b['auto'][$t] ?? 'null'); } }
$check('AUTO_INCREMENT der Datentabellen gleich', $diff === [], implode('; ', $diff));
$check('Dateien gleich: gelöschte zurück, fremde weg', $a['dateien'] === $b['dateien'] && !is_file("$u/fremd.txt") && is_file("$u/sicherungen/alt.txt"),
    implode(', ', array_keys(array_diff_assoc($a['dateien'], $b['dateien']))) . ' | ' . implode(', ', array_keys(array_diff_key($b['dateien'], $a['dateien']))));
$wert = $pdo->query("SELECT beschreibung FROM radio_groups WHERE name = 'Null'")->fetchColumn();
$check('Nullbyte und Steuerzeichen überstehen den Rundlauf', $wert === "a\0b\x1a\r\nc", bin2hex((string)$wert));
$wert = $pdo->query("SELECT notiz FROM standorte WHERE typ = 'halle'")->fetchColumn();
$check('Fieser Text identisch zurück', $wert === $fies);
$check('Eindringling weg, Sicherheitskopie vorher angelegt', (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username = 'eindringling'")->fetchColumn() === 0 && is_file(backup_dir() . '/' . $ergebnis['vorher']));
$check('Wanderungen nach dem Einspielen ohne Fehler', !array_filter($meldungen, static fn($m) => stripos($m, 'fehler') !== false));

echo "\n$ok bestanden, $fail fehlgeschlagen\n";
exit($fail === 0 ? 0 : 1);
