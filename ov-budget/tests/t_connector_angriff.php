<?php
declare(strict_types=1);
/*
 * Sicherheitstest: Der Test spielt den Angreifer.
 *
 * Er versucht, ohne Kopplung an Daten zu kommen, Anfragen zu fälschen,
 * mitgeschnittene Anfragen erneut einzuspielen, aus Pfaden auszubrechen,
 * fremde Meldungen zu lesen und den Server vollzuschreiben.
 * Jeder Versuch muss scheitern.
 */
$tmp = sys_get_temp_dir() . '/ovb-angriff-test';
@mkdir($tmp, 0777, true);
$leeren = static function (string $ordner) use (&$leeren): void {
    foreach (glob($ordner . '/*') ?: [] as $f) {
        if (is_dir($f)) { $leeren($f); @rmdir($f); } else { @unlink($f); }
    }
};
$leeren($tmp);

$quelle = (string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/connector.php');
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
@mkdir(__DIR__ . '/alt', 0777, true);
file_put_contents(__DIR__ . '/alt/connector_angriff.php', $quelle);
require __DIR__ . '/alt/connector_angriff.php';

// OV-Multitool-Seite nur für die Kryptofunktionen
$GLOBALS['settings'] = [];
function db_all(string $sql, array $p = []): array { return []; }
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/webpush.php';

$ok = 0; $fail = 0; $offen = [];
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "OFFEN: $name\n"; }
};

/* ============================================================= */
/* 1. Ohne Kopplung geht nichts                                   */
/* ============================================================= */
try {
    con_pruefe_anfrage('{"zweck":"abholen","ts":' . time() . ',"nonce":"aabbccddeeff0011"}', 'x', 'abholen');
    $check('ungekoppelt: keine Anfragen', false);
} catch (ConException $e) {
    $check('ungekoppelt: keine Anfragen', str_contains($e->getMessage(), 'gekoppelt'));
}
$check('ungekoppelt: nichts zu signieren', con_signiere('test') === '');

/* ============================================================= */
/* 2. Kopplung raten                                              */
/* ============================================================= */
$echterCode = con_kopplungscode();
[$boesePem, $boesePunkt] = con_keypair();
$versuche = 0;
$gesperrt = false;
for ($i = 0; $i < 15; $i++) {
    try {
        con_koppeln(sprintf('AAAAAA-BBBBBB-%06d', $i), con_b64u($boesePunkt));
        break;
    } catch (ConException $e) {
        $versuche++;
        if (str_contains($e->getMessage(), 'Fehlversuche')) { $gesperrt = true; break; }
    }
}
$check('Kopplung: Raten wird gesperrt', $gesperrt && $versuche <= 11);
$check('Kopplung: nach der Sperre auch mit richtigem Code zu', (static function () use ($echterCode, $boesePunkt): bool {
    try { con_koppeln($echterCode, con_b64u($boesePunkt)); return false; }
    catch (ConException $e) { return str_contains($e->getMessage(), 'Fehlversuche'); }
})());
$check('Kopplung: noch immer nicht gekoppelt', !con_gekoppelt());

// Sperre abräumen, damit der eigentliche Test weitergeht
con_schreiben('limit.json', []);

$check('Kopplungscode ist lang genug', strlen(str_replace('-', '', $echterCode)) === 18);
$check('Kopplungscode liegt nicht im Web', is_file($tmp . '/daten/kopplungscode.txt')
    && !is_file($tmp . '/../kopplungscode.txt'));

[$ovPem, $ovPunkt] = con_keypair();
con_koppeln($echterCode, con_b64u($ovPunkt));
$check('jetzt gekoppelt', con_gekoppelt());

try { con_koppeln(con_kopplungscode(), con_b64u($boesePunkt)); $check('kein zweites Koppeln', false); }
catch (ConException $e) { $check('kein zweites Koppeln', true); }

/* ============================================================= */
/* 3. Anfragen fälschen                                           */
/* ============================================================= */
$signiere = static function (array $daten, string $pem): array {
    $koerper = (string)json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    openssl_sign($koerper, $sig, openssl_pkey_get_private($pem), OPENSSL_ALGO_SHA256);
    return [$koerper, con_b64u($sig)];
};
$anfrage = static fn(string $zweck, array $extra = []) => array_merge([
    'zweck' => $zweck, 'ts' => time(), 'nonce' => bin2hex(random_bytes(12))], $extra);

