<?php
declare(strict_types=1);

/** Getränkeart anlegen oder bearbeiten */
$user = require_login();
if (!can('manage_getraenke')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('getraenke');
}

$id = get_int('id');
$getraenk = $id ? getraenk_find($id) : null;
if ($id && !$getraenk) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Getränk gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $getraenk) {
        $f = getraenk_delete($getraenk);
        flash($f === null ? 'success' : 'error', $f === null ? 'Getränk gelöscht.' : e($f));
        redirect_route('getraenke');
    }
    [$neu, $errors] = getraenk_save_from_post($getraenk);
    if ($neu) {
        flash('success', $getraenk ? 'Getränk gespeichert.' : 'Getränk angelegt.');
        redirect(url('getraenke') . '#getraenke');
    }
    $getraenk = array_merge($getraenk ?? [], $_POST, ['id' => $getraenk['id'] ?? null, 'is_active' => post_bool('is_active') ? 1 : 0]);
}

if (!$getraenk) {
    $getraenk = ['id' => null, 'name' => '', 'lieferant_id' => get_int('lieferant_id') ?: null, 'gebinde' => 'Kasten', 'flaschen' => 0, 'flasche' => '',
                 'preis' => '', 'mindestbestand' => 2, 'sort_order' => 0, 'notiz' => '', 'is_active' => 1];
}

render('getraenk_edit', [
    'title'       => $getraenk['id'] ? 'Getränk bearbeiten' : 'Getränk anlegen',
    'getraenk'    => $getraenk,
    'errors'      => $errors,
    'lieferanten' => getraenke_lieferanten(false),
]);
