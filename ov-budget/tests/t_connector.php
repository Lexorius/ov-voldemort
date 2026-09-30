<?php
declare(strict_types=1);
/*
 * Connector: Kopplung, Signaturen, Begrenzungen – und der ganze Weg einer
 * Meldung vom Handy bis in die Fahrzeugakte.
 *
 * Der Test übernimmt dabei die Rolle des Handys: Er verschlüsselt genau so,
 * wie melden.js es im Browser tut, und OV-Multitool muss es lesen können.
 */
$tmp = sys_get_temp_dir() . '/ovb-connector-test';
@mkdir($tmp, 0777, true);
foreach (glob($tmp . '/*') ?: [] as $f) {
    if (is_dir($f)) {
        foreach (glob($f . '/*') ?: [] as $g) {
            if (is_dir($g)) { array_map('unlink', glob($g . '/*') ?: []); @rmdir($g); }
            else { @unlink($g); }
        }
        @rmdir($f);
    } else {
        @unlink($f);
    }
}

// Connector-Bibliothek mit eigenem Datenordner laden
$connector = dirname(__DIR__, 2) . '/connector/src/connector.php';
$quelle = (string)file_get_contents($connector);
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
@mkdir(__DIR__ . '/alt', 0777, true);
file_put_contents(__DIR__ . '/alt/connector_test.php', $quelle);
require __DIR__ . '/alt/connector_test.php';

// OV-Multitool-Seite
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'connector_aktiv' => '1',
    'connector_url' => 'https://ov.example.de/connector',
    'connector_park_minuten' => '60', 'connector_park_radius_meter' => '50'];
$GLOBALS['merker'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];

