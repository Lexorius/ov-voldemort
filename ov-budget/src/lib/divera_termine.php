<?php
declare(strict_types=1);

/**
 * Termine nach Divera 24/7: Kalender-Termine, Veranstaltungen und
 * Besprechungen werden auf Wunsch als Termin in Divera angelegt, bei
 * Änderungen nachgezogen und bei Absage oder Löschung dort archiviert.
 * Die Rückmeldungen (Empfänger, gelesen, geantwortet) holt der minütliche
 * Abruf zurück.
 *
 * Schnittstelle: api.divera247.com, Definition api/v2/event (Version 4.1.1)
 *   POST   /api/v2/events               anlegen, JSON {"Event": {...}}
 *   PUT    /api/v2/events/{id}          ändern
 *   POST   /api/v2/events/archive/{id}  archivieren
 *   GET    /api/v2/events/{id}          Stand samt Zählern
 *   GET    /api/v2/pull/all             Gruppen der Einheit (data.cluster.group)
 * Der Zugang läuft über den persönlichen Accesskey als Parameter. Unsere
 * Kennung steht als foreign_id in Divera („ovb-termin-5"), damit nichts
 * doppelt entsteht.
 *
 * In der kostenlosen Divera-Version darf nur ein Termin je fünf Minuten
 * angelegt werden; gespeichert wird dort nur der Titel.
 */

const DIVERA_TERMIN_TYPEN = ['termin' => 'Termin', 'veranstaltung' => 'Veranstaltung', 'besprechung' => 'Besprechung'];
const DIVERA_TERMIN_VERSUCHE_MAX = 5;
const DIVERA_TERMIN_JE_ABRUF = 10;
const DIVERA_GRUPPEN_CACHE_SEK = 3600;

function divera_termine_aktiv(): bool
{
    return divera_enabled() && setting_bool('divera_termine_aktiv', false) && trim((string)setting('divera_personal_key', '')) !== '';
}

/* ==================================================================== */
/*  Anfragen                                                              */
/* ==================================================================== */

/**
 * JSON-Anfrage an Divera mit dem persönlichen Accesskey. Wirft
 * DiveraException. Die Prüfungen hängen über $GLOBALS['divera_http_attrappe'].
 */
function divera_termine_request(string $methode, string $pfad, ?array $json = null): array
{
    if (isset($GLOBALS['divera_http_attrappe']) && is_callable($GLOBALS['divera_http_attrappe'])) {
        return ($GLOBALS['divera_http_attrappe'])($methode, $pfad, $json);
    }
    $base = rtrim((string)setting('divera_base_url', 'https://app.divera247.com/api'), '/');
    // Die Basis-URL endet auf /api; die Pfade der Dokumentation beginnen mit /api
    $base = preg_replace('#/api$#', '', $base) ?? $base;
    $key = trim((string)setting('divera_personal_key', ''));
    if ($base === '' || $key === '') {
        throw new DiveraException('Divera ist für Termine nicht vollständig konfiguriert (Basis-URL / persönlicher Accesskey).');
    }
    $url = $base . $pfad . (str_contains($pfad, '?') ? '&' : '?') . 'accesskey=' . rawurlencode($key);
    $koerper = $json !== null ? (string)json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $timeout = max(3, setting_int('divera_timeout', 15));
    $headers = ['Accept: application/json', 'Content-Type: application/json'];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $methode,
            CURLOPT_USERAGENT      => 'OV-Multitool/1.0',
        ]);
        if ($koerper !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $koerper);
        }
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($errno) {
            throw new DiveraException('Verbindungsfehler: ' . $err);
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => $methode, 'content' => $koerper ?? '', 'header' => implode("\r\n", $headers), 'timeout' => $timeout, 'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $code = (int)$m[1];
            }
        }
        if ($body === false) {
            throw new DiveraException('Verbindung zur Divera-API fehlgeschlagen.');
        }
    }
    if ($code === 404) {
        throw new DiveraException('Divera kennt den Termin nicht (mehr) oder der Zugriff ist nicht gestattet.');
    }
    if ($code >= 400) {
        throw new DiveraException('Divera antwortete mit HTTP ' . $code . ': ' . mb_substr((string)$body, 0, 300));
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        throw new DiveraException('Antwort war kein gültiges JSON: ' . mb_substr((string)$body, 0, 200));
    }
    if (array_key_exists('success', $data) && $data['success'] === false) {
        $meldung = $data['message'] ?? ($data['errors'] ?? 'unbekannt');
        throw new DiveraException('Divera meldet einen Fehler: ' . (is_string($meldung) ? $meldung : json_encode($meldung, JSON_UNESCAPED_UNICODE)));
    }
    return $data;
}

