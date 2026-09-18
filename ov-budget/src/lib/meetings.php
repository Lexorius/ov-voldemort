<?php
declare(strict_types=1);

/**
 * Besprechungen und Talking Points.
 *
 * Themen entstehen im Themenspeicher (meeting_id NULL) und werden auf eine
 * Besprechung gesetzt. Dort bekommen sie Status und Ergebnis. Ein vertagtes
 * Thema bleibt im Protokoll seiner Besprechung stehen; übernommen wird eine
 * Kopie mit Verweis auf den Vorgänger – so stimmen beide Protokolle.
 */

function tp_label(): string
{
    return (string)setting('tp_bezeichnung', 'Talking Points');
}

/* ==================================================================== */
/* Reine Hilfsfunktionen (ohne Datenbank)                                */
/* ==================================================================== */

/**
 * Geplante Anfangszeiten für die Tagesordnung.
 * $beginn "HH:MM" oder "HH:MM:SS", $dauern Minuten je Punkt (null = Vorgabe).
 * Rückgabe: Liste von "HH:MM"; ohne Beginn eine Liste leerer Zeichenketten.
 */
function meeting_agenda_times(?string $beginn, array $dauern, int $vorgabe = 10): array
{
    if ($beginn === null || !preg_match('/^(\d{1,2}):(\d{2})/', $beginn, $m)) {
        return array_fill(0, count($dauern), '');
    }
    $minute = (int)$m[1] * 60 + (int)$m[2];
    $out = [];
    foreach ($dauern as $d) {
        $out[] = sprintf('%02d:%02d', intdiv($minute, 60) % 24, $minute % 60);
        $minute += ($d === null || (int)$d <= 0) ? $vorgabe : (int)$d;
    }
    return $out;
}

/** Summe der Dauer in Minuten, fehlende Angaben mit der Vorgabe */
function meeting_total_minutes(array $dauern, int $vorgabe = 10): int
{
    $summe = 0;
    foreach ($dauern as $d) {
        $summe += ($d === null || (int)$d <= 0) ? $vorgabe : (int)$d;
    }
    return $summe;
}

/** "95" -> "1 Std. 35 Min." */
function minutes_human(int $minuten): string
{
    if ($minuten < 60) {
        return $minuten . ' Min.';
    }
    $rest = $minuten % 60;
    return intdiv($minuten, 60) . ' Std.' . ($rest > 0 ? ' ' . $rest . ' Min.' : '');
}

/**
 * Reihenfolge ändern: $id in der Liste $ids eine Position nach oben oder
 * unten tauschen. Am Rand bleibt alles, wie es ist.
 */
function tp_reorder(array $ids, int $id, string $richtung): array
{
    $ids = array_values(array_map('intval', $ids));
    $pos = array_search($id, $ids, true);
    if ($pos === false) {
        return $ids;
    }
    $ziel = $richtung === 'hoch' ? $pos - 1 : $pos + 1;
    if ($ziel < 0 || $ziel >= count($ids)) {
        return $ids;
    }
    [$ids[$pos], $ids[$ziel]] = [$ids[$ziel], $ids[$pos]];
    return $ids;
}

/* ==================================================================== */
/* Besprechungen                                                         */
/* ==================================================================== */

function meeting_find(int $id): ?array
{
    return db_row(
        'SELECT m.*, t.label AS typ_label, t.color AS typ_color,
                s.titel AS serie_titel, s.regel AS serie_regel, s.intervall AS serie_intervall,
                s.wochentag AS serie_wochentag, s.nte AS serie_nte, s.monatstag AS serie_monatstag,
                s.beginn AS serie_beginn
         FROM meetings m
         LEFT JOIN list_items t ON t.id = m.typ_id
         LEFT JOIN meeting_series s ON s.id = m.series_id
         WHERE m.id = ?',
        [$id]
    );
}

/** Serienregel eines Termins für series_describe() */
function meeting_series_rule(array $m): array
{
    return [
        'regel' => $m['serie_regel'] ?? '', 'intervall' => $m['serie_intervall'] ?? 1,
        'wochentag' => $m['serie_wochentag'] ?? 1, 'nte' => $m['serie_nte'] ?? 1,
        'monatstag' => $m['serie_monatstag'] ?? 1, 'beginn' => $m['serie_beginn'] ?? null,
    ];
}

/**
 * $f: zeit (kommend | vergangen | alle), typ_id, limit
 */
