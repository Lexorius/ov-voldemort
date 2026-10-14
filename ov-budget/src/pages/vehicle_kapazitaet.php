<?php
declare(strict_types=1);

/** Kapazitätsliste: wie viele Personen und wie viel Zuladung die Fahrzeuge schaffen – filterbar */
if (!can('view_vehicles')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Fahrzeuge sind nur für die Leitung sichtbar.']);
    return;
}

$sort = in_array(get_str('sort'), ['personen', 'zuladung', 'name'], true) ? get_str('sort') : 'personen';
$filter = [
    'q'               => get_str('q'),
    'typ_id'          => get_int('typ_id'),
    'fachgruppe_id'   => get_int('fachgruppe_id'),
    'status_id'       => get_int('status_id'),
    'min_sitzplaetze' => get_int('min_personen'),
    'min_zuladung'    => get_int('min_zuladung'),
    'nur_aktive'      => get_str('alle') === '1' ? 0 : 1,
    'user_id'         => (int)(current_user()['id'] ?? 0),
    'sort'            => 'name',
];
$rows = vehicle_kapazitaet_sortieren(vehicle_mit_stellplatz_pfad(vehicle_query($filter), function_exists('standort_all') ? standort_all() : []), $sort);

render('vehicle_kapazitaet', [
    'title'  => 'Kapazitäten',
    'rows'   => $rows,
    'summe'  => vehicle_kapazitaet_summe($rows),
    'filter' => $filter + ['min_personen' => get_int('min_personen'), 'alle' => get_str('alle') === '1'],
    'sort'   => $sort,
]);
