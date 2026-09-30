<?php
declare(strict_types=1);
/*
 * Web Push: Base64url, Schlüsselgerüst, VAPID-Token und die Verschlüsselung
 * nach RFC 8291. Die Verschlüsselung wird gegengeprüft, indem der Test die
 * Rolle des Browsers übernimmt und die Nachricht wieder entschlüsselt.
 */
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'push_aktiv' => '1', 'push_kontakt' => 'ov@example.de'];
$GLOBALS['merker'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['abos'] = [];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    return str_contains($sql, 'FROM push_subscriptions') ? $GLOBALS['abos'] : [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'FROM settings')) { return $GLOBALS['merker'][$p[0] ?? ''] ?? $d; }
    if (str_contains($sql, 'FROM push_subscriptions')) { return $GLOBALS['vorhanden'] ?? $d; }
    return $d;
}
function db_exec(string $sql, array $p = []): int {
    if (str_contains($sql, 'INTO settings')) { $GLOBALS['merker'][$p[0]] = (string)$p[1]; }
    $GLOBALS['execs'][] = [$sql, $p];
    return 1;
}
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 50 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/webpush.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};
$ist = function (string $name, mixed $got, mixed $want) use (&$ok, &$fail) {
    if ($got === $want) { $ok++; return; }
    $fail++; echo "FEHL  $name\n      erwartet: " . var_export($want, true) . "\n      erhalten: " . var_export($got, true) . "\n";
};

/* ---------- Base64url ---------- */
$ist('ohne Füllzeichen', b64u_encode("\x01\x02\x03"), 'AQID');
$ist('andere Zeichen als Base64', b64u_encode("\xfb\xff"), '-_8');
$ist('hin und zurück', b64u_decode(b64u_encode(random_bytes(65))) !== '', true);
$roh = random_bytes(33);
$ist('beliebige Länge', b64u_decode(b64u_encode($roh)), $roh);
$ist('Füllzeichen erlaubt', b64u_decode('AQID=='), "\x01\x02\x03");
try { b64u_decode('###'); $check('Unsinn abgewiesen', false); }
catch (WebPushException $e) { $check('Unsinn abgewiesen', true); }

/* ---------- Schlüssel ---------- */
[$pem, $punkt] = p256_keypair();
$ist('öffentlicher Punkt 65 Byte', strlen($punkt), 65);
$ist('unkomprimiert', bin2hex($punkt[0]), '04');
$check('privater Schlüssel als PEM', str_contains($pem, 'BEGIN') && str_contains($pem, 'PRIVATE KEY'));
$check('PEM lesbar', openssl_pkey_get_private($pem) !== false);
$ist('gleicher Punkt aus dem PEM', p256_point(openssl_pkey_get_private($pem)), $punkt);

$pubPem = p256_public_pem($punkt);
$check('öffentliches PEM lesbar', openssl_pkey_get_public($pubPem) !== false);
$d = openssl_pkey_get_details(openssl_pkey_get_public($pubPem));
$ist('richtige Kurve', $d['ec']['curve_name'], 'prime256v1');
$ist('Punkt bleibt gleich', p256_point(openssl_pkey_get_public($pubPem)), $punkt);
foreach ([substr($punkt, 0, 64), "\x03" . substr($punkt, 1), ''] as $boese) {
    try { p256_public_pem($boese); $check('ungültiger Punkt abgewiesen', false); }
    catch (WebPushException $e) { $check('ungültiger Punkt abgewiesen', true); }
}

