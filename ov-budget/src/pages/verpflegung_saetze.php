<?php
declare(strict_types=1);

/** Tagessätze für die Verpflegung: Liste mit Gültigkeit und Verteilung */
if (!can('view_events')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Veranstaltungen sind nur für die Leitung sichtbar.']);
    return;
}

$heute = date('Y-m-d');
$saetze = tagessatz_query();

render('verpflegung_saetze', [
    'title'   => 'Verpflegung: Tagessätze',
    'saetze'  => $saetze,
    'aktuell' => tagessatz_am($saetze, $heute),
    'heute'   => $heute,
]);
