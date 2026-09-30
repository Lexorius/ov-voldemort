<?php
declare(strict_types=1);
/* Startseite des Connectors: Einladungsfeld ja, Zahlen nein. */
$tmp = sys_get_temp_dir() . '/ovb-start-test';
@mkdir($tmp . '/daten', 0777, true);
foreach (glob($tmp . '/daten/*') ?: [] as $f) { if (is_file($f)) { @unlink($f); } }

$quelle = (string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/connector.php');
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
file_put_contents(__DIR__ . '/alt/connector_start.php', $quelle);
require __DIR__ . '/alt/connector_start.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

// Ein Connector mit reichlich Inhalt
[$pem, $punkt] = con_keypair();
con_koppeln(con_kopplungscode(), con_b64u($punkt));
$token = con_b64u(random_bytes(24));
con_fahrzeuge_setzen([['kennung' => con_kennung($token)], ['kennung' => con_kennung('zweitesfahrzeugxx')],
                      ['kennung' => con_kennung('drittesfahrzeugxx')]]);
$ev = hash('sha256', 'ovb-veranstaltung:1');
con_veranstaltungen_setzen([[
    'kennung' => $ev, 'titel' => 'Jahresabschlussfeier', 'beginn' => '2026-12-05 18:00:00',
    'ort' => 'Unterkunft', 'bis' => '', 'status' => 'geplant', 'begleiter_max' => 2,
    'kommentare' => 1, 'vertretung' => 1,
]], [con_code_kennung('AB23CD') => $ev, con_code_kennung('XY79ZK') => $ev]);
con_meldung_ablegen($token, str_repeat('x', 100));

$_SERVER['SCRIPT_NAME'] = '/index.php';
$gekoppelt = con_gekoppelt();
$schreibbar = is_writable(con_dir());
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_start.php'; $html = (string)ob_get_clean();

$check('Feld für den Einladungscode', str_contains($html, 'name="e"')
    && str_contains($html, 'Einladungscode'));
$check('Formular geht an den Connector', str_contains($html, 'action="/index.php"'));
$check('keine Fahrzeugzahl', !str_contains($html, '>3<') && !str_contains($html, 'Fahrzeuge'));
$check('keine Veranstaltungen', !str_contains($html, 'Veranstaltung'));
$check('keine Einladungszahl', !str_contains($html, 'Einladungen'));
$check('nichts über wartende Meldungen', !str_contains($html, 'wartend') && !str_contains($html, 'Meldungen'));
$check('kein Titel einer Veranstaltung', !str_contains($html, 'Jahresabschlussfeier'));
$check('keine Fassung', !str_contains($html, CON_VERSION) && !str_contains($html, 'Fassung'));
$check('kein Kopplungshinweis, wenn gekoppelt', !str_contains($html, 'kopplungscode.txt'));
$check('kein Schlüssel', !str_contains($html, con_b64u($punkt)));

// Vor der Kopplung darf die Anleitung dastehen
$gekoppelt = false;
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_start.php'; $html = (string)ob_get_clean();
$check('ungekoppelt: Anleitung', str_contains($html, 'kopplungscode.txt'));
$check('ungekoppelt: trotzdem keine Zahlen', !str_contains($html, 'Fahrzeuge'));

$schreibbar = false;
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_start.php'; $html = (string)ob_get_clean();
$check('nicht beschreibbar wird gesagt', str_contains($html, 'nicht beschreibbar'));

// Die Zahlen gibt es weiterhin - aber nur signiert abzufragen
$st = con_status();
$check('Zustand kennt die Zahlen', $st['fahrzeuge'] === 3 && $st['veranstaltungen'] === 1
    && $st['einladungen'] === 2 && $st['meldungen'] === 1);
$check('Zustand ohne Geheimnisse', !str_contains((string)json_encode($st), 'BEGIN')
    && !isset($st['eigener_pem']));

echo "$ok bestanden, $fail fehlgeschlagen\n";
