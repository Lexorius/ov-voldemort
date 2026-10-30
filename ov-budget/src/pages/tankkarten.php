<?php
declare(strict_types=1);

if (!can('view_tankkarten')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Tankkarten sind nur für die Leitung sichtbar.']);
    return;
}

$filter = [
    'q'           => get_str('q'),
    'anbieter_id' => get_int('anbieter_id'),
    'status_id'   => get_int('status_id'),
    'art'         => get_str('art'),
    'aktiv'       => get_str('aktiv') === 'alle' ? 'alle' : 'aktiv',
    'sort'        => get_str('sort'),
];

$karten = tankkarte_query($filter);
$warn = setting_int('tankkarte_gueltig_warnung_tage', 60);

render('tankkarten', [
    'title'  => (string)setting('tankkarte_modul_name', 'Tankkarten'),
    'karten' => $karten,
    'stats'  => tankkarte_stats($karten, $warn),
    'warn'   => $warn,
    'filter' => $filter,
]);
