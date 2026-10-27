<?php
declare(strict_types=1);

if (!can('view_meetings')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Besprechungen sind nur für die Leitung sichtbar.']);
    return;
}

// Termine wiederkehrender Besprechungen nachziehen
series_materialize();

$typ = get_int('typ_id');

// Beide Listen blättern getrennt: ?seite_k für die anstehenden, ?seite_v für die zurückliegenden
$kommendAlle = meeting_query(['zeit' => 'kommend', 'typ_id' => $typ]);
$vergangenAlle = meeting_query(['zeit' => 'vergangen', 'typ_id' => $typ]);
$blattK = seiten_blaettern(count($kommendAlle), get_int('seite_k') ?: 1, BESPRECHUNGEN_JE_SEITE);
$blattV = seiten_blaettern(count($vergangenAlle), get_int('seite_v') ?: 1, BESPRECHUNGEN_JE_SEITE);

render('meetings', [
    'title'     => (string)setting('besprechung_modul_name', 'Besprechungen'),
    'kommend'   => array_slice($kommendAlle, $blattK['offset'], $blattK['pro_seite']),
    'vergangen' => array_slice($vergangenAlle, $blattV['offset'], $blattV['pro_seite']),
    'blattK'    => $blattK,
    'blattV'    => $blattV,
    'speicher'  => count(tp_backlog()),
    'serien'    => series_all(),
    'typ'       => $typ,
]);
