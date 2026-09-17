<?php
declare(strict_types=1);

/*
 * Anbindung an die Stein.APP
 *
 * Grenzen laut Dokumentation (https://stein.app/api/api/doc/intro):
 *   - höchstens 20 Anfragen je Minute und IP; wer darüber liegt, wird für
 *     eine Stunde gesperrt
 *   - der Zugriff ist auf IP-Adressen aus Deutschland beschränkt; von
 *     außerhalb antwortet die Schnittstelle mit 404
 *   - regelmäßiges Abfragen im Minutentakt ist unerwünscht, empfohlen
 *     werden Webhooks (siehe stein_webhook_handle)
 *
 * Deshalb:
 *   - je Abgleich genau EIN Aufruf (die Liste aller Fahrzeuge der BU-ID)
 *   - zwischen zwei Abgleichen liegt mindestens das eingestellte Intervall
 *     (Vorgabe 10 Minuten); der Abstand wird serverseitig erzwungen
 *   - antwortet die Schnittstelle mit 429, wird eine Stunde pausiert
 *
 * Aus dem Vollbild wird der Unterschied zum letzten Stand berechnet; jede
 * Änderung landet als Eintrag in der Fahrzeugakte.
 *
 * Endpunkte (siehe https://stein.app/api/api/doc/api-doc.yaml):
 *   GET <base>/assets/?buIds=<BU-ID>   Liste aller Fahrzeuge einer Einheit
 *   GET <base>/userinfo                wer der Schlüssel ist (für den Test)
 *
 * Ein Feld für das Kennzeichen kennt die Schnittstelle nicht. Es wird
 * deshalb aus Bezeichnung, Name, Funkrufname und Bemerkung gelesen; die
 * Namen möglicher künftiger Felder stehen in STEIN_KENNZEICHEN_FELDER.
 */

class SteinException extends RuntimeException {}

/** Kürzester Abstand zwischen zwei Abrufen, die ein Webhook auslöst (Sekunden) */
const STEIN_WEBHOOK_MINDESTABSTAND = 30;

/** Status der Stein.APP → Schlüssel unserer Liste fahrzeug_status */
const STEIN_STATUS = [
    'ready'      => ['einsatzbereit', 'Einsatzbereit'],
    'semiready'  => ['bedingt', 'Bedingt einsatzbereit'],
    'inuse'      => ['im-einsatz', 'Im Einsatz'],
    'maint'      => ['wartung', 'In Wartung'],
    'notready'   => ['nicht-einsatzbereit', 'Nicht einsatzbereit'],
];

/** Felder, deren Änderung in die Fahrzeugakte geschrieben wird */
const STEIN_FELDER = [
    'status'               => 'Status',
    'comment'              => 'Bemerkung',
    'huValidUntil'         => 'HU gültig bis',
    'spValidUntil'         => 'SP gültig bis',
    'operationReservation' => 'Einsatzvorbehalt',
    'label'                => 'Bezeichnung',
    'name'                 => 'Name',
    'radioName'            => 'Funkrufname',
    'category'             => 'Kategorie',
    'issi'                 => 'ISSI (Funkrufkennung)',
    'deleted'              => 'In der Stein.APP gelöscht',
];

/** Felder, die als Ja/Nein gelesen werden */
const STEIN_JANEIN = ['operationReservation', 'deleted'];

function stein_enabled(): bool
{
    return setting_bool('stein_aktiv', false)
        && trim((string)setting('stein_api_key', '')) !== ''
        && trim((string)setting('stein_bu_id', '')) !== '';
}

function stein_interval_minutes(): int
{
    return max(1, setting_int('stein_intervall_minuten', 10));
}

/** Wann war der letzte Abruf (Unix-Zeit, 0 = noch nie)? */
function stein_last_sync(): int
{
    return (int)setting('stein_letzter_abruf', '0');
}

/**
 * Darf jetzt abgerufen werden? Gibt null zurück, wenn ja, sonst den Grund.
 * Reine Rechnung, damit sie sich prüfen lässt.
 */
