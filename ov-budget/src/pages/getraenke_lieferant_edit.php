<?php
declare(strict_types=1);

/** Lieferant anlegen oder bearbeiten */
$user = require_login();
if (!can('manage_getraenke')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('getraenke');
}

$id = get_int('id');
$lieferant = $id ? getraenke_lieferant_find($id) : null;
if ($id && !$lieferant) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Lieferanten gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $lieferant) {
        $f = getraenke_lieferant_delete($lieferant);
        flash($f === null ? 'success' : 'error', $f === null ? 'Lieferant gelöscht.' : e($f));
        redirect(url('getraenke') . '#lieferanten');
    }
    [$neu, $errors] = getraenke_lieferant_save_from_post($lieferant);
    if ($neu) {
        flash('success', $lieferant ? 'Lieferant gespeichert.' : 'Lieferant angelegt.');
        redirect(url('getraenke') . '#lieferanten');
    }
    $lieferant = array_merge($lieferant ?? [], $_POST, ['id' => $lieferant['id'] ?? null, 'is_active' => post_bool('is_active') ? 1 : 0]);
}

if (!$lieferant) {
    $lieferant = ['id' => null, 'name' => '', 'email' => '', 'telefon' => '', 'kundennummer' => '', 'strasse' => '', 'plz' => '', 'ort' => '', 'notiz' => '', 'is_active' => 1];
}

render('getraenke_lieferant_edit', [
    'title'     => $lieferant['id'] ? 'Lieferant bearbeiten' : 'Lieferant anlegen',
    'lieferant' => $lieferant,
    'errors'    => $errors,
]);
