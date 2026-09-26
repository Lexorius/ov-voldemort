<?php
declare(strict_types=1);

/**
 * Anbindung an die OV-Budget-Connectoren.
 *
 * Ein Connector steht auf einem öffentlich erreichbaren Webserver und ist
 * Briefkasten für alles, was von außen hereinkommt, ohne dass jemand Zugang
 * zu dieser Anwendung braucht:
 *
 *   Fahrzeuge       – Standortmeldungen aus dem QR-Code im Fahrzeug
 *   Veranstaltungen – Rückmeldungen auf Einladungen
 *
 * Es können mehrere sein; jeder trägt, wofür er zuständig ist. Lesen kann
 * keiner von ihnen etwas: Was hereinkommt, ist im Browser des Absenders für
 * unseren öffentlichen Schlüssel verschlüsselt.
 *
 * Verbindung:
 *   Kopplung   – einmalig mit einem Code, danach kennen beide Seiten den
 *                öffentlichen Schlüssel der anderen
 *   Anfragen   – von uns signiert (ECDSA P-256), Antworten vom Connector auch
 *
 * Format einer Meldung (so verpackt der Browser, siehe melden.js):
 *   Byte 0      Fassung (1)
 *   Byte 1..65  flüchtiger öffentlicher Schlüssel des Absenders
 *   Byte 66..81 Salz
 *   ab Byte 82  Geheimtext samt Prüfsumme (AES-256-GCM)
 */

class ConnectorException extends RuntimeException
{
}

const CONNECTOR_INFO = 'OV-Budget Standort v1';

/** Wofür ein Connector zuständig sein kann */
const CONNECTOR_ZWECKE = [
    'fahrzeuge'       => 'Fahrzeuge: Standort melden per QR-Code',
    'veranstaltungen' => 'Veranstaltungen: Einladungen und Rückmeldungen',
    'bestand'         => 'Funkgeräte: „ist am Lagerort" per QR-Code',
    'verbrauch'       => 'Zähler: Zählerstand ablesen per QR-Code',
];

/* ==================================================================== */
/* Die Connectoren                                                       */
/* ==================================================================== */

function connector_all(bool $nurAktive = false): array
{
    return db_all('SELECT * FROM connectors'
        . ($nurAktive ? ' WHERE is_active = 1' : '')
        . ' ORDER BY name, id');
}

function connector_find(?int $id): ?array
{
    return $id ? db_row('SELECT * FROM connectors WHERE id = ?', [$id]) : null;
}

/** Ist die Kopplung vollständig? Reine Funktion. */
function connector_gekoppelt(array $c): bool
{
    return trim((string)($c['server_pub'] ?? '')) !== '' && trim((string)($c['pem'] ?? '')) !== '';
}

/** Taugt dieser Connector für den Zweck – aktiv, gekoppelt, zuständig? Reine Funktion. */
function connector_taugt(array $c, string $zweck): bool
{
    $spalte = match ($zweck) {
        'veranstaltungen' => 'fuer_veranstaltungen',
        'bestand'         => 'fuer_bestand',
        'verbrauch'       => 'fuer_verbrauch',
        default           => 'fuer_fahrzeuge',
    };
    return (int)($c['is_active'] ?? 0) === 1
        && (int)($c[$spalte] ?? 0) === 1
        && trim((string)($c['url'] ?? '')) !== ''
        && connector_gekoppelt($c);
}

/** Alle einsatzbereiten Connectoren für einen Zweck */
function connector_liste(string $zweck): array
{
    return array_values(array_filter(
        connector_all(true),
        static fn(array $c) => connector_taugt($c, $zweck)
    ));
}

/** Der erste einsatzbereite Connector für einen Zweck – oder null */
function connector_for(string $zweck): ?array
{
    return connector_liste($zweck)[0] ?? null;
}

/** Ist die Standortmeldung per QR-Code überhaupt eingerichtet? */
function connector_enabled(): bool
{
    return setting_bool('connector_aktiv', false) && connector_for('fahrzeuge') !== null;
}

function connector_url(array $c): string
{
    return rtrim(trim((string)($c['url'] ?? '')), '/');
}

/**
 * Adresse für kurze Einladungslinks – z. B. https://i.example.de
 * Ist keine eingetragen, dient die normale Adresse des Connectors.
 */
function connector_kurz_url(array $c): string
{
    $kurz = rtrim(trim((string)($c['kurz_url'] ?? '')), '/');
    return $kurz !== '' ? $kurz : connector_url($c);
}

/** Eigenes Schlüsselpaar für diesen Connector, einmalig erzeugt */
function connector_keys_ensure(array $c): array
{
    if (trim((string)($c['pem'] ?? '')) !== '') {
        return $c;
    }
    [$pem, $punkt] = p256_keypair();
    $neu = ['pem' => $pem, 'pubkey' => b64u_encode($punkt)];
    db_update('connectors', $neu, 'id = ?', [(int)$c['id']]);
    return array_merge($c, $neu);
}

