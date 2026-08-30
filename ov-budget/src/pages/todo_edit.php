<?php
declare(strict_types=1);

$user = current_user();
$id = get_int('id');
$todo = $id ? todo_find($id) : null;

if ($id && !$todo) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Aufgabe gibt es nicht (mehr).']);
    return;
}
if (!$todo && !can('create_todo')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Aufgaben dürfen derzeit nur von der Leitung angelegt werden.']);
    return;
}
if ($todo) {
    $todo['target_label'] = null;
    if (!can('edit_todo', $todo)) {
        http_response_code(403);
        render('error', ['title' => 'Kein Zugriff', 'message' => 'Diese Aufgabe darf von dir nicht bearbeitet werden.']);
        return;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$todoId, $errors] = todo_save_from_post($todo, $user);
    if ($todoId) {
        flash('success', $todo ? 'Aufgabe gespeichert.' : 'Aufgabe angelegt.');
        redirect_route('todo', ['id' => $todoId]);
    }
    $todo = array_merge($todo ?? [], $_POST, ['id' => $todo['id'] ?? null]);
}

if (!$todo) {
    $todo = [
        'id' => null, 'titel' => '', 'beschreibung' => '',
        'target_type' => get_str('target_type', 'ov'),
        'target_id' => get_int('target_id'),
        'status_id' => list_default_id('todo_status'),
        'prioritaet_id' => list_default_id('todo_prioritaet'),
        'faellig_am' => null, 'wish_id' => get_int('wish_id'),
    ];
}

render('todo_edit', [
    'title'  => $todo['id'] ? 'Aufgabe bearbeiten' : 'Neue Aufgabe',
    'todo'   => $todo,
    'errors' => $errors,
    'users'  => db_all('SELECT id, display_name, username FROM users WHERE is_active = 1 ORDER BY display_name'),
    'wishes' => db_all('SELECT id, bezeichnung FROM wishes ORDER BY created_at DESC LIMIT 200'),
]);