/* ---------- Signaturformat ---------- */
openssl_sign('test', $der, openssl_pkey_get_private($pem), OPENSSL_ALGO_SHA256);
$raw = ecdsa_der_to_raw($der);
$ist('64 Byte roh', strlen($raw), 64);
// Zurück nach DER und mit OpenSSL prüfen – nur dann stimmt die Umwandlung
$zuDer = static function (string $raw): string {
    $teil = static function (string $zahl): string {
        $zahl = ltrim($zahl, "\x00");
        if ($zahl === '' || ord($zahl[0]) > 0x7f) { $zahl = "\x00" . $zahl; }
        return "\x02" . chr(strlen($zahl)) . $zahl;
    };
    $inhalt = $teil(substr($raw, 0, 32)) . $teil(substr($raw, 32));
    return "\x30" . chr(strlen($inhalt)) . $inhalt;
};
$ist('Umwandlung ist umkehrbar', openssl_verify('test', $zuDer($raw), openssl_pkey_get_public($pubPem), OPENSSL_ALGO_SHA256), 1);
for ($i = 0; $i < 20; $i++) {   // kurze R/S mit führenden Nullen kommen selten vor
    openssl_sign('runde' . $i, $der2, openssl_pkey_get_private($pem), OPENSSL_ALGO_SHA256);
    if (strlen(ecdsa_der_to_raw($der2)) !== 64) {
        $check('auch bei kurzen Zahlen 64 Byte', false);
        break;
    }
}
$check('auch bei kurzen Zahlen 64 Byte', true);

/* ---------- VAPID ---------- */
$ist('Herkunft ohne Pfad', webpush_audience('https://fcm.googleapis.com/fcm/send/abc123'), 'https://fcm.googleapis.com');
$ist('Herkunft mit Port', webpush_audience('https://push.example.de:8443/x'), 'https://push.example.de:8443');
try { webpush_audience('kein-url'); $check('kaputte Adresse abgewiesen', false); }
catch (WebPushException $e) { $check('kaputte Adresse abgewiesen', true); }

$jwt = webpush_jwt('https://fcm.googleapis.com', 'mailto:ov@example.de', $pem, 1790000000);
[$k, $i, $s] = explode('.', $jwt);
$ist('Kopf', json_decode(b64u_decode($k), true), ['typ' => 'JWT', 'alg' => 'ES256']);
$inhalt = json_decode(b64u_decode($i), true);
$ist('Empfänger', $inhalt['aud'], 'https://fcm.googleapis.com');
$ist('Ablauf', $inhalt['exp'], 1790000000);
$ist('Kontakt', $inhalt['sub'], 'mailto:ov@example.de');
$ist('Signatur 64 Byte', strlen(b64u_decode($s)), 64);
$ist('Signatur stimmt', openssl_verify($k . '.' . $i, $zuDer(b64u_decode($s)), openssl_pkey_get_public($pubPem), OPENSSL_ALGO_SHA256), 1);
$check('keine Schrägstriche maskiert', !str_contains(b64u_decode($i), '\\/'));
$ist('Kontakt aus den Einstellungen', webpush_subject(), 'mailto:ov@example.de');

/* ---------- Verschlüsselung: der Test spielt den Browser ---------- */
[$browserPem, $browserPunkt] = p256_keypair();
$browserAuth = random_bytes(16);
$klartext = 'Neue Aufgabe: Bremsen prüfen';
$koerper = webpush_encrypt($klartext, $browserPunkt, $browserAuth);

$salt = substr($koerper, 0, 16);
$rs = unpack('N', substr($koerper, 16, 4))[1];
$idlen = ord($koerper[20]);
$serverPunkt = substr($koerper, 21, $idlen);
$chiffre = substr($koerper, 21 + $idlen);
$ist('Salz 16 Byte', strlen($salt), 16);
$ist('Datensatzgröße', $rs, 4096);
$ist('Schlüssellänge im Kopf', $idlen, 65);
$ist('Kopf ist 86 Byte', 21 + $idlen, 86);
$check('Klartext steht nicht drin', !str_contains($koerper, 'Bremsen'));

