<?php
declare(strict_types=1);

/** Absender einer Bestellung anlegen oder bearbeiten: Ortsverband, Helferverein, Regionalstelle */
$user = require_login();
if (!can('manage_getraenke')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('getraenke');
}

$id = get_int('id');
$absender = $id ? getraenke_absender_find($id) : null;
if ($id && !$absender) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Absender gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $absender) {
        $f = getraenke_absender_delete($absender);
        flash($f === null ? 'success' : 'error', $f === null ? 'Absender gelöscht.' : e($f));
        redirect(url('getraenke') . '#absender');
    }
    [$neu, $errors] = getraenke_absender_save_from_post($absender);
    if ($neu) {
        flash('success', $absender ? 'Absender gespeichert.' : 'Absender angelegt.');
        redirect(url('getraenke') . '#absender');
    }
    $absender = array_merge($absender ?? [], $_POST, ['id' => $absender['id'] ?? null, 'is_active' => post_bool('is_active') ? 1 : 0]);
}

if (!$absender) {
    $absender = ['id' => null, 'name' => '', 'anschrift' => '', 'email' => '', 'telefon' => '', 'notiz' => '', 'sort_order' => 0, 'is_active' => 1];
}

render('getraenke_absender_edit', [
    'title'    => $absender['id'] ? 'Absender bearbeiten' : 'Absender anlegen',
    'absender' => $absender,
    'errors'   => $errors,
]);
