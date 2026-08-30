<?php
declare(strict_types=1);

function wish_find(int $id): ?array
{
    return db_row('SELECT * FROM wishes WHERE id = ?', [$id]);
}

/**
 * Wunschliste mit Filtern.
 * $f: q, status_id, fachgruppe_id, kategorie_id, dringlichkeit_id, budget_id,
 *     nice, mine, offen, sort
 */
function wish_query(array $f = []): array
{
    $w = [];
    $p = [];

    if (!empty($f['q'])) {
        $w[] = '(w.bezeichnung LIKE ? OR w.beschreibung LIKE ? OR w.lieferant LIKE ? OR w.artikelnummer LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like);
    }
    foreach (['status_id', 'fachgruppe_id', 'kategorie_id', 'dringlichkeit_id', 'budget_id'] as $col) {
        if (!empty($f[$col])) {
            $w[] = 'w.' . $col . ' = ?';
            $p[] = (int)$f[$col];
        }
    }
    if (isset($f['nice']) && $f['nice'] !== '') {
        $w[] = 'w.nice_to_have = ?';
        $p[] = (int)$f['nice'];
    }
    if (!empty($f['mine'])) {
        $w[] = 'w.created_by = ?';
        $p[] = (int)$f['mine'];
    }
    if (!empty($f['offen'])) {
        $w[] = 'COALESCE(st.is_final, 0) = 0';
    }
    if (!empty($f['jahr'])) {
        $w[] = 'b.jahr = ?';
        $p[] = (int)$f['jahr'];
    }

    $sort = $f['sort'] ?? 'prio';
    $order = match ($sort) {
        'neu'     => 'w.created_at DESC',
        'betrag'  => 'w.netto_gesamt DESC',
        'name'    => 'w.bezeichnung ASC',
        'frist'   => 'w.benoetigt_bis IS NULL, w.benoetigt_bis ASC',
        'stimmen' => 'votes DESC, dr.weight DESC',
        default   => 'w.prioritaet DESC, dr.weight DESC, w.nice_to_have ASC, w.created_at DESC',
    };

    $sql = 'SELECT w.*,
                   st.label AS status_label, st.color AS status_color, st.is_final AS status_final,
                   dr.label AS dring_label, dr.color AS dring_color, dr.weight AS dring_weight,
                   fg.label AS fachgruppe_label,
                   ka.label AS kategorie_label,
                   ei.label AS einheit_label,
                   b.name AS budget_name, b.jahr AS budget_jahr,
                   u.display_name AS ersteller,
                   (SELECT COUNT(*) FROM wish_attachments a WHERE a.wish_id = w.id) AS anlagen,
                   (SELECT COALESCE(SUM(v.points),0) FROM wish_votes v WHERE v.wish_id = w.id) AS votes
            FROM wishes w
            LEFT JOIN list_items st ON st.id = w.status_id
            LEFT JOIN list_items dr ON dr.id = w.dringlichkeit_id
            LEFT JOIN list_items fg ON fg.id = w.fachgruppe_id
            LEFT JOIN list_items ka ON ka.id = w.kategorie_id
            LEFT JOIN list_items ei ON ei.id = w.einheit_id
            LEFT JOIN budgets   b  ON b.id  = w.budget_id
            LEFT JOIN users     u  ON u.id  = w.created_by'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . $order;

    return db_all($sql, $p);
}

function wish_stats(array $rows): array
{
    $s = ['anzahl' => count($rows), 'netto' => 0.0, 'netto_offen' => 0.0, 'nice' => 0.0];
    foreach ($rows as $r) {
        $s['netto'] += (float)$r['netto_gesamt'];
        if (!(int)$r['status_final']) {
            $s['netto_offen'] += (float)$r['netto_gesamt'];
        }
        if ((int)$r['nice_to_have']) {
            $s['nice'] += (float)$r['netto_gesamt'];
        }
    }
    return $s;
}

function wish_votes_of_user(int $userId): array
{
    $out = [];
    foreach (db_all('SELECT wish_id, points FROM wish_votes WHERE user_id = ?', [$userId]) as $r) {
        $out[(int)$r['wish_id']] = (int)$r['points'];
    }
    return $out;
}