/* ==================================================================== */
/*  Gruppen                                                               */
/* ==================================================================== */

/** Gruppen der Einheit als id => Name, eine Stunde zwischengespeichert; bei Fehlern leer */
function divera_gruppen(bool $frisch = false): array
{
    $cache = json_decode(state_get('divera_gruppen', ''), true);
    if (!$frisch && is_array($cache) && (int)($cache['am'] ?? 0) > time() - DIVERA_GRUPPEN_CACHE_SEK && is_array($cache['gruppen'] ?? null)) {
        return $cache['gruppen'];
    }
    try {
        $antwort = divera_termine_request('GET', '/api/v2/pull/all');
        $gruppen = divera_gruppen_aus_antwort($antwort);
        state_save('divera_gruppen', json_encode(['am' => time(), 'gruppen' => $gruppen], JSON_UNESCAPED_UNICODE));
        state_save('divera_gruppen_fehler', '');
        return $gruppen;
    } catch (DiveraException $ex) {
        state_save('divera_gruppen_fehler', mb_substr($ex->getMessage(), 0, 300));
        return is_array($cache['gruppen'] ?? null) ? $cache['gruppen'] : [];
    }
}

/** data.cluster.group aus /pull/all in id => Name, in der Reihenfolge von groupsorting. Reine Funktion. */
function divera_gruppen_aus_antwort(array $antwort): array
{
    $cluster = $antwort['data']['cluster'] ?? [];
    $roh = $cluster['group'] ?? [];
    $sortierung = $cluster['groupsorting'] ?? [];
    $out = [];
    foreach (is_array($sortierung) ? $sortierung : [] as $id) {
        if (isset($roh[(string)$id]) || isset($roh[(int)$id])) {
            $g = $roh[(string)$id] ?? $roh[(int)$id];
            $out[(int)$id] = (string)($g['name'] ?? ('Gruppe ' . $id));
        }
    }
    foreach (is_array($roh) ? $roh : [] as $id => $g) {
        if (!isset($out[(int)$id]) && is_array($g)) {
            $out[(int)$id] = (string)($g['name'] ?? ('Gruppe ' . $id));
        }
    }
    return $out;
}

/* ==================================================================== */
/*  Eintrag je Termin                                                     */
/* ==================================================================== */

function divera_termin_typ(string $typ): string
{
    return array_key_exists($typ, DIVERA_TERMIN_TYPEN) ? $typ : 'termin';
}

function divera_termin_eintrag(string $typ, int $refId): ?array
{
    $row = db_row('SELECT * FROM divera_termine WHERE typ = ? AND ref_id = ?', [divera_termin_typ($typ), $refId]);
    if ($row) {
        $row['optionen'] = divera_termin_optionen_normieren(json_decode((string)($row['optionen'] ?? ''), true) ?: []);
        $row['stand'] = json_decode((string)($row['stand'] ?? ''), true) ?: [];
    }
    return $row ?: null;
}

/** Vorgaben und Grenzen der Optionen. Reine Funktion. */
function divera_termin_optionen_normieren(array $o): array
{
    $gruppen = array_values(array_unique(array_filter(array_map('intval', (array)($o['gruppen'] ?? [])), static fn($g) => $g > 0)));
    return [
        'empfaenger'   => ($o['empfaenger'] ?? 'alle') === 'gruppen' && $gruppen ? 'gruppen' : 'alle',
        'gruppen'      => $gruppen,
        'push'         => !empty($o['push']),
        'rueckmeldung' => !empty($o['rueckmeldung']),
        'text'         => !empty($o['text']),
    ];
}

