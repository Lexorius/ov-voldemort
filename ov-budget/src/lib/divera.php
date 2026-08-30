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

/** Roh-Request gegen die Divera-API */
function divera_request(string $path, array $query = []): array
{
    $base = rtrim((string)setting('divera_base_url', 'https://app.divera247.com/api'), '/');
    $key  = (string)setting('divera_accesskey', '');
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
            'method'        => 'GET',
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

/** Formulare aus Divera holen und auf id/name normalisieren */
function divera_fetch_forms(): array
{
    $path = (string)setting('divera_forms_path', '/v2/forms');
    $raw = divera_request($path);
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

/** Einträge eines Formulars holen */
function divera_fetch_entries(string $formId): array
{
    $path = str_replace('{form_id}', rawurlencode($formId), (string)setting('divera_entries_path', '/v2/forms/{form_id}/entries'));
    $raw = divera_request($path);
    $rows = divera_extract_rows($raw);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'     => (string)(divera_pick($r, ['id', 'entry_id', 'uuid', '_key']) ?? ''),
            'date'   => divera_pick($r, ['date', 'created', 'timestamp', 'created_at']),
            'user'   => (string)(divera_pick($r, ['user_name', 'username', 'author', 'creator', 'name']) ?? ''),
            'fields' => divera_flatten_fields($r),
            'raw'    => $r,
        ];
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

/** Diese Zuordnungen können je Formular gepflegt werden */
function divera_map_targets(): array
{
    return [
        'bezeichnung'   => 'Bezeichnung',
        'beschreibung'  => 'Beschreibung',
        'begruendung'   => 'Begründung',
        'anzahl'        => 'Anzahl',
        'netto_einzel'  => 'Nettobetrag (Einzelpreis)',
        'netto_gesamt'  => 'Nettobetrag (Gesamt)',
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
        'mwst_satz'       => setting_float('mwst_satz', 19.0),
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
    ];
}

/**
 * Alle Einträge eines Formulars importieren.
 * Bereits importierte Einträge (gleiche form_id + entry_id) werden übersprungen.
 */
function divera_import_form(array $form, ?int $userId = null, bool $dryRun = false): array
{
    $map = json_decode((string)($form['field_map'] ?? '{}'), true) ?: [];
    $entries = divera_fetch_entries((string)$form['form_id']);

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

        $exists = db_val(
            'SELECT id FROM wishes WHERE divera_form_id = ? AND divera_entry_id = ?',
            [(string)$form['form_id'], $entryId]
        );
        if ($exists) {
            $skipped++;
            continue;
        }

        try {
            $data = divera_entry_to_wish($entry, $map, $form);
            if ($dryRun) {
                $preview[] = $data;
                $created++;
                continue;
            }
            $data['created_by'] = $userId;
            $wishId = db_insert('wishes', $data);
            db_insert('divera_log', [
                'form_id'  => (string)$form['form_id'],
                'entry_id' => $entryId,
                'wish_id'  => $wishId,
                'status'   => 'ok',
                'message'  => 'Wunsch angelegt: ' . $data['bezeichnung'],
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
