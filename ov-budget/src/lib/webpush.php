<?php
declare(strict_types=1);

/**
 * Web Push: Benachrichtigungen direkt an den Browser, auch wenn die Seite
 * geschlossen ist.
 *
 * Umgesetzt sind die beiden Standards, die dafür nötig sind:
 *   RFC 8292 (VAPID)  – wir weisen uns beim Push-Dienst mit einem ES256-Token aus
 *   RFC 8291/8188     – der Inhalt wird für das Endgerät verschlüsselt (aes128gcm)
 *
 * Gebraucht wird nur OpenSSL; eine Fremdbibliothek gibt es hier nicht.
 * Reine Rechenschritte (Base64url, Signaturformat, Schlüsselgerüst) stehen
 * als eigene Funktionen, damit sie prüfbar sind.
 */

class WebPushException extends RuntimeException
{
}

/* ---------------- Base64url ---------------- */

function b64u_encode(string $daten): string
{
    return rtrim(strtr(base64_encode($daten), '+/', '-_'), '=');
}

function b64u_decode(string $text): string
{
    // Manche Browser schicken die Füllzeichen mit – erst abschneiden, dann neu auffüllen
    $text = rtrim(strtr(trim($text), '-_', '+/'), '=');
    $rest = strlen($text) % 4;
    if ($rest > 0) {
        $text .= str_repeat('=', 4 - $rest);
    }
    $roh = base64_decode($text, true);
    if ($roh === false) {
        throw new WebPushException('Ungültige Base64url-Zeichenkette.');
    }
    return $roh;
}

/* ---------------- Schlüssel ---------------- */

/**
 * Öffentlichen P-256-Punkt (65 Byte, unkomprimiert) in ein PEM verpacken.
 * Der feste Vorspann ist die DER-Hülle für "EC public key, prime256v1".
 * Reine Funktion.
 */
