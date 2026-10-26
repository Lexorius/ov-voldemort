<?php
declare(strict_types=1);
/*
 * Connector: Getränke – Zugänge mit Getränkeliste setzen, Seite, Meldung
 * ablegen und abholen, nichts im Klartext, und der ganze Weg vom Handy bis
 * zur Meldung im Multitool.
 */
$tmp = sys_get_temp_dir() . '/ovb-getraenke-test';
@mkdir($tmp, 0777, true);
$leeren = static function (string $o) use (&$leeren): void {
    foreach (glob($o . '/*') ?: [] as $f) { if (is_dir($f)) { $leeren($f); @rmdir($f); } else { @unlink($f); } }
};
$leeren($tmp);
$quelle = (string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/connector.php');
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
@mkdir(__DIR__ . '/alt', 0777, true);
file_put_contents(__DIR__ . '/alt/connector_getraenke.php', $quelle);
require __DIR__ . '/alt/connector_getraenke.php';
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

$token = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-_ab';
$kennung = con_kennung($token);
$liste = [['id' => 1, 'name' => "Wasser still\x01", 'gebinde' => 'Kasten'], ['id' => 2, 'name' => 'Apfelschorle', 'gebinde' => ''], ['id' => 0, 'name' => 'ohne Nummer'], ['id' => 3, 'name' => '   '], 'kaputt'];
$n = con_getraenke_setzen([
    ['kennung' => $kennung, 'getraenke' => $liste],
    ['kennung' => str_repeat('b', 64), 'getraenke' => []],
    ['kennung' => 'zu kurz', 'getraenke' => $liste],
    'kein array',
]);
$check('nur Zugänge mit Getränken', $n === 1);
$g = con_getraenke($token);
$check('Zugang mit gesäuberter Liste', $g !== null && $g['kennung'] === $kennung && count($g['getraenke']) === 2
    && $g['getraenke'][0] === ['id' => 1, 'name' => 'Wasser still', 'gebinde' => 'Kasten'] && $g['getraenke'][1]['gebinde'] === '');
$check('kein Zugang im Klartext', !str_contains((string)file_get_contents($tmp . '/daten/getraenke.json'), $token));
$check('unbekannter Zugang null', con_getraenke('ZZZZZZZZZZZZZZZZZZZZZZZZ') === null && con_getraenke('../x') === null);

try { con_getraenkemeldung_ablegen('ZZZZZZZZZZZZZZZZZZZZZZZZ', 'geheim'); $check('unbekannter Zugang abgewiesen', false); }
catch (ConException $e) { $check('unbekannter Zugang abgewiesen', true); }
try { con_getraenkemeldung_ablegen($token, ''); $check('leere Meldung abgewiesen', false); }
catch (ConException $e) { $check('leere Meldung abgewiesen', true); }
try { con_getraenkemeldung_ablegen($token, str_repeat('x', CON_MAX_RUECKMELDUNG + 1)); $check('zu große Meldung abgewiesen', false); }
catch (ConException $e) { $check('zu große Meldung abgewiesen', true); }

$gebremst = false;
for ($i = 0; $i < CON_LIMIT_GETRAENKE + 2; $i++) {
    try { con_getraenkemeldung_ablegen($token, 'geheimtext-' . $i); }
    catch (ConException $e) { $gebremst = true; break; }
}
$check('je Zugang begrenzt', $gebremst && $i === CON_LIMIT_GETRAENKE);
$ordner = $tmp . '/daten/getraenkemeldungen/' . $kennung;
$check('Meldungen nur als Geheimtext', count(glob($ordner . '/*.json')) === CON_LIMIT_GETRAENKE && !is_dir($tmp . '/daten/getraenkemeldungen/' . $token));
$geholt = con_getraenkemeldungen_abholen(10);
$check('abholen liefert höchstens max', count($geholt) === 10 && $geholt[0]['zugang'] === $kennung && str_starts_with($geholt[0]['daten'], 'geheimtext-'));
$check('abgeholt ist gelöscht', count(glob($ordner . '/*.json')) === CON_LIMIT_GETRAENKE - 10);
con_getraenkemeldungen_abholen(500);
@unlink($tmp . '/daten/limit.json');

con_getraenkemeldung_ablegen($token, 'noch-eine');
con_getraenke_setzen([]);
$check('zurückgezogen: Zugang und Ordner weg', con_getraenke($token) === null && !is_dir($ordner));
$s = con_status();
$check('Zustand zählt Getränke', $s['getraenke'] === 0 && $s['getraenkemeldungen'] === 0);
$check('Ablage kennt die neuen Namen', con_daten_erwartet('getraenke.json', false) && con_daten_erwartet('getraenkemeldungen', true));

/* ---------- Seite ---------- */
$eintrag = null; $token = 'x';
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_getraenke.php'; $html = ob_get_clean();
$check('Seite ohne Zugang: Hinweis', str_contains($html, 'keinem Lagerplatz') && !str_contains($html, 'data-schluessel'));
con_schreiben('kopplung.json', ['ov_pubkey' => 'PUB']);
$eintrag = ['kennung' => 'k', 'getraenke' => [['id' => 1, 'name' => 'Wasser still', 'gebinde' => 'Kasten'], ['id' => 2, 'name' => 'Cola <Mix>', 'gebinde' => '']]]; $token = 'echt';
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_getraenke.php'; $html = ob_get_clean();
$check('Seite mit Zugang: Getränke, drei Stände, Zahl, Skript', str_contains($html, 'Wasser still') && str_contains($html, 'Cola &lt;Mix&gt;')
    && str_contains($html, 'name="s_1" value="leer"') && str_contains($html, 'name="s_1" value="knapp"') && str_contains($html, 'name="s_1" value="ok"')
    && str_contains($html, 'name="n_2"') && str_contains($html, 'data-token="echt"') && str_contains($html, 'getraenke.js') && str_contains($html, 'data-getraenk="2"'));

/* ---------- Der ganze Weg ---------- */
$GLOBALS['settings'] = ['waehrung' => 'EUR'];
$GLOBALS['inserts'] = [];
$GLOBALS['getraenke'] = [['id' => 1, 'name' => 'Wasser still', 'gebinde' => 'Kasten', 'is_active' => 1, 'lieferant_id' => 1], ['id' => 2, 'name' => 'Apfelschorle', 'gebinde' => 'Kasten', 'is_active' => 1, 'lieferant_id' => 2]];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM getraenke g')) { return $GLOBALS['getraenke']; }
    if (str_contains($sql, 'FROM standorte')) { return [['id' => 5, 'name' => 'Halle 1', 'getraenke_qr_token' => 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-_ab']]; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'FROM getraenke_bestand') ? -1 : $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'webpush', 'mail', 'connector', 'getraenke'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

[$ovPem, $ovPunkt] = p256_keypair();
$handy = static function (array $inhalt) use ($ovPunkt): string {
    [$handyPem, $handyPunkt] = p256_keypair();
    $gemeinsam = openssl_pkey_derive(p256_public_pem($ovPunkt), openssl_pkey_get_private($handyPem));
    $salz = random_bytes(16);
    $abgeleitet = hash_hkdf('sha256', $gemeinsam, 44, CONNECTOR_INFO, $salz);
    $tag = '';
    $geheim = openssl_encrypt((string)json_encode($inhalt), 'aes-256-gcm', substr($abgeleitet, 0, 32),
        OPENSSL_RAW_DATA, substr($abgeleitet, 32, 12), $tag);
    return b64u_encode("\x01" . $handyPunkt . $salz . $geheim . $tag);
};

$paket = connector_getraenke_paket(['id' => 1]);
$check('Paket: je Platz die Prüfsumme und alle aktiven Getränke', count($paket) === 1 && $paket[0]['kennung'] === $kennung && count($paket[0]['getraenke']) === 2
    && $paket[0]['getraenke'][0] === ['id' => 1, 'name' => 'Wasser still', 'gebinde' => 'Kasten'] && !isset($paket[0]['name']));
$check('Connector nimmt das Paket an', con_getraenke_setzen($paket) === 1 && con_getraenke('AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-_ab') !== null);
$adresse = connector_qr_getraenke_url(['url' => 'https://ov.example.de/connector'], 'tok', 'Halle 1');
$check('QR-Adresse mit Platz im Anker', str_starts_with($adresse, 'https://ov.example.de/connector/index.php?p=getraenke&t=tok#n=') && !str_contains(explode('#', $adresse)[0], 'Halle'));

con_getraenkemeldung_ablegen('AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-_ab', $handy(['meldungen' => [['id' => 1, 'stand' => 'leer', 'anzahl' => null], ['id' => 2, 'stand' => 'knapp', 'anzahl' => 1]], 'melder' => 'Anna', 'zeit' => time()]));
$platte = implode('', array_map('file_get_contents', glob($ordner . '/*.json')));
$check('nichts davon auf der Platte lesbar', !str_contains($platte, 'Anna') && !str_contains($platte, 'leer'));
$geholt = con_getraenkemeldungen_abholen();
$daten = connector_entschluesseln($geholt[0]['daten'], $ovPem, ['meldungen']);
$check('entschlüsselt: zwei Meldungen mit Melder', count($daten['meldungen']) === 2 && $daten['melder'] === 'Anna');
$n = getraenke_meldung_anwenden(['id' => 5, 'name' => 'Halle 1'], $daten, $geholt[0]['ts']);
$mel = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'getraenke_meldungen'));
$bew = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'getraenke_bewegungen'));
$check('im Multitool: zwei Meldungen, Wasser auf null, Schorle Inventur 1', $n === 2 && count($mel) === 2 && $mel[0][1]['stand'] === 'leer' && $mel[1][1]['stand'] === 'knapp'
    && count($bew) === 2 && $bew[0][1]['anzahl'] === 0 && $bew[1][1]['anzahl'] === 1 && $bew[1][1]['melder'] === 'Anna');

$leeren($tmp);
echo "$ok bestanden, $fail fehlgeschlagen\n";