/** Optionen aus dem Formular; null, wenn der Abschnitt gar nicht im Formular war. */
function divera_termin_optionen_aus_post(): ?array
{
    if (post_str('divera_feld') !== '1') {
        return null;
    }
    return ['an' => post_bool('divera_an') === 1] + divera_termin_optionen_normieren([
        'empfaenger'   => post_str('divera_empfaenger', 'alle'),
        'gruppen'      => (array)($_POST['divera_gruppen'] ?? []),
        'push'         => post_bool('divera_push'),
        'rueckmeldung' => post_bool('divera_rueckmeldung'),
        'text'         => post_bool('divera_text'),
    ]);
}

/**
 * Die Quelle eines Eintrags in einer Form: titel, text, ort, beginn, ende
 * (Y-m-d H:i:s oder null), ganztag, abgesagt. null, wenn es sie nicht mehr gibt.
 */
function divera_termin_quelle(string $typ, int $refId): ?array
{
    switch (divera_termin_typ($typ)) {
        case 'veranstaltung':
            $e = function_exists('event_find') ? event_find($refId) : null;
            if (!$e) {
                return null;
            }
            return ['titel' => (string)$e['titel'], 'text' => trim((string)($e['beschreibung'] ?? '')),
                'ort' => function_exists('event_ort_text') ? event_ort_text($e) : trim((string)($e['ort'] ?? '')),
                'beginn' => (string)$e['beginn'], 'ende' => $e['ende'] !== null ? (string)$e['ende'] : null, 'ganztag' => false,
                'abgesagt' => (string)($e['status'] ?? '') === 'abgesagt'];
        case 'besprechung':
            $m = function_exists('meeting_find') ? meeting_find($refId) : null;
            if (!$m) {
                return null;
            }
            $datum = substr((string)$m['datum'], 0, 10);
            $endeDatum = (string)($m['ende_datum'] ?? '') !== '' ? substr((string)$m['ende_datum'], 0, 10) : $datum;
            $ganztag = (string)($m['beginn'] ?? '') === '';
            return ['titel' => (string)$m['titel'], 'text' => '',
                'ort' => function_exists('meeting_treffen_text') ? meeting_treffen_text($m) : trim((string)($m['ort'] ?? '')),
                'beginn' => $ganztag ? $datum . ' 00:00:00' : $datum . ' ' . substr((string)$m['beginn'], 0, 8),
                'ende' => $ganztag ? $endeDatum . ' 23:59:59' : ((string)($m['ende'] ?? '') !== '' ? $endeDatum . ' ' . substr((string)$m['ende'], 0, 8) : null),
                'ganztag' => $ganztag, 'abgesagt' => (string)($m['status'] ?? '') === 'abgesagt'];
        default:
            $t = function_exists('kalender_find') ? kalender_find($refId) : null;
            if (!$t) {
                return null;
            }
            return ['titel' => (string)$t['titel'], 'text' => trim((string)($t['beschreibung'] ?? '')),
                'ort' => function_exists('kalender_ort_text') ? kalender_ort_text($t) : trim((string)($t['ort'] ?? '')),
                'beginn' => (string)$t['beginn'], 'ende' => $t['ende'] !== null ? (string)$t['ende'] : null,
                'ganztag' => (int)($t['ganztag'] ?? 0) === 1, 'abgesagt' => false];
    }
}