function stein_wait_reason(int $letzter, int $intervallMinuten, int $pauseBis, int $jetzt): ?string
{
    if ($pauseBis > $jetzt) {
        return sprintf('Die Schnittstelle hat gebremst. Nächster Versuch in %d Minute(n).',
            (int)ceil(($pauseBis - $jetzt) / 60));
    }
    $abstand = $jetzt - $letzter;
    $soll = $intervallMinuten * 60;
    if ($letzter > 0 && $abstand < $soll) {
        return sprintf('Der letzte Abruf ist %d Minute(n) her, das Intervall sind %d Minuten.',
            intdiv($abstand, 60), $intervallMinuten);
    }
    return null;
}

/**
 * Ein Aufruf gegen die Schnittstelle. $pfad beginnt mit einem Schrägstrich.
 * Gibt die entpackte Antwort als Feld zurück.
 */
function stein_request(string $pfad, array $query = []): array
{
    $base = rtrim((string)setting('stein_base_url', 'https://stein.app/api/api/ext'), '/');
    $key  = trim((string)setting('stein_api_key', ''));
    if ($base === '' || $key === '') {
        throw new SteinException('Die Stein.APP ist nicht vollständig eingerichtet (Schlüssel und BU-ID).');
    }

    $url = $base . $pfad;
    if ($query) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }
    $timeout = max(3, setting_int('stein_timeout', 15));
    $headers = ['Accept: application/json', 'Authorization: Bearer ' . $key];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'OV-Budget Fahrzeugakte',
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($errno) {
            throw new SteinException('Verbindungsfehler: ' . $err);
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'header' => implode("\r\n", $headers),
            'timeout' => $timeout, 'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $code = (int)$m[1];
            }
        }
        if ($body === false) {
            throw new SteinException('Verbindung zur Stein.APP fehlgeschlagen.');
        }
    }

    if ($code === 429) {
        // Laut Dokumentation sperrt die Stein.APP die IP dann für eine Stunde
        $pause = max(60, stein_interval_minutes());
        setting_save('stein_pause_bis', (string)(time() + $pause * 60));
        throw new SteinException(sprintf(
            'Rate Limit erreicht (HTTP 429). Die Stein.APP sperrt die IP dafür bis zu einer Stunde; '
            . 'der Abruf pausiert %d Minuten.',
            $pause
        ));
    }
    if ($code === 401 || $code === 403) {
        throw new SteinException('Die Stein.APP hat den Schlüssel abgelehnt (HTTP ' . $code . ').');
    }
    if ($code === 404) {
        throw new SteinException(
            'Die Stein.APP antwortete mit HTTP 404. Entweder stimmt die Adresse nicht, '
            . 'oder der Zugriff kommt von einer IP-Adresse außerhalb Deutschlands – die ist dort gesperrt.'
        );
    }
    if ($code < 200 || $code >= 300) {
        throw new SteinException('Die Stein.APP antwortete mit HTTP ' . $code . '.');
    }

    $daten = json_decode((string)$body, true);
    if (!is_array($daten)) {
        throw new SteinException('Die Antwort der Stein.APP war kein gültiges JSON.');
    }
    return $daten;
}

/** Die Fahrzeuge der eingestellten BU-ID holen – ein Aufruf je Abgleich */
function stein_fetch_assets(): array
{
    $bu = trim((string)setting('stein_bu_id', ''));
    if ($bu === '') {
        throw new SteinException('Die Stein.APP ist nicht vollständig eingerichtet (Schlüssel und BU-ID).');
    }
    $daten = stein_request('/assets/', ['buIds' => $bu]);

    // Je nach Fassung steckt die Liste in einem Umschlag
    foreach (['data', 'items', 'assets', 'content'] as $schluessel) {
        if (isset($daten[$schluessel]) && is_array($daten[$schluessel])) {
            $daten = $daten[$schluessel];
            break;
        }
    }
    return array_values(array_filter($daten, 'is_array'));
}

/**
 * Verbindungstest: fragt, wem der Schlüssel gehört. Ein eigener, sehr
 * kleiner Endpunkt – er belastet den Abruf der Fahrzeuge nicht.
 */
function stein_userinfo(): array
{
    return stein_request('/userinfo');
}

