<?php
declare(strict_types=1);

if (!can('view_contacts')) {
    http_response_code(403);
    render('error', [
        'title'   => 'Kein Zugriff',
        'message' => 'Die Kontakte sind nur für die Leitung sichtbar.',
    ]);
    return;
}

$filters = [
    'q'            => get_str('q'),
    'kategorie_id' => get_int('kategorie_id'),
    'aktiv'        => get_str('aktiv') === 'alle' ? 'alle' : 'aktiv',
    'sort'         => get_str('sort', 'name'),
    'nur_mit_email' => get_str('mit_email') === '1' ? 1 : 0,
];

$rows = contact_query($filters);

render('contacts', [
    'title'     => (string)setting('kontakte_modul_name', 'Kontakte'),
    'rows'      => $rows,
    'filters'   => $filters,
    'verteiler' => contact_groups_all(true),
]);
