<?php
declare(strict_types=1);
/*
 * Speicherplatz: Datenbankgröße aus information_schema, Ordnergrößen,
 * Plattenbelegung und die Karte auf der Verwaltungsseite.
 */
session_start();
$GLOBALS['settings'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'information_schema')) {
        return [['name' => 'audit_log', 'zeilen' => 12000, 'bytes' => 3 * 1048576], ['name' => 'users', 'zeilen' => 20, 'bytes' => 65536], ['name' => 'wishes', 'zeilen' => 300, 'bytes' => 4 * 1048576]];
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'DATABASE()') ? 'ovbudget' : $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'admin']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'view', 'speicher'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Datenbank ---------- */
$db = speicher_db();
$check('Datenbank: Name, Summe, größte Tabelle zuerst', $db['name'] === 'ovbudget' && $db['gesamt'] === 7 * 1048576 + 65536 && $db['anzahl'] === 3
    && $db['tabellen'][0]['name'] === 'wishes' && $db['tabellen'][2]['name'] === 'users' && $db['tabellen'][0]['zeilen'] === 300);
$check('leere Datenbank', speicher_db_zusammenfassen('', []) === ['name' => '', 'gesamt' => 0, 'anzahl' => 0, 'tabellen' => []]);

/* ---------- Ordner ---------- */
$tmp = sys_get_temp_dir() . '/ovb-speicher-test';
foreach (['fahrzeuge', 'sicherungen/alt', 'standorte'] as $o) { @mkdir($tmp . '/' . $o, 0777, true); }
file_put_contents($tmp . '/fahrzeuge/a.jpg', str_repeat('x', 1000));
file_put_contents($tmp . '/fahrzeuge/b.jpg', str_repeat('x', 500));
file_put_contents($tmp . '/sicherungen/alt/s.zip', str_repeat('x', 3000));
file_put_contents($tmp . '/lose.txt', str_repeat('x', 10));
$o = speicher_ordner($tmp . '/fahrzeuge');
$check('Ordnergröße und Dateizahl', $o === ['bytes' => 1500, 'dateien' => 2, 'vorhanden' => true]);
$check('Unterordner zählen mit', speicher_ordner($tmp . '/sicherungen')['bytes'] === 3000 && speicher_ordner($tmp . '/sicherungen')['dateien'] === 1);
$check('fehlender Ordner', speicher_ordner($tmp . '/gibtsnicht') === ['bytes' => 0, 'dateien' => 0, 'vorhanden' => false]);

/* ---------- Platte ---------- */
$pl = speicher_platte_rechnen(100 * 1048576 * 1024, 5 * 1048576 * 1024);
$check('Platte: 95 % belegt ist knapp', $pl['prozent'] === 95 && $pl['knapp'] && $pl['belegt'] === 95 * 1048576 * 1024);
$check('Platte: 50 % mit viel Platz ist nicht knapp', !speicher_platte_rechnen(200 * 1048576 * 1024, 100 * 1048576 * 1024)['knapp']);
$check('Platte: unter 500 MB frei ist knapp, auch bei großer Platte', speicher_platte_rechnen(10 * 1048576 * 1024, 300 * 1048576)['knapp']);
$check('Platte: frei kann gesamt nicht übersteigen', speicher_platte_rechnen(100, 200)['belegt'] === 0 && speicher_platte_rechnen(0, 0)['prozent'] === 0);
$echt = speicher_platte($tmp);
$check('echte Platte abfragbar', $echt !== null && $echt['gesamt'] > 0 && $echt['frei'] <= $echt['gesamt'] && speicher_platte($tmp . '/gibtsnicht') === null);

/* ---------- Übersicht ---------- */
$u = speicher_uebersicht($tmp);
$keys = array_column($u['ablage']['ordner'], 'key');
$check('Übersicht: Ablage mit Ordnern, größte zuerst, Rest als Sonstiges', $u['ablage']['bytes'] === 4510 && $u['ablage']['dateien'] === 4 && $keys[0] === 'sicherungen'
    && in_array('fahrzeuge', $keys, true) && in_array('standorte', $keys, true) && array_column($u['ablage']['ordner'], 'bytes', 'key')['sonstiges'] === 10
    && !in_array('veranstaltungen', $keys, true) && $u['db']['name'] === 'ovbudget' && $u['platte'] !== null);

/* ---------- Karte auf der Verwaltungsseite ---------- */
$daten = ['title' => 'Verwaltung', 'ablage' => ['pfad' => $tmp, 'vorhanden' => true, 'beschreibbar' => true, 'benutzer' => 'www', 'dateien' => 2, 'eintraege' => 2, 'fehlend' => []],
    'zeit' => ['zone' => 'Europe/Berlin', 'app' => '2026-10-10 12:00:00', 'db' => '2026-10-10 12:00:00', 'versatz' => 0],
    'zahlen' => ['benutzer' => 3, 'wuensche' => 4, 'aufgaben' => 5, 'listen' => 6]];
$u['platte'] = speicher_platte_rechnen(100 * 1048576 * 1024, 40 * 1048576 * 1024);
$html = render_partial('admin/index', $daten + ['speicher' => $u]);
$check('Karte: Datenbank, Ablage, Platte mit Balken', str_contains($html, 'Speicherplatz') && str_contains($html, '7.1 MB') && str_contains($html, 'ovbudget') && str_contains($html, '4.4 KB')
    && str_contains($html, '40 GB frei') && str_contains($html, 'width:60%') && !str_contains($html, 'wird knapp') && str_contains($html, 'Sicherungen') && str_contains($html, 'audit_log') && str_contains($html, '12.000 Zeilen'));
$u['platte'] = speicher_platte_rechnen(100 * 1048576 * 1024, 2 * 1048576 * 1024);
$html = render_partial('admin/index', $daten + ['speicher' => $u]);
$check('Karte: knapper Platz wird rot gemeldet', str_contains($html, 'wird knapp') && str_contains($html, 'speicher__balken--voll') && str_contains($html, 'width:98%'));
$html = render_partial('admin/index', $daten);
$check('ohne Speicherdaten keine Karte', !str_contains($html, 'Speicherplatz') && str_contains($html, 'Verwaltung'));

// aufräumen
foreach (['fahrzeuge/a.jpg', 'fahrzeuge/b.jpg', 'sicherungen/alt/s.zip', 'lose.txt'] as $f) { @unlink($tmp . '/' . $f); }
foreach (['sicherungen/alt', 'sicherungen', 'fahrzeuge', 'standorte'] as $o) { @rmdir($tmp . '/' . $o); }
@rmdir($tmp);

echo "$ok bestanden, $fail fehlgeschlagen\n";