/** Anzeigename eines Assets, wie in der Stein.APP zusammengesetzt */
function stein_asset_name(array $a): string
{
    $teile = array_filter([
        trim((string)($a['label'] ?? '')),
        trim((string)($a['radioName'] ?? '')),
        trim((string)($a['name'] ?? '')),
    ], static fn($t) => $t !== '');
    return $teile ? implode(' · ', array_unique($teile)) : 'Fahrzeug ' . (string)($a['id'] ?? '?');
}

/** Felder, in denen die Stein.APP ein Kennzeichen fuehren kann */
const STEIN_KENNZEICHEN_FELDER = ['licensePlate', 'numberPlate', 'plate', 'kennzeichen', 'registration'];

/**
 * Kennzeichen eines Assets: erst die eigenen Felder, sonst aus den Texten.
 * Erkannt werden THW-Kennzeichen (THW-84321) und gewöhnliche deutsche
 * Kennzeichen (HH-AB 123). Gibt null zurück, wenn keines zu finden ist.
 */
function stein_plate(array $asset): ?string
{
    foreach (STEIN_KENNZEICHEN_FELDER as $feld) {
        $wert = trim((string)($asset[$feld] ?? ''));
        if ($wert !== '') {
            return mb_substr($wert, 0, 20);
        }
    }
    foreach (['label', 'name', 'radioName', 'comment'] as $feld) {
        $treffer = stein_plate_in_text((string)($asset[$feld] ?? ''));
        if ($treffer !== null) {
            return $treffer;
        }
    }
    return null;
}

/**
 * Abkürzungen von Fahrzeugtypen, die wie ein Unterscheidungszeichen aussehen.
 * "MLW-IV 2" ist kein Kennzeichen, sondern eine Typbezeichnung.
 */
const STEIN_KEIN_KREIS = ['GKW', 'MLW', 'MTW', 'LKW', 'PKW', 'WLF', 'MZB', 'BKF', 'FGR', 'ZTR', 'ANH'];

/**
 * Kennzeichen in einem Text suchen – ohne Datenbank, damit leicht prüfbar.
 * Der Bindestrich ist Pflicht: sonst gilt schon "GKW 1" als Kennzeichen.
 */
function stein_plate_in_text(string $text): ?string
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    if (preg_match('/\bTHW[\s-]?(\d{3,6})\b/iu', $text, $m)) {
        return 'THW-' . $m[1];
    }
    if (preg_match_all('/\b([A-ZÄÖÜ]{1,3})-([A-ZÄÖÜ]{1,2})[ ]?(\d{1,4}[EH]?)\b/u', $text, $treffer, PREG_SET_ORDER)) {
        foreach ($treffer as $m) {
            if (!in_array(mb_strtoupper($m[1]), STEIN_KEIN_KREIS, true)) {
                return $m[1] . '-' . $m[2] . ' ' . $m[3];
            }
        }
    }
    return null;
}

/** Wert eines Stein-Feldes lesbar machen */
function stein_value_text(string $feld, mixed $wert): string
{
    if (in_array($feld, STEIN_JANEIN, true)) {
        return $wert ? 'ja' : 'nein';
    }
    if ($wert === null || $wert === '') {
        return '';
    }
    if ($feld === 'status') {
        return STEIN_STATUS[(string)$wert][1] ?? (string)$wert;
    }
    if (in_array($feld, STEIN_JANEIN, true)) {
        return $wert ? 'ja' : 'nein';
    }
    if (in_array($feld, ['huValidUntil', 'spValidUntil'], true)) {
        return de_date(substr((string)$wert, 0, 10));
    }
    return trim((string)$wert);
}

/**
 * Unterschied zwischen zwei Ständen eines Assets.
 * Rückgabe: [['feld','label','alt','neu'], ...] – ohne Datenbank.
 */
function stein_diff(array $alt, array $neu): array
{
    $out = [];
    foreach (STEIN_FELDER as $feld => $label) {
        // Felder, die im neuen Stand gar nicht vorkommen, gelten als unverändert
        if (!array_key_exists($feld, $neu)) {
            continue;
        }
        $a = stein_value_text($feld, $alt[$feld] ?? null);
        $b = stein_value_text($feld, $neu[$feld]);
        if ($a !== $b) {
            $out[] = ['feld' => $feld, 'label' => $label, 'alt' => $a, 'neu' => $b];
        }
    }
    return $out;
}

