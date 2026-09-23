<?php
declare(strict_types=1);

/**
 * Anbindung an den OV-Budget-Connector.
 *
 * Der Connector steht auf einem öffentlich erreichbaren Webserver und nimmt
 * Standortmeldungen aus den Fahrzeugen entgegen (QR-Code im Fahrzeug).
 * Diese Anwendung holt sie regelmäßig ab; der Connector selbst kann sie nicht
 * lesen, weil das Handy sie für unseren öffentlichen Schlüssel verschlüsselt.
 *
 * Verbindung:
 *   Kopplung   – einmalig mit einem Code, danach kennen beide Seiten den
 *                öffentlichen Schlüssel der anderen
 *   Anfragen   – von uns signiert (ECDSA P-256), Antworten vom Connector auch
 *
 * Format einer Meldung (so verpackt der Browser, siehe melden.js):
 *   Byte 0      Fassung (1)
 *   Byte 1..65  flüchtiger öffentlicher Schlüssel des Handys
 *   Byte 66..81 Salz
 *   ab Byte 82  Geheimtext samt Prüfsumme (AES-256-GCM)
 */

class ConnectorException extends RuntimeException
{
}

const CONNECTOR_INFO = 'OV-Budget Standort v1';

function connector_url(): string
{
    return rtrim(trim((string)setting('connector_url', '')), '/');
}

function connector_enabled(): bool
{
    return setting_bool('connector_aktiv', false) && connector_url() !== '' && connector_gekoppelt();
}

function connector_gekoppelt(): bool
{
    return state_get('connector_server_pub', '') !== '' && state_get('connector_pem', '') !== '';
}

/** Eigenes Schlüsselpaar, einmalig erzeugt */
function connector_keys_ensure(): void
{
    if (state_get('connector_pem', '') !== '') {
        return;
    }
    [$pem, $punkt] = p256_keypair();
    state_save('connector_pem', $pem);
    state_save('connector_pub', b64u_encode($punkt));
}

function connector_public_key(): string
{
    connector_keys_ensure();
    return state_get('connector_pub', '');
}

/* ==================================================================== */
/* Kopplung und Anfragen                                                 */
/* ==================================================================== */

/** Einmalige Kopplung mit dem Connector */
function connector_pair(string $url, string $code): array
{
    $url = rtrim(trim($url), '/');
    if ($url === '' || !preg_match('#^https://#i', $url)) {
        throw new ConnectorException('Bitte die Adresse des Connectors angeben – sie muss mit https:// beginnen.');
    }
    connector_keys_ensure();

    $antwort = connector_http($url, 'koppeln', [
        'code'   => trim($code),
        'pubkey' => connector_public_key(),
    ], null);

    $serverPub = trim((string)($antwort['pubkey'] ?? ''));
    if ($serverPub === '' || strlen(b64u_decode($serverPub)) !== 65) {
        throw new ConnectorException('Der Connector hat keinen brauchbaren Schlüssel zurückgegeben.');
    }
    state_save('connector_server_pub', $serverPub);
    setting_save('connector_url', $url);
    audit('connector.gekoppelt', 'connector', null, $url);
    return $antwort;
}

/** Kopplung hier vergessen (der Connector braucht dann auch eine neue) */
function connector_unpair(): void
{
    state_save('connector_server_pub', '');
    audit('connector.getrennt', 'connector');
}

/**
 * Signierte Anfrage an den Connector. $signieren = false nur bei der Kopplung,
 * dort kennt die Gegenseite unseren Schlüssel noch nicht.
 */
function connector_http(string $url, string $pfad, array $daten, ?string $pem, int $timeout = 15): array
{
    $koerper = (string)json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $header = ['Content-Type: application/json', 'Accept: application/json'];

    if ($pem !== null) {
        if (!openssl_sign($koerper, $sig, openssl_pkey_get_private($pem), OPENSSL_ALGO_SHA256)) {
            throw new ConnectorException('Die Anfrage ließ sich nicht signieren.');
        }
        $header[] = 'X-Signatur: ' . b64u_encode($sig);
    }

    $ch = curl_init($url . '/index.php?p=' . rawurlencode($pfad));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $koerper,
        CURLOPT_HTTPHEADER     => $header,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_USERAGENT      => 'OV-Budget/' . app_version(),
    ]);
    $roh = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $kopfLaenge = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $fehler = curl_error($ch);
    curl_close($ch);

    if ($roh === false) {
        throw new ConnectorException('Der Connector ist nicht erreichbar: ' . ($fehler ?: 'Zeitüberschreitung'));
    }
    $kopf = substr((string)$roh, 0, $kopfLaenge);
    $inhalt = substr((string)$roh, $kopfLaenge);

    $daten = json_decode($inhalt, true);
    if (!is_array($daten)) {
        throw new ConnectorException('Der Connector antwortete nicht mit JSON (HTTP ' . $code . ').');
    }
    if (empty($daten['ok'])) {
        throw new ConnectorException((string)($daten['fehler'] ?? 'Der Connector hat die Anfrage abgelehnt.'));
    }

    // Antwort prüfen, sobald wir den Schlüssel der Gegenseite kennen
    $serverPub = state_get('connector_server_pub', '');
    if ($serverPub !== '' && $pfad !== 'koppeln') {
        if (!connector_antwort_echt($kopf, $inhalt, $serverPub)) {
            throw new ConnectorException('Die Antwort war nicht richtig signiert – spricht hier wirklich unser Connector?');
        }
    }
    return $daten;
}