/** Connector aus dem Formular anlegen oder ändern. Gibt [id, fehler[]] zurück. */
function connector_save_from_post(?array $c): array
{
    $fehler = [];
    $name = mb_substr(post_str('name'), 0, 100);
    $url = rtrim(trim(post_str('url')), '/');
    $kurz = rtrim(trim(post_str('kurz_url')), '/');

    if ($name === '') {
        $fehler[] = 'Bitte einen Namen angeben.';
    }
    if ($url === '' || !preg_match('#^https://#i', $url)) {
        $fehler[] = 'Die Adresse muss mit https:// beginnen.';
    }
    if ($kurz !== '' && !preg_match('#^https://#i', $kurz)) {
        $fehler[] = 'Die kurze Adresse muss mit https:// beginnen.';
    }
    if ($fehler) {
        return [null, $fehler];
    }

    $daten = [
        'name'                 => $name,
        'url'                  => $url,
        'kurz_url'             => $kurz,
        'fuer_fahrzeuge'       => post_bool('fuer_fahrzeuge') ? 1 : 0,
        'fuer_veranstaltungen' => post_bool('fuer_veranstaltungen') ? 1 : 0,
        'fuer_bestand'         => post_bool('fuer_bestand') ? 1 : 0,
        'fuer_verbrauch'       => post_bool('fuer_verbrauch') ? 1 : 0,
        'is_active'            => post_bool('is_active') ? 1 : 0,
        'notiz'                => post_str('notiz'),
    ];

    if ($c) {
        // Die Adresse zu ändern hieße, mit einem anderen Server zu sprechen –
        // die Kopplung gälte dann nicht mehr.
        if (connector_gekoppelt($c) && $daten['url'] !== connector_url($c)) {
            return [null, ['Die Adresse lässt sich nicht ändern, solange die Kopplung steht. '
                . 'Dafür erst die Kopplung lösen.']];
        }
        db_update('connectors', $daten, 'id = ?', [(int)$c['id']]);
        $id = (int)$c['id'];
        audit('connector.bearbeitet', 'connector', $id, $name);
    } else {
        $id = db_insert('connectors', $daten);
        audit('connector.angelegt', 'connector', $id, $name);
    }
    return [$id, []];
}

function connector_delete(array $c): void
{
    db_exec('DELETE FROM connectors WHERE id = ?', [(int)$c['id']]);
    audit('connector.geloescht', 'connector', (int)$c['id'], (string)$c['name']);
}

/* ==================================================================== */
/* Kopplung und Anfragen                                                 */
/* ==================================================================== */

/** Einmalige Kopplung mit dem Connector */
function connector_pair(array $c, string $code): array
{
    if (connector_url($c) === '' || !preg_match('#^https://#i', connector_url($c))) {
        throw new ConnectorException('Die Adresse des Connectors muss mit https:// beginnen.');
    }
    $c = connector_keys_ensure($c);

    $antwort = connector_http(connector_url($c), 'koppeln', [
        'code'   => trim($code),
        'pubkey' => (string)$c['pubkey'],
    ], null);

    $serverPub = trim((string)($antwort['pubkey'] ?? ''));
    if ($serverPub === '' || strlen(b64u_decode($serverPub)) !== 65) {
        throw new ConnectorException('Der Connector hat keinen brauchbaren Schlüssel zurückgegeben.');
    }
    db_update('connectors', [
        'server_pub'    => $serverPub,
        'version'       => mb_substr((string)($antwort['version'] ?? ''), 0, 20),
        'gekoppelt_am'  => date('Y-m-d H:i:s'),
        'angemeldet_am' => null,
    ], 'id = ?', [(int)$c['id']]);
    audit('connector.gekoppelt', 'connector', (int)$c['id'], connector_url($c));
    return $antwort;
}

/** Kopplung hier vergessen (der Connector braucht dann auch eine neue) */
function connector_unpair(array $c): void
{
    db_update('connectors', ['server_pub' => '', 'angemeldet_am' => null], 'id = ?', [(int)$c['id']]);
    audit('connector.getrennt', 'connector', (int)$c['id'], (string)$c['name']);
}

/**
 * Signierte Anfrage an den Connector. $pem = null nur bei der Kopplung,
 * dort kennt die Gegenseite unseren Schlüssel noch nicht.
 */
function connector_http(
    string $url,
    string $pfad,
    array $daten,
    ?string $pem,
    string $serverPub = '',
    int $timeout = 15
): array {
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

/** Signierte Anfrage an einen gekoppelten Connector */
function connector_call(array $c, string $pfad, array $daten = []): array
{
    if (!connector_gekoppelt($c)) {
        throw new ConnectorException(sprintf('Der Connector „%s" ist nicht gekoppelt.', (string)$c['name']));
    }
    $daten['zweck'] = $pfad;
    $daten['ts'] = time();
    $daten['nonce'] = bin2hex(random_bytes(12));
    return connector_http(connector_url($c), $pfad, $daten, (string)$c['pem'], (string)$c['server_pub']);
}

/**
 * Zahlen vom Connector holen: wie viele Zugänge, Veranstaltungen und
 * Einladungen er kennt und was gerade wartet.
 *
 * Auf seiner eigenen Startseite steht davon nichts – die verrät bewusst
 * nichts über den Ortsverband. Fragen darf nur das gekoppelte OV-Budget.
 */
function connector_zustand(array $c): array
{
    return (array)(connector_call($c, 'zustand')['zustand'] ?? []);
}

/* ==================================================================== */
/* Prüfung: Ist der Connector sauber?                                    */
/* ==================================================================== */

/** Die Prüfsummen der Connector-Fassung, die OV-Budget kennt */
function connector_manifest(): array
{
    static $m = null;
    if ($m === null) {
        $roh = json_decode((string)@file_get_contents(dirname(__DIR__) . '/connector-manifest.json'), true);
        $m = is_array($roh) && is_array($roh['dateien'] ?? null) ? $roh : ['version' => '', 'dateien' => []];
    }
    return $m;
}

/** Eine Datei ohne Signatur holen, wie ein Browser. Rückgabe: [code, kopf, inhalt] */
function connector_http_get(string $url, int $timeout = 10): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout, CURLOPT_USERAGENT => 'OV-Budget/' . app_version(),
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $roh = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $kopfLaenge = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($roh === false) {
        return [0, '', ''];
    }
    return [$code, substr((string)$roh, 0, $kopfLaenge), substr((string)$roh, $kopfLaenge)];
}