/** Datum aus der Stein.APP auf unser Format kürzen */
function stein_date(mixed $wert): ?string
{
    $s = trim((string)($wert ?? ''));
    return preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m) ? $m[1] : null;
}

/**
 * Stammdaten eines Fahrzeugs aus dem Stein-Stand ableiten.
 * Nur was die Stein.APP führt: Status, HU, SP, Funkrufname. Eigene Felder
 * werden nur gefüllt, wenn sie leer sind – Eingetragenes bleibt stehen.
 */
function stein_vehicle_data(array $asset, array $vehicle): array
{
    $data = [
        'stein_status'  => (string)($asset['status'] ?? ''),
        'stein_daten'   => json_encode($asset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'stein_sync_at' => date('Y-m-d H:i:s'),
    ];

    $slug = STEIN_STATUS[(string)($asset['status'] ?? '')][0] ?? null;
    if ($slug && ($id = list_id_by_slug('fahrzeug_status', $slug))) {
        $data['status_id'] = $id;
    }
    foreach (['huValidUntil' => 'hu_bis', 'spValidUntil' => 'sp_bis'] as $von => $nach) {
        $d = stein_date($asset[$von] ?? null);
        if ($d !== null) {
            $data[$nach] = $d;
        }
    }
    $funk = trim((string)($asset['radioName'] ?? ''));
    if ($funk !== '' && trim((string)($vehicle['funkrufname'] ?? '')) === '') {
        $data['funkrufname'] = mb_substr($funk, 0, 80);
    }

    // Kennzeichen: die Schnittstelle hat kein eigenes Feld dafür, es steckt
    // in den Texten. Ein selbst eingetragenes Kennzeichen bleibt stehen.
    $kennzeichen = stein_plate($asset);
    if ($kennzeichen !== null && trim((string)($vehicle['kennzeichen'] ?? '')) === '') {
        $data['kennzeichen'] = $kennzeichen;
    }
    return $data;
}

/**
 * Abgleich durchführen.
 * Rückgabe: ['status','assets','zuordnungen','aenderungen','message']
 */
function stein_sync(bool $erzwingen = false): array
{
    $start = microtime(true);

    if (!stein_enabled()) {
        return ['status' => 'aus', 'assets' => 0, 'zuordnungen' => 0, 'aenderungen' => 0,
                'message' => 'Der Abgleich mit der Stein.APP ist nicht eingeschaltet.'];
    }
    if (!$erzwingen) {
        $grund = stein_wait_reason(
            stein_last_sync(),
            stein_interval_minutes(),
            (int)setting('stein_pause_bis', '0'),
            time()
        );
        if ($grund !== null) {
            return ['status' => 'wartet', 'assets' => 0, 'zuordnungen' => 0, 'aenderungen' => 0, 'message' => $grund];
        }
    }

    // Zeitstempel vor dem Aufruf setzen: auch ein Fehlschlag zählt gegen das Rate Limit
    setting_save('stein_letzter_abruf', (string)time());

    try {
        $assets = stein_fetch_assets();
    } catch (SteinException $ex) {
        $res = ['status' => 'fehler', 'assets' => 0, 'zuordnungen' => 0, 'aenderungen' => 0,
                'message' => $ex->getMessage()];
        stein_log($res, $start);
        return $res;
    }

    setting_save('stein_pause_bis', '0');

    $user = null;
    $zuordnungen = 0;
    $aenderungen = 0;
    $offen = [];

    foreach ($assets as $asset) {
        $assetId = trim((string)($asset['id'] ?? ''));
        if ($assetId === '') {
            continue;
        }
        $vehicle = vehicle_by_stein($assetId);

        if (!$vehicle) {
            // Automatisch anlegen nur mit erkennbarem Kennzeichen – sonst
            // entstehen Akten für Anhänger, Aggregate und Geräte
            $kennzeichen = stein_plate($asset);
            $geloescht = !empty($asset['deleted']);
            if (!setting_bool('stein_auto_anlegen', false) || $kennzeichen === null || $geloescht) {
                // Für die Zuordnung in der Verwaltung merken – ohne neuen Aufruf
                $offen[] = [
                    'id'          => $assetId,
                    'name'        => stein_asset_name($asset),
                    'status'      => stein_value_text('status', $asset['status'] ?? ''),
                    'funk'        => trim((string)($asset['radioName'] ?? '')),
                    'kennzeichen' => (string)($kennzeichen ?? ''),
                    'grund'       => $geloescht
                        ? 'in der Stein.APP gelöscht'
                        : ($kennzeichen === null && setting_bool('stein_auto_anlegen', false)
                            ? 'kein Kennzeichen erkannt' : ''),
                    'geloescht'   => $geloescht,
                ];
                continue;
            }
            $id = db_insert('vehicles', [
                'bezeichnung'    => mb_substr(stein_asset_name($asset), 0, 150),
                'funkrufname'    => mb_substr(trim((string)($asset['radioName'] ?? '')), 0, 80),
                'kennzeichen'    => $kennzeichen,
                'stein_asset_id' => $assetId,
                'status_id'      => list_default_id('fahrzeug_status'),
            ]);
            journal_add($id, [
                'art'    => 'anlage',
                'titel'  => 'Fahrzeugakte aus der Stein.APP angelegt',
                'text'   => stein_asset_name($asset) . ' · Kennzeichen ' . $kennzeichen,
                'quelle' => 'stein',
                'autor'  => 'Stein.APP',
            ], null);
            $vehicle = vehicle_by_stein($assetId);
            $zuordnungen++;
            if (!$vehicle) {
                continue;
            }
        }

        $alt = json_decode((string)($vehicle['stein_daten'] ?? ''), true);
        $alt = is_array($alt) ? $alt : [];
        $erstkontakt = $alt === [];

        foreach (stein_diff($alt, $asset) as $d) {
            // Beim ersten Abgleich ist noch nichts "geändert" – nur den Stand merken
            if ($erstkontakt) {
                break;
            }
            journal_add((int)$vehicle['id'], [
                'art'      => 'stein',
                'titel'    => $d['label'] . ' in der Stein.APP geändert',
                'feld'     => $d['feld'],
                'alt_wert' => $d['alt'],
                'neu_wert' => $d['neu'],
                'text'     => trim((string)($asset['lastModifiedBy'] ?? '')) !== ''
                    ? 'Zuletzt geändert von ' . (string)$asset['lastModifiedBy'] : '',
                'quelle'   => 'stein',
                'autor'    => 'Stein.APP',
            ], null);
            $aenderungen++;
        }

        if ($erstkontakt) {
            journal_add((int)$vehicle['id'], [
                'art'    => 'stein',
                'titel'  => 'Mit der Stein.APP verbunden',
                'text'   => 'Stand übernommen: ' . stein_value_text('status', $asset['status'] ?? ''),
                'quelle' => 'stein',
                'autor'  => 'Stein.APP',
            ], null);
        }

        $daten = stein_vehicle_data($asset, $vehicle);
        if (isset($daten['kennzeichen']) && !$erstkontakt) {
            journal_add((int)$vehicle['id'], [
                'art'      => 'stein',
                'titel'    => 'Kennzeichen aus der Stein.APP übernommen',
                'feld'     => 'kennzeichen',
                'neu_wert' => $daten['kennzeichen'],
                'quelle'   => 'stein',
                'autor'    => 'Stein.APP',
            ], null);
            $aenderungen++;
        }
        db_update('vehicles', $daten, 'id = ?', [(int)$vehicle['id']]);
    }

    setting_save('stein_offene_assets', json_encode($offen));

    $res = [
        'status'      => 'ok',
        'assets'      => count($assets),
        'zuordnungen' => $zuordnungen,
        'aenderungen' => $aenderungen,
        'message'     => sprintf('%d Fahrzeug(e) abgerufen, %d Änderung(en) in den Akten%s.',
            count($assets), $aenderungen, $offen ? ', ' . count($offen) . ' ohne Zuordnung' : ''),
    ];
    stein_log($res, $start);
    return $res;
}

function stein_log(array $res, float $start): void
{
    db_insert('stein_log', [
        'status'      => $res['status'],
        'assets'      => (int)$res['assets'],
        'zuordnungen' => (int)$res['zuordnungen'],
        'aenderungen' => (int)$res['aenderungen'],
        'dauer_ms'    => (int)round((microtime(true) - $start) * 1000),
        'message'     => mb_substr((string)$res['message'], 0, 500),
    ]);
}

/**
 * Abgleich beim Öffnen des Moduls – nur wenn eingeschaltet und fällig.
 * Läuft still: Fehler stehen im Protokoll, nicht auf der Seite.
 */
function stein_sync_if_due(): void
{
    if (!stein_enabled() || !setting_bool('stein_sync_beim_aufruf', true)) {
        return;
    }
    if (stein_wait_reason(stein_last_sync(), stein_interval_minutes(), (int)setting('stein_pause_bis', '0'), time()) !== null) {
        return;
    }
    try {
        stein_sync();
    } catch (Throwable) {
        // Der Abruf darf die Seite nie aufhalten
    }
}

/**
 * Webhook der Stein.APP entgegennehmen.
 *
 * Die Stein.APP meldet nur, WAS sich geändert hat, nicht die Daten selbst.
 * Wir holen daraufhin den gewohnten Vollstand – das ist ein Aufruf und
 * bleibt weit unter dem Limit von 20 Anfragen je Minute.
 *
 * Rückgabe: [HTTP-Code, Meldung]
 */
function stein_webhook_handle(string $secret, string $body): array
{
    $erwartet = trim((string)setting('stein_webhook_secret', ''));
    if ($erwartet === '') {
        return [403, 'Der Webhook ist nicht eingerichtet (kein Secret hinterlegt).'];
    }
    if (!hash_equals($erwartet, trim($secret))) {
        return [403, 'Falsches Secret.'];
    }
    if (!stein_enabled()) {
        return [200, 'Der Abgleich mit der Stein.APP ist ausgeschaltet – nichts zu tun.'];
    }

    $daten = json_decode($body, true);
    $posten = is_array($daten) && isset($daten['items']) && is_array($daten['items']) ? $daten['items'] : [];
    $fahrzeuge = 0;
    foreach ($posten as $i) {
        if (is_array($i) && ($i['type'] ?? '') === 'asset') {
            $fahrzeuge++;
        }
    }
    if ($posten && $fahrzeuge === 0) {
        return [200, 'Keine Fahrzeugänderung enthalten – kein Abruf nötig.'];
    }

    // Auch bei vielen Meldungen kurz hintereinander nur selten abrufen
    $abstand = time() - stein_last_sync();
    if ($abstand < STEIN_WEBHOOK_MINDESTABSTAND) {
        return [200, sprintf('Erst vor %d Sekunden abgerufen – der Stand ist aktuell.', $abstand)];
    }

    $res = stein_sync(true);
    return [$res['status'] === 'fehler' ? 502 : 200, $res['message']];
}

/** Assets, die noch keinem Fahrzeug zugeordnet sind (aus dem letzten Abruf) */
function stein_pending_assets(): array
{
    $liste = json_decode((string)setting('stein_offene_assets', '[]'), true);
    if (!is_array($liste)) {
        return [];
    }
    $out = [];
    foreach ($liste as $a) {
        if (is_array($a) && ($a['id'] ?? '') !== '' && !vehicle_by_stein((string)$a['id'])) {
            $out[] = $a + ['name' => '', 'status' => '', 'funk' => '', 'kennzeichen' => '', 'grund' => '', 'geloescht' => false];
        }
    }
    return $out;
}

/** Ein offenes Asset einem Fahrzeug zuordnen */
function stein_assign(int $vehicleId, string $assetId, ?array $user = null): ?string
{
    $vehicle = vehicle_find($vehicleId);
    if (!$vehicle) {
        return 'Fahrzeug nicht gefunden.';
    }
    if (vehicle_by_stein($assetId)) {
        return 'Dieses Fahrzeug aus der Stein.APP ist bereits zugeordnet.';
    }
    db_update('vehicles', ['stein_asset_id' => $assetId], 'id = ?', [$vehicleId]);
    journal_add($vehicleId, [
        'art'      => 'stein',
        'titel'    => 'Mit der Stein.APP verknüpft',
        'neu_wert' => $assetId,
    ], $user);
    audit('fahrzeug.stein_verknuepft', 'vehicle', $vehicleId, $assetId);
    return null;
}