function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) {
        $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
    }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    return str_contains($sql, 'FROM settings') ? ($GLOBALS['merker'][$p[0] ?? ''] ?? $d) : $d;
}
function db_exec(string $sql, array $p = []): int {
    if (str_contains($sql, 'INTO settings')) { $GLOBALS['merker'][$p[0]] = (string)$p[1]; }
    return 1;
}
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 1; }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function db_lock(string $name, int $warten = 0): bool { return true; }
function db_unlock(string $name): void {}
function current_user(): ?array { return ['id' => 1]; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/webpush.php';
require $app . '/src/lib/vehicles.php';
require $app . '/src/lib/divera_vehicles.php';
require $app . '/src/lib/notify.php';
require $app . '/src/lib/connector.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ================= Kopplung ================= */
$code = con_kopplungscode();
$check('Kopplungscode erzeugt', preg_match('/^[0-9A-F]{6}-[0-9A-F]{6}-[0-9A-F]{6}$/', $code) === 1);
$check('Code bleibt gleich', con_kopplungscode() === $code);
$check('noch nicht gekoppelt', !con_gekoppelt());

[$ovPem, $ovPunkt] = p256_keypair();
try { con_koppeln('FALSCH-FALSCH-FALSCH', con_b64u($ovPunkt)); $check('falscher Code abgewiesen', false); }
catch (ConException $e) { $check('falscher Code abgewiesen', !con_gekoppelt()); }

$antwort = con_koppeln($code, con_b64u($ovPunkt));
$check('gekoppelt', con_gekoppelt() && strlen(con_unb64u($antwort['pubkey'])) === 65);
$check('Code verbraucht', !is_file($tmp . '/daten/kopplungscode.txt'));
try { con_koppeln($code, con_b64u($ovPunkt)); $check('kein zweites Koppeln', false); }
catch (ConException $e) { $check('kein zweites Koppeln', true); }

/* ================= Signierte Anfragen ================= */
$signiere = static function (array $daten) use ($ovPem): array {
    $koerper = (string)json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    openssl_sign($koerper, $sig, openssl_pkey_get_private($ovPem), OPENSSL_ALGO_SHA256);
    return [$koerper, con_b64u($sig)];
};
$anfrage = static fn(string $zweck, array $extra = []) => [
    'zweck' => $zweck, 'ts' => time(), 'nonce' => bin2hex(random_bytes(12))] + $extra;

[$k, $s] = $signiere($anfrage('abholen'));
$check('gültige Anfrage geht durch', is_array(con_pruefe_anfrage($k, $s, 'abholen')));
try { con_pruefe_anfrage($k, $s, 'abholen'); $check('Wiedereinspielen abgewiesen', false); }
catch (ConException $e) { $check('Wiedereinspielen abgewiesen', str_contains($e->getMessage(), 'schon')); }

[$k, $s] = $signiere($anfrage('abholen'));
try { con_pruefe_anfrage($k, $s, 'fahrzeuge'); $check('fremder Zweck abgewiesen', false); }
catch (ConException $e) { $check('fremder Zweck abgewiesen', str_contains($e->getMessage(), 'Zweck')); }

[$k, $s] = $signiere($anfrage('abholen'));
try { con_pruefe_anfrage($k . ' ', $s, 'abholen'); $check('verändeter Rumpf abgewiesen', false); }
catch (ConException $e) { $check('veränderter Rumpf abgewiesen', str_contains($e->getMessage(), 'Signatur')); }

[$fremdPem, $fremdPunkt] = p256_keypair();
$koerper = (string)json_encode($anfrage('abholen'));
openssl_sign($koerper, $fremdSig, openssl_pkey_get_private($fremdPem), OPENSSL_ALGO_SHA256);
try { con_pruefe_anfrage($koerper, con_b64u($fremdSig), 'abholen'); $check('fremder Schlüssel abgewiesen', false); }
catch (ConException $e) { $check('fremder Schlüssel abgewiesen', true); }

[$k, $s] = $signiere(['zweck' => 'abholen', 'ts' => time() - 3600, 'nonce' => bin2hex(random_bytes(12))]);
try { con_pruefe_anfrage($k, $s, 'abholen'); $check('alte Anfrage abgewiesen', false); }
catch (ConException $e) { $check('alte Anfrage abgewiesen', str_contains($e->getMessage(), 'alt')); }

/* ================= Zugänge ================= */
$token = b64u_encode(random_bytes(24));
$check('Zugang sieht gültig aus', con_token_gueltig($token));
$check('Pfadwechsel unmöglich', !con_token_gueltig('../../etc/passwd') && !con_token_gueltig('kurz'));
con_fahrzeuge_setzen([['kennung' => connector_kennung($token)], ['kennung' => 'unsinn']]);
$check('nur gültige Kennungen übernommen', con_fahrzeug($token) !== null && con_fahrzeug('../boese') === null);
$abgelegt = (string)file_get_contents($tmp . '/daten/fahrzeuge.json');
$check('Zugang steht nicht im Klartext', !str_contains($abgelegt, $token));
$check('kein Fahrzeugname auf dem Server', !str_contains($abgelegt, 'GKW') && !str_contains($abgelegt, 'THW'));
$check('nur Prüfsummen abgelegt', str_contains($abgelegt, connector_kennung($token)));
$check('Prüfsumme beidseitig gleich', con_kennung($token) === connector_kennung($token));

// Die Bezeichnung reist im Anker – der Browser schickt ihn nicht mit
$con = ['id' => 1, 'name' => 'Test', 'url' => 'https://ov.example.de/connector',
        'kurz_url' => '', 'is_active' => 1, 'fuer_fahrzeuge' => 1, 'fuer_veranstaltungen' => 0,
        'pem' => $ovPem, 'pubkey' => b64u_encode($ovPunkt), 'server_pub' => 'x'];
$check('Connector taugt für Fahrzeuge', connector_taugt($con, 'fahrzeuge'));
$check('aber nicht für Veranstaltungen', !connector_taugt($con, 'veranstaltungen'));
$check('ohne Kopplung taugt er nicht', !connector_taugt(array_merge($con, ['server_pub' => '']), 'fahrzeuge'));
$check('kurze Adresse fällt auf die normale zurück', connector_kurz_url($con) === $con['url']);
$adresse = connector_qr_url($con, $token, 'GKW 1 · THW 99020');
[$vorAnker, $anker] = explode('#', $adresse, 2);
$check('Zugang im Adressteil', str_contains($vorAnker, rawurlencode($token)));
$check('Name nur im Anker', !str_contains($vorAnker, 'GKW') && str_contains(b64u_decode(substr($anker, 2)), 'GKW 1 · THW 99020'));
$check('ohne Namen kein Anker', !str_contains(connector_qr_url($con, $token), '#'));
$check('Bezeichnung zusammengesetzt',
    connector_qr_name(['bezeichnung' => 'GKW 1', 'kennzeichen' => 'THW 99020']) === 'GKW 1 · THW 99020'
    && connector_qr_name(['bezeichnung' => 'MTW', 'kennzeichen' => '']) === 'MTW');

/* ================= Meldung: der Test spielt das Handy ================= */
$handyMeldung = static function (array $inhalt) use ($ovPunkt): string {
    // genau wie melden.js: flüchtiges Paar, ECDH, HKDF, AES-256-GCM
    [$handyPem, $handyPunkt] = p256_keypair();
    $gemeinsam = openssl_pkey_derive(p256_public_pem($ovPunkt), openssl_pkey_get_private($handyPem));
    $salz = random_bytes(16);
    $abgeleitet = hash_hkdf('sha256', $gemeinsam, 44, CONNECTOR_INFO, $salz);
    $tag = '';
    $geheim = openssl_encrypt((string)json_encode($inhalt), 'aes-256-gcm', substr($abgeleitet, 0, 32),
        OPENSSL_RAW_DATA, substr($abgeleitet, 32, 12), $tag);
    return b64u_encode("\x01" . $handyPunkt . $salz . $geheim . $tag);
};

$paket = $handyMeldung(['lat' => 51.45, 'lng' => 7.01, 'genauigkeit' => 12, 'zeit' => time(), 'melder' => 'Anna']);
con_meldung_ablegen($token, $paket);
$ordner = $tmp . '/daten/meldungen/' . connector_kennung($token);
$check('Meldung liegt beim Connector', count(glob($ordner . '/*.json')) === 1);
$check('Ordner heißt nach der Prüfsumme, nicht nach dem Zugang', !is_dir($tmp . '/daten/meldungen/' . $token));
$aufPlatte = (string)file_get_contents(glob($ordner . '/*.json')[0]);
$check('keine Koordinaten auf der Platte', !str_contains($aufPlatte, '51.45') && !str_contains($aufPlatte, 'Anna'));

try { con_meldung_ablegen('unbekannt-unbekannt-123456', $paket); $check('unbekannter Zugang abgewiesen', false); }
catch (ConException $e) { $check('unbekannter Zugang abgewiesen', true); }
try { con_meldung_ablegen($token, str_repeat('x', 9000)); $check('zu große Meldung abgewiesen', false); }
catch (ConException $e) { $check('zu große Meldung abgewiesen', true); }

$geholt = con_meldungen_abholen();
$check('abgeholt', count($geholt) === 1);
$check('nach dem Abholen gelöscht', count(glob($ordner . '/*.json')) === 0);
$check('Meldung trägt die Prüfsumme', $geholt[0]['fz'] === connector_kennung($token));

$klartext = connector_entschluesseln($geholt[0]['daten'], $ovPem);
$check('OV-Multitool kann lesen', abs($klartext['lat'] - 51.45) < 0.000001 && $klartext['melder'] === 'Anna');

// Mit einem anderen Schlüssel geht es nicht
try { connector_entschluesseln($geholt[0]['daten'], $fremdPem); $check('fremder Schlüssel liest nichts', false); }
catch (ConnectorException $e) { $check('fremder Schlüssel liest nichts', true); }
try { connector_entschluesseln(b64u_encode("\x09" . random_bytes(120)), $ovPem); $check('unbekannte Fassung abgewiesen', false); }
catch (ConnectorException $e) { $check('unbekannte Fassung abgewiesen', str_contains($e->getMessage(), 'Fassung')); }

/* ================= Begrenzung ================= */
$zaehler = 0;
for ($i = 0; $i < CON_LIMIT_STUNDE + 5; $i++) {
    try { con_meldung_ablegen($token, $paket); $zaehler++; } catch (ConException $e) { break; }
}
$check('Begrenzung greift', $zaehler < CON_LIMIT_STUNDE + 5);

// Zurückgezogener Zugang wirft auch die wartenden Meldungen weg
con_fahrzeuge_setzen([]);
$check('Zugang weg, Meldungen weg', !is_dir($ordner) && con_fahrzeug($token) === null);

/* ================= Parkposition ================= */
$GLOBALS['updates'] = []; $GLOBALS['inserts'] = [];
$fz = ['id' => 4, 'qr_token' => $token, 'geo_lat' => null, 'geo_lng' => null,
       'geo_park_seit' => null, 'geo_park_gemeldet' => 0];
$jetzt = time();

$r = connector_position_anwenden($fz, ['lat' => 51.45, 'lng' => 7.01, 'zeit' => $jetzt], $jetzt);
$check('erste Meldung: kein Journaleintrag', !$r['park'] && $GLOBALS['inserts'] === []);
$check('Position gespeichert', $GLOBALS['updates'][0][1]['geo_quelle'] === 'qr');

// Zehn Minuten später ein paar Meter weiter – noch keine Parkposition
$fz = $r['fahrzeug'];
$r = connector_position_anwenden($fz, ['lat' => 51.45002, 'lng' => 7.01002, 'zeit' => $jetzt + 600], $jetzt + 600);
$check('nach 10 Minuten noch nichts', !$r['park'] && $GLOBALS['inserts'] === []);

// Nach gut einer Stunde am selben Fleck: genau ein Eintrag
$fz = $r['fahrzeug'];
$r = connector_position_anwenden($fz, ['lat' => 51.45003, 'lng' => 7.01001, 'zeit' => $jetzt + 3700], $jetzt + 3700);
$eintrag = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'vehicle_journal'));
$check('Parkposition im Journal', $r['park'] && count($eintrag) === 1
    && $eintrag[0][1]['titel'] === 'Parkposition');
