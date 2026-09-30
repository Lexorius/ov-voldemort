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
con_zaehlerstaende_abholen(500);

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
