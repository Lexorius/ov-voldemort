<?php
declare(strict_types=1);

$user = require_login();
if (!can('report_vehicle')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('vehicles');
}

$id = get_int('id');
$order = $id ? order_find($id) : null;
$vehicle = $order
    ? vehicle_find((int)$order['vehicle_id'])
    : vehicle_find((int)(get_int('vehicle_id') ?? 0));

if (($id && !$order) || !$vehicle) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Fahrzeug oder Auftrag gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$neu, $errors] = order_save_from_post($order, $vehicle, $user);
    if ($neu) {
        flash('success', $order ? 'Auftrag gespeichert.' : 'Auftrag angelegt und in der Fahrzeugakte vermerkt.');
        redirect_route('vehicle_order', ['id' => $neu]);
    }
    $order = array_merge($order ?? [], $_POST);
}

render('vehicle_order_edit', [
    'title'   => $order && !empty($order['id']) ? 'Auftrag bearbeiten' : 'Auftrag anlegen',
    'order'   => $order ?? [
        'id' => null, 'titel' => '', 'beschreibung' => '',
        'art_id' => list_default_id('auftrag_art'), 'prioritaet_id' => list_default_id('auftrag_prioritaet'),
        'werkstatt' => '', 'auftragsnummer' => '',
        'gemeldet_von' => (string)($user['display_name'] ?: $user['username']),
        'gemeldet_am' => date('Y-m-d'), 'faellig_am' => null, 'km_stand' => $vehicle['km_stand'],
        'kosten_geschaetzt' => null, 'kosten_netto' => null, 'ausfall' => 0,
    ],
    'vehicle' => $vehicle,
    'errors'  => $errors,
]);