// fremder Schlüssel
[$k, $s] = $signiere($anfrage('abholen'), $boesePem);
try { con_pruefe_anfrage($k, $s, 'abholen'); $check('fremder Schlüssel abgewiesen', false); }
catch (ConException $e) { $check('fremder Schlüssel abgewiesen', true); }

// gar keine Signatur
[$k, $s] = $signiere($anfrage('abholen'), $ovPem);
try { con_pruefe_anfrage($k, '', 'abholen'); $check('ohne Signatur abgewiesen', false); }
catch (ConException $e) { $check('ohne Signatur abgewiesen', true); }

// Signatur aus einer anderen Anfrage
[$k1, $s1] = $signiere($anfrage('abholen'), $ovPem);
[$k2, $s2] = $signiere($anfrage('fahrzeuge'), $ovPem);
try { con_pruefe_anfrage($k1, $s2, 'abholen'); $check('vertauschte Signatur abgewiesen', false); }
catch (ConException $e) { $check('vertauschte Signatur abgewiesen', true); }

// Zweck umbiegen (mitgeschnittene Anfrage an anderem Endpunkt)
[$k, $s] = $signiere($anfrage('abholen'), $ovPem);
try { con_pruefe_anfrage($k, $s, 'rueckmeldungen'); $check('Zweck lässt sich nicht umbiegen', false); }
catch (ConException $e) { $check('Zweck lässt sich nicht umbiegen', str_contains($e->getMessage(), 'Zweck')); }

// Wiedereinspielen
[$k, $s] = $signiere($anfrage('veranstaltungen'), $ovPem);
con_pruefe_anfrage($k, $s, 'veranstaltungen');
try { con_pruefe_anfrage($k, $s, 'veranstaltungen'); $check('Wiedereinspielen abgewiesen', false); }
catch (ConException $e) { $check('Wiedereinspielen abgewiesen', str_contains($e->getMessage(), 'schon')); }

// zu alt und zu neu
foreach (['alt' => time() - 3600, 'aus der Zukunft' => time() + 3600] as $was => $ts) {
    [$k, $s] = $signiere(['zweck' => 'abholen', 'ts' => $ts, 'nonce' => bin2hex(random_bytes(12))], $ovPem);
    try { con_pruefe_anfrage($k, $s, 'abholen'); $check("Anfrage $was abgewiesen", false); }
    catch (ConException $e) { $check("Anfrage $was abgewiesen", true); }
}

// Einmalkennung ohne Format
foreach (['', 'kurz', '../../etc', str_repeat('a', 200)] as $nonce) {
    [$k, $s] = $signiere(['zweck' => 'abholen', 'ts' => time(), 'nonce' => $nonce], $ovPem);
    try { con_pruefe_anfrage($k, $s, 'abholen'); $check('krumme Einmalkennung abgewiesen', false); break; }
    catch (ConException $e) { /* erwartet */ }
}
$check('krumme Einmalkennung abgewiesen', true);

// Rumpf nachträglich ändern
[$k, $s] = $signiere($anfrage('fahrzeuge', ['fahrzeuge' => []]), $ovPem);
try { con_pruefe_anfrage(str_replace('[]', '[{"kennung":"' . str_repeat('a', 64) . '"}]', $k), $s, 'fahrzeuge');
      $check('veränderter Rumpf abgewiesen', false); }
catch (ConException $e) { $check('veränderter Rumpf abgewiesen', str_contains($e->getMessage(), 'Signatur')); }

/* ============================================================= */
/* 4. Pfade und Zugänge                                           */
/* ============================================================= */
$token = b64u_encode(random_bytes(24));
con_fahrzeuge_setzen([['kennung' => con_kennung($token)]]);

$boese = ['../../etc/passwd', '..\\..\\windows', 'a/b', "null\x00byte", '', '.', '..',
          str_repeat('A', 500), 'A', '%2e%2e%2f'];
$durch = 0;
foreach ($boese as $versuch) {
    if (con_fahrzeug($versuch) !== null) { $durch++; }
    try { con_meldung_ablegen($versuch, 'x'); $durch++; } catch (ConException $e) { /* gut */ }
    if (con_einladung($versuch) !== null) { $durch++; }
}
$check('Pfadwechsel über Zugang oder Code unmöglich', $durch === 0);
$check('nichts außerhalb des Datenordners entstanden',
    !is_dir($tmp . '/daten/meldungen/..') || count(glob($tmp . '/daten/meldungen/*') ?: []) <= 1);

