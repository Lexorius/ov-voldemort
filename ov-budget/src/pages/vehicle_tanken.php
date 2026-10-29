<?php
declare(strict_types=1);

/* Tanken und Verbrauch eines Fahrzeugs: Kennzahlen, Verlauf, alle Tankungen */
if (!can('view_vehicles')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Fahrzeuge sind nur für die Leitung sichtbar.']);
    return;
}

$vehicle = vehicle_find((int)get_int('id', 0));
if (!$vehicle) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Fahrzeug gibt es nicht (mehr).']);
    return;
}

$a = tank_uebersicht((int)$vehicle['id']);
$blatt = seiten_blaettern(count($a['eintraege']), get_int('seite') ?: 1, LISTE_JE_SEITE);

render('vehicle_tanken', [
    'title'     => 'Tanken und Verbrauch',
    'vehicle'   => $vehicle,
    'a'         => $a,
    'rows'      => array_slice($a['eintraege'], $blatt['offset'], $blatt['pro_seite']),
    'blatt'     => $blatt,
    'erfassen'  => can('tank_vehicle'),
    'verwalten' => can('manage_vehicles'),
    'user'      => current_user(),
]);
