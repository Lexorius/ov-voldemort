<?php
declare(strict_types=1);

/** Auswertung nach Objektkonten: die zweite Sicht auf Ausgaben und Einnahmen eines Jahres */
if (!can('view_expenses')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Buchungen sind nur für die Leitung sichtbar.']);
    return;
}

$jahre = budget_years_known();
$jahr = get_int('jahr') ?: haushaltsjahr();
if (!in_array($jahr, $jahre, true)) {
    $jahre[] = $jahr;
    rsort($jahre);
}

render('budget_objektkonten', [
    'title'      => 'Objektkonten ' . $jahr,
    'jahr'       => $jahr,
    'jahre'      => $jahre,
    'auswertung' => objektkonto_auswertung_jahr($jahr),
    'konten'     => count(objektkonto_all(true)),
]);