// Kennung ist nicht zurückzurechnen
$check('aus der Ablage kein Zugang zu bauen',
    !str_contains((string)file_get_contents($tmp . '/daten/fahrzeuge.json'), $token));

/* ============================================================= */
/* 5. Meldungen lesen oder fälschen                               */
/* ============================================================= */
$handy = static function (array $inhalt, string $punkt): string {
    [$pem, $eigen] = con_keypair();
    $gemeinsam = openssl_pkey_derive(con_pubkey_pem($punkt), openssl_pkey_get_private($pem));
    $salz = random_bytes(16);
    $abgeleitet = hash_hkdf('sha256', $gemeinsam, 44, 'OV-Budget Standort v1', $salz);
    $tag = '';
    $geheim = openssl_encrypt((string)json_encode($inhalt), 'aes-256-gcm', substr($abgeleitet, 0, 32),
        OPENSSL_RAW_DATA, substr($abgeleitet, 32, 12), $tag);
    return con_b64u("\x01" . $eigen . $salz . $geheim . $tag);
};
$paket = $handy(['lat' => 51.45, 'lng' => 7.01, 'melder' => 'Anna', 'zeit' => time()], $ovPunkt);
con_meldung_ablegen($token, $paket);

$aufPlatte = '';
foreach (glob($tmp . '/daten/meldungen/*/*.json') ?: [] as $f) { $aufPlatte .= (string)file_get_contents($f); }
$check('Koordinaten stehen nirgends im Klartext',
    !str_contains($aufPlatte, '51.45') && !str_contains($aufPlatte, 'Anna'));
$check('der Connector hat keinen Schlüssel zum Lesen',
    !str_contains((string)file_get_contents($tmp . '/daten/kopplung.json'), $ovPem));

// Wer den Serverschlüssel stiehlt, kann trotzdem nicht lesen
$k = con_kopplung();
$gestohlen = (string)$k['eigener_pem'];
$geholt = con_meldungen_abholen();
$gelesen = false;
try {
    $roh = con_unb64u($geholt[0]['daten']);
    $gemeinsam = openssl_pkey_derive(con_pubkey_pem(substr($roh, 1, 65)), openssl_pkey_get_private($gestohlen));
    $abgeleitet = hash_hkdf('sha256', (string)$gemeinsam, 44, 'OV-Budget Standort v1', substr($roh, 66, 16));
    $klar = openssl_decrypt(substr($roh, 82, -16), 'aes-256-gcm', substr($abgeleitet, 0, 32),
        OPENSSL_RAW_DATA, substr($abgeleitet, 32, 12), substr($roh, -16));
    $gelesen = is_string($klar) && str_contains($klar, '51.45');
} catch (Throwable $e) { /* erwartet */ }
$check('gestohlener Serverschlüssel liest keine Meldung', !$gelesen);

// Geheimtext verbiegen: AES-GCM muss es merken
$verbogen = con_unb64u($geholt[0]['daten']);
$verbogen[100] = chr(ord($verbogen[100]) ^ 0x01);
$gemerkt = false;
$gemeinsam = openssl_pkey_derive(con_pubkey_pem(substr($verbogen, 1, 65)), openssl_pkey_get_private($ovPem));
$abgeleitet = hash_hkdf('sha256', (string)$gemeinsam, 44, 'OV-Budget Standort v1', substr($verbogen, 66, 16));
$klar = @openssl_decrypt(substr($verbogen, 82, -16), 'aes-256-gcm', substr($abgeleitet, 0, 32),
    OPENSSL_RAW_DATA, substr($abgeleitet, 32, 12), substr($verbogen, -16));
$check('veränderter Geheimtext fällt auf', $klar === false);

/* ============================================================= */
/* 6. Vollschreiben und Überlasten                                */
/* ============================================================= */
try { con_meldung_ablegen($token, str_repeat('x', 100000)); $check('große Meldung abgewiesen', false); }
catch (ConException $e) { $check('große Meldung abgewiesen', true); }

$kennung = con_kennung($token);
$angenommen = 0;
for ($i = 0; $i < CON_LIMIT_STUNDE + 30; $i++) {
    try { con_meldung_ablegen($token, $paket); $angenommen++; } catch (ConException $e) { break; }
}
$check('Meldungen je Fahrzeug begrenzt', $angenommen <= CON_LIMIT_STUNDE);
$check('nie mehr als CON_MAX_OFFEN wartende',
    count(glob($tmp . '/daten/meldungen/' . $kennung . '/*.json') ?: []) <= CON_MAX_OFFEN);

