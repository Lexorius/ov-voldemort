<?php
declare(strict_types=1);

/*
 * Ein Kartenausschnitt: die Karte mit ihren Einheiten (gesetzten Symbolen), daneben die
 * Gesamtstärke und die Abschnitte. Gezeichnet wird im Browser (karte.js); Rechtsklick setzt
 * ein Symbol, gespeichert wird über karten_api.
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
// Fahrzeuge ein- und ausblenden kann jeder, der die Karte sieht – auch ohne Zugang zum Fahrzeugmodul.
// Eine Position von Hand setzen bleibt an das Recht der Fahrzeugakte gebunden.
$darf = ['setzen' => can('mark_karten'), 'verwalten' => can('manage_karten'), 'fahrzeuge' => true,
         'fahrzeugSetzen' => can('view_vehicles') && can('report_vehicle')];
$GLOBALS['ovb_karte_js'] = true;
// Die Lage als PDF in einen Einsatz speichern darf, wer Einsätze erfasst
$speichern = can('manage_einsaetze');
$GLOBALS['ovb_karte_einsatz_js'] = $speichern;

$marker = karten_marker((int)$karte['id']);
$konfig = karten_konfig($karte, $marker, $symbole, $darf, karten_abschnitte((int)$karte['id']));
// Die fremden Kräfte des Einsatzes stehen neben der Karte zur Auswahl – ein Klick setzt sie
$konfig['fremde'] = !empty($karte['einsatz_id']) && can('view_einsaetze')
    ? einsatz_fremde_vorauswahl(einsatz_fremde((int)$karte['einsatz_id'])) : [];
render('karte', [
    'title'   => (string)$karte['name'],
    'karte'   => $karte,
    'konfig'  => $konfig,
    'symbole' => $symbole,
    'darf'    => $darf,
    'einsatz' => !empty($karte['einsatz_id']) && can('view_einsaetze') ? einsatz_find((int)$karte['einsatz_id']) : null,
    'einsaetze' => $speichern ? einsatz_auswahl(!empty($karte['einsatz_id']) ? (int)$karte['einsatz_id'] : null, 50) : [],
    // für die Seitenleiste, bevor das Skript sie übernimmt
    'staerke' => karten_staerke_summe($marker),
]);