/** Die Nutzlast für Divera. Reine Funktion. */
function divera_termin_payload(array $quelle, array $optionen, string $foreignId): array
{
    $o = divera_termin_optionen_normieren($optionen);
    $beginn = strtotime((string)$quelle['beginn']) ?: time();
    $ev = [
        'foreign_id'        => $foreignId,
        'title'             => mb_substr(trim((string)$quelle['titel']), 0, 200),
        'address'           => mb_substr(trim((string)($quelle['ort'] ?? '')), 0, 200),
        'ts_start'          => $beginn,
        'fullday'           => !empty($quelle['ganztag']),
        'private_mode'      => false,
        'notification_type' => $o['empfaenger'] === 'gruppen' ? 3 : 2,
        'send_push'         => $o['push'],
    ];
    if ($o['text'] && trim((string)($quelle['text'] ?? '')) !== '') {
        $ev['text'] = mb_substr(trim((string)$quelle['text']), 0, 4000);
    }
    if (!empty($quelle['ende'])) {
        $ende = strtotime((string)$quelle['ende']) ?: $beginn;
        $ev['ts_end'] = max($ende, $beginn);
    }
    if (!empty($quelle['ganztag'])) {
        $tage = !empty($quelle['ende']) ? (int)floor((strtotime(substr((string)$quelle['ende'], 0, 10)) - strtotime(substr((string)$quelle['beginn'], 0, 10))) / 86400) + 1 : 1;
        $ev['days'] = max(1, $tage);
    }
    if ($o['empfaenger'] === 'gruppen') {
        $ev['group'] = $o['gruppen'];
    }
    if ($o['rueckmeldung']) {
        $ev['response_type'] = 3;
        $ev['show_result_count'] = 2;
        $ev['show_result_names'] = 1;
    }
    return ['Event' => $ev];
}

function divera_termin_foreign_id(string $typ, int $refId): string
{
    return 'ovb-' . divera_termin_typ($typ) . '-' . $refId;
}

/* ==================================================================== */
/*  Schreiben                                                             */
/* ==================================================================== */

/** Eintrag anlegen oder Optionen ändern, dann gleich an Divera schicken */
function divera_termin_speichern(string $typ, int $refId, array $optionen): array
{
    $typ = divera_termin_typ($typ);
    $quelle = divera_termin_quelle($typ, $refId);
    $daten = [
        'optionen' => json_encode(divera_termin_optionen_normieren($optionen), JSON_UNESCAPED_UNICODE),
        'beginn'   => $quelle['beginn'] ?? null,
        'status'   => 'offen',
        'versuche' => 0,
        'fehler'   => '',
    ];
    $row = divera_termin_eintrag($typ, $refId);
    if ($row) {
        db_update('divera_termine', $daten, 'id = ?', [(int)$row['id']]);
    } else {
        db_insert('divera_termine', $daten + ['typ' => $typ, 'ref_id' => $refId, 'foreign_id' => divera_termin_foreign_id($typ, $refId), 'created_at' => date('Y-m-d H:i:s')]);
    }
    $row = divera_termin_eintrag($typ, $refId) ?? ($daten + ['id' => 0, 'typ' => $typ, 'ref_id' => $refId, 'divera_id' => null, 'optionen' => divera_termin_optionen_normieren($optionen)]);
    return divera_termin_senden($row);
}

/** Anlegen oder ändern in Divera; bei Absage archivieren. Rückgabe: ok, meldung */
function divera_termin_senden(array $row): array
{
    $typ = (string)$row['typ'];
    $refId = (int)$row['ref_id'];
    $quelle = divera_termin_quelle($typ, $refId);
    if ($quelle === null) {
        divera_termin_entfernen($typ, $refId);
        return ['ok' => false, 'meldung' => 'Quelle gibt es nicht mehr – Eintrag entfernt.'];
    }
    if ($quelle['abgesagt']) {
        divera_termin_archivieren($row);
        return ['ok' => true, 'meldung' => 'abgesagt – in Divera archiviert'];
    }
    $optionen = is_array($row['optionen']) ? $row['optionen'] : divera_termin_optionen_normieren(json_decode((string)$row['optionen'], true) ?: []);
    $payload = divera_termin_payload($quelle, $optionen, divera_termin_foreign_id($typ, $refId));
    try {
        if (!empty($row['divera_id'])) {
            $antwort = divera_termine_request('PUT', '/api/v2/events/' . (int)$row['divera_id'], $payload);
        } else {
            $antwort = divera_termine_request('POST', '/api/v2/events', $payload);
        }
        $diveraId = (int)($antwort['data']['id'] ?? $row['divera_id'] ?? 0);
        if (!empty($row['id'])) {
            db_update('divera_termine', ['divera_id' => $diveraId ?: null, 'status' => 'gesendet', 'fehler' => '', 'versuche' => 0,
                'gesendet_am' => date('Y-m-d H:i:s'), 'beginn' => $quelle['beginn']], 'id = ?', [(int)$row['id']]);
        }
        audit('divera.termin', $typ, $refId, (!empty($row['divera_id']) ? 'geändert' : 'angelegt') . ' #' . $diveraId);
        return ['ok' => true, 'meldung' => (!empty($row['divera_id']) ? 'in Divera geändert' : 'in Divera angelegt') . ($diveraId ? ' (#' . $diveraId . ')' : '')];
    } catch (DiveraException $ex) {
        if (!empty($row['id'])) {
            db_update('divera_termine', ['status' => 'fehler', 'fehler' => mb_substr($ex->getMessage(), 0, 300), 'versuche' => (int)($row['versuche'] ?? 0) + 1], 'id = ?', [(int)$row['id']]);
        }
        return ['ok' => false, 'meldung' => $ex->getMessage()];
    }
}