/**
 * Was OV-Budget selbst über das Netz sieht – unabhängig von der Selbstprüfung.
 * Rückgabe: ['js' => [pfad => bool|null], 'daten_offen' => ?bool,
 *            'unsigniert' => ?bool, 'kopfzeilen' => [fehlende], 'hsts' => ?bool]
 */
function connector_netzpruefung(array $c, array $manifest): array
{
    $basis = connector_url($c);
    $out = ['js' => [], 'daten_offen' => null, 'unsigniert' => null, 'kopfzeilen' => [], 'hsts' => null];

    foreach ($manifest['dateien'] as $rel => $soll) {
        if (!str_starts_with($rel, 'public/assets/') || !str_ends_with($rel, '.js')) {
            continue;
        }
        [$code, , $inhalt] = connector_http_get($basis . '/' . substr($rel, 7));
        $out['js'][$rel] = $code === 200 ? hash('sha256', $inhalt) === (string)$soll['sha256'] : null;
    }

    [$code, , $inhalt] = connector_http_get($basis . '/../daten/kopplung.json');
    $out['daten_offen'] = $code === 200 && str_contains($inhalt, 'ov_pubkey');

    [$code, , $inhalt] = connector_http_get($basis . '/index.php?p=zustand');
    $antwort = json_decode($inhalt, true);
    $out['unsigniert'] = $code === 200 && is_array($antwort) && !empty($antwort['ok']);

    [$code, $kopf] = connector_http_get($basis . '/');
    if ($code > 0) {
        foreach (['Content-Security-Policy', 'X-Frame-Options', 'X-Content-Type-Options'] as $k) {
            if (!preg_match('/^' . preg_quote($k, '/') . ':/mi', $kopf)) {
                $out['kopfzeilen'][] = $k;
            }
        }
        $out['hsts'] = (bool)preg_match('/^Strict-Transport-Security:/mi', $kopf);
    }
    return $out;
}

/**
 * Selbstprüfung, Maßstab und Netzsicht zu einem Urteil zusammenführen.
 * Reine Funktion. Rückgabe: ['stufe' => sauber|hinweis|alarm, 'befunde' => [[stufe, text]], …]
 */
function connector_pruefung_auswerten(array $bericht, array $manifest, array $netz): array
{
    $befunde = [];
    $ist = (array)($bericht['dateien'] ?? []);
    $soll = (array)($manifest['dateien'] ?? []);

    if ($soll === []) {
        $befunde[] = ['stufe' => 'hinweis', 'text' => 'OV-Budget hat keinen Maßstab (connector-manifest.json fehlt) – nur die Selbstprüfung zählt.'];
    }
    if ((string)($bericht['version'] ?? '') !== (string)($manifest['version'] ?? '') && $soll !== []) {
        $befunde[] = ['stufe' => 'hinweis', 'text' => sprintf('Der Connector läuft als Fassung %s, OV-Budget erwartet %s – bitte die Dateien auf dem Webserver aktualisieren.',
            (string)($bericht['version'] ?? '?'), (string)$manifest['version'])];
    }
    $veraendert = [];
    $fehlt = [];
    foreach ($soll as $rel => $s) {
        if (!isset($ist[$rel])) {
            $fehlt[] = $rel;
        } elseif ((string)$ist[$rel]['sha256'] !== (string)$s['sha256']) {
            $veraendert[] = $rel;
        }
    }
    $fremd = $soll === [] ? (array)($bericht['fremd'] ?? []) : array_values(array_diff(array_keys($ist), array_keys($soll)));
    foreach ($veraendert as $f) {
        $befunde[] = ['stufe' => 'alarm', 'text' => 'Datei weicht vom Maßstab ab: ' . $f];
    }
    foreach ($fehlt as $f) {
        $befunde[] = ['stufe' => 'hinweis', 'text' => 'Datei fehlt auf dem Server: ' . $f];
    }
    foreach ($fremd as $f) {
        $befunde[] = ['stufe' => 'alarm', 'text' => 'Fremde Datei auf dem Server: ' . $f];
    }
    foreach ((array)($bericht['befunde'] ?? []) as $b) {
        $text = (string)($b['text'] ?? '');
        if ($text !== '' && !str_starts_with($text, 'Datei gehört nicht zum Connector')) {
            $befunde[] = ['stufe' => ($b['stufe'] ?? '') === 'alarm' ? 'alarm' : 'hinweis', 'text' => 'Selbstprüfung: ' . $text];
        }
    }
    foreach ((array)($netz['js'] ?? []) as $rel => $ok) {
        if ($ok === false) {
            $befunde[] = ['stufe' => 'alarm', 'text' => 'Ausgeliefertes Skript weicht ab: ' . $rel . ' – so erreicht es die Browser der Besucher.'];
        } elseif ($ok === null) {
            $befunde[] = ['stufe' => 'hinweis', 'text' => 'Skript nicht abrufbar: ' . $rel];
        }
    }
    if (($netz['daten_offen'] ?? null) === true) {
        $befunde[] = ['stufe' => 'alarm', 'text' => 'daten/kopplung.json ist von außen lesbar.'];
    }
    if (($netz['unsigniert'] ?? null) === true) {
        $befunde[] = ['stufe' => 'alarm', 'text' => '?p=zustand antwortet ohne Signatur – das darf nicht sein.'];
    }
    foreach ((array)($netz['kopfzeilen'] ?? []) as $k) {
        $befunde[] = ['stufe' => 'hinweis', 'text' => 'Kopfzeile fehlt: ' . $k];
    }
    if (($netz['hsts'] ?? null) === false) {
        $befunde[] = ['stufe' => 'hinweis', 'text' => 'Kein Strict-Transport-Security – im Webserver einschalten.'];
    }

    $stufe = 'sauber';
    foreach ($befunde as $b) {
        if ($b['stufe'] === 'alarm') {
            $stufe = 'alarm';
            break;
        }
        $stufe = 'hinweis';
    }
    return [
        'stufe'      => $stufe,
        'befunde'    => $befunde,
        'dateien'    => count($ist),
        'geprueft'   => count($soll),
        'version'    => (string)($bericht['version'] ?? ''),
        'erwartet'   => (string)($manifest['version'] ?? ''),
        'js_geprueft' => count(array_filter((array)($netz['js'] ?? []), static fn($v) => $v === true)),
        'zeit'       => date('Y-m-d H:i:s'),
    ];
}

