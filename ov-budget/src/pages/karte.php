<?php
declare(strict_types=1);

/*
 * Ein Kartenausschnitt: die Karte mit ihren gesetzten Symbolen. Gezeichnet wird im
 * Browser (karte.js); Rechtsklick setzt ein Symbol, gespeichert wird über karten_api.
 */
$user = require_login();
if (!can('view_karten')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Karten sind nur für die Leitung sichtbar.']);
    return;
}
$karte = karte_find(get_int('id'));
if (!$karte) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Karte gibt es nicht (mehr).']);
    return;
}

$symbole = karten_symbole();
$darf = ['setzen' => can('mark_karten'), 'verwalten' => can('manage_karten'), 'fahrzeuge' => can('view_vehicles'),
         'fahrzeugSetzen' => can('view_vehicles') && can('report_vehicle')];
$GLOBALS['ovb_karte_js'] = true;

render('karte', [
    'title'   => (string)$karte['name'],
    'karte'   => $karte,
    'konfig'  => karten_konfig($karte, karten_marker((int)$karte['id']), $symbole, $darf),
    'symbole' => $symbole,
    'darf'    => $darf,
]);
