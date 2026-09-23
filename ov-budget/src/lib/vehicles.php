<?php
declare(strict_types=1);

/*
 * Fahrzeuge, Fahrzeugakte und Instandsetzungsaufträge.
 *
 * Das Journal der Akte wird nur angehängt. Es gibt hier bewusst keine
 * Funktion zum Ändern oder Löschen eines Eintrags. Damit eine Änderung
 * an der Datenbank vorbei auffällt, trägt jeder Eintrag den Hash des
 * vorherigen: journal_verify() rechnet die Kette nach.
 */

/** Felder der Fahrzeugstammdaten, die eine Änderung ins Journal schreiben */
const FZ_JOURNAL_FELDER = [
    'bezeichnung'       => 'Bezeichnung',
    'funkrufname'       => 'Funkrufname',
    'issi'              => 'ISSI',
    'opta'              => 'OPTA',
    'ric'               => 'RIC',
    'kennzeichen'       => 'Kennzeichen',
    'kennung'           => 'Kennung',
    'typ_id'            => 'Art',
    'fachgruppe_id'     => 'Fachgruppe',
    'status_id'         => 'Status',
    'hersteller'        => 'Hersteller',
    'modell'            => 'Modell',
    'baujahr'           => 'Baujahr',
    'fahrgestellnummer' => 'Fahrgestellnummer',
    'erstzulassung'     => 'Erstzulassung',
    'km_stand'          => 'Kilometerstand',
    'betriebsstunden'   => 'Betriebsstunden',
    'hu_bis'            => 'HU gültig bis',
    'sp_bis'            => 'SP gültig bis',
    'uvv_bis'           => 'UVV gültig bis',
    'standort'          => 'Standort',
    'is_active'         => 'Im Dienst',
];

function vehicle_find(int $id): ?array
{
    return db_row(
        'SELECT v.*, t.label AS typ_label, t.color AS typ_color,
                f.label AS fachgruppe_label,
                s.label AS status_label, s.color AS status_color, s.slug AS status_slug
         FROM vehicles v
         LEFT JOIN list_items t ON t.id = v.typ_id
         LEFT JOIN list_items f ON f.id = v.fachgruppe_id
         LEFT JOIN list_items s ON s.id = v.status_id
         WHERE v.id = ?',
        [$id]
    );
}

/** Fahrzeugliste. $f: q, status_id, typ_id, fachgruppe_id, nur_aktive, mit_auftraegen */
/** Sortierungen der Fahrzeugliste: Schlüssel => Beschriftung */
function vehicle_sorts(): array
{
    return [
        'standard'    => 'Favoriten, dann Name',
        'name'        => 'Name (A–Z)',
        'name_ab'     => 'Name (Z–A)',
        'status'      => 'Status',
        'fachgruppe'  => 'Fachgruppe',
        'kennzeichen' => 'Kennzeichen',
        'frist'       => 'nächste Frist zuerst',
        'auftraege'   => 'offene Aufträge zuerst',
        'km'          => 'Kilometerstand',
        'fms'         => 'Funkstatus',
        'neu'         => 'zuletzt angelegt',
    ];
}

/**
 * ORDER BY zu einer Sortierung. Reine Funktion; unbekannte Schlüssel
 * fallen auf die Vorgabe zurück. Ausgemusterte stehen immer hinten.
 */
function vehicle_sort_sql(string $sort): string
{
    $vorne = 'v.is_active DESC, ';
    return $vorne . match ($sort) {
        'name'        => 'v.bezeichnung ASC',
        'name_ab'     => 'v.bezeichnung DESC',
        'status'      => 's.sort_order IS NULL, s.sort_order ASC, v.bezeichnung ASC',
        'fachgruppe'  => 'f.label IS NULL, f.label ASC, v.bezeichnung ASC',
        'kennzeichen' => "NULLIF(v.kennzeichen, '') IS NULL, v.kennzeichen ASC",
        'frist'       => 'naechste_frist ASC, v.bezeichnung ASC',
        'auftraege'   => 'offene_auftraege DESC, v.bezeichnung ASC',
        'km'          => 'v.km_stand IS NULL, v.km_stand DESC',
        'fms'         => 'v.fms_status IS NULL, v.fms_status ASC, v.bezeichnung ASC',
        'neu'         => 'v.id DESC',
        default       => 'favorit DESC, v.bezeichnung ASC',
    };
}

