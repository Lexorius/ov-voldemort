<?php
declare(strict_types=1);

/*
 * Anbindung an die Stein.APP
 *
 * Die Schnittstelle hat ein striktes Rate Limit. Deshalb:
 *   - je Abgleich genau EIN Aufruf (die Liste aller Fahrzeuge der BU-ID)
 *   - zwischen zwei Abgleichen liegt mindestens das eingestellte Intervall
 *     (Vorgabe 10 Minuten); der Abstand wird serverseitig erzwungen
 *   - antwortet die Schnittstelle mit 429, wird eine Pause eingelegt
 *
 * Aus dem Vollbild wird der Unterschied zum letzten Stand berechnet; jede
 * Änderung landet als Eintrag in der Fahrzeugakte.
 *
 * Endpunkt: GET <base>/assets/?buIds=<BU-ID>, Bearer-Token im Header.
 */

class SteinException extends RuntimeException {}

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
    'issi'                 => 'ISSI',
];

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

/** Ein Aufruf gegen die Schnittstelle. Gibt die Liste der Assets zurück. */
function stein_fetch_assets(): array
{
    $base = rtrim((string)setting('stein_base_url', 'https://stein.app/api/api/ext'), '/');
    $key  = trim((string)setting('stein_api_key', ''));
    $bu   = trim((string)setting('stein_bu_id', ''));
    if ($base === '' || $key === '' || $bu === '') {
        throw new SteinException('Die Stein.APP ist nicht vollständig eingerichtet (Schlüssel und BU-ID).');
    }

    $url = $base . '/assets/?buIds=' . rawurlencode($bu);
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
        $pause = max(2, stein_interval_minutes() * 3);
        setting_save('stein_pause_bis', (string)(time() + $pause * 60));
        throw new SteinException(sprintf('Rate Limit erreicht (HTTP 429). Der Abruf pausiert %d Minuten.', $pause));
    }
    if ($code === 401 || $code === 403) {
        throw new SteinException('Die Stein.APP hat den Schlüssel abgelehnt (HTTP ' . $code . ').');
    }
    if ($code < 200 || $code >= 300) {
        throw new SteinException('Die Stein.APP antwortete mit HTTP ' . $code . '.');
    }

    $daten = json_decode((string)$body, true);
    if (!is_array($daten)) {
        throw new SteinException('Die Antwort der Stein.APP war kein gültiges JSON.');
    }
    // Je nach Endpunkt steckt die Liste in einem Umschlag
    foreach (['data', 'items', 'assets', 'content'] as $schluessel) {
        if (isset($daten[$schluessel]) && is_array($daten[$schluessel])) {
            $daten = $daten[$schluessel];
            break;
        }
    }
    return array_values(array_filter($daten, 'is_array'));
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

/** Wert eines Stein-Feldes lesbar machen */
function stein_value_text(string $feld, mixed $wert): string
{
    if ($wert === null || $wert === '') {
        return '';
    }
    if ($feld === 'status') {
        return STEIN_STATUS[(string)$wert][1] ?? (string)$wert;
    }
    if ($feld === 'operationReservation') {
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
            if (!setting_bool('stein_auto_anlegen', false)) {
                // Für die Zuordnung in der Verwaltung merken – ohne neuen Aufruf
                $offen[] = [
                    'id'     => $assetId,
                    'name'   => stein_asset_name($asset),
                    'status' => stein_value_text('status', $asset['status'] ?? ''),
                    'funk'   => trim((string)($asset['radioName'] ?? '')),
                ];
                continue;
            }
            $id = db_insert('vehicles', [
                'bezeichnung'    => mb_substr(stein_asset_name($asset), 0, 150),
                'funkrufname'    => mb_substr(trim((string)($asset['radioName'] ?? '')), 0, 80),
                'stein_asset_id' => $assetId,
                'status_id'      => list_default_id('fahrzeug_status'),
            ]);
            journal_add($id, [
                'art'    => 'anlage',
                'titel'  => 'Fahrzeugakte aus der Stein.APP angelegt',
                'text'   => stein_asset_name($asset),
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

        db_update('vehicles', stein_vehicle_data($asset, $vehicle), 'id = ?', [(int)$vehicle['id']]);
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
            $out[] = $a + ['name' => '', 'status' => '', 'funk' => ''];
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
