<?php
declare(strict_types=1);

$user = current_user();
$id = (int)get_int('id', 0);
$rows = $id ? wish_query([]) : [];

$wish = null;
foreach ($rows as $r) {
    if ((int)$r['id'] === $id) {
        $wish = $r;
        break;
    }
}

if (!$wish) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Wunsch gibt es nicht (mehr).']);
    return;
}

render('wish_view', [
    'title'    => $wish['bezeichnung'],
    'wish'     => $wish,
    'anlagen'  => wish_attachments($id),
    'kommentare' => wish_comments($id),
    'meinVote' => isset(wish_votes_of_user((int)$user['id'])[$id]),
    'todos'    => db_all(
        'SELECT t.*, st.label AS status_label, st.color AS status_color, st.is_final AS status_final,
                pr.label AS prio_label, pr.color AS prio_color,
                li.label AS target_label, pu.display_name AS target_user,
                (SELECT COUNT(*) FROM todo_comments c WHERE c.todo_id = t.id) AS kommentare
         FROM todos t
         LEFT JOIN list_items st ON st.id = t.status_id
         LEFT JOIN list_items pr ON pr.id = t.prioritaet_id
         LEFT JOIN list_items li ON li.id = t.target_id AND t.target_type IN (\'fachgruppe\',\'funktion\')
         LEFT JOIN users pu ON pu.id = t.target_id AND t.target_type = \'user\'
         WHERE t.wish_id = ? ORDER BY t.created_at',
        [$id]
    ),
]);