function meeting_query(array $f = []): array
{
    $w = [];
    $p = [];
    $zeit = $f['zeit'] ?? 'alle';

    if ($zeit === 'kommend') {
        $w[] = "(m.datum >= CURDATE() OR m.status = 'geplant')";
    } elseif ($zeit === 'vergangen') {
        $w[] = "(m.datum < CURDATE() AND m.status = 'abgeschlossen')";
    }
    if (!empty($f['typ_id'])) {
        $w[] = 'm.typ_id = ?';
        $p[] = (int)$f['typ_id'];
    }
    // Für Auswahllisten: nur Besprechungen, auf die man noch Themen setzen kann
    if (!empty($f['nur_geplant'])) {
        $w[] = "m.status = 'geplant'";
    }

    $order = $zeit === 'kommend' ? 'm.datum ASC, m.beginn ASC' : 'm.datum DESC, m.beginn DESC';

    $sql = 'SELECT m.*, t.label AS typ_label, t.color AS typ_color,
                   (SELECT COUNT(*) FROM talking_points tp WHERE tp.meeting_id = m.id) AS punkte,
                   (SELECT COUNT(*) FROM talking_points tp
                     LEFT JOIN list_items s ON s.id = tp.status_id
                    WHERE tp.meeting_id = m.id AND COALESCE(s.is_final,0) = 0
                      AND COALESCE(s.slug,\'\') <> \'vertagt\') AS offen
            FROM meetings m
            LEFT JOIN list_items t ON t.id = m.typ_id'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . $order;

    if (!empty($f['limit'])) {
        $sql .= ' LIMIT ' . (int)$f['limit'];
    }
    return db_all($sql, $p);
}

/** Nächste geplante Besprechung – für die Übersicht */
function meeting_next(): ?array
{
    return meeting_query(['zeit' => 'kommend', 'limit' => 1])[0] ?? null;
}

function meeting_save_from_post(?array $existing, array $user): array
{
    $errors = [];
    $titel = post_str('titel');
    $datum = post_date('datum');

    if ($titel === '') {
        $errors[] = 'Bitte einen Titel angeben.';
    }
    if (!$datum) {
        $errors[] = 'Bitte ein gültiges Datum angeben.';
    }
    $uhrzeit = static function (string $feld): ?string {
        $v = post_str($feld);
        return preg_match('/^\d{1,2}:\d{2}$/', $v) ? $v . ':00' : null;
    };

    if ($errors) {
        return [null, $errors];
    }

    $data = [
        'titel'         => mb_substr($titel, 0, 200),
        'typ_id'        => post_int('typ_id'),
        'datum'         => $datum,
        'beginn'        => $uhrzeit('beginn'),
        'ende'          => $uhrzeit('ende'),
        'ort'           => mb_substr(post_str('ort'), 0, 150),
        'leitung'       => mb_substr(post_str('leitung'), 0, 150),
        'protokoll_von' => mb_substr(post_str('protokoll_von'), 0, 150),
        'teilnehmer'    => post_str('teilnehmer'),
        'beschreibung'  => post_str('beschreibung'),
    ];

    if ($existing) {
        db_update('meetings', $data, 'id = ?', [$existing['id']]);
        $id = (int)$existing['id'];
        audit('besprechung.bearbeitet', 'meeting', $id, $data['titel']);
    } else {
        $data['created_by'] = (int)$user['id'];
        $id = db_insert('meetings', $data);
        audit('besprechung.angelegt', 'meeting', $id, $data['titel']);
    }
    return [$id, []];
}

/* ==================================================================== */
/* Talking Points                                                        */
/* ==================================================================== */

function tp_find(int $id): ?array
{
    return db_row('SELECT * FROM talking_points WHERE id = ?', [$id]);
}

/** Gemeinsamer Abfragekopf mit allen Beschriftungen */
function tp_select(): string
{
    return 'SELECT tp.*,
                   st.label AS status_label, st.color AS status_color, st.slug AS status_slug,
                   st.is_final AS status_final,
                   pr.label AS prio_label, pr.color AS prio_color, pr.weight AS prio_weight,
                   fg.label AS fachgruppe_label,
                   u.display_name AS einbringer,
                   m.titel AS meeting_titel, m.datum AS meeting_datum, m.status AS meeting_status,
                   td.titel AS todo_titel,
                   (SELECT COUNT(*) FROM talking_points n WHERE n.vorgaenger_id = tp.id) AS nachfolger
            FROM talking_points tp
            LEFT JOIN list_items st ON st.id = tp.status_id
            LEFT JOIN list_items pr ON pr.id = tp.prioritaet_id
            LEFT JOIN list_items fg ON fg.id = tp.fachgruppe_id
            LEFT JOIN users      u  ON u.id  = tp.eingebracht_von
            LEFT JOIN meetings   m  ON m.id  = tp.meeting_id
            LEFT JOIN todos      td ON td.id = tp.todo_id';
}

/** Punkte einer Besprechung in Reihenfolge */
function meeting_points(int $meetingId): array
{
    return db_all(tp_select() . ' WHERE tp.meeting_id = ? ORDER BY tp.sort_order, tp.id', [$meetingId]);
}

/**
 * Themenspeicher: offene Themen ohne Besprechung und vertagte Themen, die
 * noch nicht auf eine neue Besprechung übernommen wurden.
 * $f: q, fachgruppe_id
 */
function tp_backlog(array $f = []): array
{
    $w = ["((tp.meeting_id IS NULL AND COALESCE(st.is_final,0) = 0)
            OR (st.slug = 'vertagt' AND NOT EXISTS
                (SELECT 1 FROM talking_points n WHERE n.vorgaenger_id = tp.id)))"];
    $p = [];

    if (!empty($f['q'])) {
        $w[] = '(tp.titel LIKE ? OR tp.beschreibung LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like);
    }
    if (!empty($f['fachgruppe_id'])) {
        $w[] = 'tp.fachgruppe_id = ?';
        $p[] = (int)$f['fachgruppe_id'];
    }

    return db_all(
        tp_select() . ' WHERE ' . implode(' AND ', $w)
        . ' ORDER BY pr.weight DESC, tp.created_at ASC',
        $p
    );
}

/** Darf der Benutzer diesen Punkt bearbeiten? */
function tp_editable(array $tp, ?array $user = null): bool
{
    $user ??= current_user();
    if (!$user) {
        return false;
    }
    if (can('manage_meetings')) {
        return true;
    }
    if ((int)($tp['eingebracht_von'] ?? 0) !== (int)$user['id']) {
        return false;
    }
    // Eigene Themen nur, solange sie noch nicht besprochen sind
    $status = list_item((int)($tp['status_id'] ?? 0));
    if ($status && ((int)$status['is_final'] === 1 || $status['slug'] === 'vertagt')) {
        return false;
    }
    if (!empty($tp['meeting_id'])) {
        $m = db_row('SELECT status FROM meetings WHERE id = ?', [(int)$tp['meeting_id']]);
        if ($m && $m['status'] === 'abgeschlossen') {
            return false;
        }
    }
    return true;
}

function tp_save_from_post(?array $existing, array $user): array
{
    $errors = [];
    $titel = post_str('titel');
    if ($titel === '') {
        $errors[] = 'Bitte einen Titel angeben.';
    }
    if ($errors) {
        return [null, $errors];
    }

    $dauer = post_int('dauer_min');
    $data = [
        'titel'         => mb_substr($titel, 0, 200),
        'beschreibung'  => post_str('beschreibung'),
        'fachgruppe_id' => post_int('fachgruppe_id'),
        'prioritaet_id' => post_int('prioritaet_id') ?: list_default_id('todo_prioritaet'),
        'dauer_min'     => ($dauer !== null && $dauer > 0) ? min($dauer, 600) : null,
    ];

    // Zuordnung, Verantwortung und Ergebnis nur für die Leitung
    if (can('manage_meetings')) {
        $data['verantwortlich'] = mb_substr(post_str('verantwortlich'), 0, 150);
        $data['ergebnis'] = post_str('ergebnis');
        if ($existing) {
            $data['status_id'] = post_int('status_id') ?: $existing['status_id'];
        }
    }

    if ($existing) {
        db_update('talking_points', $data, 'id = ?', [$existing['id']]);
        $id = (int)$existing['id'];
        audit('tp.bearbeitet', 'talking_point', $id, $data['titel']);
        return [$id, []];
    }

    $meetingId = post_int('meeting_id');
    if ($meetingId && !db_val('SELECT id FROM meetings WHERE id = ? AND status = ?', [$meetingId, 'geplant'])) {
        $meetingId = null;
    }
    $data += [
        'meeting_id'      => $meetingId,
        'status_id'       => list_default_id('tp_status'),
        'sort_order'      => $meetingId ? meeting_next_sort($meetingId) : 0,
        'eingebracht_von' => (int)$user['id'],
    ];
    $id = db_insert('talking_points', $data);
    audit('tp.angelegt', 'talking_point', $id, $data['titel']);
    return [$id, []];
}

function meeting_next_sort(int $meetingId): int
{
    return (int)db_val('SELECT COALESCE(MAX(sort_order),0) + 10 FROM talking_points WHERE meeting_id = ?', [$meetingId], 10);
}

/**
 * Thema auf eine Besprechung setzen. Vertagte Themen werden kopiert, alle
 * anderen verschoben. Gibt die id des Punktes auf der Besprechung zurück.
 */
function tp_assign(int $tpId, int $meetingId): ?int
{
    $tp = tp_find($tpId);
    if (!$tp) {
        return null;
    }
    $status = list_item((int)$tp['status_id']);
    $vertagt = $status && $status['slug'] === 'vertagt';

    if ($vertagt && $tp['meeting_id']) {
        $neu = db_insert('talking_points', [
            'meeting_id'      => $meetingId,
            'vorgaenger_id'   => (int)$tp['id'],
            'titel'           => $tp['titel'],
            'beschreibung'    => $tp['beschreibung'],
            'fachgruppe_id'   => $tp['fachgruppe_id'],
            'prioritaet_id'   => $tp['prioritaet_id'],
            'status_id'       => list_default_id('tp_status'),
            'dauer_min'       => $tp['dauer_min'],
            'verantwortlich'  => $tp['verantwortlich'],
            'sort_order'      => meeting_next_sort($meetingId),
            'eingebracht_von' => $tp['eingebracht_von'],
        ]);
        audit('tp.uebernommen', 'talking_point', $neu, $tp['titel']);
        return $neu;
    }

    db_update('talking_points', [
        'meeting_id' => $meetingId,
        'sort_order' => meeting_next_sort($meetingId),
    ], 'id = ?', [$tpId]);
    return $tpId;
}

/** Punkt in der Tagesordnung verschieben */
function tp_move(int $tpId, string $richtung): void
{
    $tp = tp_find($tpId);
    if (!$tp || !$tp['meeting_id']) {
        return;
    }
    $ids = array_map(
        static fn($r) => (int)$r['id'],
        db_all('SELECT id FROM talking_points WHERE meeting_id = ? ORDER BY sort_order, id', [(int)$tp['meeting_id']])
    );
    foreach (tp_reorder($ids, $tpId, $richtung) as $pos => $id) {
        db_exec('UPDATE talking_points SET sort_order = ? WHERE id = ?', [($pos + 1) * 10, $id]);
    }
}

/** Aus dem Ergebnis eines Punktes eine Aufgabe machen */
/**
 * Aufgabe aus einem Talking Point anlegen.
 * $opt: target => [typ, id] (sonst Fachgruppe des Punkts oder OV), faellig_am
 */
function tp_create_todo(array $tp, array $user, array $opt = []): int
{
    $meeting = $tp['meeting_id'] ? meeting_find((int)$tp['meeting_id']) : null;
    $vor = todo_prefill_from_tp($tp, $meeting);
    [$typ, $zielId] = $opt['target'] ?? [$vor['target_type'], $vor['target_id']];

    $id = db_insert('todos', [
        'titel'            => $vor['titel'],
        'beschreibung'     => $vor['beschreibung'],
        'target_type'      => $typ,
        'target_id'        => $typ === 'ov' ? null : $zielId,
        'status_id'        => list_default_id('todo_status'),
        'prioritaet_id'    => $vor['prioritaet_id'],
        'faellig_am'       => $opt['faellig_am'] ?? null,
        'meeting_id'       => $tp['meeting_id'] ?: null,
        'talking_point_id' => (int)$tp['id'],
        'created_by'       => (int)$user['id'],
    ]);
    db_exec('UPDATE talking_points SET todo_id = ? WHERE id = ? AND todo_id IS NULL', [$id, (int)$tp['id']]);
    audit('tp.aufgabe', 'talking_point', (int)$tp['id'], 'Aufgabe #' . $id);
    return $id;
}

/** Aufgaben, die in einer Besprechung entstanden sind */
function meeting_todos(int $meetingId): array
{
    return todo_query(['meeting_id' => $meetingId, 'sort' => 'standard']);
}
