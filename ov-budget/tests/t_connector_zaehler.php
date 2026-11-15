<?php
declare(strict_types=1);
/* Connector: Zählerstände – Zugänge setzen, Seite, Meldung ablegen, abholen, nichts im Klartext */
$tmp = sys_get_temp_dir() . '/ovb-zaehler-test';
@mkdir($tmp, 0777, true);
$leeren = static function (string $o) use (&$leeren): void {
    foreach (glob($o . '/*') ?: [] as $f) { if (is_dir($f)) { $leeren($f); @rmdir($f); } else { @unlink($f); } }
};
$leeren($tmp);
$quelle = (string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/connector.php');
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
@mkdir(__DIR__ . '/alt', 0777, true);
file_put_contents(__DIR__ . '/alt/connector_zaehler.php', $quelle);
require __DIR__ . '/alt/connector_zaehler.php';
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

$token = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-_ab';
$kennung = con_kennung($token);

$n = con_zaehler_setzen([
    ['kennung' => $kennung, 'art' => 'gas', 'einheit' => 'm³'],
    ['kennung' => 'zu kurz', 'art' => 'strom', 'einheit' => 'kWh'],
    ['kennung' => str_repeat('a', 64), 'art' => 'unsinn', 'einheit' => "kWh<script>\n"],
    'kein array',
]);
$check('nur gültige Zugänge', $n === 2);
$z = con_zaehler($token);
$check('Zugang mit Art und Einheit', $z !== null && $z['art'] === 'gas' && $z['einheit'] === 'm³');
$check('unbekannte Art wird Strom, Einheit gesäubert', con_lesen('zaehler.json')[str_repeat('a', 64)] === ['art' => 'strom', 'einheit' => 'kWhscript']);
$check('unbekannter Zugang null', con_zaehler('ZZZZZZZZZZZZZZZZZZZZZZZZ') === null && con_zaehler('../x') === null);

$roh = (string)file_get_contents($tmp . '/daten/zaehler.json');
$check('kein Zugang im Klartext', !str_contains($roh, $token));

try { con_zaehlerstand_ablegen('ZZZZZZZZZZZZZZZZZZZZZZZZ', 'geheim'); $check('unbekannter Zugang abgewiesen', false); }
catch (ConException $e) { $check('unbekannter Zugang abgewiesen', true); }
try { con_zaehlerstand_ablegen($token, ''); $check('leere Meldung abgewiesen', false); }
catch (ConException $e) { $check('leere Meldung abgewiesen', true); }
try { con_zaehlerstand_ablegen($token, str_repeat('x', CON_MAX_MELDUNG + 1)); $check('zu große Meldung abgewiesen', false); }
catch (ConException $e) { $check('zu große Meldung abgewiesen', true); }

$gebremst = false;
for ($i = 0; $i < CON_LIMIT_ZAEHLER + 2; $i++) {
    try { con_zaehlerstand_ablegen($token, 'geheimtext-' . $i); }
    catch (ConException $e) { $gebremst = true; break; }
}
$check('je Zugang begrenzt', $gebremst && $i === CON_LIMIT_ZAEHLER);
$check('Meldungen liegen nur als Geheimtext', count(glob($tmp . '/daten/zaehlerstaende/' . $kennung . '/*.json')) === CON_LIMIT_ZAEHLER);

$geholt = con_zaehlerstaende_abholen(10);
$check('abholen liefert höchstens max', count($geholt) === 10 && $geholt[0]['zugang'] === $kennung && str_starts_with($geholt[0]['daten'], 'geheimtext-'));
$check('abgeholt ist gelöscht', count(glob($tmp . '/daten/zaehlerstaende/' . $kennung . '/*.json')) === CON_LIMIT_ZAEHLER - 10);

/* Abholen mit Quittung: gelöscht wird erst, wenn OV-Multitool den Empfang bestätigt hat */
$liegt = static fn(): int => count(glob($tmp . '/daten/zaehlerstaende/' . $kennung . '/*.json') ?: []);
$holen = static fn(array $anfrage): array => con_abholen('zaehler_abholen', $anfrage, 'con_zaehlerstaende_abholen');
[$liste, $q1] = $holen(['max' => 5, 'quittung' => '']);
$check('mit Quittung: geliefert, aber noch nicht gelöscht', count($liste) === 5 && strlen($q1['quittung']) === 16 && $liegt() === CON_LIMIT_ZAEHLER - 10);
[$nochmal, $q2] = $holen(['max' => 5, 'quittung' => '']);
$check('Antwort ging verloren: dasselbe kommt noch einmal', $nochmal === $liste && $q2['quittung'] !== $q1['quittung'] && $liegt() === CON_LIMIT_ZAEHLER - 10);
[, $q3] = $holen(['max' => 5, 'quittung' => $q1['quittung']]);
$check('veraltete Quittung löscht nichts', $liegt() === CON_LIMIT_ZAEHLER - 10);
[$weiter, $q4] = $holen(['max' => 500, 'quittung' => $q3['quittung']]);
$check('bestätigt: die fünf sind gelöscht, der Rest kommt', count($weiter) === CON_LIMIT_ZAEHLER - 15 && $liegt() === CON_LIMIT_ZAEHLER - 15
    && array_intersect(array_column($weiter, 'daten'), array_column($liste, 'daten')) === []);
[$leer] = $holen(['max' => 500, 'quittung' => $q4['quittung']]);
$check('alles bestätigt: nichts liegt mehr', $leer === [] && $liegt() === 0);
file_put_contents($tmp . '/daten/zaehlerstaende/' . $kennung . '/1-alt.json', '{"ts":1,"daten":"x"}');
[$alt, $ohne] = $holen(['max' => 500]);
$check('älteres OV-Multitool ohne Quittung: wie bisher beim Abholen gelöscht', count($alt) === 1 && $ohne === [] && $liegt() === 0);
$check('Merkdatei gehört zur Ablage', con_daten_erwartet('abholung.json', false) && is_file($tmp . '/daten/abholung.json'));

/* Anschluss: bei IPv6 zählt das Netz, und wer zu oft danebengreift, ist gesperrt */
$anschluss = static function (string $ip): string { $_SERVER['REMOTE_ADDR'] = $ip; return con_anschluss(); };
$check('IPv6: dasselbe /64 ist ein Anschluss', $anschluss('2001:db8:1:2::1') === $anschluss('2001:db8:1:2:ffff::9'));
$check('IPv6: anderes Netz, anderer Anschluss', $anschluss('2001:db8:1:2::1') !== $anschluss('2001:db8:1:3::1'));
$check('IPv4 zählt je Adresse – auch in IPv6-Schreibweise', $anschluss('203.0.113.7') !== $anschluss('203.0.113.8')
    && $anschluss('::ffff:203.0.113.7') !== $anschluss('::ffff:203.0.113.8'));
$_SERVER['REMOTE_ADDR'] = '192.0.2.44';
try { con_rueckmeldung_ablegen('ZZZZ99', 'x'); } catch (ConException $e) { }
$check('ein falscher Code sperrt noch nicht', !con_gesperrt());
for ($i = 0; $i < CON_LIMIT_FEHLGRIFF; $i++) {
    try { con_umfrage_antwort_ablegen('ZZZZ99', 'x'); } catch (ConException $e) { }
}
$check('falsche Codes beim Absenden zählen: danach gesperrt', con_gesperrt());
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
$check('anderer Anschluss ist nicht gesperrt', !con_gesperrt());

con_zaehler_setzen([]);
$check('zurückgezogen: Zugang und Ordner weg', con_zaehler($token) === null && !is_dir($tmp . '/daten/zaehlerstaende/' . $kennung));
$s = con_status();
$check('Zustand zählt Zähler', array_key_exists('zaehler', $s) && $s['zaehler'] === 0 && $s['zaehlerstaende'] === 0);

// Seite: unbekannter Zugang zeigt keinen Schlüssel und keinen Stand
$eintrag = null; $token = 'x';
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_zaehler.php'; $html = ob_get_clean();
$check('Seite ohne Zugang: Hinweis', str_contains($html, 'keinem Zähler') && !str_contains($html, 'data-schluessel'));
$eintrag = ['kennung' => 'k', 'art' => 'wasser', 'einheit' => 'm³']; $token = 'echt';
con_schreiben('kopplung.json', ['ov_pubkey' => 'PUB']);
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_zaehler.php'; $html = ob_get_clean();
$check('Seite mit Zugang: Einheit, Skript, kein Name', str_contains($html, 'm³') && str_contains($html, 'zaehler.js')
    && str_contains($html, 'Wasserzähler') && str_contains($html, 'data-token="echt"'));

$leeren($tmp);
echo "$ok bestanden, $fail fehlgeschlagen\n";
