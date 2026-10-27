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

// Alle Treffer für Zahlen und Adressliste, angezeigt wird seitenweise
$alle = contact_query($filters);
$blatt = seiten_blaettern(count($alle), get_int('seite') ?: 1, KONTAKTE_JE_SEITE);

render('contacts', [
    'title'     => (string)setting('kontakte_modul_name', 'Kontakte'),
    'rows'      => array_slice($alle, $blatt['offset'], $blatt['pro_seite']),
    'alle'      => $alle,
    'blatt'     => $blatt,
    'filters'   => $filters,
    'verteiler' => contact_groups_all(true),
]);
