<?php
declare(strict_types=1);

/* Ein Einsatz: Tage und Schichten, Fahrzeuge, Ausgaben und Einnahmen */
$user = require_login();
if (!can('view_einsaetze')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Einsätze sind nur für die Leitung sichtbar.']);
    return;
}

$einsatz = einsatz_find(get_int('id', 0));
if (!$einsatz) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Einsatz gibt es nicht (mehr).']);
    return;
}

$id = (int)$einsatz['id'];
$tage = einsatz_tage($id);
$buchungen = can('view_expenses') ? einsatz_buchungen($id) : [];

render('einsatz', [
    'title'     => einsatz_bezeichnung($einsatz),
    'einsatz'   => $einsatz,
    'tage'      => $tage,
    'summen'    => einsatz_summen($tage),
    'fahrzeuge' => can('view_vehicles') ? einsatz_fahrzeuge($id) : [],
    'buchungen' => $buchungen,
    'geld'      => einsatz_geld_aus($buchungen),
    'mitGeld'   => can('view_expenses'),
]);
