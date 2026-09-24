<?php
declare(strict_types=1);

if (!can('view_sims')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die SIM-Karten sind nur für die Leitung sichtbar.']);
    return;
}

$filter = [
    'q'         => get_str('q'),
    'typ_id'    => get_int('typ_id'),
    'status_id' => get_int('status_id'),
    'ziel_typ'  => get_str('ziel_typ'),
    'aktiv'     => get_str('aktiv') === 'alle' ? 'alle' : 'aktiv',
    'sort'      => get_str('sort'),
];

$sims = sim_query($filter);
$warn = setting_int('sim_vertrag_warnung_tage', 60);

render('sims', [
    'title'  => (string)setting('sim_modul_name', 'SIM-Karten'),
    'sims'   => $sims,
    'stats'  => sim_stats($sims, $warn),
    'warn'   => $warn,
    'filter' => $filter,
]);
