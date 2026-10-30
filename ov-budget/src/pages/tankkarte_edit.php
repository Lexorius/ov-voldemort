<?php
declare(strict_types=1);

$user = require_login();
if (!can('manage_tankkarten')) {
    flash('error', 'Tankkarten darf nur die Leitung pflegen.');
    redirect_route('tankkarten');
}

$id = get_int('id');
$karte = tankkarte_find($id);
if ($id && !$karte) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Tankkarte gibt es nicht (mehr).']);
    return;
}

$errors = [];
$fahrzeugeGewaehlt = array_map(static fn($v) => (int)$v['id'], $karte['fahrzeuge'] ?? []);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $karte) {
        tankkarte_delete($karte);
        flash('success', 'Tankkarte gelöscht.');
        redirect_route('tankkarten');
    }
    [$neu, $errors] = tankkarte_save_from_post($karte, $user);
    if ($neu) {
        flash('success', $karte ? 'Tankkarte gespeichert.' : 'Tankkarte angelegt.');
        $zurueck = get_int('vehicle_id');
        if ($zurueck) {
            redirect_route('vehicle', ['id' => $zurueck]);
        }
        redirect_route('tankkarten');
    }
    // Eingaben bei Fehlern erhalten
    $karte = array_merge($karte ?? [], $_POST, ['id' => $karte['id'] ?? null]);
    $fahrzeugeGewaehlt = wahl_ids('fahrzeuge');
}

if (!$karte) {
    $karte = [
        'id' => null, 'nummer' => '', 'anbieter_id' => list_default_id('tankkarte_anbieter'),
        'art' => 'kraftstoff', 'status_id' => list_default_id('tankkarte_status'),
        'kundennummer' => '', 'gueltig_bis' => null, 'hat_pin' => 0, 'pin' => '',
        'person_id' => null, 'aufbewahrung' => '', 'ausgegeben_am' => null, 'notiz' => '', 'is_active' => 1,
        'verbleib' => '', 'verbleib_am' => null, 'sperrgrund' => '',
    ];
    // Aus der Fahrzeugakte heraus ist das Fahrzeug schon gesetzt
    if (get_int('vehicle_id')) {
        $fahrzeugeGewaehlt = [(int)get_int('vehicle_id')];
    }
}

$fahrzeugOptionen = [];
foreach (db_all('SELECT id, bezeichnung, kennzeichen, is_active FROM vehicles ORDER BY is_active DESC, bezeichnung') as $v) {
    $fahrzeugOptionen[] = ['id' => (int)$v['id'], 'text' => (string)$v['bezeichnung'], 'sub' => (string)$v['kennzeichen'],
        'gruppe' => (int)$v['is_active'] === 1 ? '' : 'Ausgemustert'];
}

render('tankkarte_edit', [
    'title'     => $karte['id'] ? 'Tankkarte bearbeiten' : 'Tankkarte anlegen',
    'karte'     => $karte,
    'errors'    => $errors,
    'fahrzeugOptionen'  => $fahrzeugOptionen,
    'fahrzeugeGewaehlt' => $fahrzeugeGewaehlt,
    'personen'  => db_all('SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name'),
    'vehicleId' => get_int('vehicle_id'),
]);
