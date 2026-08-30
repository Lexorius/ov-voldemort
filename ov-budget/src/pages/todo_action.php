<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('todos');
}

$user = current_user();
$id = post_int('id', 0) ?? 0;
$todo = todo_find($id);
if (!$todo) {
    flash('error', 'Die Aufgabe wurde nicht gefunden.');
    redirect_route('todos');
}

switch (post_str('action')) {

    case 'status':
        if (!can('edit_todo', $todo)) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        if (post_str('quick') === 'done') {
            $statusId = null;
            foreach (list_items('todo_status') as $s) {
                if ((int)$s['is_final'] === 1) {
                    $statusId = (int)$s['id'];
                    break;
                }
            }
        } else {
            $statusId = post_int('status_id');
        }
        $status = list_item($statusId);
        db_update('todos', [
            'status_id'   => $statusId,
            'erledigt_am' => ($status && (int)$status['is_final'] === 1) ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [$id]);
        audit('aufgabe.status', 'todo', $id, list_label($statusId));
        flash('success', 'Status aktualisiert.');
        break;

    case 'comment':
        $body = post_str('body');
        if ($body !== '') {
            db_insert('todo_comments', ['todo_id' => $id, 'user_id' => (int)$user['id'], 'body' => $body]);
            flash('success', 'Notiz gespeichert.');
        }
        break;

    case 'delete':
        if (!can('manage_todos') && (int)$todo['created_by'] !== (int)$user['id']) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        db_exec('DELETE FROM todos WHERE id = ?', [$id]);
        audit('aufgabe.geloescht', 'todo', $id, $todo['titel']);
        flash('success', 'Aufgabe gelöscht.');
        redirect_route('todos');
        // no break

    default:
        flash('error', 'Unbekannte Aktion.');
}

redirect_route('todo', ['id' => $id]);
