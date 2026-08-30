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
                   (SELECT COUNT(*) FROM todo_comments c WHERE c.todo_id = t.id) AS kommentare
            FROM todos t
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
        audit('aufgabe.angelegt', 'todo', $id, $data['titel']);
    }
    return [$id, []];
}
