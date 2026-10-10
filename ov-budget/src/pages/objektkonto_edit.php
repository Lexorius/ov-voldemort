<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');

$id = get_int('id');
$konto = $id ? objektkonto_find($id) : null;
if ($id && !$konto) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Objektkonto gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $konto) {
        objektkonto_delete($konto);
        flash('success', 'Objektkonto gelöscht. Die Kategorien bleiben, nur die Zuordnung ist weg.');
        redirect_route('objektkonten');
    }
    [$neu, $errors] = objektkonto_save_from_post($konto, $user);
    if ($neu) {
        flash('success', $konto ? 'Objektkonto gespeichert.' : 'Objektkonto angelegt.');
        redirect_route('objektkonten');
    }
    $konto = array_merge($konto ?? [], $_POST, ['id' => $konto['id'] ?? null]);
}

$kategorien = objektkonto_kategorien();
$gewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gewaehlt = array_map('intval', (array)($_POST['kategorien'] ?? []));
} elseif ($konto) {
    foreach ($kategorien as $k) {
        if ((int)($k['objektkonto_id'] ?? 0) === (int)$konto['id']) {
            $gewaehlt[] = (int)$k['id'];
        }
    }
}
if (!$konto) {
    $konto = ['id' => null, 'nummer' => '', 'name' => '', 'beschreibung' => '', 'color' => '#64748b', 'sort_order' => 0, 'is_active' => 1];
}

render('objektkonto_edit', [
    'title'      => $konto['id'] ? 'Objektkonto bearbeiten' : 'Objektkonto anlegen',
    'konto'      => $konto,
    'errors'     => $errors,
    'kategorien' => $kategorien,
    'gewaehlt'   => $gewaehlt,
    'kontenNamen' => array_column(objektkonto_all(), null, 'id'),
]);