/** Signatur aus dem Antwortkopf prüfen. Reine Funktion. */
function connector_antwort_echt(string $kopf, string $inhalt, string $serverPub): bool
{
    if (!preg_match('/^X-Signatur:\s*(\S+)\s*$/mi', $kopf, $m)) {
        return false;
    }
    try {
        $pem = p256_public_pem(b64u_decode($serverPub));
        return openssl_verify($inhalt, b64u_decode(trim($m[1])), $pem, OPENSSL_ALGO_SHA256) === 1;
    } catch (Throwable $ex) {
        return false;
    }
}

/** Signierte Anfrage an den gekoppelten Connector */
function connector_call(string $pfad, array $daten = []): array
{
    if (!connector_gekoppelt()) {
        throw new ConnectorException('Es ist kein Connector gekoppelt.');
    }
    $daten['zweck'] = $pfad;
    $daten['ts'] = time();
    $daten['nonce'] = bin2hex(random_bytes(12));
    return connector_http(connector_url(), $pfad, $daten, state_get('connector_pem', ''));
}

/* ==================================================================== */
/* Zugänge der Fahrzeuge                                                 */
/* ==================================================================== */

/** Neuer Zugang für ein Fahrzeug (der Inhalt des QR-Codes) */
function connector_token_neu(): string
{
    return b64u_encode(random_bytes(24));
}

/** Adresse, die im QR-Code steht */
function connector_qr_url(string $token): string
{
    return connector_url() . '/index.php?p=melden&fz=' . rawurlencode($token);
}

