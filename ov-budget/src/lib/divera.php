<?php
declare(strict_types=1);

/**
 * Anbindung an Divera 24/7.
 *
 * Divera liefert JSON in unterschiedlichen Strukturen (je nach Endpunkt und
 * Tarif). Deshalb ist hier bewusst nichts fest verdrahtet:
 *   - Basis-URL, Pfade und die Art der Schlüsselübergabe stehen in den
 *     Einstellungen (Adminbereich → Divera 24/7)
 *   - die Antwort wird tolerant nach einer Liste von Objekten durchsucht
 *   - die Zuordnung Formularfeld → Wunschfeld pflegt der Admin je Formular
 */

class DiveraException extends RuntimeException {}

function divera_enabled(): bool
{
    return setting_bool('divera_aktiv', false) && setting('divera_accesskey', '') !== '';
}

/**
 * Roh-Request gegen die Divera-API.
 * $key: abweichender Schlüssel, etwa der persönliche für /api/v3
 * $post: Formularfelder – dann als POST gesendet
 */
function divera_request(string $path, array $query = [], ?string $key = null, ?array $post = null): array
{
    $base = rtrim((string)setting('divera_base_url', 'https://app.divera247.com/api'), '/');
    $key  = trim((string)($key ?? setting('divera_accesskey', '')));
    if ($base === '' || $key === '') {
        throw new DiveraException('Divera ist nicht vollständig konfiguriert (Basis-URL / Accesskey).');
    }

    $headers = ['Accept: application/json'];
    if (setting('divera_auth_mode', 'query') === 'header') {
        $headers[] = 'Authorization: Bearer ' . $key;
    } else {
        $query['accesskey'] = $key;
    }

    $url = $base . '/' . ltrim($path, '/');
    if ($query) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    $timeout = max(3, setting_int('divera_timeout', 15));

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'OV-Budget/1.0',
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
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
        if ($post !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $ctx = stream_context_create(['http' => [
            'method'        => $post !== null ? 'POST' : 'GET',
            'content'       => $post !== null ? http_build_query($post) : '',
            'header'        => implode("\r\n", $headers),
            'timeout'       => $timeout,
            'ignore_errors' => true,
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

    if ($code >= 400) {
        throw new DiveraException('Divera antwortete mit HTTP ' . $code . ': ' . mb_substr((string)$body, 0, 300));
    }

    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        throw new DiveraException('Antwort war kein gültiges JSON: ' . mb_substr((string)$body, 0, 200));
    }
    if (array_key_exists('success', $data) && $data['success'] === false) {
        throw new DiveraException('Divera meldet einen Fehler: ' . (string)($data['message'] ?? 'unbekannt'));
    }
    return $data;
}

/**
 * Sucht in einer beliebig verschachtelten Antwort die erste Liste von
 * Datensätzen (Array von Objekten oder Objekt mit numerischen Schlüsseln).
 */
function divera_extract_rows(array $data, int $depth = 0): array
{
    // Häufige Container zuerst
    foreach (['data', 'items', 'entries', 'forms', 'result', 'records'] as $k) {
        if (isset($data[$k]) && is_array($data[$k])) {
            $inner = divera_extract_rows($data[$k], $depth + 1);
            if ($inner) {
                return $inner;
            }
        }
    }

    $rows = [];
    $isList = true;
    foreach ($data as $k => $v) {
        if (!is_array($v)) {
            $isList = false;
            break;
        }
        $rows[] = is_int($k) ? $v : $v + ['_key' => $k];
    }
    if ($isList && $rows) {
        return $rows;
    }

    if ($depth < 4) {
        foreach ($data as $v) {
            if (is_array($v)) {
                $inner = divera_extract_rows($v, $depth + 1);
                if ($inner) {
                    return $inner;
                }
            }
        }
    }
    return [];
}

/*
 * Formulare heißen bei Divera "reporttypes", ihre Einträge "reports"
 * (https://api.divera247.com/docs/api_v2_reporttype.yaml):
 *   GET /api/v2/reporttypes              Formulare samt Felddefinitionen
 *   GET /api/v2/reporttypes/{id}         ein Formular
 *   GET /api/v2/reporttypes/{id}/reports Einträge, 50 je Seite (offset)
 * Die Schnittstelle verlangt den persönlichen Accesskey.
 */
const DIVERA_FORMS_PATH = '/v2/reporttypes';
const DIVERA_ENTRIES_PATH = '/v2/reporttypes/{form_id}/reports';
const DIVERA_SEITE = 50;
const DIVERA_MAX_SEITEN = 40;

/** Schlüssel für die Formular-Schnittstelle: der persönliche, sonst der allgemeine */
function divera_form_key(): ?string
{
    $key = trim((string)setting('divera_personal_key', ''));
    return $key !== '' ? $key : null;
}

/** Formulare aus Divera holen und auf id/name normalisieren */
function divera_fetch_forms(): array
{
    $path = (string)setting('divera_forms_path', DIVERA_FORMS_PATH);
    $raw = divera_request($path, [], divera_form_key());
    $rows = divera_extract_rows($raw);

    $out = [];
    foreach ($rows as $r) {
        $id = (string)(divera_pick($r, ['id', 'form_id', 'formId', 'uuid', '_key']) ?? '');
        if ($id === '') {
            continue;
        }
        $out[] = [
            'id'   => $id,
            'name' => (string)(divera_pick($r, ['name', 'title', 'bezeichnung', 'label']) ?? ('Formular ' . $id)),
            'raw'  => $r,
        ];
    }
    return $out;
}

/**
 * Einträge eines Formulars holen – alle Seiten.
 * Divera liefert je Aufruf 50 Einträge und die Gesamtzahl (itemcount);
 * weitere Seiten gibt es über den Parameter offset.
 */
function divera_fetch_entries(string $formId): array
{
    $path = str_replace('{form_id}', rawurlencode($formId),
        (string)setting('divera_entries_path', DIVERA_ENTRIES_PATH));

    $out = [];
    $offset = 0;
    for ($seite = 0; $seite < DIVERA_MAX_SEITEN; $seite++) {
        $raw = divera_request($path, $offset > 0 ? ['offset' => $offset] : [], divera_form_key());
        $rows = divera_extract_rows($raw);
        foreach ($rows as $r) {
            $out[] = divera_entry_from_row($r);
        }
        $gesamt = (int)($raw['data']['itemcount'] ?? 0);
        $offset += count($rows);
        // Ende: leere Seite, alles da, oder die Schnittstelle blättert nicht
        if (!$rows || $gesamt <= 0 || $offset >= $gesamt || count($rows) < DIVERA_SEITE) {
            break;
        }
    }
    return $out;
}

/** Einen Eintrag aus der Antwort auf unser Format bringen */
function divera_entry_from_row(array $r): array
{
    $felder = divera_report_fields($r);
    $zeit = divera_pick($r, ['ts_create', 'date', 'created', 'timestamp', 'created_at']);
    return [
        'id'      => (string)(divera_pick($r, ['id', 'entry_id', 'uuid', '_key']) ?? ''),
        'status'  => isset($r['status']) && is_numeric($r['status']) ? (int)$r['status'] : null,
        'date'    => $zeit,
        'user'    => (string)(divera_pick($r, ['user_name', 'username', 'author', 'creator']) ?? ''),
        // Das Divera-Format hat Felder als {field, value}; anderes wird flach gelesen
        'fields'  => $felder ?? divera_flatten_fields($r),
        'anhaenge' => (int)($r['attachment_count'] ?? (is_array($r['attachment'] ?? null) ? count($r['attachment']) : 0)),
        'adresse' => trim((string)($r['address'] ?? '')),
        'raw'     => $r,
    ];
}

/**
 * Felder eines Divera-Eintrags als "Feldname => Text".
 * Gibt null zurück, wenn der Eintrag nicht im Divera-Format vorliegt.
 * Reine Funktion.
 */
function divera_report_fields(array $report): ?array
{
    $liste = $report['fields'] ?? null;
    if (!is_array($liste) || !array_is_list($liste) || $liste === []) {
        return null;
    }
    foreach ($liste as $f) {
        if (!is_array($f) || !isset($f['field']) || !is_array($f['field'])) {
            return null;
        }
    }
    $out = [];
    foreach ($liste as $f) {
        $feld = $f['field'];
        $name = trim((string)($feld['name'] ?? ''));
        if ($name === '' || ($feld['type'] ?? '') === 'headline') {
            continue;
        }
        // Gleich benannte Felder nicht überschreiben
        $schluessel = $name;
        for ($n = 2; array_key_exists($schluessel, $out); $n++) {
            $schluessel = $name . ' (' . $n . ')';
        }
        $out[$schluessel] = divera_field_text($feld, $f['value'] ?? null);
    }
    return $out;
}

/**
 * Wert eines Feldes lesbar machen. Auswahlfelder liefern die ids der
 * gewählten Optionen – die werden in deren Bezeichnung übersetzt.
 */
function divera_field_text(array $feld, mixed $wert): string
{
    $typ = (string)($feld['type'] ?? '');
    if (!in_array($typ, ['radio', 'selectbox', 'checkbox'], true)) {
        if (is_array($wert)) {
            return implode(', ', array_map(static fn($x) => is_scalar($x) ? (string)$x : '', $wert));
        }
        return is_bool($wert) ? ($wert ? '1' : '0') : trim((string)$wert);
    }

    $optionen = [];
    foreach ((array)($feld['options'] ?? []) as $o) {
        if (is_array($o) && isset($o['id'])) {
            $optionen[(string)$o['id']] = (string)($o['name'] ?? $o['id']);
        }
    }

    // Mehrfachauswahl: Liste, JSON-Liste als Text oder durch Komma getrennt
    if (is_string($wert) && str_starts_with(trim($wert), '[')) {
        $json = json_decode($wert, true);
        $wert = is_array($json) ? $json : $wert;
    }
    $ids = is_array($wert) ? $wert : preg_split('/\s*[,;]\s*/', trim((string)$wert));
    $texte = [];
    foreach ((array)$ids as $id) {
        $id = trim((string)$id);
        if ($id === '') {
            continue;
        }
        $texte[] = $optionen[$id] ?? $id;
    }
    return implode(', ', $texte);
}

/** Feldnamen eines Formulars aus seiner Definition – auch ohne Einträge */
function divera_fetch_form_fields(string $formId): array
{
    try {
        $raw = divera_request(rtrim((string)setting('divera_forms_path', DIVERA_FORMS_PATH), '/')
            . '/' . rawurlencode($formId), [], divera_form_key());
    } catch (Throwable) {
        return [];
    }
    $felder = $raw['data']['fields'] ?? $raw['fields'] ?? [];
    $namen = [];
    foreach (is_array($felder) ? $felder : [] as $f) {
        if (is_array($f) && ($f['type'] ?? '') !== 'headline' && trim((string)($f['name'] ?? '')) !== '') {
            $namen[] = trim((string)$f['name']);
        }
    }
    return array_values(array_unique($namen));
}

/**
 * Vorschlag für die Zuordnung: Formularfelder, deren Name nach einem
 * Wunschfeld klingt. Reine Funktion.
 */
function divera_suggest_map(array $felder, string $ziel = 'wunsch'): array
{
    $muster = $ziel === 'thema' ? [
        'titel'        => ['thema', 'titel', 'betreff', 'überschrift', 'ueberschrift', 'worum geht'],
        'beschreibung' => ['beschreibung', 'details', 'inhalt', 'erläuterung', 'erlaeuterung', 'hintergrund', 'text'],
        'fachgruppe'   => ['fachgruppe', 'einheit', 'gruppe'],
        'prioritaet'   => ['priorität', 'prioritaet', 'dringlichkeit', 'wichtigkeit', 'dringend'],
        'dauer_min'    => ['dauer', 'zeitbedarf', 'minuten', 'zeit'],
        'einbringer'   => ['eingereicht von', 'einbringer', 'name', 'von', 'ansprechpartner'],
    ] : [
        'bezeichnung'   => ['bezeichnung', 'was wird benötigt', 'gegenstand', 'artikel', 'titel', 'was'],
        'beschreibung'  => ['beschreibung', 'details'],
        'begruendung'   => ['begründung', 'begruendung', 'warum', 'grund'],
        'anzahl'        => ['anzahl', 'menge', 'stück', 'stueck'],
        // "Nettobetrag" neben einer Anzahl ist meist der Stückpreis; der Gesamtbetrag rechnet sich dann
        'netto_gesamt'  => ['gesamtpreis', 'gesamtbetrag', 'summe', 'gesamt'],
        'netto_einzel'  => ['einzelpreis', 'preis pro stück', 'stückpreis', 'nettobetrag', 'netto', 'preis', 'kosten', 'betrag'],
        'fachgruppe'    => ['fachgruppe', 'einheit', 'gruppe'],
        'kategorie'     => ['kategorie'],
        'dringlichkeit' => ['dringlichkeit', 'priorität', 'prioritaet', 'dringend'],
        'nice_to_have'  => ['nice to have', 'nice-to-have', 'optional'],
        'benoetigt_bis' => ['benötigt bis', 'benoetigt bis', 'bis wann', 'frist', 'datum'],
        'lieferant'     => ['lieferant', 'händler', 'haendler', 'bezugsquelle', 'shop'],
        'artikelnummer' => ['artikelnummer', 'art.-nr', 'bestellnummer'],
        'link'          => ['link', 'url', 'internet'],
        'antragsteller' => ['antragsteller', 'name', 'von', 'melder'],
    ];
    $vergeben = [];
    $out = [];
    foreach ($muster as $ziel => $woerter) {
        foreach ($woerter as $wort) {
            foreach ($felder as $feld) {
                $klein = mb_strtolower(trim((string)$feld));
                if (!isset($vergeben[$feld]) && ($klein === $wort || str_starts_with($klein, $wort))) {
                    $out[$ziel] = $feld;
                    $vergeben[$feld] = true;
                    continue 3;
                }
            }
        }
    }
    return $out;
}

/** Ersten vorhandenen Schlüssel aus einer Liste zurückgeben */
function divera_pick(array $row, array $keys): mixed
{
    foreach ($keys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
            return $row[$k];
        }
    }
    return null;
}

/**
 * Macht aus einem Divera-Eintrag eine flache Liste "Feldname => Wert".
 * Unterstützt sowohl {"felder": {"Menge": 3}} als auch
 * {"fields":[{"name":"Menge","value":3}]} sowie verschachtelte Objekte.
 */
function divera_flatten_fields(array $row, string $prefix = '', int $depth = 0): array
{
    $out = [];
    foreach ($row as $k => $v) {
        if ($k === 'raw' || $k === '_key') {
            continue;
        }
        $name = $prefix === '' ? (string)$k : $prefix . '.' . $k;

        if (is_array($v)) {
            // Liste von {name,value}-Paaren
            $isNameValue = false;
            foreach ($v as $item) {
                if (is_array($item) && (isset($item['name']) || isset($item['label']) || isset($item['key']))) {
                    $isNameValue = true;
                    break;
                }
            }
            if ($isNameValue) {
                foreach ($v as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $fname = (string)(divera_pick($item, ['name', 'label', 'key', 'title']) ?? '');
                    $fval = divera_pick($item, ['value', 'val', 'text', 'content', 'answer']);
                    if ($fname !== '') {
                        $out[$fname] = is_array($fval) ? implode(', ', array_map('strval', $fval)) : (string)$fval;
                    }
                }
                continue;
            }
            if ($depth < 3) {
                $out += divera_flatten_fields($v, $name, $depth + 1);
                continue;
            }
            $out[$name] = implode(', ', array_map(static fn($x) => is_scalar($x) ? (string)$x : '', $v));
            continue;
        }
        $out[$name] = is_bool($v) ? ($v ? '1' : '0') : (string)$v;
    }
    return $out;
}

/** Feldwert unabhängig von Groß-/Kleinschreibung suchen */
function divera_field_value(array $fields, string $wanted): string
{
    if ($wanted === '') {
        return '';
    }
    if (array_key_exists($wanted, $fields)) {
        return (string)$fields[$wanted];
    }
    $needle = mb_strtolower(trim($wanted));
    foreach ($fields as $k => $v) {
        if (mb_strtolower(trim((string)$k)) === $needle) {
            return (string)$v;
        }
    }
    foreach ($fields as $k => $v) {
        if (str_contains(mb_strtolower((string)$k), $needle)) {
            return (string)$v;
        }
    }
    return '';
}

/* ==================================================================== */
/* Bearbeitungsstand an Divera zurückmelden                              */
/* ==================================================================== */

/** Divera-Status laut Schnittstelle (reporttype-status) */
const DIVERA_REPORT_STATUS = [
    0 => 'Ungelesen', 1 => 'Gelesen', 2 => 'In Bearbeitung', 3 => 'Nachkontrolle',
    4 => 'Abgeschlossen', 5 => 'Archiviert', 6 => 'Weitergeleitet',
];
/** Reihenfolge im Ablauf – Weitergeleitet kommt vor In Bearbeitung */
const DIVERA_STATUS_RANG = [0 => 0, 1 => 1, 6 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6];
const DIVERA_STATUS_MAX_JE_LAUF = 25;

/** Ist $neu ein Schritt nach vorn gegenüber $bisher? Reine Funktion. */
function divera_status_weiter(?int $bisher, int $neu): bool
{
    if (!isset(DIVERA_STATUS_RANG[$neu])) {
        return false;
    }
    return $bisher === null || DIVERA_STATUS_RANG[$neu] > (DIVERA_STATUS_RANG[$bisher] ?? -1);
}

/** Slug-Liste aus einer Einstellung */
function divera_slugs(string $wert): array
{
    return array_values(array_filter(array_map('trim', explode(',', mb_strtolower($wert)))));
}

/**
 * Gewünschter Divera-Status eines Wunsches. Reine Funktion.
 * $w: status_slug, status_final
 */
function divera_zielstatus_wunsch(array $w, array $bearbeitung, array $abgeschlossen): int
{
    $slug = (string)($w['status_slug'] ?? '');
    if ((int)($w['status_final'] ?? 0) === 1 || in_array($slug, $abgeschlossen, true)) {
        return 4;
    }
    if (in_array($slug, $bearbeitung, true)) {
        return 2;
    }
    return 6;
}

/**
 * Gewünschter Divera-Status eines Themas. Reine Funktion.
 * $t: status_final, meeting_id
 */
function divera_zielstatus_thema(array $t): int
{
    if ((int)($t['status_final'] ?? 0) === 1) {
        return 4;
    }
    return !empty($t['meeting_id']) ? 2 : 6;
}

/** Status eines Eintrags in Divera setzen */
function divera_set_report_status(string $entryId, int $status): void
{
    divera_request('/v2/reports/' . rawurlencode($entryId) . '/status', [], divera_form_key(),
        ['Report' => ['status' => $status]]);
}

/**
 * Stand aller übernommenen Einträge eines Formulars an Divera melden –
 * nur Schritte nach vorn, höchstens DIVERA_STATUS_MAX_JE_LAUF je Aufruf.
 * Bricht beim ersten Fehler ab (meist fehlende Rechte), damit Divera nicht
 * mit Anfragen überhäuft wird.
 */
function divera_status_sync_form(array $form): array
{
    $res = ['gemeldet' => 0, 'offen' => 0, 'fehler' => ''];
    if (!(int)($form['status_sync'] ?? 0)) {
        return $res;
    }
    $thema = divera_ziel($form) === 'thema';
    $zeilen = $thema
        ? db_all("SELECT t.id, t.titel AS name, t.meeting_id, t.divera_entry_id, t.divera_status,
                         COALESCE(st.is_final, 0) AS status_final, st.slug AS status_slug
                  FROM talking_points t LEFT JOIN list_items st ON st.id = t.status_id
                  WHERE t.divera_form_id = ? AND t.divera_entry_id <> ''", [(string)$form['form_id']])
        : db_all("SELECT w.id, w.bezeichnung AS name, w.divera_entry_id, w.divera_status,
                         COALESCE(st.is_final, 0) AS status_final, st.slug AS status_slug
                  FROM wishes w LEFT JOIN list_items st ON st.id = w.status_id
                  WHERE w.divera_form_id = ? AND w.divera_entry_id <> ''", [(string)$form['form_id']]);

    $bearbeitung = divera_slugs((string)setting('divera_status_bearbeitung', 'freigegeben'));
    $abgeschlossen = divera_slugs((string)setting('divera_status_abgeschlossen', 'bestellt'));

    foreach ($zeilen as $z) {
        $ziel = $thema ? divera_zielstatus_thema($z) : divera_zielstatus_wunsch($z, $bearbeitung, $abgeschlossen);
        $bisher = $z['divera_status'] !== null ? (int)$z['divera_status'] : null;
        if (!divera_status_weiter($bisher, $ziel)) {
            continue;
        }
        if ($res['gemeldet'] >= DIVERA_STATUS_MAX_JE_LAUF) {
            $res['offen']++;
            continue;
        }
        try {
            divera_set_report_status((string)$z['divera_entry_id'], $ziel);
        } catch (Throwable $ex) {
            $res['fehler'] = $ex->getMessage();
            db_insert('divera_log', [
                'form_id'  => (string)$form['form_id'],
                'entry_id' => (string)$z['divera_entry_id'],
                'wish_id'  => $thema ? null : (int)$z['id'],
                'tp_id'    => $thema ? (int)$z['id'] : null,
                'status'   => 'fehler',
                'message'  => mb_substr('Status „' . DIVERA_REPORT_STATUS[$ziel] . '“ nicht gesetzt: ' . $ex->getMessage(), 0, 500),
            ]);
            break;
        }
        db_exec(($thema ? 'UPDATE talking_points' : 'UPDATE wishes') . ' SET divera_status = ? WHERE id = ?', [$ziel, (int)$z['id']]);
        db_insert('divera_log', [
            'form_id'  => (string)$form['form_id'],
            'entry_id' => (string)$z['divera_entry_id'],
            'wish_id'  => $thema ? null : (int)$z['id'],
            'tp_id'    => $thema ? (int)$z['id'] : null,
            'status'   => 'ok',
            'message'  => mb_substr('Divera-Status „' . DIVERA_REPORT_STATUS[$ziel] . '“: ' . $z['name'], 0, 500),
        ]);
        $res['gemeldet']++;
    }
    return $res;
}

/** Wofür ein Formular gedacht ist */
const DIVERA_ZIELE = [
    'wunsch' => 'Wünsch dir was',
    'thema'  => 'Themen für Besprechungen',
];

function divera_ziel(array $form): string
{
    return ($form['ziel'] ?? '') === 'thema' ? 'thema' : 'wunsch';
}

/** Diese Zuordnungen können je Formular gepflegt werden */
function divera_map_targets(string $ziel = 'wunsch'): array
{
    if ($ziel === 'thema') {
        return [
            'titel'        => 'Thema (Titel)',
            'beschreibung' => 'Beschreibung',
            'fachgruppe'   => 'Fachgruppe',
            'prioritaet'   => 'Priorität',
            'dauer_min'    => 'Zeitbedarf in Minuten',
            'einbringer'   => 'Eingereicht von (Name)',
        ];
    }
    return [
        'bezeichnung'   => 'Bezeichnung',
        'beschreibung'  => 'Beschreibung',
        'begruendung'   => 'Begründung',
        'anzahl'        => 'Anzahl',
        'netto_einzel'  => 'Betrag (Einzelpreis)',
        'netto_gesamt'  => 'Betrag (Gesamt)',
        'fachgruppe'    => 'Fachgruppe',
        'kategorie'     => 'Kategorie',
        'dringlichkeit' => 'Dringlichkeit',
        'nice_to_have'  => 'Nice to have (ja/nein)',
        'benoetigt_bis' => 'Benötigt bis (Datum)',
        'lieferant'     => 'Lieferant',
        'artikelnummer' => 'Artikelnummer',
        'link'          => 'Link',
        'antragsteller' => 'Antragsteller',
    ];
}

function divera_parse_bool(string $v): int
{
    $v = mb_strtolower(trim($v));
    return in_array($v, ['1', 'ja', 'yes', 'true', 'x', 'wahr', 'on'], true) ? 1 : 0;
}

function divera_parse_dec(string $v): float
{
    $v = trim(str_replace(['€', ' ', "\xc2\xa0"], '', $v));
    if ($v === '') {
        return 0.0;
    }
    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return (float)preg_replace('/[^0-9.\-]/', '', $v);
}

function divera_parse_date(string $v): ?string
{
    $v = trim($v);
    if ($v === '') {
        return null;
    }
    if (ctype_digit($v) && strlen($v) >= 9) {
        return date('Y-m-d', (int)$v);
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $v, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}

/** Einen Divera-Eintrag in Wunsch-Felder übersetzen */
function divera_entry_to_wish(array $entry, array $map, array $form): array
{
    $f = $entry['fields'];
    $get = static fn(string $target) => divera_field_value($f, (string)($map[$target] ?? ''));

    $bezeichnung = trim($get('bezeichnung'));
    if ($bezeichnung === '') {
        $bezeichnung = 'Divera-Import ' . ($entry['id'] ?: date('Y-m-d H:i'));
    }

    $anzahl = divera_parse_dec($get('anzahl'));
    if ($anzahl <= 0) {
        $anzahl = 1;
    }
    $einzel = divera_parse_dec($get('netto_einzel'));
    $gesamt = divera_parse_dec($get('netto_gesamt'));
    if ($gesamt <= 0) {
        $gesamt = round($einzel * $anzahl, 2);
    } elseif ($einzel <= 0 && $anzahl > 0) {
        $einzel = round($gesamt / $anzahl, 2);
    }

    $statusSlug = (string)setting('divera_import_status', 'neu');
    $statusId = $form['default_status_id'] ? (int)$form['default_status_id']
        : (list_id_by_slug('wunsch_status', $statusSlug) ?? list_default_id('wunsch_status'));

    $fgText = $get('fachgruppe');
    $fachgruppeId = $fgText !== '' ? list_id_by_text('fachgruppe', $fgText) : null;
    $fachgruppeId ??= $form['default_fachgruppe_id'] ? (int)$form['default_fachgruppe_id'] : null;

    $driText = $get('dringlichkeit');
    $dringlichkeitId = $driText !== '' ? list_id_by_text('dringlichkeit', $driText) : null;
    $dringlichkeitId ??= list_default_id('dringlichkeit');

    $katText = $get('kategorie');

    // Nicht zugeordnete Felder als Notiz anhängen – nichts geht verloren
    $used = array_filter(array_values($map));
    $rest = [];
    foreach ($f as $k => $v) {
        if ($v === '' || in_array($k, $used, true)) {
            continue;
        }
        $rest[] = $k . ': ' . $v;
    }

    $beschreibung = trim($get('beschreibung'));
    if (!empty($entry['adresse'])) {
        $rest[] = 'Adresse: ' . $entry['adresse'];
    }
    if (!empty($entry['anhaenge'])) {
        // Divera liefert Anhänge verschlüsselt – sie bleiben dort und werden hier nur genannt
        $rest[] = sprintf('In Divera hängen %d Datei(en) an diesem Eintrag (z. B. Angebote).', (int)$entry['anhaenge']);
    }
    if ($rest) {
        $beschreibung = trim($beschreibung . "\n\n— Weitere Angaben aus Divera —\n" . implode("\n", $rest));
    }

    return [
        'bezeichnung'     => mb_substr($bezeichnung, 0, 200),
        'beschreibung'    => $beschreibung,
        'begruendung'     => $get('begruendung'),
        'anzahl'          => $anzahl,
        'einheit_id'      => list_default_id('einheit'),
        'netto_einzel'    => $einzel,
        'netto_gesamt'    => $gesamt,
        'mwst_satz'       => 0,
        'fachgruppe_id'   => $fachgruppeId,
        'kategorie_id'    => $katText !== '' ? list_id_by_text('kategorie', $katText) : null,
        'dringlichkeit_id' => $dringlichkeitId,
        'status_id'       => $statusId,
        'nice_to_have'    => divera_parse_bool($get('nice_to_have')),
        'benoetigt_bis'   => divera_parse_date($get('benoetigt_bis')),
        'lieferant'       => mb_substr($get('lieferant'), 0, 150),
        'artikelnummer'   => mb_substr($get('artikelnummer'), 0, 100),
        'link'            => mb_substr($get('link'), 0, 500),
        'antragsteller'   => mb_substr($get('antragsteller') ?: (string)$entry['user'], 0, 150),
        'source'          => 'divera',
        'divera_form_id'  => (string)$form['form_id'],
        'divera_entry_id' => (string)$entry['id'],
        'divera_status'   => $entry['status'] ?? null,
    ];
}

/**
 * Einen Divera-Eintrag in einen Talking Point für den Themenspeicher übersetzen.
 * $userByName: Name → Benutzer-id, damit eingereichte Themen wie selbst
 * angelegte dem Einbringer zugeordnet werden.
 */
function divera_entry_to_tp(array $entry, array $map, array $form, array $userByName = []): array
{
    $f = $entry['fields'];
    $get = static fn(string $target) => trim(divera_field_value($f, (string)($map[$target] ?? '')));

    $titel = $get('titel');
    if ($titel === '') {
        $titel = 'Thema aus Divera ' . ($entry['id'] ?: date('Y-m-d H:i'));
    }

    $fgText = $get('fachgruppe');
    $fachgruppeId = $fgText !== '' ? list_id_by_text('fachgruppe', $fgText) : null;
    $fachgruppeId ??= !empty($form['default_fachgruppe_id']) ? (int)$form['default_fachgruppe_id'] : null;

    $prText = $get('prioritaet');
    $prioritaetId = $prText !== '' ? list_id_by_text('todo_prioritaet', $prText) : null;
    $prioritaetId ??= list_default_id('todo_prioritaet');

    $dauer = (int)round(divera_parse_dec($get('dauer_min')));

    $name = $get('einbringer') ?: trim((string)($entry['user'] ?? ''));
    $userId = $name !== '' ? ($userByName[mb_strtolower($name)] ?? null) : null;

    // Nicht zugeordnete Felder anhängen – nichts geht verloren
    $used = array_filter(array_values($map));
    $rest = [];
    foreach ($f as $k => $v) {
        if ($v === '' || in_array($k, $used, true)) {
            continue;
        }
        $rest[] = $k . ': ' . $v;
    }
    if (!empty($entry['anhaenge'])) {
        $rest[] = sprintf('In Divera hängen %d Datei(en) an diesem Eintrag.', (int)$entry['anhaenge']);
    }
    $beschreibung = $get('beschreibung');
    if ($rest) {
        $beschreibung = trim($beschreibung . "\n\n— Weitere Angaben aus Divera —\n" . implode("\n", $rest));
    }

    return [
        'titel'           => mb_substr($titel, 0, 200),
        'beschreibung'    => $beschreibung,
        'fachgruppe_id'   => $fachgruppeId,
        'prioritaet_id'   => $prioritaetId,
        'status_id'       => !empty($form['default_status_id']) ? (int)$form['default_status_id'] : list_default_id('tp_status'),
        'dauer_min'       => $dauer > 0 ? min($dauer, 600) : null,
        'meeting_id'      => null,   // Themenspeicher
        'sort_order'      => 0,
        'eingebracht_von' => $userId,
        'einbringer_name' => $userId ? '' : mb_substr($name, 0, 150),
        'divera_form_id'  => (string)$form['form_id'],
        'divera_entry_id' => (string)$entry['id'],
        'divera_status'   => $entry['status'] ?? null,
    ];
}

/** Aktive Benutzer nach Anzeige- und Benutzername, klein geschrieben */
function divera_user_index(): array
{
    $out = [];
    foreach (db_all('SELECT id, display_name, username FROM users WHERE is_active = 1') as $u) {
        foreach ([$u['display_name'] ?? '', $u['username'] ?? ''] as $n) {
            $n = mb_strtolower(trim((string)$n));
            if ($n !== '') {
                $out[$n] ??= (int)$u['id'];
            }
        }
    }
    return $out;
}

/** Alle Themenformulare mit automatischem oder manuellem Abruf importieren */
function divera_import_themes(?int $userId = null): array
{
    $gesamt = ['formulare' => 0, 'total' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0];
    foreach (db_all("SELECT * FROM divera_forms WHERE ziel = 'thema' ORDER BY name") as $form) {
        $res = divera_import_form($form, $userId);
        divera_status_sync_form($form);
        $gesamt['formulare']++;
        foreach (['total', 'created', 'skipped', 'failed'] as $k) {
            $gesamt[$k] += $res[$k];
        }
    }
    return $gesamt;
}

/**
 * Alle Einträge eines Formulars importieren.
 * Bereits importierte Einträge (gleiche form_id + entry_id) werden übersprungen.
 */
function divera_import_form(array $form, ?int $userId = null, bool $dryRun = false): array
{
    $map = json_decode((string)($form['field_map'] ?? '{}'), true) ?: [];
    $entries = divera_fetch_entries((string)$form['form_id']);
    $thema = divera_ziel($form) === 'thema';
    $tabelle = $thema ? 'talking_points' : 'wishes';
    $nutzer = $thema ? divera_user_index() : [];

    $created = 0;
    $skipped = 0;
    $failed = 0;
    $preview = [];

    foreach ($entries as $entry) {
        $entryId = (string)$entry['id'];
        if ($entryId === '') {
            $entryId = substr(sha1(json_encode($entry['fields'], JSON_UNESCAPED_UNICODE) ?: ''), 0, 32);
            $entry['id'] = $entryId;
        }

        $exists = db_row(
            "SELECT id, divera_status FROM $tabelle WHERE divera_form_id = ? AND divera_entry_id = ?",
            [(string)$form['form_id'], $entryId]
        );
        if ($exists) {
            $skipped++;
            // Wer in Divera weitergeschaltet hat, wird nicht zurückgedreht
            $bekannt = $exists['divera_status'] !== null ? (int)$exists['divera_status'] : null;
            if (!$dryRun && $entry['status'] !== null && divera_status_weiter($bekannt, (int)$entry['status'])) {
                db_exec("UPDATE $tabelle SET divera_status = ? WHERE id = ?", [(int)$entry['status'], (int)$exists['id']]);
            }
            continue;
        }

        try {
            $data = $thema
                ? divera_entry_to_tp($entry, $map, $form, $nutzer)
                : divera_entry_to_wish($entry, $map, $form);
            if ($dryRun) {
                $preview[] = $data;
                $created++;
                continue;
            }
            if ($thema) {
                $neuId = db_insert('talking_points', $data);
                audit('tp.divera', 'talking_point', $neuId, $data['titel']);
            } else {
                $data['created_by'] = $userId;
                $neuId = db_insert('wishes', $data);
            }
            db_insert('divera_log', [
                'form_id'  => (string)$form['form_id'],
                'entry_id' => $entryId,
                'wish_id'  => $thema ? null : $neuId,
                'tp_id'    => $thema ? $neuId : null,
                'status'   => 'ok',
                'message'  => $thema ? 'Thema eingereicht: ' . $data['titel'] : 'Wunsch angelegt: ' . $data['bezeichnung'],
                'payload'  => json_encode($entry['fields'], JSON_UNESCAPED_UNICODE),
            ]);
            $created++;
        } catch (Throwable $ex) {
            $failed++;
            if (!$dryRun) {
                db_insert('divera_log', [
                    'form_id'  => (string)$form['form_id'],
                    'entry_id' => $entryId,
                    'status'   => 'fehler',
                    'message'  => mb_substr($ex->getMessage(), 0, 500),
                    'payload'  => json_encode($entry['fields'] ?? [], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
    }

    if (!$dryRun) {
        db_exec('UPDATE divera_forms SET last_sync = NOW() WHERE id = ?', [$form['id']]);
    }

    return [
        'total'   => count($entries),
        'created' => $created,
        'skipped' => $skipped,
        'failed'  => $failed,
        'preview' => $preview,
    ];
}