/* ============================================================= */
/* 7. Einladungen                                                 */
/* ============================================================= */
$ev = hash('sha256', 'ovb-veranstaltung:1');
con_veranstaltungen_setzen([[
    'kennung' => $ev, 'titel' => 'Feier', 'beginn' => '2026-12-05 18:00:00', 'ort' => 'Heim',
    'bis' => '', 'status' => 'geplant', 'begleiter_max' => 2, 'kommentare' => 1, 'vertretung' => 1,
]], [con_code_kennung('AB23CD') => $ev]);

try { con_rueckmeldung_ablegen('ZZ99ZZ', 'x'); $check('geratener Code abgewiesen', false); }
catch (ConException $e) { $check('geratener Code abgewiesen', true); }

$antwort = $handy(['status' => 'zusage', 'begleiter' => 1, 'kommentar' => 'geheim'], $ovPunkt);
$angenommen = 0;
for ($i = 0; $i < CON_LIMIT_EINLADUNG + 10; $i++) {
    try { con_rueckmeldung_ablegen('AB23CD', $antwort); $angenommen++; } catch (ConException $e) { break; }
}
$check('Rückmeldungen je Einladung begrenzt', $angenommen <= CON_LIMIT_EINLADUNG);

$aufPlatte = '';
foreach (glob($tmp . '/daten/rueckmeldungen/*/*.json') ?: [] as $f) { $aufPlatte .= (string)file_get_contents($f); }
$check('Rückmeldung steht nicht im Klartext',
    !str_contains($aufPlatte, 'zusage') && !str_contains($aufPlatte, 'geheim'));

// Eine fremde Veranstaltung lässt sich nicht einschmuggeln
$res = con_veranstaltungen_setzen(
    [['kennung' => $ev, 'titel' => 'Feier', 'beginn' => '2026-12-05 18:00:00']],
    ['nicht-hex' => $ev, con_code_kennung('XX22XX') => 'unbekannte-veranstaltung']
);
$check('Einladung ohne Veranstaltung fällt raus', $res['einladungen'] === 0);
$check('zurückgezogener Code gilt nicht mehr', con_einladung('AB23CD') === null);

/* ============================================================= */
/* 7b. Bestandsmeldungen                                          */
/* ============================================================= */
$funkToken = b64u_encode(random_bytes(24));
con_bestand_setzen([
    ['kennung' => con_kennung($funkToken), 'art' => 'gruppe', 'anzahl' => 8],
    ['kennung' => 'unsinn', 'art' => 'geraet', 'anzahl' => 1],
]);
$check('nur gültige Zugänge im Bestand', con_bestand($funkToken) !== null
    && con_bestand('../boese') === null && con_bestand('ZZZZZZZZZZZZZZZZZZZZZZ') === null);
$check('Anzahl der Gruppe bekannt', (con_bestand($funkToken)['anzahl'] ?? 0) === 8);
$check('kein Zugang im Klartext',
    !str_contains((string)file_get_contents($tmp . '/daten/bestand.json'), $funkToken));
$check('keine Bezeichnung auf dem Server',
    !str_contains((string)file_get_contents($tmp . '/daten/bestand.json'), 'Koffer'));

$bestandPaket = $handy(['da' => true, 'anzahl' => 8, 'melder' => 'Anna'], $ovPunkt);
con_bestandsmeldung_ablegen($funkToken, $bestandPaket);
$aufPlatte = '';
foreach (glob($tmp . '/daten/bestandsmeldungen/*/*.json') ?: [] as $f) {
    $aufPlatte .= (string)file_get_contents($f);
}
$check('Bestandsmeldung nur als Geheimtext',
    !str_contains($aufPlatte, 'Anna') && !str_contains($aufPlatte, '"da"'));

try { con_bestandsmeldung_ablegen('ZZZZZZZZZZZZZZZZZZZZZZ', $bestandPaket);
      $check('unbekannter Bestandszugang abgewiesen', false); }
catch (ConException $e) { $check('unbekannter Bestandszugang abgewiesen', true); }

$angenommen = 0;
for ($i = 0; $i < CON_LIMIT_BESTAND + 10; $i++) {
    try { con_bestandsmeldung_ablegen($funkToken, $bestandPaket); $angenommen++; }
    catch (ConException $e) { break; }
}
$check('Bestandsmeldungen begrenzt', $angenommen <= CON_LIMIT_BESTAND);