function p256_public_pem(string $punkt): string
{
    if (strlen($punkt) !== 65 || $punkt[0] !== "\x04") {
        throw new WebPushException('Der öffentliche Schlüssel hat nicht die erwarteten 65 Byte.');
    }
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $punkt;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Öffentlicher Punkt (65 Byte) aus einem OpenSSL-Schlüssel */
function p256_point(mixed $key): string
{
    $d = openssl_pkey_get_details($key);
    if (!$d || ($d['type'] ?? null) !== OPENSSL_KEYTYPE_EC) {
        throw new WebPushException('Kein EC-Schlüssel.');
    }
    return "\x04" . str_pad((string)$d['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                  . str_pad((string)$d['ec']['y'], 32, "\x00", STR_PAD_LEFT);
}

/** Neues Schlüsselpaar: [PEM privat, öffentlicher Punkt] */
function p256_keypair(): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$key) {
        throw new WebPushException('Schlüsselpaar konnte nicht erzeugt werden: ' . openssl_error_string());
    }
    openssl_pkey_export($key, $pem);
    return [(string)$pem, p256_point($key)];
}

/** VAPID-Schlüssel anlegen, falls noch keine da sind */
function webpush_keys_ensure(): void
{
    if (webpush_private_key() !== '') {
        return;
    }
    [$pem, $punkt] = p256_keypair();
    // Die Schlüssel gehören nicht in die Einstellungsmaske – sie werden nie von Hand gepflegt
    state_save('push_vapid_private', $pem);
    state_save('push_vapid_public', b64u_encode($punkt));
}

/** Öffentlicher VAPID-Schlüssel für den Browser (Base64url) */
function webpush_public_key(): string
{
    return trim(state_get('push_vapid_public', ''));
}

function webpush_private_key(): string
{
    return trim(state_get('push_vapid_private', ''));
}

function webpush_enabled(): bool
{
    return setting_bool('push_aktiv', false) && webpush_public_key() !== '';
}

/* ---------------- VAPID ---------------- */

/**
 * OpenSSL liefert die Signatur als DER-Folge; JWT braucht R und S roh
 * hintereinander (je 32 Byte). Reine Funktion.
 */
function ecdsa_der_to_raw(string $der): string
{
    $pos = 0;
    $lies = static function () use ($der, &$pos): string {
        if (($der[$pos] ?? '') !== "\x02") {
            throw new WebPushException('Unerwartetes Signaturformat.');
        }
        $pos++;
        $len = ord($der[$pos] ?? "\x00");
        $pos++;
        $zahl = substr($der, $pos, $len);
        $pos += $len;
        return ltrim($zahl, "\x00");
    };
    if (($der[0] ?? '') !== "\x30") {
        throw new WebPushException('Unerwartetes Signaturformat.');
    }
    $pos = 2;
    if (ord($der[1]) > 0x80) {          // lange Längenangabe
        $pos = 2 + (ord($der[1]) & 0x7f);
    }
    $r = $lies();
    $s = $lies();
    return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
}

/** Herkunft (scheme://host) einer Adresse. Reine Funktion. */
function webpush_audience(string $endpoint): string
{
    $t = parse_url($endpoint);
    if (!$t || empty($t['scheme']) || empty($t['host'])) {
        throw new WebPushException('Ungültige Push-Adresse.');
    }
    return $t['scheme'] . '://' . $t['host'] . (empty($t['port']) ? '' : ':' . $t['port']);
}

/** Signiertes VAPID-Token für diesen Push-Dienst */
function webpush_jwt(string $audience, string $subject, string $privatPem, int $ablauf): string
{
    $kopf = b64u_encode((string)json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $inhalt = b64u_encode((string)json_encode([
        'aud' => $audience,
        'exp' => $ablauf,
        'sub' => $subject,
    ], JSON_UNESCAPED_SLASHES));

    $key = openssl_pkey_get_private($privatPem);
    if (!$key) {
        throw new WebPushException('Der VAPID-Schlüssel lässt sich nicht lesen.');
    }
    if (!openssl_sign($kopf . '.' . $inhalt, $der, $key, OPENSSL_ALGO_SHA256)) {
        throw new WebPushException('Signatur fehlgeschlagen: ' . openssl_error_string());
    }
    return $kopf . '.' . $inhalt . '.' . b64u_encode(ecdsa_der_to_raw($der));
}

/** Kennung für das "sub"-Feld: eine Adresse, unter der man uns erreicht */
function webpush_subject(): string
{
    $kontakt = trim((string)setting('push_kontakt', ''));
    if ($kontakt === '') {
        return 'mailto:ov-budget@localhost';
    }
    return str_starts_with($kontakt, 'http') || str_starts_with($kontakt, 'mailto:')
        ? $kontakt
        : 'mailto:' . $kontakt;
}

/* ---------------- Verschlüsselung (RFC 8291) ---------------- */

const WEBPUSH_RS = 4096;

/**
 * Inhalt für ein Gerät verschlüsseln.
 * $p256dh und $auth sind die Werte aus dem Browser-Abo (roh, nicht Base64url).
 * $test erlaubt feste Zufallswerte für Prüfungen.
 * Rückgabe: fertiger Nachrichtenkörper (aes128gcm).
 */
function webpush_encrypt(string $klartext, string $p256dh, string $auth, array $test = []): string
{
    if (strlen($p256dh) !== 65) {
        throw new WebPushException('Der Geräteschlüssel (p256dh) ist ungültig.');
    }
    if (strlen($auth) !== 16) {
        throw new WebPushException('Das Geräte-Geheimnis (auth) ist ungültig.');
    }

    $salt = $test['salt'] ?? random_bytes(16);
    if (isset($test['privat_pem'])) {
        $eigenPem = (string)$test['privat_pem'];
        $eigenPunkt = p256_point(openssl_pkey_get_private($eigenPem));
    } else {
        [$eigenPem, $eigenPunkt] = p256_keypair();
    }

    // ECDH auf P-256 liefert genau 32 Byte; eine Längenangabe ist seit PHP 8.5 veraltet
    $geheim = openssl_pkey_derive(p256_public_pem($p256dh), openssl_pkey_get_private($eigenPem));
    if ($geheim === false) {
        throw new WebPushException('Schlüsselaustausch fehlgeschlagen: ' . openssl_error_string());
    }

    // RFC 8291: erst ein gemeinsamer Ausgangswert, daraus Schlüssel und Nonce
    $info = 'WebPush: info' . "\x00" . $p256dh . $eigenPunkt;
    $ikm = hash_hkdf('sha256', $geheim, 32, $info, $auth);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

    // 0x02 schließt den Klartext ab (letzter Datensatz)
    $daten = $klartext . "\x02";
    $tag = '';
    $chiffre = openssl_encrypt($daten, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($chiffre === false) {
        throw new WebPushException('Verschlüsselung fehlgeschlagen: ' . openssl_error_string());
    }

    return $salt . pack('N', WEBPUSH_RS) . chr(strlen($eigenPunkt)) . $eigenPunkt . $chiffre . $tag;
}

/* ---------------- Versand ---------------- */

/**
 * Eine Nachricht an ein Abo schicken.
 * Rückgabe: HTTP-Code des Push-Dienstes (201 = angenommen).
 * 404 und 410 heißen: Das Abo gibt es nicht mehr.
 */
function webpush_send(array $abo, array $nutzlast, int $ttl = 86400): int
{
    $endpoint = (string)$abo['endpoint'];
    $koerper = webpush_encrypt(
        (string)json_encode($nutzlast, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        b64u_decode((string)$abo['p256dh']),
        b64u_decode((string)$abo['auth'])
    );

    $jwt = webpush_jwt(webpush_audience($endpoint), webpush_subject(),
        webpush_private_key(), time() + 12 * 3600);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $koerper,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: vapid t=' . $jwt . ',k=' . webpush_public_key(),
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'TTL: ' . $ttl,
        ],
    ]);
    $antwort = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $fehler = curl_error($ch);
    curl_close($ch);

    if ($antwort === false) {
        throw new WebPushException('Push-Dienst nicht erreichbar: ' . ($fehler ?: 'Zeitüberschreitung'));
    }
    if ($code >= 400 && $code !== 404 && $code !== 410) {
        throw new WebPushException('Push-Dienst antwortete mit HTTP ' . $code . ': '
            . mb_substr((string)$antwort, 0, 200));
    }
    return $code;
}

/* ---------------- Abos ---------------- */

/** Abos einer Person */
function push_subscriptions(int $userId): array
{
    return db_all('SELECT * FROM push_subscriptions WHERE user_id = ? ORDER BY id', [$userId]);
}

function push_subscription_count(int $userId): int
{
    return (int)db_val('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?', [$userId], 0);
}

/** Abo speichern (gleiche Adresse = gleiches Gerät) */
function push_subscribe(int $userId, array $abo, string $geraet = ''): int
{
    $endpoint = trim((string)($abo['endpoint'] ?? ''));
    $p256dh = trim((string)($abo['keys']['p256dh'] ?? ''));
    $auth = trim((string)($abo['keys']['auth'] ?? ''));
    if ($endpoint === '' || !str_starts_with($endpoint, 'https://') || $p256dh === '' || $auth === '') {
        throw new WebPushException('Das Abo aus dem Browser ist unvollständig.');
    }
    if (strlen(b64u_decode($p256dh)) !== 65 || strlen(b64u_decode($auth)) !== 16) {
        throw new WebPushException('Die Schlüssel des Abos haben die falsche Länge.');
    }

    $vorhanden = db_val('SELECT id FROM push_subscriptions WHERE endpoint = ?', [$endpoint]);
    $daten = [
        'user_id'  => $userId,
        'endpoint' => mb_substr($endpoint, 0, 500),
        'p256dh'   => $p256dh,
        'auth'     => $auth,
        'geraet'   => mb_substr($geraet, 0, 150),
    ];
    if ($vorhanden) {
        db_update('push_subscriptions', $daten, 'id = ?', [(int)$vorhanden]);
        return (int)$vorhanden;
    }
    return db_insert('push_subscriptions', $daten);
}

function push_unsubscribe(string $endpoint, ?int $userId = null): void
{
    if ($userId === null) {
        db_exec('DELETE FROM push_subscriptions WHERE endpoint = ?', [$endpoint]);
        return;
    }
    db_exec('DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?', [$endpoint, $userId]);
}

/**
 * Eine Benachrichtigung an alle Geräte einer Person schicken.
 * Abgelaufene Abos werden dabei entfernt.
 * Rückgabe: ['gesendet' => int, 'entfernt' => int, 'fehler' => string]
 */
function push_to_user(int $userId, string $titel, string $text, string $url = ''): array
{
    $res = ['gesendet' => 0, 'entfernt' => 0, 'fehler' => ''];
    foreach (push_subscriptions($userId) as $abo) {
        try {
            $code = webpush_send($abo, ['titel' => $titel, 'text' => $text, 'url' => $url]);
            if ($code === 404 || $code === 410) {
                push_unsubscribe((string)$abo['endpoint']);
                $res['entfernt']++;
                continue;
            }
            db_exec('UPDATE push_subscriptions SET last_ok = NOW() WHERE id = ?', [(int)$abo['id']]);
            $res['gesendet']++;
        } catch (Throwable $ex) {
            $res['fehler'] = $ex->getMessage();
        }
    }
    return $res;
}