// Genau so rechnet der Browser
$geheim = openssl_pkey_derive(p256_public_pem($serverPunkt), openssl_pkey_get_private($browserPem));
$ikm = hash_hkdf('sha256', $geheim, 32, 'WebPush: info' . "\x00" . $browserPunkt . $serverPunkt, $browserAuth);
$cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
$nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);
$tag = substr($chiffre, -16);
$entschluesselt = openssl_decrypt(substr($chiffre, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
$ist('Browser kann entschlüsseln', $entschluesselt, $klartext . "\x02");

// Falscher Schlüssel darf nichts ergeben
$falsch = openssl_decrypt(substr($chiffre, 0, -16), 'aes-128-gcm', random_bytes(16), OPENSSL_RAW_DATA, $nonce, $tag);
$ist('mit falschem Schlüssel nichts', $falsch, false);

// Zwei Nachrichten sind nie gleich (eigenes Salz, eigener Schlüssel je Nachricht)
$zwei = webpush_encrypt($klartext, $browserPunkt, $browserAuth);
$check('jede Nachricht eigen verschlüsselt', $zwei !== $koerper);

// Feste Werte: gleiche Eingabe, gleiches Ergebnis
$festSalt = str_repeat("\x01", 16);
$a = webpush_encrypt('hallo', $browserPunkt, $browserAuth, ['salt' => $festSalt, 'privat_pem' => $pem]);
$b = webpush_encrypt('hallo', $browserPunkt, $browserAuth, ['salt' => $festSalt, 'privat_pem' => $pem]);
$ist('mit festen Werten wiederholbar', $a, $b);
$ist('Salz übernommen', substr($a, 0, 16), $festSalt);
$ist('eigener Punkt im Kopf', substr($a, 21, 65), $punkt);

foreach ([['kurz', $browserAuth], [$browserPunkt, 'kurz']] as [$p1, $p2]) {
    try { webpush_encrypt('x', $p1, $p2); $check('ungültige Geräteschlüssel abgewiesen', false); }
    catch (WebPushException $e) { $check('ungültige Geräteschlüssel abgewiesen', true); }
}

/* ---------- Abos ---------- */
$GLOBALS['merker'] = [];
$check('ohne Schlüssel nicht bereit', !webpush_enabled());
webpush_keys_ensure();
$check('Schlüssel erzeugt', webpush_public_key() !== '' && webpush_private_key() !== '');
$ist('öffentlicher Schlüssel 65 Byte', strlen(b64u_decode(webpush_public_key())), 65);
$vorher = webpush_public_key();
webpush_keys_ensure();
$ist('vorhandene Schlüssel bleiben', webpush_public_key(), $vorher);
$check('jetzt bereit', webpush_enabled());

$abo = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        'keys' => ['p256dh' => b64u_encode($browserPunkt), 'auth' => b64u_encode($browserAuth)]];
$GLOBALS['inserts'] = [];
$GLOBALS['vorhanden'] = null;
$id = push_subscribe(7, $abo, 'Firefox auf Android');
$ist('Abo gespeichert', $GLOBALS['inserts'][0][0], 'push_subscriptions');
$d = $GLOBALS['inserts'][0][1];
$ist('Person und Gerät', [$d['user_id'], $d['geraet']], [7, 'Firefox auf Android']);
$ist('Adresse gemerkt', $d['endpoint'], 'https://fcm.googleapis.com/fcm/send/abc');
$GLOBALS['vorhanden'] = 42;
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = [];
push_subscribe(7, $abo, 'Firefox auf Android');
$ist('gleiches Gerät wird aktualisiert', [$GLOBALS['inserts'], $GLOBALS['updates'][0][2]], [[], [42]]);
$GLOBALS['vorhanden'] = null;

foreach ([
    ['endpoint' => 'http://unsicher/x', 'keys' => ['p256dh' => b64u_encode($browserPunkt), 'auth' => b64u_encode($browserAuth)]],
    ['endpoint' => 'https://x/y', 'keys' => ['p256dh' => 'zu-kurz', 'auth' => b64u_encode($browserAuth)]],
    ['endpoint' => 'https://x/y', 'keys' => ['p256dh' => b64u_encode($browserPunkt), 'auth' => 'kurz']],
    ['keys' => []],
] as $nr => $boese) {
    try { push_subscribe(7, $boese); $check('schlechtes Abo abgewiesen (' . $nr . ')', false); }
    catch (WebPushException $e) { $check('schlechtes Abo abgewiesen (' . $nr . ')', true); }
}

echo "$ok bestanden, $fail fehlgeschlagen\n";
