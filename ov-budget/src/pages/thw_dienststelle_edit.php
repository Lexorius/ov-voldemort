<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');
$id = get_int('id');
$d = $id ? thw_find($id) : null;
if ($id && !$d) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Dienststelle gibt es nicht (mehr).']);
    return;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $d) {
        $fehler = thw_delete($d);
        flash($fehler ? 'error' : 'success', $fehler ?? sprintf('„%s" gelöscht.', (string)$d['name']));
        redirect_route($fehler ? 'thw_dienststelle_edit' : 'thw', $fehler ? ['id' => $d['id']] : []);
    }
    [$neu, $errors] = thw_save_from_post($d, $user);
    if ($neu) {
        flash('success', $d ? 'Dienststelle gespeichert.' : 'Dienststelle angelegt.');
        redirect_route('thw');
    }
    $d = array_merge($d ?? [], $_POST, ['id' => $d['id'] ?? null]);
}
$alle = thw_all();
if (!$d) {
    $parent = thw_find((int)get_int('parent_id', 0));
    $typVorgabe = match ((string)($parent['typ'] ?? '')) { 'leitung' => 'landesverband', 'landesverband' => 'regionalstelle', 'regionalstelle' => 'ortsverband', default => 'ortsverband' };
    $d = ['id' => null, 'parent_id' => $parent['id'] ?? null, 'typ' => $typVorgabe, 'name' => '', 'kurz' => '', 'strasse' => '', 'plz' => '', 'ort' => '', 'telefon' => '', 'email' => '', 'web' => '', 'notiz' => '', 'sort_order' => 0, 'is_active' => 1];
}
$ausser = !empty($d['id']) ? array_merge([(int)$d['id']], standort_nachkommen((int)$d['id'], $alle)) : [];

render('thw_dienststelle_edit', [
    'title'  => $d['id'] ? 'Dienststelle bearbeiten' : 'Dienststelle anlegen',
    'd'      => $d,
    'errors' => $errors,
    'alle'   => $alle,
    'ausser' => $ausser,
]);