/** Prüfung ausführen und das Ergebnis merken */
function connector_pruefen(array $c): array
{
    $manifest = connector_manifest();
    $bericht = (array)(connector_call($c, 'pruefung')['pruefung'] ?? []);
    $netz = connector_netzpruefung($c, $manifest);
    $ergebnis = connector_pruefung_auswerten($bericht, $manifest, $netz);
    state_save('connector_pruefung_' . (int)$c['id'], (string)json_encode($ergebnis, JSON_UNESCAPED_UNICODE));
    audit('connector.geprueft', 'connector', (int)$c['id'], $ergebnis['stufe'] . ', ' . count($ergebnis['befunde']) . ' Befund(e)');
    return $ergebnis;
}

function connector_pruefung_letzte(array $c): ?array
{
    $r = json_decode(state_get('connector_pruefung_' . (int)$c['id'], ''), true);
    return is_array($r) ? $r : null;
}

/** Für den Abruf: einmal am Tag alle gekoppelten Connectoren prüfen, bei Alarm die Leitung benachrichtigen */
function connector_pruefung_taeglich(): array
{
    $res = ['geprueft' => 0, 'alarm' => 0, 'fehler' => 0];
    if (!setting_bool('connector_pruefung_taeglich', true)) {
        return $res;
    }
    if (time() - (int)state_get('connector_pruefung_letzter_lauf', '0') < 86400) {
        return $res;
    }
    state_save('connector_pruefung_letzter_lauf', (string)time());
    foreach (connector_all(true) as $c) {
        if (!connector_gekoppelt($c)) {
            continue;
        }
        $vorher = connector_pruefung_letzte($c);
        try {
            $e = connector_pruefen($c);
        } catch (Throwable $ex) {
            $res['fehler']++;
            continue;
        }
        $res['geprueft']++;
        if ($e['stufe'] === 'alarm') {
            $res['alarm']++;
            if (($vorher['stufe'] ?? '') !== 'alarm') {
                notify_queue(notify_leitung(), 'connector_alarm',
                    'Connector „' . $c['name'] . '": Prüfung schlägt an',
                    implode(' · ', array_map(static fn($b) => $b['text'],
                        array_slice(array_filter($e['befunde'], static fn($b) => $b['stufe'] === 'alarm'), 0, 3))),
                    notify_url('?p=admin_connector&id=' . (int)$c['id']));
            }
        }
    }
    return $res;
}

/* ==================================================================== */
/* Zugänge der Fahrzeuge                                                 */
/* ==================================================================== */

/** Neuer Zugang (der Inhalt eines QR-Codes) */
function connector_token_neu(): string
{
    return b64u_encode(random_bytes(24));
}

/** So steht ein Zugang beim Connector: als Prüfsumme. Reine Funktion. */
function connector_kennung(string $token): string
{
    return hash('sha256', 'ovb-zugang:' . $token);
}

/**
 * Adresse, die im QR-Code steht.
 *
 * Die Bezeichnung hängt hinter dem #. Browser schicken den Anker nicht mit –
 * der Connector erfährt also nie, um welches Fahrzeug es geht, die Seite kann
 * es aber anzeigen.
 */
function connector_qr_url(array $c, string $token, string $name = ''): string
{
    $url = connector_url($c) . '/index.php?p=melden&fz=' . rawurlencode($token);
    $name = trim($name);
    return $name === '' ? $url : $url . '#n=' . b64u_encode($name);
}

/** Bezeichnung für den Anker: "GKW 1 · THW 99020". Reine Funktion. */
function connector_qr_name(array $fahrzeug): string
{
    return trim(implode(' · ', array_filter([
        trim((string)($fahrzeug['bezeichnung'] ?? '')),
        trim((string)($fahrzeug['kennzeichen'] ?? '')),
    ])));
}

/** Der Connector, über den der QR-Code eines Fahrzeugs läuft */
function connector_of_vehicle(array $fahrzeug): ?array
{
    // Steht einer am Fahrzeug, gilt genau der – auch wenn es ihn nicht mehr gibt.
    // Ohne Eintrag (alte Daten) nehmen wir den ersten zuständigen.
    $id = (int)($fahrzeug['qr_connector_id'] ?? 0);
    return $id > 0 ? connector_find($id) : connector_for('fahrzeuge');
}

