<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');

$id = get_int('id');
$s = $id ? standort_find($id) : null;
if ($id && !$s) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Platz gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $s) {
        $fehler = standort_delete($s);
        flash($fehler ? 'error' : 'success', $fehler ?? sprintf('„%s" gelöscht.', (string)$s['name']));
        redirect_route($fehler ? 'admin_standort_edit' : 'admin_standorte', $fehler ? ['id' => $s['id']] : []);
    }
    if (post_str('action') === 'aktiv' && $s) {
        $neu = (int)$s['is_active'] === 1 ? 0 : 1;
        db_update('standorte', ['is_active' => $neu], 'id = ?', [(int)$s['id']]);
        audit('standort.bearbeitet', 'standort', (int)$s['id'], $neu ? 'wieder aktiv' : 'stillgelegt');
        flash('success', $neu ? 'Wieder aktiv.' : 'Stillgelegt – der Platz lässt sich nicht mehr auswählen.');
        redirect_route('admin_standort_edit', ['id' => $s['id']]);
    }
    [$ids, $errors] = standort_save_from_post($s, $user);
    if ($ids) {
        flash('success', count($ids) > 1 ? count($ids) . ' Plätze angelegt.' : ($s ? 'Platz gespeichert.' : 'Platz angelegt.'));
        redirect_route('admin_standorte');
    }
    $s = array_merge($s ?? [], $_POST, ['id' => $s['id'] ?? null]);
}

$alle = standort_all();
if (!$s) {
    $parent = standort_find((int)get_int('parent_id', 0));
    $kindtypen = standort_kindtypen($parent ? (string)$parent['typ'] : null);
    $s = ['id' => null, 'name' => '', 'typ' => $kindtypen[0] ?? 'gebaeude', 'parent_id' => $parent['id'] ?? null, 'kurz' => '', 'notiz' => '', 'is_active' => 1];
}
$parent = standort_find((int)($s['parent_id'] ?? 0));

render('admin/standort_edit', [
    'title'   => $s['id'] ? 'Platz bearbeiten' : 'Platz anlegen',
    's'       => $s,
    'parent'  => $parent,
    'alle'    => $alle,
    'errors'  => $errors,
    'pfad'    => $s['id'] ? standort_pfad((int)$s['id'], $alle) : ($parent ? standort_pfad((int)$parent['id'], $alle) : ''),
    'kinder'  => $s['id'] ? (int)db_val('SELECT COUNT(*) FROM standorte WHERE parent_id = ?', [(int)$s['id']], 0) : 0,
]);
