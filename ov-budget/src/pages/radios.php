<?php
declare(strict_types=1);

if (!can('view_radios')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Funkgeräte sind nur für die Leitung sichtbar.']);
    return;
}

$filter = [
    'q'          => get_str('q'),
    'typ_id'     => get_int('typ_id'),
    'status_id'  => get_int('status_id'),
    'ziel_typ'   => get_str('ziel_typ'),
    'ohne_karte' => get_str('ohne_karte') === '1' ? 1 : 0,
    'aktiv'      => get_str('aktiv') === 'alle' ? 'alle' : 'aktiv',
    'sort'       => get_str('sort'),
];

$radios = radio_query($filter);
$warn = setting_int('funk_pruefung_warnung_tage', 30);

render('radios', [
    'gruppen' => radio_group_all(false),
    'title'  => (string)setting('funk_modul_name', 'Funkgeräte'),
    'radios' => $radios,
    'stats'  => radio_stats($radios, $warn),
    'warn'   => $warn,
    'filter' => $filter,
]);
