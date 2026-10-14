<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');
$id = get_int('id');
$p = $id ? postfach_find($id) : null;
if ($id && !$p) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Postfach gibt es nicht (mehr).']);
    return;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $p) {
        postfach_delete($p);
        flash('success', sprintf('Postfach „%s" gelöscht.', (string)$p['name']));
        redirect_route('thw');
    }
    [$neu, $errors] = postfach_save_from_post($p, $user);
    if ($neu) {
        flash('success', $p ? 'Postfach gespeichert.' : 'Postfach angelegt.');
        redirect_route('thw');
    }
    $p = array_merge($p ?? [], $_POST, ['id' => $p['id'] ?? null]);
    // Kästchen: nicht gesendet heißt aus
    $p['offiziell'] = post_bool('offiziell');
    $p['behoerdennetz'] = post_bool('behoerdennetz');
    $p['is_active'] = post_bool('is_active');
}
if (!$p) {
    $p = ['id' => null, 'dienststelle_id' => get_int('dienststelle_id'), 'name' => '', 'email' => '', 'beschreibung' => '', 'offiziell' => 1, 'behoerdennetz' => 0, 'notiz' => '', 'sort_order' => 0, 'is_active' => 1];
}

render('thw_postfach_edit', [
    'title'  => $p['id'] ? 'Postfach bearbeiten' : 'Postfach anlegen',
    'p'      => $p,
    'errors' => $errors,
    'alle'   => thw_all(),
]);
