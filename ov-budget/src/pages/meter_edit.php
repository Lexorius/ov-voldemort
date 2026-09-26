<?php
declare(strict_types=1);

$user = require_login();
if (!can('manage_verbrauch')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('verbrauch');
}

$id = get_int('id');
$meter = $id ? meter_find($id) : null;
if ($id && !$meter) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Zähler gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $meter) {
        meter_delete($meter);
        flash('success', 'Zähler samt Ständen gelöscht.');
        redirect_route('verbrauch');
    }
    [$neu, $errors] = meter_save_from_post($meter, $user);
    if ($neu) {
        flash('success', $meter ? 'Zähler gespeichert.' : 'Zähler angelegt. Jetzt den ersten Stand eintragen.');
        redirect_route('meter', ['id' => $neu]);
    }
    $meter = array_merge($meter ?? [], $_POST, ['id' => $meter['id'] ?? null]);
}

if (!$meter) {
    $art = meter_art(get_str('art', 'strom'));
    $meter = [
        'id' => null, 'art' => $art, 'name' => '', 'zaehlernummer' => '', 'standort' => '',
        'einheit' => METER_ARTEN[$art]['einheit'], 'umrechnung' => 1, 'quelle' => 'manuell',
        'ha_entity' => '', 'ha_faktor' => 1, 'notiz' => '', 'is_active' => 1,
    ];
}

// Entitäten aus Home Assistant – ohne Zugang bleibt es beim freien Textfeld
$entitaeten = [];
$haHinweis = '';
try {
    $entitaeten = ha_zaehler_entitaeten(get_str('frisch') === '1');
} catch (Throwable $ex) {
    $haHinweis = $ex->getMessage();
}

render('meter_edit', [
    'title'      => $meter['id'] ? 'Zähler bearbeiten' : 'Zähler anlegen',
    'meter'      => $meter,
    'errors'     => $errors,
    'entitaeten' => $entitaeten,
    'haHinweis'  => $haHinweis,
]);