/** Alle Zugänge an einen Connector melden – die Liste dort wird ersetzt */
function connector_push_vehicles(array $c): int
{
    // Übertragen wird nur die Prüfsumme des Zugangs – keine Namen, keine Kennzeichen
    $liste = [];
    $fahrzeuge = db_all(
        "SELECT qr_token FROM vehicles
         WHERE qr_token <> '' AND is_active = 1 AND qr_connector_id = ?",
        [(int)$c['id']]
    );
    foreach ($fahrzeuge as $v) {
        $liste[] = ['kennung' => connector_kennung((string)$v['qr_token'])];
    }
    $antwort = connector_call($c, 'fahrzeuge', ['fahrzeuge' => $liste]);
    db_update('connectors', ['angemeldet_am' => date('Y-m-d H:i:s')], 'id = ?', [(int)$c['id']]);
    return (int)($antwort['fahrzeuge'] ?? count($liste));
}

/** Zugänge an alle zuständigen Connectoren melden */
function connector_push_vehicles_all(): int
{
    $n = 0;
    foreach (connector_liste('fahrzeuge') as $c) {
        $n += connector_push_vehicles($c);
    }
    return $n;
}

/* ==================================================================== */
/* Veranstaltungen und Einladungen                                       */
/* ==================================================================== */

/**
 * Was der Connector über eine Veranstaltung wissen muss, damit die
 * Einladungsseite sie zeigen kann – und nicht mehr. Reine Funktion.
 */
function connector_event_daten(array $e): array
{
    return [
        'kennung'       => event_kennung($e),
        'titel'         => (string)$e['titel'],
        'beginn'        => (string)$e['beginn'],
        'ende'          => (string)($e['ende'] ?? ''),
        'ort'           => (string)($e['ort'] ?? ''),
        'hinweis'       => (string)($e['hinweis'] ?? ''),
        'bis'           => (string)($e['rueckmeldung_bis'] ?? ''),
        'status'        => (string)$e['status'],
        'begleiter_max' => (int)$e['begleiter_max'],
        'kommentare'    => (int)$e['kommentare_erlaubt'],
        'vertretung'    => (int)$e['vertretung_erlaubt'],
    ];
}

/**
 * Das Paket für einen Connector: seine Veranstaltungen und die Prüfsummen
 * der Einladungscodes. Namen der Eingeladenen sind nicht dabei.
 *
 * Lange vorbei ist lange vorbei: Was seit mehr als einer Woche zu Ende ist,
 * muss nicht weiter im Netz stehen.
 */
function connector_event_paket(array $c): array
{
    $veranstaltungen = [];
    $einladungen = [];
    $grenze = time() - 7 * 86400;

    foreach (db_all('SELECT * FROM events WHERE connector_id = ? ORDER BY beginn', [(int)$c['id']]) as $e) {
        $ende = strtotime((string)($e['ende'] ?: $e['beginn']));
        if ($ende !== false && $ende < $grenze) {
            continue;
        }
        $veranstaltungen[] = connector_event_daten($e);
        foreach (db_all('SELECT code FROM event_guests WHERE event_id = ?', [(int)$e['id']]) as $g) {
            $einladungen[event_code_kennung((string)$g['code'])] = event_kennung($e);
        }
    }
    return ['veranstaltungen' => $veranstaltungen, 'einladungen' => $einladungen];
}

/**
 * Veranstaltungen und Einladungen anmelden. Geschickt wird nur, wenn sich
 * etwas geändert hat – sonst schriebe jeder Abruf dieselben Dateien neu.
 */
function connector_push_events(array $c, bool $erzwingen = false): array
{
    $paket = connector_event_paket($c);
    $stand = hash('sha256', (string)json_encode($paket, JSON_UNESCAPED_UNICODE));
    $merker = 'connector_events_' . (int)$c['id'];

    if (!$erzwingen && state_get($merker, '') === $stand) {
        return [
            'gesendet'        => false,
            'veranstaltungen' => count($paket['veranstaltungen']),
            'einladungen'     => count($paket['einladungen']),
        ];
    }
    $antwort = connector_call($c, 'veranstaltungen', $paket);
    state_save($merker, $stand);
    return [
        'gesendet'        => true,
        'veranstaltungen' => (int)($antwort['veranstaltungen'] ?? count($paket['veranstaltungen'])),
        'einladungen'     => (int)($antwort['einladungen'] ?? count($paket['einladungen'])),
    ];
}

/**
 * Rückmeldungen abholen und in die Gästelisten eintragen.
 * Rückgabe: ['geholt', 'uebernommen', 'fehler']
 */