/** In Divera archivieren (wenn dort angelegt) und den Eintrag so markieren */
function divera_termin_archivieren(array $row): void
{
    if (!empty($row['divera_id'])) {
        try {
            divera_termine_request('POST', '/api/v2/events/archive/' . (int)$row['divera_id']);
        } catch (DiveraException $ex) {
            // Schon weg oder nicht erreichbar: der Eintrag gilt trotzdem als archiviert, wir merken den Grund
            if (!empty($row['id'])) {
                db_update('divera_termine', ['fehler' => mb_substr($ex->getMessage(), 0, 300)], 'id = ?', [(int)$row['id']]);
            }
        }
    }
    if (!empty($row['id'])) {
        db_update('divera_termine', ['status' => 'archiviert', 'geprueft_am' => date('Y-m-d H:i:s')], 'id = ?', [(int)$row['id']]);
    }
    audit('divera.termin', (string)$row['typ'], (int)$row['ref_id'], 'archiviert #' . (int)($row['divera_id'] ?? 0));
}

/** Eintrag ganz entfernen – in Divera archivieren, bei uns löschen */
function divera_termin_entfernen(string $typ, int $refId): void
{
    $row = divera_termin_eintrag($typ, $refId);
    if (!$row) {
        return;
    }
    divera_termin_archivieren($row);
    db_exec('DELETE FROM divera_termine WHERE id = ?', [(int)$row['id']]);
}

/** Nach einer Absage: archivieren, Eintrag bleibt – wird der Termin wieder angesetzt, geht er neu hinaus */
function divera_termin_bei_absage(string $typ, int $refId): void
{
    $row = divera_termin_eintrag($typ, $refId);
    if ($row && (string)$row['status'] !== 'archiviert') {
        divera_termin_archivieren($row);
    }
}

/** Wieder angesetzt: als neuer Termin hinaus (der alte ist in Divera archiviert) */
function divera_termin_bei_wiederansetzung(string $typ, int $refId): void
{
    $row = divera_termin_eintrag($typ, $refId);
    if ($row) {
        db_update('divera_termine', ['divera_id' => null, 'status' => 'offen', 'versuche' => 0, 'fehler' => ''], 'id = ?', [(int)$row['id']]);
    }
}

/**
 * Nach dem Speichern eines Termins, einer Veranstaltung oder Besprechung:
 * Haken gesetzt → anlegen oder nachziehen; Haken weg → in Divera archivieren.
 */
function divera_termin_nach_speichern(string $typ, int $refId): void
{
    if (!divera_termine_aktiv()) {
        return;
    }
    $opt = divera_termin_optionen_aus_post();
    if ($opt === null) {
        return;
    }
    if (empty($opt['an'])) {
        if (divera_termin_eintrag($typ, $refId)) {
            divera_termin_entfernen($typ, $refId);
            flash('info', 'In Divera archiviert.');
        }
        return;
    }
    unset($opt['an']);
    $r = divera_termin_speichern($typ, $refId, $opt);
    flash($r['ok'] ? 'success' : 'warn', $r['ok'] ? 'Divera: ' . e($r['meldung']) . '.' : 'Divera hat den Termin nicht angenommen: ' . e($r['meldung']) . ' Der Abruf versucht es weiter.');
}

