<?php
declare(strict_types=1);

/* Tankung erfassen oder ändern – mit Belegfotos */
$user = require_login();
if (!can('tank_vehicle')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('vehicles');
}

$id = get_int('id');
$tank = $id ? tank_find($id) : null;
$vehicle = $tank
    ? vehicle_find((int)$tank['vehicle_id'])
    : vehicle_find((int)(get_int('vehicle_id') ?? 0));

if (($id && !$tank) || !$vehicle) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Fahrzeug oder Tankung gibt es nicht (mehr).']);
    return;
}
if ($tank && !tank_darf_aendern($tank, $user)) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Diese Tankung hat jemand anderes erfasst – ändern kann sie nur die Leitung.']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$neu, $errors] = tank_save_from_post($tank, $vehicle, $user);
    if ($neu) {
        flash('success', $tank ? 'Tankung gespeichert.' : 'Tankung erfasst und in der Fahrzeugakte vermerkt.');
        if (!empty($_FILES['belege']['name'][0])) {
            [$n, $fehler] = vfile_store_uploads((int)$vehicle['id'], null, 'belege', 'auto', 'Tankbeleg ' . de_date(post_date('datum')), $user, $neu);
            foreach ($fehler as $f) {
                flash('warn', e($f));
            }
            if ($n > 0) {
                flash('success', sprintf('%d Beleg(e) an die Tankung gehängt.', $n));
            }
        }
        redirect_route('vehicle_tanken', ['id' => (int)$vehicle['id']]);
    }
    $tank = array_merge($tank ?? [], $_POST);
    $tank['voll'] = post_bool('voll');
}

// Vorgaben für eine neue Tankung: heute, Stand des Fahrzeugs, Volltankung
$letzte = $tank ? null : db_row(
    'SELECT datum, km_stand, tankstelle FROM vehicle_tankungen WHERE vehicle_id = ? ORDER BY datum DESC, km_stand DESC, id DESC LIMIT 1',
    [(int)$vehicle['id']]
);

render('vehicle_tank_edit', [
    'title'   => $tank && !empty($tank['id']) ? 'Tankung bearbeiten' : 'Tankung erfassen',
    'tank'    => $tank ?? [
        'id' => null, 'datum' => date('Y-m-d'), 'km_stand' => null, 'liter' => '', 'betrag' => null,
        'voll' => 1, 'art' => 'kraftstoff', 'tankstelle' => (string)($letzte['tankstelle'] ?? ''), 'beleg_nr' => '', 'notiz' => '',
    ],
    'vehicle' => $vehicle,
    'letzte'  => $letzte,
    'belege'  => $tank && !empty($tank['id']) ? vfile_list_tank((int)$tank['id']) : [],
    'errors'  => $errors,
    'loeschen' => $tank && !empty($tank['id']) && tank_darf_aendern($tank, $user),
    'verwalten' => can('manage_vehicles'),
]);