$geholt = con_bestandsmeldungen_abholen();
$check('Bestandsmeldungen abgeholt und gelöscht', count($geholt) > 0
    && count(glob($tmp . '/daten/bestandsmeldungen/*/*.json') ?: []) === 0);
$check('Meldung trägt die Prüfsumme', ($geholt[0]['zugang'] ?? '') === con_kennung($funkToken));
$roh = con_unb64u($geholt[0]['daten']);
$gemeinsam = openssl_pkey_derive(con_pubkey_pem(substr($roh, 1, 65)), openssl_pkey_get_private($ovPem));
$abgeleitet = hash_hkdf('sha256', (string)$gemeinsam, 44, 'OV-Budget Standort v1', substr($roh, 66, 16));
$klar = json_decode((string)openssl_decrypt(substr($roh, 82, -16), 'aes-256-gcm',
    substr($abgeleitet, 0, 32), OPENSSL_RAW_DATA, substr($abgeleitet, 32, 12), substr($roh, -16)), true);
$check('OV-Multitool liest die Bestandsmeldung', ($klar['anzahl'] ?? 0) === 8 && $klar['melder'] === 'Anna');

con_bestand_setzen([]);
$check('zurückgezogen: Zugang und Meldungen weg', con_bestand($funkToken) === null
    && !is_dir($tmp . '/daten/bestandsmeldungen/' . con_kennung($funkToken)));

/* ============================================================= */
/* 8. Was in der Ablage steht                                     */
/* ============================================================= */
$alles = '';
foreach (glob($tmp . '/daten/*.json') ?: [] as $f) { $alles .= (string)file_get_contents($f); }
$check('kein Einladungscode im Klartext', !str_contains($alles, 'AB23CD'));
$check('kein Zugang im Klartext', !str_contains($alles, $token));
$check('keine Personennamen', !str_contains($alles, 'Anna'));

$protokoll = (string)@file_get_contents($tmp . '/daten/protokoll.log');
$check('Protokoll ohne Codes und Zugänge',
    !str_contains($protokoll, 'AB23CD') && !str_contains($protokoll, $token));

/* ============================================================= */
/* 9. Codes durchprobieren                                        */
/* ============================================================= */
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
con_schreiben('limit.json', []);
$durchprobiert = 0;
for ($i = 0; $i < CON_LIMIT_FEHLGRIFF + 20; $i++) {
    if (!con_fehlgriff()) { break; }
    $durchprobiert++;
}
$check('Fehlgriffe je Anschluss begrenzt', $durchprobiert === CON_LIMIT_FEHLGRIFF);
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
$check('anderer Anschluss ist davon unberührt', con_fehlgriff());

/* ============================================================= */
/* 10. Protokoll lässt sich nicht fälschen                        */
/* ============================================================= */
con_notiz("fehler", "erste Zeile
2099-01-01	kopplung.ok	gefaelscht");
$protokoll = (string)file_get_contents($tmp . '/daten/protokoll.log');
$check('kein Zeilenumbruch ins Protokoll', !str_contains($protokoll, "
2099-01-01"));

/* ============================================================= */
/* 11. Uraltes wird weggeworfen                                   */
/* ============================================================= */
$ordner = con_dir('meldungen/' . str_repeat('c', 64));
file_put_contents($ordner . '/alt.json', '{"ts":1,"daten":"x"}');
touch($ordner . '/alt.json', time() - (CON_AUFHEBEN_TAGE + 1) * 86400);
file_put_contents($ordner . '/neu.json', '{"ts":1,"daten":"x"}');
con_alte_wegwerfen('meldungen');
$check('Uraltes verschwindet', !is_file($ordner . '/alt.json') && is_file($ordner . '/neu.json'));

/* ============================================================= */
/* 12. Schutz des Datenordners                                    */
/* ============================================================= */
@unlink($tmp . '/daten/.htaccess');
con_dir();
$check('Schutzdatei wird nachgelegt', is_file($tmp . '/daten/.htaccess')
    && is_file($tmp . '/daten/index.html'));

/* ============================================================= */
/* 13. Wie viel verrät der Zustand?                               */
/* ============================================================= */
$st = con_status();
$check('Status ohne Schlüssel', !isset($st['eigener_pem'])
    && !str_contains((string)json_encode($st), substr($ovPem, 30, 40)));

echo "$ok bestanden, $fail fehlgeschlagen\n";
