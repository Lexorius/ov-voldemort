<?php
declare(strict_types=1);

if (!can('view_vehicles')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Fahrzeuge sind nur für die Leitung sichtbar.']);
    return;
}

// Der Abgleich mit der Stein.APP läuft höchstens einmal je Intervall
stein_sync_if_due();

$filter = [
    'q'             => get_str('q'),
    'status_id'     => get_int('status_id'),
    'typ_id'        => get_int('typ_id'),
    'fachgruppe_id' => get_int('fachgruppe_id'),
    'nur_aktive'    => get_str('alle') === '1' ? 0 : 1,
];
$fahrzeuge = vehicle_query($filter);
$warnTage = setting_int('fahrzeug_frist_warnung_tage', 30);

$fristen = [];
$auffaellig = 0;
foreach ($fahrzeuge as $v) {
    $fristen[(int)$v['id']] = vehicle_deadlines($v, $warnTage);
    foreach ($fristen[(int)$v['id']] as $f) {
        if ($f['status'] !== 'ok') {
            $auffaellig++;
            break;
        }
    }
}

render('vehicles', [
    'title'      => (string)setting('fahrzeug_modul_name', 'Fahrzeuge'),
    'fahrzeuge'  => $fahrzeuge,
    'fristen'    => $fristen,
    'auffaellig' => $auffaellig,
    'filter'     => $filter,
    'alle'       => get_str('alle') === '1',
    'auftraege'  => order_query(['offen' => 1, 'limit' => 10]),
    'steinAktiv' => stein_enabled(),
    'steinStand' => stein_last_sync(),
]);