/** Alle Zugänge an den Connector melden – die Liste dort wird ersetzt */
function connector_push_vehicles(): int
{
    $liste = [];
    foreach (db_all("SELECT id, bezeichnung, kennzeichen, qr_token FROM vehicles
                     WHERE qr_token <> '' AND is_active = 1") as $v) {
        $liste[] = [
            'token'       => (string)$v['qr_token'],
            'name'        => (string)$v['bezeichnung'],
            'kennzeichen' => (string)$v['kennzeichen'],
        ];
    }
    $antwort = connector_call('fahrzeuge', ['fahrzeuge' => $liste]);
    return (int)($antwort['fahrzeuge'] ?? count($liste));
}

/* ==================================================================== */
/* Meldungen abholen                                                     */
/* ==================================================================== */

/**
 * Eine Meldung entschlüsseln. Rückgabe: Angaben des Handys.
 * Reine Funktion bis auf den eigenen Schlüssel.
 */
function connector_entschluesseln(string $paket, ?string $pem = null): array
{
    $roh = b64u_decode($paket);
    if (strlen($roh) < 82 + 16) {
        throw new ConnectorException('Die Meldung ist unvollständig.');
    }
    if (ord($roh[0]) !== 1) {
        throw new ConnectorException('Unbekannte Fassung der Meldung: ' . ord($roh[0]));
    }
    $punkt = substr($roh, 1, 65);
    $salz = substr($roh, 66, 16);
    $geheim = substr($roh, 82);

    $pem ??= state_get('connector_pem', '');
    if ($pem === '') {
        throw new ConnectorException('Ohne eigenen Schlüssel lässt sich nichts entschlüsseln.');
    }
    $gemeinsam = openssl_pkey_derive(p256_public_pem($punkt), openssl_pkey_get_private($pem));
    if ($gemeinsam === false) {
        throw new ConnectorException('Schlüsselaustausch fehlgeschlagen: ' . openssl_error_string());
    }

    $abgeleitet = hash_hkdf('sha256', $gemeinsam, 44, CONNECTOR_INFO, $salz);
    $tag = substr($geheim, -16);
    $klartext = openssl_decrypt(substr($geheim, 0, -16), 'aes-256-gcm', substr($abgeleitet, 0, 32),
        OPENSSL_RAW_DATA, substr($abgeleitet, 32, 12), $tag);
    if ($klartext === false) {
        throw new ConnectorException('Die Meldung ließ sich nicht entschlüsseln.');
    }
    $daten = json_decode($klartext, true);
    if (!is_array($daten) || !isset($daten['lat'], $daten['lng'])) {
        throw new ConnectorException('Die Meldung enthielt keine Position.');
    }
    return $daten;
}

/**
 * Meldungen abholen und auf die Fahrzeuge anwenden.
 * Rückgabe: ['geholt', 'uebernommen', 'fehler', 'parkpositionen']
 */
function connector_fetch(): array
{
    $res = ['geholt' => 0, 'uebernommen' => 0, 'fehler' => 0, 'parkpositionen' => 0];
    if (!connector_enabled()) {
        return $res;
    }
    $antwort = connector_call('abholen', ['max' => 200]);
    $meldungen = (array)($antwort['meldungen'] ?? []);
    $res['geholt'] = count($meldungen);
    if (!$meldungen) {
        return $res;
    }

    // Fahrzeuge zu den Zugängen, damit wir nicht je Meldung suchen
    $fahrzeuge = [];
    foreach (db_all("SELECT * FROM vehicles WHERE qr_token <> ''") as $v) {
        $fahrzeuge[(string)$v['qr_token']] = $v;
    }

    // Nur die jeweils jüngste Meldung je Fahrzeug zählt für die Karte,
    // für die Parkerkennung laufen aber alle der Reihe nach durch
    usort($meldungen, static fn($a, $b) => (int)($a['ts'] ?? 0) <=> (int)($b['ts'] ?? 0));

    foreach ($meldungen as $m) {
        $fz = $fahrzeuge[(string)($m['fz'] ?? '')] ?? null;
        if ($fz === null) {
            $res['fehler']++;
            continue;
        }
        try {
            $daten = connector_entschluesseln((string)($m['daten'] ?? ''));
        } catch (Throwable $ex) {
            $res['fehler']++;
            continue;
        }
        $ergebnis = connector_position_anwenden($fz, $daten, (int)($m['ts'] ?? time()));
        $fahrzeuge[(string)$fz['qr_token']] = $ergebnis['fahrzeug'];
        $res['uebernommen']++;
        $res['parkpositionen'] += $ergebnis['park'] ? 1 : 0;
    }

    state_save('connector_letzter_abruf', (string)time());
    return $res;
}

/**
 * Eine gemeldete Position übernehmen.
 * Ins Journal kommt nur, was als Parkposition zählt: Das Fahrzeug steht
 * länger als die eingestellte Zeit im selben Umkreis.
 */
function connector_position_anwenden(array $fz, array $daten, int $ts): array
{
    $lat = (float)$daten['lat'];
    $lng = (float)$daten['lng'];
    if (abs($lat) > 90 || abs($lng) > 180 || ($lat === 0.0 && $lng === 0.0)) {
        return ['fahrzeug' => $fz, 'park' => false];
    }
    // Uralte oder in der Zukunft liegende Meldungen ignorieren
    $gemeldet = (int)($daten['zeit'] ?? 0);
    if ($gemeldet > 0 && abs(time() - $gemeldet) > 86400) {
        return ['fahrzeug' => $fz, 'park' => false];
    }

    $radius = max(5, setting_int('connector_park_radius_meter', 50));
    $minuten = max(1, setting_int('connector_park_minuten', 60));
    $jetzt = date('Y-m-d H:i:s', $ts > 0 ? $ts : time());

    $bewegt = true;
    if ($fz['geo_lat'] !== null && $fz['geo_lng'] !== null) {
        $bewegt = dv_distance_m((float)$fz['geo_lat'], (float)$fz['geo_lng'], $lat, $lng) > $radius;
    }

    $seit = $bewegt ? $jetzt : (string)($fz['geo_park_seit'] ?: $jetzt);
    $gemeldet = $bewegt ? 0 : (int)($fz['geo_park_gemeldet'] ?? 0);

    $park = false;
    if (!$gemeldet && !$bewegt && strtotime($jetzt) - strtotime($seit) >= $minuten * 60) {
        $melder = trim((string)($daten['melder'] ?? ''));
        journal_add((int)$fz['id'], [
            'art'      => 'notiz',
            'titel'    => 'Parkposition',
            'text'     => sprintf('Steht seit %s an derselben Stelle.%s%s',
                de_datetime($seit),
                sprintf("\nStandort: %.5f, %.5f", $lat, $lng),
                $melder !== '' ? "\nGemeldet über QR-Code von " . $melder : "\nGemeldet über QR-Code"),
            'feld'     => 'geo',
            'neu_wert' => sprintf('%.5f, %.5f', $lat, $lng),
            'quelle'   => 'system',
            'autor'    => $melder !== '' ? $melder : 'QR-Meldung',
        ], null);
        $gemeldet = 1;
        $park = true;
    }

    $neu = [
        'geo_lat'           => round($lat, 6),
        'geo_lng'           => round($lng, 6),
        'geo_at'            => $jetzt,
        'geo_quelle'        => 'qr',
        'geo_park_seit'     => $seit,
        'geo_park_gemeldet' => $gemeldet,
    ];
    db_update('vehicles', $neu, 'id = ?', [(int)$fz['id']]);

    return ['fahrzeug' => array_merge($fz, $neu), 'park' => $park];
}

/** Ist ein Abruf fällig? */
function connector_due(?int $jetzt = null): bool
{
    $jetzt ??= time();
    if (!connector_enabled()) {
        return false;
    }
    $intervall = max(1, setting_int('connector_intervall_minuten', 2)) * 60;
    return $jetzt - (int)state_get('connector_letzter_abruf', '0') >= $intervall;
}
