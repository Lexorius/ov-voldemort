<?php
declare(strict_types=1);

$user = require_login();
if (!can('manage_verbrauch')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('tarife');
}

$id = get_int('id');
$tarif = $id ? tarif_find($id) : null;
if ($id && !$tarif) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Tarif gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $tarif) {
        tarif_delete($tarif);
        flash('success', 'Tarif gelöscht.');
        redirect_route('tarife');
    }
    [$neu, $errors] = tarif_save_from_post($tarif);
    if ($neu) {
        flash('success', $tarif ? 'Tarif gespeichert.' : 'Tarif angelegt.');
        redirect_route('tarife');
    }
    $tarif = array_merge($tarif ?? [], $_POST, ['id' => $tarif['id'] ?? null]);
}

if (!$tarif) {
    $art = meter_art(get_str('art', 'strom'));
    // Ein neuer Tarif beginnt meist, wo der bisherige endet
    $letzter = tarif_am(tarif_query($art), $art, date('Y-m-d'));
    $tarif = [
        'id' => null, 'art' => $art, 'name' => '', 'anbieter' => (string)($letzter['anbieter'] ?? ''),
        'gueltig_von' => date('Y-01-01'), 'gueltig_bis' => null,
        'arbeitspreis' => $letzter['arbeitspreis'] ?? '', 'grundpreis_monat' => $letzter['grundpreis_monat'] ?? '',
        'einheit' => (string)($letzter['einheit'] ?? ($art === 'gas' ? 'kWh' : METER_ARTEN[$art]['einheit'])),
        'notiz' => '',
    ];
}

render('tarif_edit', [
    'title'  => $tarif['id'] ? 'Tarif bearbeiten' : 'Tarif anlegen',
    'tarif'  => $tarif,
    'errors' => $errors,
]);