/**
 * Stempel für die Kachel: Text oder null. Reine Funktion.
 * Ausgemustert schlägt den Status – ein ausgemustertes Fahrzeug ist ohnehin weg.
 */
function vehicle_stamp(array $v): ?string
{
    if (!(int)($v['is_active'] ?? 1)) {
        return 'Ausgemustert';
    }
    return match ((string)($v['status_slug'] ?? '')) {
        'nicht-einsatzbereit' => 'Nicht einsatzbereit',
        'wartung'             => 'In Wartung',
        default               => null,
    };
}

/**
 * Standort von Hand setzen (vom Handy). Rückgabe: Fehlermeldung oder null.
 * $genauigkeit in Metern, wie der Browser sie meldet.
 */
function vehicle_position_set(array $vehicle, float $lat, float $lng, ?float $genauigkeit, array $user): ?string
{
    if (abs($lat) > 90 || abs($lng) > 180 || ($lat === 0.0 && $lng === 0.0)) {
        return 'Die übermittelte Position ist unbrauchbar.';
    }
    $text = sprintf('%.5f, %.5f', $lat, $lng);
    db_update('vehicles', [
        'geo_lat'    => round($lat, 6),
        'geo_lng'    => round($lng, 6),
        'geo_at'     => date('Y-m-d H:i:s'),
        'geo_quelle' => 'mensch',
    ], 'id = ?', [(int)$vehicle['id']]);

    journal_add((int)$vehicle['id'], [
        'art'      => 'notiz',
        'titel'    => 'Standort gesetzt',
        'text'     => 'Standort: ' . $text
            . ($genauigkeit !== null && $genauigkeit > 0 ? sprintf(' (±%d m)', (int)round($genauigkeit)) : ''),
        'feld'     => 'geo',
        'neu_wert' => $text,
    ], $user);
    audit('fahrzeug.standort', 'vehicle', (int)$vehicle['id'], $text);
    return null;
}

/** Fahrzeug-ids, die diese Person angeheftet hat */
function vehicle_favorites(int $userId): array
{
    return array_map(static fn($r) => (int)$r['vehicle_id'],
        db_all('SELECT vehicle_id FROM vehicle_favorites WHERE user_id = ?', [$userId]));
}

/** Anheften oder lösen. Rückgabe: true, wenn es jetzt angeheftet ist. */
function vehicle_favorite_toggle(int $userId, int $vehicleId): bool
{
    if (db_val('SELECT 1 FROM vehicle_favorites WHERE user_id = ? AND vehicle_id = ?', [$userId, $vehicleId])) {
        db_exec('DELETE FROM vehicle_favorites WHERE user_id = ? AND vehicle_id = ?', [$userId, $vehicleId]);
        return false;
    }
    db_exec('INSERT IGNORE INTO vehicle_favorites (user_id, vehicle_id) VALUES (?,?)', [$userId, $vehicleId]);
    return true;
}

function vehicle_is_favorite(int $userId, int $vehicleId): bool
{
    return (bool)db_val('SELECT 1 FROM vehicle_favorites WHERE user_id = ? AND vehicle_id = ?', [$userId, $vehicleId]);
}