function wish_attachments(int $wishId): array
{
    return db_all('SELECT a.*, u.display_name AS uploader
                   FROM wish_attachments a
                   LEFT JOIN users u ON u.id = a.uploaded_by
                   WHERE a.wish_id = ? ORDER BY a.created_at', [$wishId]);
}

function wish_comments(int $wishId): array
{
    return db_all('SELECT c.*, u.display_name AS autor
                   FROM wish_comments c
                   LEFT JOIN users u ON u.id = c.user_id
                   WHERE c.wish_id = ? ORDER BY c.created_at', [$wishId]);
}

/** Zusatzfelder (JSON-Spalte extra) lesen */
function wish_extra(array $wish): array
{
    $v = json_decode((string)($wish['extra'] ?? ''), true);
    return is_array($v) ? $v : [];
}

/**
 * Wunsch aus dem Formular speichern.
 * Gibt [id, fehler[]] zurück.
 */
function wish_save_from_post(?array $existing, array $user): array
{
    $errors = [];

    $bezeichnung = post_str('bezeichnung');
    if ($bezeichnung === '') {
        $errors[] = 'Bitte eine Bezeichnung angeben.';
    }

    $anzahl = post_dec('anzahl', 1);
    if ($anzahl <= 0) {
        $anzahl = 1;
    }
    $einzel = post_dec('netto_einzel');
    $gesamt = post_dec('netto_gesamt');
    if ($gesamt <= 0) {
        $gesamt = round($einzel * $anzahl, 2);
    }
    if ($einzel <= 0 && $anzahl > 0) {
        $einzel = round($gesamt / $anzahl, 2);
    }

    $begruendung = post_str('begruendung');
    if (setting_bool('wunsch_begruendung_pflicht', true) && $begruendung === '') {
        $errors[] = 'Bitte eine Begründung angeben – sie hilft bei der gemeinsamen Priorisierung.';
    }

    $extra = [];
    foreach (wish_extra_fields() as $key => $def) {
        $extra[$key] = $def['type'] === 'bool' ? post_bool('extra_' . $key) : post_str('extra_' . $key);
    }

    $data = [
        'bezeichnung'      => mb_substr($bezeichnung, 0, 200),
        'beschreibung'     => post_str('beschreibung'),
        'begruendung'      => $begruendung,
        'anzahl'           => $anzahl,
        'einheit_id'       => post_int('einheit_id'),
        'netto_einzel'     => $einzel,
        'netto_gesamt'     => $gesamt,
        'mwst_satz'        => post_dec('mwst_satz', setting_float('mwst_satz', 19.0)),
        'fachgruppe_id'    => post_int('fachgruppe_id'),
        'kategorie_id'     => post_int('kategorie_id'),
        'dringlichkeit_id' => post_int('dringlichkeit_id') ?: list_default_id('dringlichkeit'),
        'nice_to_have'     => post_bool('nice_to_have'),
        'benoetigt_bis'    => post_date('benoetigt_bis'),
        'lieferant'        => mb_substr(post_str('lieferant'), 0, 150),
        'artikelnummer'    => mb_substr(post_str('artikelnummer'), 0, 100),
        'link'             => mb_substr(post_str('link'), 0, 500),
        'antragsteller'    => mb_substr(post_str('antragsteller') ?: (string)$user['display_name'], 0, 150),
        'extra'            => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
        'updated_by'       => (int)$user['id'],
    ];

    // Status, Priorität und Budgetzuordnung nur mit entsprechendem Recht
    if (can('change_status')) {
        $data['status_id'] = post_int('status_id') ?: list_default_id('wunsch_status');
    } elseif (!$existing) {
        $data['status_id'] = list_default_id('wunsch_status');
    }
    if (can('manage_wishes')) {
        $data['prioritaet'] = post_int('prioritaet', 0);
        $data['budget_id']  = post_int('budget_id');
    }

    // Angebotspflicht ab Betrag
    $pflichtAb = setting_float('wunsch_angebot_pflicht_ab', 0);
    if ($pflichtAb > 0 && $gesamt >= $pflichtAb) {
        $hasFile = $existing && (int)db_val('SELECT COUNT(*) FROM wish_attachments WHERE wish_id = ?', [$existing['id']], 0) > 0;
        $uploadNow = !empty($_FILES['anlagen']['name'][0]);
        if (!$hasFile && !$uploadNow) {
            $errors[] = sprintf(
                'Ab einem Nettobetrag von %s muss mindestens ein Angebot hochgeladen werden.',
                money($pflichtAb)
            );
        }
    }

    if ($errors) {
        return [null, $errors];
    }

    if ($existing) {
        db_update('wishes', $data, 'id = ?', [$existing['id']]);
        $id = (int)$existing['id'];
        audit('wunsch.bearbeitet', 'wish', $id, $data['bezeichnung']);
    } else {
        $data['created_by'] = (int)$user['id'];
        $data['source'] = 'manuell';
        $id = db_insert('wishes', $data);
        audit('wunsch.angelegt', 'wish', $id, $data['bezeichnung']);
    }

    return [$id, []];
}