/* ==================================================================== */
/*  Stand und Abruf                                                      */
/* ==================================================================== */

/** Zähler aus der Divera-Antwort: empfaenger, gelesen, antworten. Reine Funktion. */
function divera_termin_stand_aus_antwort(array $antwort): array
{
    $d = $antwort['data'] ?? [];
    return [
        'empfaenger' => (int)($d['count_recipients'] ?? 0),
        'gelesen'    => (int)($d['count_read'] ?? 0),
        'antworten'  => is_array($d['ucr_answered'] ?? null) ? count($d['ucr_answered']) : (int)($d['ucr_answered'] ?? 0),
    ];
}

function divera_termin_stand_holen(array $row): array
{
    $antwort = divera_termine_request('GET', '/api/v2/events/' . (int)$row['divera_id']);
    $stand = divera_termin_stand_aus_antwort($antwort);
    db_update('divera_termine', ['stand' => json_encode($stand), 'geprueft_am' => date('Y-m-d H:i:s'), 'fehler' => ''], 'id = ?', [(int)$row['id']]);
    return $stand;
}

/** Stand als Text: „12 Empfänger · 8 gelesen · 5 Rückmeldungen". Reine Funktion. */
function divera_termin_stand_text(array $stand): string
{
    if (!$stand) {
        return '';
    }
    return sprintf('%d Empfänger · %d gelesen · %d Rückmeldungen', (int)($stand['empfaenger'] ?? 0), (int)($stand['gelesen'] ?? 0), (int)($stand['antworten'] ?? 0));
}

/**
 * Vom minütlichen Abruf: Offenes und Fehlgeschlagenes schicken, dann den
 * Stand kommender Termine holen. Rückgabe: gesendet, fehler, geprueft.
 */
function divera_termine_cron(?int $jetzt = null): array
{
    $jetzt ??= time();
    $r = ['gesendet' => 0, 'fehler' => 0, 'geprueft' => 0, 'meldungen' => []];
    if (!divera_termine_aktiv()) {
        return $r;
    }
    foreach (db_all("SELECT * FROM divera_termine WHERE status IN ('offen', 'fehler') AND versuche < ? ORDER BY id LIMIT " . DIVERA_TERMIN_JE_ABRUF, [DIVERA_TERMIN_VERSUCHE_MAX]) as $row) {
        $row['optionen'] = divera_termin_optionen_normieren(json_decode((string)($row['optionen'] ?? ''), true) ?: []);
        $erg = divera_termin_senden($row);
        if ($erg['ok']) {
            $r['gesendet']++;
        } else {
            $r['fehler']++;
            $r['meldungen'][] = DIVERA_TERMIN_TYPEN[(string)$row['typ']] . ' #' . (int)$row['ref_id'] . ': ' . $erg['meldung'];
        }
    }
    $minuten = max(5, setting_int('divera_termine_intervall_minuten', 30));
    $zeilen = db_all(
        "SELECT * FROM divera_termine WHERE status = 'gesendet' AND divera_id IS NOT NULL AND (beginn IS NULL OR beginn >= ?)
         AND (geprueft_am IS NULL OR geprueft_am < ?) ORDER BY geprueft_am IS NULL DESC, geprueft_am LIMIT " . DIVERA_TERMIN_JE_ABRUF,
        [date('Y-m-d H:i:s', $jetzt - 86400), date('Y-m-d H:i:s', $jetzt - $minuten * 60)]
    );
    foreach ($zeilen as $row) {
        try {
            divera_termin_stand_holen($row);
            $r['geprueft']++;
        } catch (DiveraException $ex) {
            db_update('divera_termine', ['fehler' => mb_substr($ex->getMessage(), 0, 300), 'geprueft_am' => date('Y-m-d H:i:s', $jetzt)], 'id = ?', [(int)$row['id']]);
            $r['meldungen'][] = 'Stand #' . (int)$row['divera_id'] . ': ' . $ex->getMessage();
        }
    }
    return $r;
}