$check('Melder genannt', str_contains($eintrag[0][1]['text'], 'QR-Code'));

// Weitere Meldungen am selben Ort: kein zweiter Eintrag
$fz = $r['fahrzeug'];
$GLOBALS['inserts'] = [];
$r = connector_position_anwenden($fz, ['lat' => 51.45003, 'lng' => 7.01001, 'zeit' => $jetzt + 7200], $jetzt + 7200);
$check('kein zweiter Eintrag', !$r['park'] && $GLOBALS['inserts'] === []);

// Fahrzeug bewegt sich: Zähler beginnt von vorn
$fz = $r['fahrzeug'];
$r = connector_position_anwenden($fz, ['lat' => 51.48, 'lng' => 7.05, 'zeit' => $jetzt + 7800], $jetzt + 7800);
$check('nach Ortswechsel neu gezählt', !$r['park'] && (int)$r['fahrzeug']['geo_park_gemeldet'] === 0);

// Unsinnige und uralte Meldungen ändern nichts
$GLOBALS['updates'] = [];
connector_position_anwenden($r['fahrzeug'], ['lat' => 0, 'lng' => 0, 'zeit' => $jetzt], $jetzt);
connector_position_anwenden($r['fahrzeug'], ['lat' => 51.4, 'lng' => 7.0, 'zeit' => $jetzt - 200000], $jetzt);
$check('Unsinn wird verworfen', $GLOBALS['updates'] === []);

echo "$ok bestanden, $fail fehlgeschlagen\n";
