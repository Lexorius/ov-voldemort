<?php
declare(strict_types=1);
/* Connector: Newsletter-Abmeldungen – Kennung prüfen, ablegen, bremsen, abholen, die Abmeldeseite */
$tmp = sys_get_temp_dir() . '/ovb-abmelden-test';
@mkdir($tmp, 0777, true);
$leeren = static function (string $o) use (&$leeren): void {
    foreach (scandir($o) ?: [] as $name) {
        if ($name === '.' || $name === '..') { continue; }
        $f = $o . '/' . $name;
        if (is_dir($f)) { $leeren($f); @rmdir($f); } else { @unlink($f); }
    }
};
$leeren($tmp);
$quelle = (string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/connector.php');
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
@mkdir(__DIR__ . '/alt', 0777, true);
file_put_contents(__DIR__ . '/alt/connector_newsletter.php', $quelle);
require __DIR__ . '/alt/connector_newsletter.php';
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

$k1 = str_repeat('a', 64);
$k2 = hash('sha256', 'zwei');
$check('Kennung: 64 Hex-Zeichen', con_abmeldung_gueltig($k1) && con_abmeldung_gueltig($k2) && !con_abmeldung_gueltig('abc') && !con_abmeldung_gueltig(str_repeat('g', 64)) && !con_abmeldung_gueltig('../' . str_repeat('a', 61)));
try { con_abmeldung_ablegen('kaputt'); $check('kaputte Kennung abgewiesen', false); }
catch (ConException $e) { $check('kaputte Kennung abgewiesen', str_contains($e->getMessage(), 'nicht vollständig')); }
con_abmeldung_ablegen(strtoupper($k1));
con_abmeldung_ablegen($k1);
con_abmeldung_ablegen($k2);
$dateien = glob($tmp . '/daten/abmeldungen/*.json') ?: [];
$check('abgelegt: je Kennung eine Datei, Groß/Klein egal, keine Adresse', count($dateien) === 2 && is_file($tmp . '/daten/abmeldungen/' . $k1 . '.json'));
$check('Zustand zählt', con_status()['abmeldungen'] === 2);
$check('Ablage kennt den Ordner', con_daten_erwartet('abmeldungen', true));

$gebremst = false;
for ($i = 0; $i < CON_LIMIT_ABMELDUNG + 5; $i++) {
    try { con_abmeldung_ablegen(hash('sha256', 'n' . $i)); } catch (ConException $e) { $gebremst = true; break; }
}
$check('je Anschluss gebremst', $gebremst);
@unlink($tmp . '/daten/limit.json');

$geholt = con_abmeldungen_abholen(1000);
$check('abholen liefert Kennungen mit Zeit und löscht', count($geholt) >= 2 && in_array($k1, array_column($geholt, 'kennung'), true) && $geholt[0]['ts'] > 0
    && glob($tmp . '/daten/abmeldungen/*.json') === [] && con_abmeldungen_abholen() === []);
$geholtKeine = con_abmeldungen_abholen(0);
$check('max null: nichts', $geholtKeine === []);

/* ---------- Seite ---------- */
$seite = static function (string $kennungWert, bool $erledigtWert, string $fehler = ''): string {
    $kennung = $kennungWert; $erledigt = $erledigtWert; $fehlerText = $fehler;
    ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_abmelden.php'; return ob_get_clean();
};
$html = $seite($k1, false);
$check('Seite: Formular mit Kennung, ohne Adresse', str_contains($html, 'name="a" value="' . $k1 . '"') && str_contains($html, '>Abmelden<') && str_contains($html, 'p=abmelden') && !str_contains($html, '@'));
$html = $seite('kaputt', false);
$check('Seite: kaputter Link', str_contains($html, 'nicht vollständig') && !str_contains($html, '<form'));
$html = $seite($k1, true);
$check('Seite: erledigt', str_contains($html, 'Erledigt') && !str_contains($html, '<form'));
$html = $seite($k1, false, 'Zu viele');
$check('Seite: Fehler bleibt mit Formular', str_contains($html, 'Zu viele') && str_contains($html, '<form'));

$leeren($tmp);
echo "$ok bestanden, $fail fehlgeschlagen\n";