function connector_fetch_answers(array $c): array
{
    $res = ['geholt' => 0, 'uebernommen' => 0, 'fehler' => 0];
    if (!connector_taugt($c, 'veranstaltungen')) {
        return $res;
    }
    $antwort = connector_call($c, 'rueckmeldungen', ['max' => 200]);
    $liste = (array)($antwort['rueckmeldungen'] ?? []);
    $res['geholt'] = count($liste);
    if (!$liste) {
        return $res;
    }

    // Gäste dieses Connectors, nach der Prüfsumme ihres Codes
    $gaeste = [];
    foreach (db_all(
        'SELECT g.* FROM event_guests g
         JOIN events e ON e.id = g.event_id
         WHERE e.connector_id = ?',
        [(int)$c['id']]
    ) as $g) {
        $gaeste[event_code_kennung((string)$g['code'])] = $g;
    }

    // Älteste zuerst: Wer zweimal antwortet, dessen letzte Antwort gilt
    usort($liste, static fn($a, $b) => (int)($a['ts'] ?? 0) <=> (int)($b['ts'] ?? 0));

    $veranstaltungen = [];
    foreach ($liste as $m) {
        $gast = $gaeste[(string)($m['code'] ?? '')] ?? null;
        if ($gast === null) {
            $res['fehler']++;
            continue;
        }
        try {
            $daten = connector_entschluesseln((string)($m['daten'] ?? ''), (string)$c['pem'], ['status']);
        } catch (Throwable $ex) {
            $res['fehler']++;
            continue;
        }
        $eventId = (int)$gast['event_id'];
        $veranstaltungen[$eventId] ??= event_find($eventId);
        if ($veranstaltungen[$eventId] === null) {
            $res['fehler']++;
            continue;
        }
        $gaeste[(string)$m['code']] = event_guest_antwort($veranstaltungen[$eventId], $gast, $daten, 'einladung');
        $res['uebernommen']++;
    }
    return $res;
}

/** Anmelden und abholen bei allen Connectoren für Veranstaltungen */
function connector_events_sync(): array
{
    $res = ['connectoren' => 0, 'gesendet' => 0, 'geholt' => 0, 'uebernommen' => 0, 'fehler' => 0];
    foreach (connector_liste('veranstaltungen') as $c) {
        $res['connectoren']++;
        $push = connector_push_events($c);
        $res['gesendet'] += $push['gesendet'] ? 1 : 0;
        foreach (connector_fetch_answers($c) as $k => $v) {
            $res[$k] += $v;
        }
    }
    state_save('veranstaltung_letzter_abruf', (string)time());
    return $res;
}

/** Ist ein Abgleich der Einladungen fällig? */
function connector_events_due(?int $jetzt = null): bool
{
    $jetzt ??= time();
    if (!connector_for('veranstaltungen')) {
        return false;
    }
    $intervall = max(1, setting_int('veranstaltung_intervall_minuten', 10)) * 60;
    return $jetzt - (int)state_get('veranstaltung_letzter_abruf', '0') >= $intervall;
}

/* ==================================================================== */
/* Bestand: Funkgeräte und Gruppen am Lagerort                           */
/* ==================================================================== */

/**
 * Adresse, die im QR-Code am Gerät oder am Koffer steht. Die Bezeichnung
 * hängt hinter dem # und erreicht den Connector nie.
 */
function connector_qr_bestand_url(array $c, string $token, string $name = ''): string
{
    $url = connector_url($c) . '/index.php?p=bestand&g=' . rawurlencode($token);
    $name = trim($name);
    return $name === '' ? $url : $url . '#n=' . b64u_encode($name);
}

/**
 * Was der Connector über den Bestand wissen muss: je Zugang nur die
 * Prüfsumme, ob Gerät oder Gruppe – und bei einer Gruppe, wie viele Geräte
 * dazugehören. Bezeichnungen bleiben hier.
 */