function vehicle_query(array $f = []): array
{
    $w = [];
    $p = [];
    if (!empty($f['q'])) {
        $w[] = '(v.bezeichnung LIKE ? OR v.funkrufname LIKE ? OR v.kennzeichen LIKE ?'
            . ' OR v.kennung LIKE ? OR v.issi LIKE ? OR v.opta LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like, $like, $like);
    }
    foreach (['status_id', 'typ_id', 'fachgruppe_id'] as $col) {
        if (!empty($f[$col])) {
            $w[] = 'v.' . $col . ' = ?';
            $p[] = (int)$f[$col];
        }
    }
    if (!empty($f['nur_aktive'])) {
        $w[] = 'v.is_active = 1';
    }
    if (!empty($f['nur_favoriten'])) {
        $w[] = 'EXISTS (SELECT 1 FROM vehicle_favorites vf2
                        WHERE vf2.vehicle_id = v.id AND vf2.user_id = ?)';
        $p[] = (int)$f['user_id'];
    }

    // Für die Sortierung nach Favoriten und nach der nächsten Frist
    $favUser = (int)($f['user_id'] ?? 0);
    $vorne = [$favUser];

    return db_all(
        'SELECT v.*, t.label AS typ_label, t.color AS typ_color,
                f.label AS fachgruppe_label,
                s.label AS status_label, s.color AS status_color, s.slug AS status_slug,
                (SELECT COUNT(*) FROM vehicle_favorites vf
                  WHERE vf.vehicle_id = v.id AND vf.user_id = ?) AS favorit,
                LEAST(COALESCE(v.hu_bis, \'9999-12-31\'),
                      COALESCE(v.sp_bis, \'9999-12-31\'),
                      COALESCE(v.uvv_bis, \'9999-12-31\')) AS naechste_frist,
                (SELECT COUNT(*) FROM vehicle_orders o
                  LEFT JOIN list_items os ON os.id = o.status_id
                 WHERE o.vehicle_id = v.id AND COALESCE(os.is_final,0) = 0) AS offene_auftraege
         FROM vehicles v
         LEFT JOIN list_items t ON t.id = v.typ_id
         LEFT JOIN list_items f ON f.id = v.fachgruppe_id
         LEFT JOIN list_items s ON s.id = v.status_id'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . vehicle_sort_sql((string)($f['sort'] ?? 'standard')),
        array_merge($vorne, $p)
    );
}

function vehicle_by_stein(string $assetId): ?array
{
    return $assetId === '' ? null : db_row('SELECT * FROM vehicles WHERE stein_asset_id = ?', [$assetId]);
}

function vehicle_extra_fields(): array
{
    return extra_fields('fahrzeug_extra_felder');
}

/**
 * Fristen eines Fahrzeugs bewerten: [['feld','label','datum','tage','status'], ...]
 * status: 'abgelaufen', 'bald' oder 'ok'. Ohne Datenbank, damit leicht prüfbar.
 */
function vehicle_deadlines(array $v, int $warnTage = 30, ?string $heute = null): array
{
    $heute ??= date('Y-m-d');
    $out = [];
    foreach (['hu_bis' => 'HU', 'sp_bis' => 'SP', 'uvv_bis' => 'UVV'] as $feld => $label) {
        $datum = $v[$feld] ?? null;
        if (!$datum) {
            continue;
        }
        $tage = (int)floor((strtotime((string)$datum) - strtotime($heute)) / 86400);
        $out[] = [
            'feld'   => $feld,
            'label'  => $label,
            'datum'  => (string)$datum,
            'tage'   => $tage,
            'status' => $tage < 0 ? 'abgelaufen' : ($tage <= $warnTage ? 'bald' : 'ok'),
        ];
    }
    return $out;
}

/* ==================================================================== *
 * Journal
 * ==================================================================== */

/** Hash einer Journalzeile – Grundlage der Kette */
function journal_hash(array $e, string $prev): string
{
    return hash('sha256', implode('|', [
        $prev,
        (int)$e['vehicle_id'],
        (string)$e['created_at'],
        (string)($e['user_id'] ?? ''),
        (string)($e['autor'] ?? ''),
        (string)($e['quelle'] ?? 'mensch'),
        (string)($e['art'] ?? ''),
        (string)($e['titel'] ?? ''),
        (string)($e['text'] ?? ''),
        (string)($e['feld'] ?? ''),
        (string)($e['alt_wert'] ?? ''),
        (string)($e['neu_wert'] ?? ''),
        (string)($e['ref_typ'] ?? ''),
        (string)($e['ref_id'] ?? ''),
    ]));
}

/**
 * Eintrag anhängen. Gibt die id zurück.
 * $e: art, titel, text, feld, alt_wert, neu_wert, ref_typ, ref_id, quelle
 */
function journal_add(int $vehicleId, array $e, ?array $user = null): int
{
    // Einträge aus dem Abgleich haben keinen Benutzer
    if ($user === null && ($e['quelle'] ?? 'mensch') === 'mensch') {
        $user = current_user();
    }

    $eintrag = [
        'vehicle_id' => $vehicleId,
        'created_at' => date('Y-m-d H:i:s'),
        'user_id'    => $user['id'] ?? null,
        'autor'      => mb_substr((string)($e['autor'] ?? ($user['display_name'] ?? $user['username'] ?? '')), 0, 150),
        'quelle'     => in_array($e['quelle'] ?? '', ['stein', 'divera', 'system'], true) ? $e['quelle'] : 'mensch',
        'art'        => mb_substr((string)($e['art'] ?? 'notiz'), 0, 40),
        'titel'      => mb_substr((string)($e['titel'] ?? ''), 0, 200),
        'text'       => (string)($e['text'] ?? ''),
        'feld'       => mb_substr((string)($e['feld'] ?? ''), 0, 60),
        'alt_wert'   => mb_substr((string)($e['alt_wert'] ?? ''), 0, 255),
        'neu_wert'   => mb_substr((string)($e['neu_wert'] ?? ''), 0, 255),
        'ref_typ'    => mb_substr((string)($e['ref_typ'] ?? ''), 0, 20),
        'ref_id'     => $e['ref_id'] ?? null,
    ];

    // Lesen des Vorgängers und Schreiben dürfen nicht von einem zweiten Abruf
    // unterbrochen werden – sonst zeigen zwei Einträge auf denselben Vorgänger
    // und die Prüfung meldet eine Manipulation, die es nicht gab.
    $sperre = 'ovb_journal_' . $vehicleId;
    db_lock($sperre, 10);
    try {
        $prev = (string)db_val(
            'SELECT hash FROM vehicle_journal WHERE vehicle_id = ? ORDER BY id DESC LIMIT 1',
            [$vehicleId],
            ''
        );
        $eintrag['prev_hash'] = $prev;
        $eintrag['hash'] = journal_hash($eintrag, $prev);
        return db_insert('vehicle_journal', $eintrag);
    } finally {
        db_unlock($sperre);
    }
}

/** Änderungen an Stammdaten ins Journal schreiben */
function journal_changes(int $vehicleId, array $alt, array $neu, ?array $user = null, string $quelle = 'mensch'): int
{
    $n = 0;
    foreach (FZ_JOURNAL_FELDER as $feld => $label) {
        if (!array_key_exists($feld, $neu)) {
            continue;
        }
        $a = vehicle_field_text($feld, $alt[$feld] ?? null);
        $b = vehicle_field_text($feld, $neu[$feld]);
        if ($a === $b) {
            continue;
        }
        journal_add($vehicleId, [
            'art'      => 'stammdaten',
            'titel'    => $label . ' geändert',
            'feld'     => $feld,
            'alt_wert' => $a,
            'neu_wert' => $b,
            'quelle'   => $quelle,
        ], $user);
        $n++;
    }
    return $n;
}

/** Feldwert lesbar machen (Listen-IDs, Ja/Nein, Datum) */
function vehicle_field_text(string $feld, mixed $wert): string
{
    if ($wert === null || $wert === '') {
        return '';
    }
    if (str_ends_with($feld, '_id')) {
        return list_label((int)$wert, '');
    }
    if ($feld === 'is_active') {
        return $wert ? 'ja' : 'nein';
    }
    if (in_array($feld, ['erstzulassung', 'hu_bis', 'sp_bis', 'uvv_bis'], true)) {
        return de_date((string)$wert);
    }
    return trim((string)$wert);
}

function journal_query(int $vehicleId, array $f = []): array
{
    $w = ['j.vehicle_id = ?'];
    $p = [$vehicleId];
    if (!empty($f['art'])) {
        $w[] = 'j.art = ?';
        $p[] = $f['art'];
    }
    if (!empty($f['ref_typ'])) {
        $w[] = 'j.ref_typ = ? AND j.ref_id = ?';
        array_push($p, $f['ref_typ'], (int)($f['ref_id'] ?? 0));
    }
    $sql = 'SELECT j.*, u.display_name AS benutzer
            FROM vehicle_journal j
            LEFT JOIN users u ON u.id = j.user_id
            WHERE ' . implode(' AND ', $w) . ' ORDER BY j.id DESC';
    if (!empty($f['limit'])) {
        $sql .= ' LIMIT ' . (int)$f['limit'];
    }
    return db_all($sql, $p);
}

/**
 * Die Hash-Kette nachrechnen.
 * Rückgabe: ['ok' => bool, 'geprueft' => int, 'fehler' => [['id','grund'], ...]]
 * Reine Funktion – bekommt die Einträge aufsteigend übergeben.
 */
function journal_check_chain(array $eintraege): array
{
    $prev = '';
    $fehler = [];
    foreach ($eintraege as $e) {
        if ((string)$e['prev_hash'] !== $prev) {
            $fehler[] = ['id' => (int)$e['id'], 'grund' => 'Verkettung stimmt nicht – es fehlt ein Eintrag davor.'];
        }
        $soll = journal_hash($e, (string)$e['prev_hash']);
        if (!hash_equals((string)$e['hash'], $soll)) {
            $fehler[] = ['id' => (int)$e['id'], 'grund' => 'Inhalt passt nicht zur Prüfsumme – der Eintrag wurde verändert.'];
        }
        $prev = (string)$e['hash'];
    }
    return ['ok' => $fehler === [], 'geprueft' => count($eintraege), 'fehler' => $fehler];
}

function journal_verify(int $vehicleId): array
{
    return journal_check_chain(
        db_all('SELECT * FROM vehicle_journal WHERE vehicle_id = ? ORDER BY id', [$vehicleId])
    );
}

/* ==================================================================== *
 * Fahrzeug speichern
 * ==================================================================== */

/** Fahrzeug aus dem Formular speichern. Gibt [id, fehler[]] zurück. */
function vehicle_save_from_post(?array $existing, array $user): array
{
    $errors = [];
    $bezeichnung = trim(post_str('bezeichnung'));
    if ($bezeichnung === '') {
        $errors[] = 'Bitte eine Bezeichnung angeben, zum Beispiel „GKW 1".';
    }

    $data = [
        'bezeichnung'       => mb_substr($bezeichnung, 0, 150),
        'funkrufname'       => mb_substr(post_str('funkrufname'), 0, 80),
        'issi'              => mb_substr(post_str('issi'), 0, 80),
        'opta'              => mb_substr(post_str('opta'), 0, 60),
        'ric'               => mb_substr(post_str('ric'), 0, 30),
        'kennzeichen'       => mb_substr(post_str('kennzeichen'), 0, 20),
        'kennung'           => mb_substr(post_str('kennung'), 0, 40),
        'typ_id'            => post_int('typ_id'),
        'fachgruppe_id'     => post_int('fachgruppe_id'),
        'status_id'         => post_int('status_id') ?: list_default_id('fahrzeug_status'),
        'hersteller'        => mb_substr(post_str('hersteller'), 0, 80),
        'modell'            => mb_substr(post_str('modell'), 0, 80),
        'baujahr'           => post_int('baujahr') ?: null,
        'fahrgestellnummer' => mb_substr(post_str('fahrgestellnummer'), 0, 40),
        'erstzulassung'     => post_date('erstzulassung'),
        'km_stand'          => post_int('km_stand'),
        'betriebsstunden'   => post_int('betriebsstunden'),
        'hu_bis'            => post_date('hu_bis'),
        'sp_bis'            => post_date('sp_bis'),
        'uvv_bis'           => post_date('uvv_bis'),
        'standort'          => mb_substr(post_str('standort'), 0, 150),
        'notiz'             => post_str('notiz'),
        'extra'             => extra_from_post(vehicle_extra_fields()),
        'is_active'         => post_bool('is_active'),
    ];
    $data['ausgemustert_am'] = $data['is_active'] ? null : (post_date('ausgemustert_am') ?: date('Y-m-d'));

    if ($errors) {
        return [null, $errors];
    }

    if ($existing) {
        db_update('vehicles', $data, 'id = ?', [$existing['id']]);
        $id = (int)$existing['id'];
        journal_changes($id, $existing, $data, $user);
        audit('fahrzeug.bearbeitet', 'vehicle', $id, $data['bezeichnung']);
    } else {
        $data['created_by'] = (int)$user['id'];
        $id = db_insert('vehicles', $data);
        journal_add($id, [
            'art'   => 'anlage',
            'titel' => 'Fahrzeugakte angelegt',
            'text'  => $data['bezeichnung'] . ($data['kennzeichen'] ? ' · ' . $data['kennzeichen'] : ''),
        ], $user);
        audit('fahrzeug.angelegt', 'vehicle', $id, $data['bezeichnung']);
    }

    return [$id, []];
}

/* ==================================================================== *
 * Instandsetzungsaufträge
 * ==================================================================== */

function order_find(int $id): ?array
{
    return db_row(
        'SELECT o.*, v.bezeichnung AS fahrzeug, v.kennzeichen,
                a.label AS art_label, a.color AS art_color,
                p.label AS prio_label, p.color AS prio_color, p.weight AS prio_weight,
                s.label AS status_label, s.color AS status_color, s.slug AS status_slug, s.is_final AS status_final,
                u.display_name AS ersteller
         FROM vehicle_orders o
         JOIN vehicles v ON v.id = o.vehicle_id
         LEFT JOIN list_items a ON a.id = o.art_id
         LEFT JOIN list_items p ON p.id = o.prioritaet_id
         LEFT JOIN list_items s ON s.id = o.status_id
         LEFT JOIN users u ON u.id = o.created_by
         WHERE o.id = ?',
        [$id]
    );
}

/** Aufträge. $f: vehicle_id, offen, status_id, limit */
function order_query(array $f = []): array
{
    $w = [];
    $p = [];
    if (!empty($f['vehicle_id'])) {
        $w[] = 'o.vehicle_id = ?';
        $p[] = (int)$f['vehicle_id'];
    }
    if (!empty($f['status_id'])) {
        $w[] = 'o.status_id = ?';
        $p[] = (int)$f['status_id'];
    }
    if (!empty($f['offen'])) {
        $w[] = 'COALESCE(s.is_final, 0) = 0';
    }
    if (!empty($f['q'])) {
        $w[] = '(o.titel LIKE ? OR o.nummer LIKE ? OR o.thw_nummer LIKE ? OR o.auftragsnummer LIKE ?'
            . ' OR o.werkstatt LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like, $like);
    }
    $sql = 'SELECT o.*, v.bezeichnung AS fahrzeug, v.kennzeichen,
                   (SELECT COUNT(*) FROM vehicle_files f
                     WHERE f.order_id = o.id AND f.art = \'bild\') AS fotos,
                   (SELECT COUNT(*) FROM vehicle_files f
                     WHERE f.order_id = o.id AND f.art = \'dokument\') AS dokumente,
                   a.label AS art_label, a.color AS art_color,
                   p.label AS prio_label, p.color AS prio_color, p.weight AS prio_weight,
                   s.label AS status_label, s.color AS status_color, s.is_final AS status_final
            FROM vehicle_orders o
            JOIN vehicles v ON v.id = o.vehicle_id
            LEFT JOIN list_items a ON a.id = o.art_id
            LEFT JOIN list_items p ON p.id = o.prioritaet_id
            LEFT JOIN list_items s ON s.id = o.status_id'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY COALESCE(s.is_final,0), p.weight DESC, o.gemeldet_am DESC, o.id DESC';
    if (!empty($f['limit'])) {
        $sql .= ' LIMIT ' . (int)$f['limit'];
    }
    return db_all($sql, $p);
}

/** Fortlaufende Auftragsnummer, z. B. 2026-0007 */
function order_next_number(?string $jahr = null): string
{
    $jahr ??= date('Y');
    $max = (string)db_val(
        'SELECT MAX(nummer) FROM vehicle_orders WHERE nummer LIKE ?',
        [$jahr . '-%'],
        ''
    );
    $lfd = $max !== '' ? (int)substr($max, 5) + 1 : 1;
    return sprintf('%s-%04d', $jahr, $lfd);
}

/** Auftrag anlegen oder ändern. Gibt [id, fehler[]] zurück. */
function order_save_from_post(?array $existing, array $vehicle, array $user): array
{
    $errors = [];
    $titel = trim(post_str('titel'));
    if ($titel === '') {
        $errors[] = 'Bitte kurz beschreiben, worum es geht.';
    }

    $data = [
        'titel'         => mb_substr($titel, 0, 200),
        'beschreibung'  => post_str('beschreibung'),
        'art_id'        => post_int('art_id') ?: list_default_id('auftrag_art'),
        'prioritaet_id' => post_int('prioritaet_id') ?: list_default_id('auftrag_prioritaet'),
        'werkstatt'     => mb_substr(post_str('werkstatt'), 0, 150),
        'auftragsnummer' => mb_substr(post_str('auftragsnummer'), 0, 60),
        'thw_nummer'    => mb_substr(trim(post_str('thw_nummer')), 0, 60),
        'gemeldet_von'  => mb_substr(post_str('gemeldet_von') ?: (string)($user['display_name'] ?: $user['username']), 0, 150),
        'gemeldet_am'   => post_date('gemeldet_am') ?: date('Y-m-d'),
        'faellig_am'    => post_date('faellig_am'),
        'km_stand'      => post_int('km_stand'),
        'kosten_geschaetzt' => post_dec('kosten_geschaetzt') ?: null,
        'ausfall'       => post_bool('ausfall'),
    ];
    if (can('manage_vehicles')) {
        $data['kosten_netto'] = post_dec('kosten_netto') ?: null;
    }

    if ($errors) {
        return [null, $errors];
    }

    if ($existing) {
        db_update('vehicle_orders', $data, 'id = ?', [$existing['id']]);
        $id = (int)$existing['id'];
        if (trim((string)($existing['thw_nummer'] ?? '')) !== trim($data['thw_nummer'])) {
            journal_add((int)$vehicle['id'], [
                'art'      => 'auftrag',
                'titel'    => 'Nummer der THW-Verwaltung ' . ($data['thw_nummer'] !== '' ? 'gesetzt' : 'entfernt'),
                'text'     => $data['thw_nummer'] !== '' ? $data['thw_nummer'] : (string)$existing['thw_nummer'],
                'alt_wert' => (string)($existing['thw_nummer'] ?? ''),
                'neu_wert' => $data['thw_nummer'],
                'ref_typ'  => 'auftrag',
                'ref_id'   => (int)$existing['id'],
            ], $user);
        }
        journal_add((int)$vehicle['id'], [
            'art'     => 'auftrag',
            'titel'   => 'Auftrag ' . $existing['nummer'] . ' bearbeitet',
            'text'    => $data['titel'],
            'ref_typ' => 'auftrag',
            'ref_id'  => $id,
        ], $user);
        audit('auftrag.bearbeitet', 'vehicle_order', $id, $data['titel']);
    } else {
        $data['vehicle_id'] = (int)$vehicle['id'];
        $data['nummer']     = order_next_number();
        $data['status_id']  = list_default_id('auftrag_status');
        $data['created_by'] = (int)$user['id'];
        $id = db_insert('vehicle_orders', $data);
        journal_add((int)$vehicle['id'], [
            'art'     => 'auftrag',
            'titel'   => 'Auftrag ' . $data['nummer'] . ' angelegt: ' . $data['titel'],
            'text'    => $data['beschreibung'],
            'neu_wert' => list_label($data['art_id'], ''),
            'ref_typ' => 'auftrag',
            'ref_id'  => $id,
        ], $user);
        audit('auftrag.angelegt', 'vehicle_order', $id, $data['titel']);
        $empfaenger = array_diff(notify_leitung(), [(int)$user['id']]);
        $wer = sprintf('%s · gemeldet von %s', (string)$vehicle['bezeichnung'], $data['gemeldet_von']);
        notify_queue($empfaenger, 'auftrag_neu', 'Neue Meldung: ' . $data['titel'], $wer,
            '?p=vehicle_order&id=' . $id);
        if (!empty($data['ausfall'])) {
            notify_queue($empfaenger, 'fahrzeug_ausfall',
                'Fahrzeug steht still: ' . (string)$vehicle['bezeichnung'],
                $data['titel'], '?p=vehicle_order&id=' . $id);
        }
    }

    return [$id, []];
}

/**
 * Status eines Auftrags ändern; schreibt den Schritt ins Journal.
 * Gibt eine Fehlermeldung zurück oder null.
 */
function order_set_status(array $order, int $statusId, string $bemerkung, array $user): ?string
{
    $status = list_item($statusId);
    if (!$status || $status['list_key'] !== 'auftrag_status') {
        return 'Unbekannter Status.';
    }
    $data = ['status_id' => $statusId];
    if ((int)$status['is_final'] === 1 && !$order['erledigt_am']) {
        $data['erledigt_am'] = date('Y-m-d');
    }
    if ((int)$status['is_final'] === 0) {
        $data['erledigt_am'] = null;
    }
    db_update('vehicle_orders', $data, 'id = ?', [(int)$order['id']]);

    journal_add((int)$order['vehicle_id'], [
        'art'      => 'auftrag',
        'titel'    => 'Auftrag ' . $order['nummer'] . ': ' . $status['label'],
        'text'     => $bemerkung,
        'feld'     => 'status',
        'alt_wert' => (string)($order['status_label'] ?? ''),
        'neu_wert' => (string)$status['label'],
        'ref_typ'  => 'auftrag',
        'ref_id'   => (int)$order['id'],
    ], $user);
    audit('auftrag.status', 'vehicle_order', (int)$order['id'], $status['label']);
    return null;
}
