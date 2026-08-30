<?php
declare(strict_types=1);

$id = (int)get_int('id', 0);
$rows = todo_query([]);
$todo = null;
foreach ($rows as $r) {
    if ((int)$r['id'] === $id) {
        $todo = $r;
        break;
    }
}

if (!$todo) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Aufgabe gibt es nicht (mehr).']);
    return;
}

render('todo_view', [
    'title'      => $todo['titel'],
    'todo'       => $todo,
    'kommentare' => todo_comments($id),
    'wish'       => $todo['wish_id'] ? wish_find((int)$todo['wish_id']) : null,
]);
