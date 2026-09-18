<?php
declare(strict_types=1);

$user = require_login();
if (!can('manage_vehicles')) {
    flash('error', 'Fahrzeuge darf nur die Leitung anlegen und ändern.');
    redirect_route('vehicles');
}

$id = get_int('id');
$vehicle = $id ? vehicle_find($id) : null;
if ($id && !$vehicle) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Fahrzeug gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$neu, $errors] = vehicle_save_from_post($vehicle, $user);
    if ($neu) {
        flash('success', $vehicle ? 'Fahrzeug gespeichert.' : 'Fahrzeugakte angelegt.');
        redirect_route('vehicle', ['id' => $neu]);
    }
    // Eingaben bei Fehlern erhalten
    $vehicle = array_merge($vehicle ?? [], $_POST);
}

render('vehicle_edit', [
    'title'   => $vehicle && !empty($vehicle['id']) ? 'Fahrzeug bearbeiten' : 'Fahrzeug anlegen',
    'vehicle' => $vehicle ?? [
        'id' => null, 'bezeichnung' => '', 'funkrufname' => '', 'issi' => '', 'opta' => '', 'ric' => '',
        'kennzeichen' => '', 'kennung' => '',
        'typ_id' => null, 'fachgruppe_id' => null, 'status_id' => list_default_id('fahrzeug_status'),
        'hersteller' => '', 'modell' => '', 'baujahr' => null, 'fahrgestellnummer' => '',
        'erstzulassung' => null, 'km_stand' => null, 'betriebsstunden' => null,
        'hu_bis' => null, 'sp_bis' => null, 'uvv_bis' => null, 'standort' => '', 'notiz' => '',
        'extra' => null, 'is_active' => 1, 'ausgemustert_am' => null, 'stein_asset_id' => null,
    ],
    'errors'  => $errors,
    'felder'  => vehicle_extra_fields(),
    'extra'   => extra_values($vehicle ?? []),
]);
