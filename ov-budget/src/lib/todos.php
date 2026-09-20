<?php
declare(strict_types=1);

const TODO_TARGETS = [
    'ov'         => 'Ortsverband (alle)',
    'fachgruppe' => 'Fachgruppe',
    'funktion'   => 'Funktion',
    'user'       => 'Person',
];

function todo_find(int $id): ?array
{
    return db_row('SELECT * FROM todos WHERE id = ?', [$id]);
}

/**
 * $f: q, status_id, target_type, target_id, mine (user array), offen, ueberfaellig, sort
 */
function todo_query(array $f = []): array
{
    $w = [];
    $p = [];

    if (!empty($f['q'])) {
        $w[] = '(t.titel LIKE ? OR t.beschreibung LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like);
    }
    if (!empty($f['status_id'])) {
        $w[] = 't.status_id = ?';
        $p[] = (int)$f['status_id'];
    }
    if (!empty($f['target_type'])) {
        $w[] = 't.target_type = ?';
        $p[] = $f['target_type'];
        if (!empty($f['target_id'])) {
            $w[] = 't.target_id = ?';
            $p[] = (int)$f['target_id'];
        }
    }
    if (!empty($f['offen'])) {
        $w[] = 'COALESCE(st.is_final, 0) = 0';
    }
    if (!empty($f['meeting_id'])) {
        $w[] = 't.meeting_id = ?';
        $p[] = (int)$f['meeting_id'];
    }
    if (!empty($f['ueberfaellig'])) {
        $w[] = 't.faellig_am IS NOT NULL AND t.faellig_am < CURDATE() AND COALESCE(st.is_final,0) = 0';
    }

    // Nur Aufgaben aus meinem Zuständigkeitsbereich
    if (!empty($f['mine']) && is_array($f['mine'])) {
        $u = $f['mine'];
        $parts = ["t.target_type = 'ov'", 't.created_by = ?'];
        $p[] = (int)$u['id'];
        $parts[] = "(t.target_type = 'user' AND t.target_id = ?)";
        $p[] = (int)$u['id'];
        if (!empty($u['fachgruppe_id'])) {
            $parts[] = "(t.target_type = 'fachgruppe' AND t.target_id = ?)";
            $p[] = (int)$u['fachgruppe_id'];
        }
        $fn = $u['functions'] ?? user_functions((int)$u['id']);
        if ($fn) {
            $in = implode(',', array_fill(0, count($fn), '?'));
            $parts[] = "(t.target_type = 'funktion' AND t.target_id IN ($in))";
            foreach ($fn as $x) {
                $p[] = (int)$x;
            }
        }
        $w[] = '(' . implode(' OR ', $parts) . ')';
    }

    $order = match ($f['sort'] ?? 'standard') {
        'neu'    => 't.created_at DESC',
        'titel'  => 't.titel ASC',
        default  => 'COALESCE(st.is_final,0) ASC, t.faellig_am IS NULL, t.faellig_am ASC, pr.weight DESC, t.created_at DESC',
    };

    $sql = 'SELECT t.*,
                   st.label AS status_label, st.color AS status_color, st.is_final AS status_final,
                   pr.label AS prio_label, pr.color AS prio_color, pr.weight AS prio_weight,
                   u.display_name AS ersteller,
                   li.label AS target_label,
                   pu.display_name AS target_user,
                   mt.titel AS meeting_titel, mt.datum AS meeting_datum,
                   tpx.titel AS tp_titel,
                   (SELECT COUNT(*) FROM todo_comments c WHERE c.todo_id = t.id) AS kommentare
            FROM todos t
            LEFT JOIN meetings  mt  ON mt.id  = t.meeting_id
            LEFT JOIN talking_points tpx ON tpx.id = t.talking_point_id
            LEFT JOIN list_items st ON st.id = t.status_id
            LEFT JOIN list_items pr ON pr.id = t.prioritaet_id
            LEFT JOIN list_items li ON li.id = t.target_id AND t.target_type IN (\'fachgruppe\',\'funktion\')
            LEFT JOIN users     pu ON pu.id = t.target_id AND t.target_type = \'user\'
            LEFT JOIN users     u  ON u.id  = t.created_by'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . $order;

    return db_all($sql, $p);
}

/** Anzeigename des Zuständigen */
function todo_target_name(array $t): string
{
    return match ($t['target_type']) {
        'ov'         => 'Ortsverband',
        'fachgruppe' => (string)($t['target_label'] ?? 'Fachgruppe'),
        'funktion'   => (string)($t['target_label'] ?? 'Funktion'),
        'user'       => (string)($t['target_user'] ?? 'Person'),
        default      => '–',
    };
}

/** <option>-Liste für die Sammelauswahl, Werte wie "fachgruppe:12" */
function todo_target_options(string $listKey): string
{
    $html = '';
    foreach (list_items($listKey) as $i) {
        $html .= '<option value="' . e($listKey . ':' . (int)$i['id']) . '">' . e((string)$i['label']) . '</option>';
    }
    return $html;
}

function todo_comments(int $todoId): array
{
    return db_all('SELECT c.*, u.display_name AS autor
                   FROM todo_comments c
                   LEFT JOIN users u ON u.id = c.user_id
                   WHERE c.todo_id = ? ORDER BY c.created_at', [$todoId]);
}

function todo_save_from_post(?array $existing, array $user): array
{
    $errors = [];
    $titel = post_str('titel');
    if ($titel === '') {
        $errors[] = 'Bitte einen Titel angeben.';
    }

    $targetType = post_str('target_type', 'ov');
    if (!array_key_exists($targetType, TODO_TARGETS)) {
        $targetType = 'ov';
    }
    $targetId = $targetType === 'ov' ? null : post_int('target_' . $targetType);
    if ($targetType !== 'ov' && !$targetId) {
        $errors[] = 'Bitte auswählen, für wen die Aufgabe gilt.';
    }

    $statusId = post_int('status_id') ?: list_default_id('todo_status');
    $status = list_item($statusId);
    $erledigt = $existing['erledigt_am'] ?? null;
    if ($status && (int)$status['is_final'] === 1 && !$erledigt) {
        $erledigt = date('Y-m-d H:i:s');
    } elseif ($status && (int)$status['is_final'] === 0) {
        $erledigt = null;
    }

    $data = [
        'titel'         => mb_substr($titel, 0, 200),
        'beschreibung'  => post_str('beschreibung'),
        'target_type'   => $targetType,
        'target_id'     => $targetId,
        'status_id'     => $statusId,
        'prioritaet_id' => post_int('prioritaet_id') ?: list_default_id('todo_prioritaet'),
        'faellig_am'    => post_date('faellig_am'),
        'erledigt_am'   => $erledigt,
        'wish_id'       => post_int('wish_id'),
    ];

    // Herkunft aus einer Besprechung – nur beim Anlegen und nur, wenn es sie gibt
    if (!$existing) {
        [$meetingId, $tpId] = todo_origin_from_post();
        $data['meeting_id'] = $meetingId;
        $data['talking_point_id'] = $tpId;
    }

    if ($errors) {
        return [null, $errors];
    }

    if ($existing) {
        db_update('todos', $data, 'id = ?', [$existing['id']]);
        $id = (int)$existing['id'];
        audit('aufgabe.bearbeitet', 'todo', $id, $data['titel']);
    } else {
        $data['created_by'] = (int)$user['id'];
        $id = db_insert('todos', $data);
        if (!empty($data['talking_point_id'])) {
            // Die erste Aufgabe steht auch am Talking Point (für ältere Ansichten)
            db_exec('UPDATE talking_points SET todo_id = ? WHERE id = ? AND todo_id IS NULL', [$id, $data['talking_point_id']]);
        }
        audit('aufgabe.angelegt', 'todo', $id, $data['titel']);
        notify_queue(
            array_diff(notify_todo_users($data), [(int)$user['id']]),
            'aufgabe_neu',
            'Neue Aufgabe: ' . $data['titel'],
            trim(sprintf('Zuständig: %s%s', todo_target_name($data + ['target_label' => null, 'target_user' => null]),
                $data['faellig_am'] ? ' · fällig am ' . de_date($data['faellig_am']) : '')),
            '?p=todo&id=' . $id
        );
    }
    return [$id, []];
}

/** Besprechung und Talking Point aus dem Formular – geprüft */
function todo_origin_from_post(): array
{
    $meetingId = post_int('meeting_id') ?: null;
    $tpId = post_int('talking_point_id') ?: null;
    if ($meetingId !== null && !db_val('SELECT id FROM meetings WHERE id = ?', [$meetingId])) {
        $meetingId = null;
    }
    if ($tpId !== null) {
        $tp = db_row('SELECT id, meeting_id FROM talking_points WHERE id = ?', [$tpId]);
        if (!$tp) {
            $tpId = null;
        } else {
            $meetingId ??= $tp['meeting_id'] ? (int)$tp['meeting_id'] : null;
        }
    }
    return [$meetingId, $tpId];
}

/**
 * Zuständigkeit aus einem Auswahlwert wie "fachgruppe:12", "user:5" oder "ov".
 * Rückgabe [typ, id] oder null bei "tp" (= wie der Talking Point). Reine Funktion.
 */
function todo_target_from_value(string $wert): ?array
{
    if ($wert === '' || $wert === 'tp') {
        return null;
    }
    if ($wert === 'ov') {
        return ['ov', null];
    }
    if (preg_match('/^(fachgruppe|funktion|user):(\d+)$/', $wert, $m)) {
        return [$m[1], (int)$m[2]];
    }
    return null;
}

/**
 * Vorbelegung einer Aufgabe aus einem Talking Point. Reine Funktion
 * (bis auf die Vorgabewerte der Listen).
 */
function todo_prefill_from_tp(array $tp, ?array $meeting): array
{
    $beschreibung = trim((string)($tp['ergebnis'] ?? ''));
    if (trim((string)($tp['beschreibung'] ?? '')) !== '') {
        $beschreibung = trim($beschreibung . "\n\n— Hintergrund —\n" . $tp['beschreibung']);
    }
    if ($meeting) {
        $beschreibung = trim($beschreibung . "\n\nAus: " . $meeting['titel'] . ' am ' . de_date($meeting['datum']));
    }
    if (trim((string)($tp['verantwortlich'] ?? '')) !== '') {
        $beschreibung = trim($beschreibung . "\nVerantwortlich laut Besprechung: " . $tp['verantwortlich']);
    }
    return [
        'titel'         => mb_substr((string)$tp['titel'], 0, 200),
        'beschreibung'  => $beschreibung,
        'target_type'   => !empty($tp['fachgruppe_id']) ? 'fachgruppe' : 'ov',
        'target_id'     => !empty($tp['fachgruppe_id']) ? (int)$tp['fachgruppe_id'] : null,
        'prioritaet_id' => !empty($tp['prioritaet_id']) ? (int)$tp['prioritaet_id'] : list_default_id('todo_prioritaet'),
    ];
}
