<?php
declare(strict_types=1);
/* Rendert die Einladungsseite und sieht nach, was darauf steht. */
$tmp = sys_get_temp_dir() . '/ovb-seite-test';
@mkdir($tmp, 0777, true);
foreach (glob($tmp . '/daten/*') ?: [] as $f) { if (is_file($f)) { @unlink($f); } }

$quelle = (string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/connector.php');
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
file_put_contents(__DIR__ . '/alt/connector_seite.php', $quelle);
require __DIR__ . '/alt/connector_seite.php';

[$pem, $punkt] = con_keypair();
con_koppeln(con_kopplungscode(), con_b64u($punkt));

$kennung = hash('sha256', 'ovb-veranstaltung:12');
con_veranstaltungen_setzen([[
    'kennung' => $kennung, 'titel' => 'Jahresabschlussfeier',
    'beginn' => '2026-12-05 18:00:00', 'ende' => '2026-12-05 23:00:00',
    'ort' => 'Unterkunft, Musterweg 1', 'hinweis' => "Einlass ab 17:30.\nBitte Rückmeldung bis Ende November.",
    'bis' => '2026-11-28', 'status' => 'geplant', 'begleiter_max' => 2,
    'kommentare' => 1, 'vertretung' => 1,
]], [con_code_kennung('AB23CD') => $kennung]);

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

$_SERVER['SCRIPT_NAME'] = '/index.php';
$code = 'AB23CD';
$einladung = con_einladung($code);
ob_start();
require dirname(__DIR__, 2) . '/connector/src/seite_einladung.php';
$html = (string)ob_get_clean();

$check('Titel steht drauf', str_contains($html, 'Jahresabschlussfeier'));
$check('Datum ausgeschrieben', str_contains($html, '5. Dezember 2026, 18:00 Uhr'));
$check('Ort steht drauf', str_contains($html, 'Musterweg 1'));
$check('Hinweis steht drauf', str_contains($html, 'Einlass ab 17:30'));
$check('drei Antworten zur Auswahl', str_contains($html, 'Ich nehme teil')
    && str_contains($html, 'Ich kann leider nicht teilnehmen')
    && str_contains($html, 'es kommt jemand für mich'));
$check('Begleiterfrage wie gewünscht', str_contains($html, 'Mit wie vielen Begleitern kommen Sie?'));
$check('Auswahl bis zum Höchstwert', substr_count($html, '<option value="') === 3);
$check('Nachrichtenfeld da', str_contains($html, 'id="kommentar"'));
$check('Frist genannt', str_contains($html, '28. November 2026'));
$check('Schlüssel für die Verschlüsselung dabei', str_contains($html, 'data-schluessel="'));
$check('Skript wird geladen', str_contains($html, '/assets/einladung.js'));
$check('kein Name auf der Seite', !str_contains($html, 'Anna'));

// Ohne Begleiter und ohne Vertretung faellt beides weg
con_veranstaltungen_setzen([[
    'kennung' => $kennung, 'titel' => 'Stiller Abend', 'beginn' => '2026-12-05 18:00:00',
    'ort' => '', 'bis' => '', 'status' => 'geplant', 'begleiter_max' => 0,
    'kommentare' => 0, 'vertretung' => 0,
]], [con_code_kennung('AB23CD') => $kennung]);
$einladung = con_einladung($code);
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_einladung.php'; $html = (string)ob_get_clean();
$check('ohne Begleiter keine Frage danach', !str_contains($html, 'Mit wie vielen Begleitern'));
$check('ohne Vertretung keine Vertretung', !str_contains($html, 'es kommt jemand für mich'));
$check('ohne Kommentare kein Feld', !str_contains($html, 'id="kommentar"'));

// Abgesagt
con_veranstaltungen_setzen([[
    'kennung' => $kennung, 'titel' => 'Fällt aus', 'beginn' => '2026-12-05 18:00:00',
    'status' => 'abgesagt', 'begleiter_max' => 0, 'kommentare' => 0, 'vertretung' => 0,
]], [con_code_kennung('AB23CD') => $kennung]);
$einladung = con_einladung($code);
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_einladung.php'; $html = (string)ob_get_clean();
$check('abgesagt wird gesagt', str_contains($html, 'findet nicht statt') && !str_contains($html, 'id="formular"'));

// Unbekannter Code
$einladung = null;
$code = 'ZZ99ZZ';
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_einladung.php'; $html = (string)ob_get_clean();
$check('unbekannter Code: freundliche Absage', str_contains($html, 'gilt nicht (mehr)'));

// Kein HTML aus fremden Angaben
con_veranstaltungen_setzen([[
    'kennung' => $kennung, 'titel' => '<script>alert(1)</script>', 'beginn' => '2026-12-05 18:00:00',
    'status' => 'geplant', 'begleiter_max' => 0, 'kommentare' => 0, 'vertretung' => 0,
]], [con_code_kennung('AB23CD') => $kennung]);
$code = 'AB23CD';
$einladung = con_einladung($code);
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_einladung.php'; $html = (string)ob_get_clean();
$check('kein Skript aus den Angaben', !str_contains($html, '<script>alert')
    && str_contains($html, '&lt;script&gt;'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