function connector_bestand_paket(array $c): array
{
    $liste = [];
    foreach (db_all("SELECT qr_token FROM radios
                     WHERE qr_token <> '' AND is_active = 1 AND qr_connector_id = ?", [(int)$c['id']]) as $r) {
        $liste[] = ['kennung' => connector_kennung((string)$r['qr_token']), 'art' => 'geraet', 'anzahl' => 1];
    }
    foreach (db_all("SELECT g.qr_token,
                            (SELECT COUNT(*) FROM radios r WHERE r.group_id = g.id AND r.is_active = 1) AS geraete
                     FROM radio_groups g
                     WHERE g.qr_token <> '' AND g.is_active = 1 AND g.qr_connector_id = ?",
                    [(int)$c['id']]) as $g) {
        $liste[] = [
            'kennung' => connector_kennung((string)$g['qr_token']),
            'art'     => 'gruppe',
            'anzahl'  => (int)$g['geraete'],
        ];
    }
    return $liste;
}

/** Zugänge anmelden – geschickt wird nur, wenn sich etwas geändert hat */
function connector_push_bestand(array $c, bool $erzwingen = false): array
{
    $paket = connector_bestand_paket($c);
    $stand = hash('sha256', (string)json_encode($paket));
    $merker = 'connector_bestand_' . (int)$c['id'];

    if (!$erzwingen && state_get($merker, '') === $stand) {
        return ['gesendet' => false, 'zugaenge' => count($paket)];
    }
    $antwort = connector_call($c, 'bestandsliste', ['bestand' => $paket]);
    state_save($merker, $stand);
    return ['gesendet' => true, 'zugaenge' => (int)($antwort['bestand'] ?? count($paket))];
}

/**
 * Bestandsmeldungen abholen und eintragen.
 * Rückgabe: ['geholt', 'uebernommen', 'fehler']
 */
function connector_fetch_bestand(array $c): array
{
    $res = ['geholt' => 0, 'uebernommen' => 0, 'fehler' => 0];
    if (!connector_taugt($c, 'bestand')) {
        return $res;
    }
    if (state_get('connector_bestand_' . (int)$c['id'], '') === '') {
        connector_push_bestand($c, true);
    }

    $antwort = connector_call($c, 'bestand_abholen', ['max' => 200]);
    $meldungen = (array)($antwort['meldungen'] ?? []);
    $res['geholt'] = count($meldungen);
    if (!$meldungen) {
        return $res;
    }

    // Geräte und Gruppen zu den Zugängen
    $ziele = [];
    foreach (db_all("SELECT * FROM radios WHERE qr_token <> '' AND qr_connector_id = ?", [(int)$c['id']]) as $r) {
        $ziele[connector_kennung((string)$r['qr_token'])] = ['art' => 'geraet', 'ziel' => $r];
    }
    foreach (db_all("SELECT g.*,
                            (SELECT COUNT(*) FROM radios r WHERE r.group_id = g.id AND r.is_active = 1) AS geraete
                     FROM radio_groups g WHERE g.qr_token <> '' AND g.qr_connector_id = ?",
                    [(int)$c['id']]) as $g) {
        $ziele[connector_kennung((string)$g['qr_token'])] = ['art' => 'gruppe', 'ziel' => $g];
    }

    usort($meldungen, static fn($a, $b) => (int)($a['ts'] ?? 0) <=> (int)($b['ts'] ?? 0));

    foreach ($meldungen as $m) {
        $eintrag = $ziele[(string)($m['zugang'] ?? '')] ?? null;
        if ($eintrag === null) {
            $res['fehler']++;
            continue;
        }
        try {
            $daten = connector_entschluesseln((string)($m['daten'] ?? ''), (string)$c['pem'], ['da']);
        } catch (Throwable $ex) {
            $res['fehler']++;
            continue;
        }
        // Uralte oder in der Zukunft liegende Meldungen ignorieren
        $gemeldet = (int)($daten['zeit'] ?? 0);
        if ($gemeldet > 0 && abs(time() - $gemeldet) > 86400) {
            $res['fehler']++;
            continue;
        }
        radio_bestand_anwenden($eintrag['art'], $eintrag['ziel'], $daten, (int)($m['ts'] ?? time()));
        $res['uebernommen']++;
    }
    return $res;
}

/** Anmelden und abholen bei allen Connectoren für den Bestand */
function connector_bestand_sync(): array
{
    $res = ['connectoren' => 0, 'gesendet' => 0, 'geholt' => 0, 'uebernommen' => 0, 'fehler' => 0];
    foreach (connector_liste('bestand') as $c) {
        $res['connectoren']++;
        $push = connector_push_bestand($c);
        $res['gesendet'] += $push['gesendet'] ? 1 : 0;
        foreach (connector_fetch_bestand($c) as $k => $v) {
            $res[$k] += $v;
        }
    }
    state_save('connector_bestand_letzter_abruf', (string)time());
    return $res;
}

/** Ist ein Abgleich der Bestandsmeldungen fällig? */
function connector_bestand_due(?int $jetzt = null): bool
{
    $jetzt ??= time();
    if (!connector_for('bestand')) {
        return false;
    }
    $intervall = max(1, setting_int('connector_intervall_minuten', 2)) * 60;
    return $jetzt - (int)state_get('connector_bestand_letzter_abruf', '0') >= $intervall;
}

/* ==================================================================== */
/* Zähler: Stände per QR-Code                                            */
/* ==================================================================== */

/** Adresse im QR-Code am Zähler. Name und Nummer hängen hinter dem #. */
function connector_qr_zaehler_url(array $c, string $token, string $name = ''): string
{
    $url = connector_url($c) . '/index.php?p=zaehler&z=' . rawurlencode($token);
    $name = trim($name);
    return $name === '' ? $url : $url . '#n=' . b64u_encode($name);
}

/** Je Zugang nur Prüfsumme, Art und Einheit – die Seite muss "kWh" schreiben können */
function connector_zaehler_paket(array $c): array
{
    $liste = [];
    foreach (db_all("SELECT qr_token, art, einheit FROM meters
                     WHERE qr_token <> '' AND is_active = 1 AND qr_connector_id = ?", [(int)$c['id']]) as $m) {
        $liste[] = ['kennung' => connector_kennung((string)$m['qr_token']),
                    'art' => (string)$m['art'], 'einheit' => (string)$m['einheit']];
    }
    return $liste;
}

/** Zugänge anmelden – geschickt wird nur, wenn sich etwas geändert hat */
function connector_push_zaehler(array $c, bool $erzwingen = false): array
{
    $paket = connector_zaehler_paket($c);
    $stand = hash('sha256', (string)json_encode($paket));
    $merker = 'connector_zaehler_' . (int)$c['id'];
    if (!$erzwingen && state_get($merker, '') === $stand) {
        return ['gesendet' => false, 'zugaenge' => count($paket)];
    }
    $antwort = connector_call($c, 'zaehlerliste', ['zaehler' => $paket]);
    state_save($merker, $stand);
    return ['gesendet' => true, 'zugaenge' => (int)($antwort['zaehler'] ?? count($paket))];
}

/** Zählerstände abholen und eintragen. Rückgabe: ['geholt', 'uebernommen', 'fehler'] */
function connector_fetch_zaehler(array $c): array
{
    $res = ['geholt' => 0, 'uebernommen' => 0, 'fehler' => 0];
    if (!connector_taugt($c, 'verbrauch')) {
        return $res;
    }
    if (state_get('connector_zaehler_' . (int)$c['id'], '') === '') {
        connector_push_zaehler($c, true);
    }
    $antwort = connector_call($c, 'zaehler_abholen', ['max' => 200]);
    $meldungen = (array)($antwort['meldungen'] ?? []);
    $res['geholt'] = count($meldungen);
    if (!$meldungen) {
        return $res;
    }
    $ziele = [];
    foreach (db_all("SELECT * FROM meters WHERE qr_token <> '' AND qr_connector_id = ?", [(int)$c['id']]) as $m) {
        $ziele[connector_kennung((string)$m['qr_token'])] = $m;
    }
    usort($meldungen, static fn($a, $b) => (int)($a['ts'] ?? 0) <=> (int)($b['ts'] ?? 0));
    foreach ($meldungen as $m) {
        $meter = $ziele[(string)($m['zugang'] ?? '')] ?? null;
        if ($meter === null) {
            $res['fehler']++;
            continue;
        }
        try {
            $daten = connector_entschluesseln((string)($m['daten'] ?? ''), (string)$c['pem'], ['stand']);
        } catch (Throwable $ex) {
            $res['fehler']++;
            continue;
        }
        if (!is_numeric($daten['stand']) || meter_qr_anwenden($meter, $daten, (int)($m['ts'] ?? time())) !== null) {
            $res['fehler']++;
            continue;
        }
        $res['uebernommen']++;
    }
    return $res;
}

/** Anmelden und abholen bei allen Connectoren für Zähler */
function connector_zaehler_sync(): array
{
    $res = ['connectoren' => 0, 'gesendet' => 0, 'geholt' => 0, 'uebernommen' => 0, 'fehler' => 0];
    foreach (connector_liste('verbrauch') as $c) {
        $res['connectoren']++;
        $push = connector_push_zaehler($c);
        $res['gesendet'] += $push['gesendet'] ? 1 : 0;
        foreach (connector_fetch_zaehler($c) as $k => $v) {
            $res[$k] += $v;
        }
    }
    state_save('connector_zaehler_letzter_abruf', (string)time());
    return $res;
}

/** Ist ein Abgleich der Zählerstände fällig? */
function connector_zaehler_due(?int $jetzt = null): bool
{
    $jetzt ??= time();
    if (!connector_for('verbrauch')) {
        return false;
    }
    $intervall = max(1, setting_int('connector_intervall_minuten', 2)) * 60;
    return $jetzt - (int)state_get('connector_zaehler_letzter_abruf', '0') >= $intervall;
}

/* ==================================================================== */
/* Meldungen abholen                                                     */
/* ==================================================================== */

/**
 * Eine Meldung entschlüsseln. Rückgabe: Angaben des Absenders.
 * Reine Funktion bis auf den Schlüssel, der mitgegeben wird.
 */
function connector_entschluesseln(string $paket, string $pem, array $pflicht = ['lat', 'lng']): array
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

    if (trim($pem) === '') {
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
    if (!is_array($daten)) {
        throw new ConnectorException('Die Meldung war kein gültiges JSON.');
    }
    foreach ($pflicht as $feld) {
        if (!isset($daten[$feld])) {
            throw new ConnectorException('Der Meldung fehlt die Angabe „' . $feld . '".');
        }
    }
    return $daten;
}

/**
 * Meldungen eines Connectors abholen und auf die Fahrzeuge anwenden.
 * Rückgabe: ['geholt', 'uebernommen', 'fehler', 'parkpositionen']
 */
function connector_fetch(array $c): array
{
    $res = ['geholt' => 0, 'uebernommen' => 0, 'fehler' => 0, 'parkpositionen' => 0];
    if (!connector_taugt($c, 'fahrzeuge')) {
        return $res;
    }
    // Nach dem Koppeln (oder einer Umstellung) die Zugänge einmal anmelden
    if (($c['angemeldet_am'] ?? null) === null) {
        connector_push_vehicles($c);
    }

    $antwort = connector_call($c, 'abholen', ['max' => 200]);
    $meldungen = (array)($antwort['meldungen'] ?? []);
    $res['geholt'] = count($meldungen);
    db_update('connectors', ['letzter_abruf' => date('Y-m-d H:i:s')], 'id = ?', [(int)$c['id']]);
    if (!$meldungen) {
        return $res;
    }

    // Fahrzeuge zu den Zugängen, damit wir nicht je Meldung suchen
    $fahrzeuge = [];
    foreach (db_all("SELECT * FROM vehicles WHERE qr_token <> '' AND qr_connector_id = ?", [(int)$c['id']]) as $v) {
        $fahrzeuge[connector_kennung((string)$v['qr_token'])] = $v;
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
            $daten = connector_entschluesseln((string)($m['daten'] ?? ''), (string)$c['pem']);
        } catch (Throwable $ex) {
            $res['fehler']++;
            continue;
        }
        $ergebnis = connector_position_anwenden($fz, $daten, (int)($m['ts'] ?? time()));
        $fahrzeuge[connector_kennung((string)$fz['qr_token'])] = $ergebnis['fahrzeug'];
        $res['uebernommen']++;
        $res['parkpositionen'] += $ergebnis['park'] ? 1 : 0;
    }

    return $res;
}

/** Bei allen zuständigen Connectoren abholen */
function connector_fetch_all(): array
{
    $gesamt = ['geholt' => 0, 'uebernommen' => 0, 'fehler' => 0, 'parkpositionen' => 0];
    foreach (connector_liste('fahrzeuge') as $c) {
        foreach (connector_fetch($c) as $k => $v) {
            $gesamt[$k] += $v;
        }
    }
    state_save('connector_letzter_abruf', (string)time());
    return $gesamt;
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
